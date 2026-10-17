<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Services\Games;
use Database\Seeders\LugandaCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(LugandaCourseSeeder::class);
        $this->user = User::factory()->create();
    }

    private function finishFirstLesson(): void
    {
        $this->actingAs($this->user)->postJson(route('lessons.complete', Lesson::orderBy('id')->first()), ['score' => 100]);
    }

    public function test_games_are_locked_until_a_lesson_is_finished(): void
    {
        $this->actingAs($this->user)->get('/games')->assertOk()->assertSee('Finish your first lesson');
        $this->actingAs($this->user)->get('/games/memory')->assertRedirect('/games');
    }

    public function test_game_pages_render_with_learned_phrases_only(): void
    {
        $this->finishFirstLesson();
        $this->actingAs($this->user)->get('/games')->assertOk()->assertSee('Memory match')->assertSee('The talking drum');
        $this->actingAs($this->user)->get('/games/memory')->assertOk()->assertSee('memoryGame')->assertSee('Oli otya?');
        $this->actingAs($this->user)->get('/games/drum')->assertOk()->assertSee('drumGame')->assertDontSee('Nnyabo');
        $this->actingAs($this->user)->get('/games/chess')->assertNotFound();
    }

    public function test_pool_has_unique_meanings(): void
    {
        $this->finishFirstLesson();
        $pool = app(Games::class)->pool($this->user);
        $this->assertSame(6, $pool->count());
        $this->assertSame($pool->count(), $pool->pluck('english')->unique()->count());
    }

    public function test_completing_games_awards_cowries(): void
    {
        $this->finishFirstLesson(); // 15 cowries
        $this->actingAs($this->user)->postJson('/games/memory/complete', ['score' => 100])
            ->assertOk()->assertJson(['cowries_earned' => 7, 'capped' => false, 'cowries' => 22]);
        $this->actingAs($this->user)->postJson('/games/drum/complete', ['score' => 14])
            ->assertJson(['cowries_earned' => 4]);
        $this->actingAs($this->user)->postJson('/games/drum/complete', ['score' => 300])
            ->assertJson(['cowries_earned' => 10]);
        $this->actingAs($this->user)->postJson('/games/memory/complete', ['score' => 101])->assertUnprocessable();
        $this->actingAs($this->user)->get('/games')->assertSee('Your best: 100%')->assertSee('Your best: 300 points');
    }

    public function test_daily_game_cowries_are_capped(): void
    {
        $this->finishFirstLesson();
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->user)->postJson('/games/drum/complete', ['score' => 60]); // 10 each
        }
        $this->actingAs($this->user)->postJson('/games/memory/complete', ['score' => 100])
            ->assertJson(['cowries_earned' => 0, 'capped' => true]);
        $this->assertSame(15 + 30, $this->user->fresh()->cowries);

        $this->travel(1)->days();
        $this->actingAs($this->user)->postJson('/games/memory/complete', ['score' => 100])
            ->assertJson(['cowries_earned' => 7, 'capped' => false]);
    }
}
