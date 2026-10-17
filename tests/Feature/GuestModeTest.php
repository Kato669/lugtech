<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\LugandaCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(LugandaCourseSeeder::class);
    }

    public function test_visitors_can_learn_without_signing_up(): void
    {
        $this->get('/learn')->assertOk()->assertSee('Up next')->assertSee('Save progress')
            ->assertSee('<span data-cowries>0</span>', false);
        $guest = User::where('is_guest', true)->sole();
        $this->assertAuthenticatedAs($guest);

        $this->get(route('lessons.show', Lesson::orderBy('id')->first()))->assertOk();
        $this->postJson(route('lessons.complete', Lesson::orderBy('id')->first()), ['score' => 100])->assertOk();
        $this->get('/games')->assertOk()->assertSee('Memory match');
        $this->get('/learn')->assertSee('Keep your progress');

        // Visiting again does not create another guest.
        $this->get('/review')->assertOk();
        $this->assertSame(1, User::count());
    }

    public function test_crawlers_do_not_get_guest_accounts(): void
    {
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/learn')->assertRedirect('/');
        $this->assertSame(0, User::count());
    }

    public function test_registering_upgrades_the_guest_and_keeps_progress(): void
    {
        $this->get('/learn');
        $this->postJson(route('lessons.complete', Lesson::orderBy('id')->first()), ['score' => 90]);
        $guest = User::sole();

        $this->get('/register')->assertOk();
        $this->post('/register', [
            'name' => 'Nakato Sarah', 'email' => 'nakato@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect('/learn');

        $user = User::sole();
        $this->assertSame($guest->id, $user->id);
        $this->assertFalse($user->is_guest);
        $this->assertSame('nakato@example.com', $user->email);
        $this->assertSame(10, $user->cowries); // first completion, below 100% so no bonus
        $this->assertSame(1, $user->completions()->count());
        $this->get('/learn')->assertSee('Oli otya, Nakato!')->assertSee('Log out')->assertDontSee('Save progress');
        $this->get('/register')->assertRedirect('/learn');
    }

    public function test_logging_in_merges_guest_progress_into_the_account(): void
    {
        $account = User::factory()->create(['email' => 'kato@example.com', 'password' => 'secret123', 'cowries' => 100]);
        $this->actingAs($account)->postJson(route('lessons.complete', Lesson::orderBy('id')->first()), ['score' => 50]);
        $this->post('/logout');

        // Back as a guest on another device: finish two lessons.
        $this->get('/learn');
        $lessons = Lesson::orderBy('position')->take(2)->get();
        foreach ($lessons as $lesson) {
            $this->postJson(route('lessons.complete', $lesson), ['score' => 100]);
        }

        $this->post('/login', ['email' => 'kato@example.com', 'password' => 'secret123'])->assertRedirect('/learn');
        $this->assertAuthenticatedAs($account);
        $this->assertSame(1, User::count(), 'guest account is removed after merging');

        $account->refresh();
        $this->assertSame(100 + 10 + 15 + 15, $account->cowries);
        $this->assertSame(2, $account->completions()->count());
        $this->assertSame(100, $account->completions()->where('lesson_id', $lessons[0]->id)->value('best_score'));
        $this->assertSame(
            $lessons->flatMap->phrases->pluck('id')->unique()->count(),
            $account->reviewItems()->count(),
        );
    }

    public function test_prune_command_removes_only_stale_guests(): void
    {
        $stale = User::factory()->create();
        User::whereKey($stale->id)->update(['is_guest' => true, 'updated_at' => now()->subDays(90)]);
        $fresh = User::factory()->create();
        $fresh->forceFill(['is_guest' => true])->save();
        $real = User::factory()->create();
        User::whereKey($real->id)->update(['updated_at' => now()->subDays(90)]);

        $this->artisan('app:prune-guests')->assertSuccessful();
        $this->assertModelMissing($stale);
        $this->assertModelExists($fresh);
        $this->assertModelExists($real);
    }
}
