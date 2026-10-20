<?php

namespace App\Http\Controllers;

use App\Services\DailyHabit;
use App\Services\Wardrobe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Phase 13: the cowrie shop and the wardrobe (/wardrobe). */
class WardrobeController extends Controller
{
    public function show(Request $request, Wardrobe $wardrobe, DailyHabit $habit)
    {
        $user = $request->user();
        $owned = $wardrobe->owned($user)->flip();
        $items = collect(Wardrobe::ITEMS)->map(fn ($i, $key) => [
            'key' => $key, 'name' => $i[0], 'who' => $i[1], 'part' => $i[2], 'price' => $i[3], 'look' => $i[4],
            'owned' => $i[3] === 0 || $owned->has($key),
        ]);

        return view('wardrobe.show', [
            'me' => $user,
            'outfits' => collect(Wardrobe::CHARACTERS)->mapWithKeys(fn ($c) => [$c => $wardrobe->outfit($user, $c)]),
            'groups' => [
                'mukasa' => $items->where('who', 'mukasa')->values(),
                'nakato' => $items->where('who', 'nakato')->values(),
                'both' => $items->where('who', 'both')->values(),
            ],
            'teacher' => $user->teacher_choice ?: 'turns',
            'freeze' => $habit->status($user),
        ]);
    }

    public function buy(Request $request, Wardrobe $wardrobe, string $item)
    {
        abort_unless(isset(Wardrobe::ITEMS[$item]), 404);
        $problem = $wardrobe->buy($request->user(), $item);
        $name = Wardrobe::ITEMS[$item][0];

        return redirect()->route('wardrobe')->with('status', $problem ?? "🛍️ You bought the {$name}. Tap “Wear” to put it on.");
    }

    public function wear(Request $request, Wardrobe $wardrobe)
    {
        $data = $request->validate([
            'character' => ['required', Rule::in(Wardrobe::CHARACTERS)],
            'part' => ['required', 'string', 'max:10'],
            'item' => ['nullable', 'string', 'max:40'],
        ]);
        $problem = $wardrobe->wear($request->user(), $data['character'], $data['part'], $data['item'] ?: null);
        $who = ucfirst($data['character']);

        return redirect()->route('wardrobe')->with('status', $problem ?? ($data['item']
            ? "✨ {$who} is wearing the ".Wardrobe::ITEMS[$data['item']][0].'.'
            : "{$who} took it off."));
    }

    public function teacher(Request $request, Wardrobe $wardrobe)
    {
        $data = $request->validate(['teacher' => ['required', Rule::in(array_keys(Wardrobe::TEACHERS))]]);
        $wardrobe->chooseTeacher($request->user(), $data['teacher']);

        return redirect()->route('wardrobe')->with('status', [
            'turns' => 'Mukasa and Nakato will take turns teaching you.',
            'mukasa' => 'Mukasa will teach all your lessons.',
            'nakato' => 'Nakato will teach all your lessons.',
        ][$data['teacher']]);
    }
}
