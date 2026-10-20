<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Models\UserItem;
use App\Services\Wardrobe;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 13: the cowrie shop, the wardrobe, and choosing who teaches you. */
class WardrobeTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->learner = User::factory()->create(['cowries' => 100]);
    }

    public function test_the_wardrobe_page(): void
    {
        $this->actingAs($this->learner)->get('/wardrobe')->assertOk()
            ->assertSee('Wardrobe')->assertSee('100 cowries')
            ->assertSee('Sky-blue kanzu')->assertSee('Maroon cap')->assertSee('Gold gomesi')->assertSee('Bead necklace')->assertSee('Small drum')
            ->assertSee('Streak freeze')->assertSee('Who teaches your lessons?')
            ->assertSee('White kanzu')->assertSee('Free');
        $this->get('/learn')->assertSee('Your guides')->assertSee(route('wardrobe'), false);
    }

    public function test_buying_once_with_enough_cowries(): void
    {
        $this->actingAs($this->learner)->post(route('wardrobe.buy', 'drum'))->assertRedirect('/wardrobe')
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'You bought the Small drum'));
        $this->assertSame(40, $this->learner->fresh()->cowries);
        $this->assertDatabaseHas('user_items', ['user_id' => $this->learner->id, 'item' => 'drum', 'price' => 60]);

        $this->post(route('wardrobe.buy', 'drum'))->assertSessionHas('status', fn ($s) => str_contains($s, 'already have'));
        $this->post(route('wardrobe.buy', 'sash'))->assertSessionHas('status', fn ($s) => str_contains($s, 'costs 50 cowries'));
        $this->post(route('wardrobe.buy', 'kanzu-white'))->assertSessionHas('status', fn ($s) => str_contains($s, 'already have'), 'free things are everyone\'s');
        $this->post(route('wardrobe.buy', 'golden-throne'))->assertNotFound();
        $this->assertSame([40, 1], [$this->learner->fresh()->cowries, UserItem::count()]);
    }

    public function test_wearing_and_taking_off(): void
    {
        $wardrobe = app(Wardrobe::class);
        $this->actingAs($this->learner)->post(route('wardrobe.wear'), ['character' => 'mukasa', 'part' => 'cap', 'item' => 'cap-maroon'])
            ->assertSessionHas('status', 'Buy it first, then you can wear it.');
        $this->post(route('wardrobe.buy', 'cap-maroon'));
        $this->post(route('wardrobe.buy', 'beads'));
        $this->post(route('wardrobe.wear'), ['character' => 'mukasa', 'part' => 'cap', 'item' => 'cap-maroon'])->assertSessionHas('status', '✨ Mukasa is wearing the Maroon cap.');
        $this->post(route('wardrobe.wear'), ['character' => 'nakato', 'part' => 'beads', 'item' => 'beads']);
        $this->post(route('wardrobe.wear'), ['character' => 'nakato', 'part' => 'cap', 'item' => 'cap-maroon'])->assertSessionHas('status', "That doesn't fit.");
        $this->post(route('wardrobe.wear'), ['character' => 'nakato', 'part' => 'robe', 'item' => 'kanzu-white'])->assertSessionHas('status', 'Buy it first, then you can wear it.');

        $user = $this->learner->fresh();
        $this->assertSame(['robe' => 'kanzu-white', 'cap' => 'cap-maroon', 'beads' => null, 'sash' => null, 'drum' => null], $wardrobe->outfit($user, 'mukasa'));
        $this->assertSame('beads', $wardrobe->outfit($user, 'nakato')['beads']);
        $look = app(Wardrobe::class)->look($user, 'mukasa');
        $this->assertSame('wear-cap', $look['class']);
        $this->assertStringContainsString('--cap: #8b2a1f;', $look['style']);

        // The guides are drawn dressed: in the wardrobe, on the path and in lessons.
        $this->get('/wardrobe')->assertSee('char char-mukasa wear-cap', false)->assertSee('char char-nakato wear-beads', false);
        $this->get(route('lessons.show', Lesson::where('slug', 'okulamusa')->first()))->assertSee('char char-mukasa wear-cap', false);
        $this->get('/learn')->assertSee('wear-cap', false);

        $this->post(route('wardrobe.wear'), ['character' => 'mukasa', 'part' => 'cap', 'item' => ''])->assertSessionHas('status', 'Mukasa took it off.');
        $this->assertNull($wardrobe->outfit($this->learner->fresh(), 'mukasa')['cap']);

        // Someone else's guides are not dressed by this learner's choices.
        $this->assertSame('', app(Wardrobe::class)->look(User::factory()->create(), 'nakato')['class']);
    }

    public function test_a_new_robe_colour(): void
    {
        $this->actingAs($this->learner)->post(route('wardrobe.buy', 'gomesi-purple'));
        $this->post(route('wardrobe.wear'), ['character' => 'nakato', 'part' => 'robe', 'item' => 'gomesi-purple']);
        $this->assertStringContainsString('--robe: #6b3a8c;', app(Wardrobe::class)->look($this->learner->fresh(), 'nakato')['style']);
        // Taking the robe "off" goes back to the free one.
        $this->post(route('wardrobe.wear'), ['character' => 'nakato', 'part' => 'robe', 'item' => '']);
        $this->assertSame('gomesi-green', app(Wardrobe::class)->outfit($this->learner->fresh(), 'nakato')['robe']);
    }

    public function test_choosing_who_teaches(): void
    {
        $stage1 = Lesson::where('slug', 'okulamusa')->first(); // Mukasa's turn
        $stage2 = Lesson::where('slug', 'emmere')->first();    // Nakato's turn
        $teacher = fn (Lesson $l) => $this->get(route('lessons.show', $l))->viewData('teacher');

        $this->actingAs($this->learner);
        $this->assertSame(['mukasa', 'nakato'], [$teacher($stage1), $teacher($stage2)]);
        $this->post(route('wardrobe.teacher'), ['teacher' => 'nakato'])->assertSessionHas('status', 'Nakato will teach all your lessons.');
        $this->assertSame(['nakato', 'nakato'], [$teacher($stage1), $teacher($stage2)]);
        $this->post(route('wardrobe.teacher'), ['teacher' => 'mukasa']);
        $this->assertSame(['mukasa', 'mukasa'], [$teacher($stage1), $teacher($stage2)]);
        // Lines written for Mukasa are said by the chosen teacher, but not lines about Mukasa himself.
        $steps = app(Wardrobe::class)->applyTeacher($this->learner->fresh()->forceFill(['teacher_choice' => 'nakato']), [
            ['type' => 'intro', 'character' => 'mukasa', 'say' => 'Oli otya! Today we greet people.'],
            ['type' => 'teach', 'character' => 'mukasa', 'say' => 'My name is Mukasa, so I say: Nze Mukasa!'],
            ['type' => 'dialogue', 'character' => 'mukasa'],
            ['type' => 'reward', 'character' => 'ngoma'],
        ]);
        $this->assertSame(['nakato', 'mukasa', 'mukasa', 'ngoma'], array_column($steps, 'character'));
        $this->post(route('wardrobe.teacher'), ['teacher' => 'turns']);
        $this->assertNull($this->learner->fresh()->teacher_choice);
        $this->post(route('wardrobe.teacher'), ['teacher' => 'ngoma'])->assertSessionHasErrors('teacher');
    }

    public function test_streak_freezes_from_the_shop_come_back_to_it(): void
    {
        $this->actingAs($this->learner)->post(route('habit.freeze'), ['from' => 'wardrobe'])->assertRedirect('/wardrobe');
        $this->assertSame(1, $this->learner->fresh()->streak_freezes);
    }

    public function test_a_guest_keeps_what_they_bought(): void
    {
        $account = User::factory()->create(['email' => 'kato@example.com', 'password' => 'secret123']);
        UserItem::create(['user_id' => $account->id, 'item' => 'beads', 'price' => 30]);
        $this->get('/learn');
        $guest = auth()->user();
        $guest->forceFill(['cowries' => 100])->save();
        $this->post(route('wardrobe.buy', 'beads'));
        $this->post(route('wardrobe.buy', 'drum'));
        $this->post(route('wardrobe.wear'), ['character' => 'mukasa', 'part' => 'drum', 'item' => 'drum']);
        $this->post('/login', ['email' => 'kato@example.com', 'password' => 'secret123'])->assertRedirect('/learn');

        $this->assertEqualsCanonicalizing(['beads', 'drum'], UserItem::where('user_id', $account->id)->pluck('item')->all());
        $this->assertSame(1, UserItem::where('item', 'beads')->count(), 'no double');
        $this->assertSame('drum', app(Wardrobe::class)->outfit($account->fresh(), 'mukasa')['drum']);
    }
}
