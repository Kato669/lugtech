<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13: the cowrie shop and the wardrobe.
 *
 * Items are bought once with cowries, then worn or taken off for free. What each guide wears is
 * kept per learner (users.outfit) and drawn by <x-character> with CSS only: a robe colour
 * (CSS variables) and item layers (cap, beads, sash, drum) switched on by classes.
 * Learners also choose who teaches them (users.teacher_choice).
 */
class Wardrobe
{
    public const CHARACTERS = ['mukasa', 'nakato'];

    /**
     * key => [name, who (mukasa | nakato | both | you), part, price, look]
     * Price 0 = everyone has it. Robe colours are CSS variables used by the drawings.
     */
    public const ITEMS = [
        // Mukasa's kanzu
        'kanzu-white' => ['White kanzu', 'mukasa', 'robe', 0, ['--robe' => '#fbf6ec']],
        'kanzu-cream' => ['Cream kanzu', 'mukasa', 'robe', 25, ['--robe' => '#efdcb6']],
        'kanzu-sky' => ['Sky-blue kanzu', 'mukasa', 'robe', 25, ['--robe' => '#cfe3f2']],
        'kanzu-grey' => ['Grey kanzu', 'mukasa', 'robe', 25, ['--robe' => '#d6d2cc']],
        'cap-maroon' => ['Maroon cap', 'mukasa', 'cap', 40, ['--cap' => '#8b2a1f']],
        'cap-black' => ['Black cap', 'mukasa', 'cap', 40, ['--cap' => '#241812']],
        // Nakato's gomesi
        'gomesi-green' => ['Green gomesi', 'nakato', 'robe', 0, ['--robe' => '#3e7c3a', '--robe-light' => '#4f9348', '--robe-dark' => '#2f652c']],
        'gomesi-gold' => ['Gold gomesi', 'nakato', 'robe', 25, ['--robe' => '#c98a12', '--robe-light' => '#e0a92e', '--robe-dark' => '#9c6a0c', '--sash' => '#8b2a1f']],
        'gomesi-purple' => ['Purple gomesi', 'nakato', 'robe', 25, ['--robe' => '#6b3a8c', '--robe-light' => '#8352a6', '--robe-dark' => '#542c70']],
        'gomesi-red' => ['Red gomesi', 'nakato', 'robe', 25, ['--robe' => '#a33a2c', '--robe-light' => '#c0503f', '--robe-dark' => '#7f2a1f']],
        // For both guides (bought once, worn by either)
        'beads' => ['Bead necklace', 'both', 'beads', 30, []],
        'sash' => ['Barkcloth sash', 'both', 'sash', 50, []],
        'drum' => ['Small drum', 'both', 'drum', 60, []],
    ];

    /** What each guide wears until the learner changes it. */
    public const DEFAULTS = [
        'mukasa' => ['robe' => 'kanzu-white', 'cap' => null, 'beads' => null, 'sash' => null, 'drum' => null],
        'nakato' => ['robe' => 'gomesi-green', 'beads' => null, 'sash' => null, 'drum' => null],
    ];

    public const TEACHERS = ['turns' => 'Take turns', 'mukasa' => 'Always Mukasa', 'nakato' => 'Always Nakato'];

    /** user id => owned item keys, once per request */
    private array $ownedCache = [];

    public function owns(?User $user, string $item): bool
    {
        if (! isset(self::ITEMS[$item])) {
            return false;
        }
        if (self::ITEMS[$item][3] === 0) {
            return true;
        }

        return $user !== null && $this->owned($user)->contains($item);
    }

    /** @return Collection<int, string> */
    public function owned(User $user)
    {
        return collect($this->ownedCache[$user->id] ??= $user->items()->pluck('item')->all());
    }

    /** @return string|null why not, or null when bought */
    public function buy(User $user, string $item): ?string
    {
        if (! isset(self::ITEMS[$item])) {
            return 'That item is not in the shop.';
        }
        [$name, , , $price] = self::ITEMS[$item];
        if ($this->owns($user, $item)) {
            return "You already have the {$name}.";
        }

        return DB::transaction(function () use ($user, $item, $name, $price) {
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            if ($fresh->cowries < $price) {
                return "The {$name} costs {$price} cowries. Keep practising to earn more!";
            }
            if (UserItem::where('user_id', $fresh->id)->where('item', $item)->exists()) {
                return "You already have the {$name}.";
            }
            UserItem::create(['user_id' => $fresh->id, 'item' => $item, 'price' => $price]);
            $fresh->cowries -= $price;
            $fresh->save();
            $user->cowries = $fresh->cowries;
            unset($this->ownedCache[$user->id]);

            return null;
        });
    }

    /** What a guide is wearing now: part => item key (or null). */
    public function outfit(?User $user, string $character): array
    {
        $saved = $user?->outfit[$character] ?? [];
        $outfit = self::DEFAULTS[$character] ?? [];
        foreach ($outfit as $part => $default) {
            $item = $saved[$part] ?? $default;
            // Only what they own and what fits this guide and this part.
            $outfit[$part] = $item && $this->fits($item, $character, $part) && $this->owns($user, $item) ? $item : $default;
        }

        return $outfit;
    }

    private function fits(string $item, string $character, string $part): bool
    {
        [, $who, $itemPart] = self::ITEMS[$item] ?? [null, null, null];

        return $itemPart === $part && ($who === $character || $who === 'both');
    }

    /** Put something on (or take it off with $item = null). @return string|null why not */
    public function wear(User $user, string $character, string $part, ?string $item): ?string
    {
        if (! array_key_exists($part, self::DEFAULTS[$character] ?? [])) {
            return 'That doesn\'t fit.';
        }
        if ($item !== null && (! $this->fits($item, $character, $part) || ! $this->owns($user, $item))) {
            return 'Buy it first, then you can wear it.';
        }
        if ($item === null && $part === 'robe') {
            $item = self::DEFAULTS[$character]['robe'];
        }
        $outfit = $user->outfit ?? [];
        $outfit[$character][$part] = $item;
        $user->forceFill(['outfit' => $outfit])->save();

        return null;
    }

    /**
     * How <x-character> draws a guide for this learner: CSS classes and variables.
     *
     * @return array{class: string, style: string}
     */
    public function look(?User $user, string $character): array
    {
        if (! in_array($character, self::CHARACTERS, true)) {
            return ['class' => '', 'style' => ''];
        }
        $classes = [];
        $vars = [];
        foreach ($this->outfit($user, $character) as $part => $item) {
            if (! $item) {
                continue;
            }
            if ($part !== 'robe') {
                $classes[] = 'wear-'.$part;
            }
            $vars += self::ITEMS[$item][4];
        }

        return [
            'class' => implode(' ', $classes),
            'style' => implode(' ', array_map(fn ($k, $v) => "{$k}: {$v};", array_keys($vars), $vars)),
        ];
    }

    /** Who teaches this lesson: the learner's choice, or turns (Mukasa in odd stages, Nakato in even). */
    public function teacherFor(?User $user, Lesson $lesson): string
    {
        $choice = $user?->teacher_choice;
        if (in_array($choice, self::CHARACTERS, true)) {
            return $choice;
        }

        return $lesson->stage->position % 2 === 0 ? 'nakato' : 'mukasa';
    }

    /**
     * With a chosen teacher, steps written for the other guide (intros, praise, rewards) are said by
     * the chosen one, except lines about that guide themselves ("My name is Nakato…"), and
     * conversations, which always have both.
     */
    public function applyTeacher(?User $user, array $steps): array
    {
        $choice = $user?->teacher_choice;
        if (! in_array($choice, self::CHARACTERS, true)) {
            return $steps;
        }
        foreach ($steps as $i => $step) {
            $own = $step['character'] ?? null;
            if (! $own || $own === $choice || in_array($step['type'] ?? null, ['dialogue', 'converse'], true)) {
                continue;
            }
            if (! in_array($own, self::CHARACTERS, true) || str_contains((string) ($step['say'] ?? ''), ucfirst($own))) {
                continue; // Ngoma, or a line about that guide: keep it theirs
            }
            $steps[$i]['character'] = $choice;
        }

        return $steps;
    }

    public function chooseTeacher(User $user, string $choice): void
    {
        $user->forceFill(['teacher_choice' => array_key_exists($choice, self::TEACHERS) && $choice !== 'turns' ? $choice : null])->save();
    }
}
