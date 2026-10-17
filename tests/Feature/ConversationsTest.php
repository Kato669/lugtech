<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Phrase;
use App\Services\LessonPlan;
use App\Support\Conversations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedAutomaticCourse(); // these lessons as automatic lessons (Phase 6 makes them scripted)
    }

    private function dialogues(string $slug): array
    {
        return collect((new LessonPlan(seed: 1))->build(Lesson::where('slug', $slug)->first()))
            ->where('type', 'dialogue')->pluck('lines')
            ->map(fn ($lines) => collect($lines)->pluck('luganda')->implode(' / '))->values()->all();
    }

    public function test_every_script_matches_seeded_phrases(): void
    {
        $this->assertSame(array_keys(Conversations::SCRIPTS), Conversations::all()->keys()->all());
        $line = Conversations::all()['greeting'][0];
        $this->assertSame(['who' => 0, 'luganda' => 'Oli otya?', 'english' => 'How are you?'], array_intersect_key($line, array_flip(['who', 'luganda', 'english'])));
    }

    public function test_conversations_appear_in_the_lesson_where_they_become_possible(): void
    {
        $this->assertSame(['Oli otya? / Bulungi', 'Ki kati? / Gyendi'], $this->dialogues('okulamusa'));
        $this->assertSame(['Wasuze otya? / Nsuze bulungi', 'Osiibye otya? / Nsiibye bulungi'], $this->dialogues('enkya-n-akawungeezi'));
        $this->assertSame(['Jjangu tulye! / Webale nnyo'], $this->dialogues('ku-mmeeza'));
        $this->assertSame([], $this->dialogues('ssente'), 'market talk needs the buying phrases first');
        $this->assertSame(['Kino kya ssente mmeka? / Omutwalo / Kendeezaako / Kale'], $this->dialogues('okugula'));
        $this->assertSame([], $this->dialogues('emmere'));
    }

    public function test_a_renamed_phrase_drops_its_conversation_quietly(): void
    {
        Phrase::where('luganda', 'Ki kati?')->update(['luganda' => 'Kiki kati?']);
        $this->assertSame(['Oli otya? / Bulungi'], $this->dialogues('okulamusa'));
        $this->get('/learn/okulamusa')->assertOk();
    }

    public function test_home_page_taster_and_lesson_show_the_characters(): void
    {
        $this->get('/')->assertOk()->assertSee('Listen in. Then answer.')
            ->assertSee('aria-label="Mukasa"', false)->assertSee('aria-label="Nakato"', false)->assertSee('Wasuze otya?');
        $this->get('/learn/okulamusa')->assertOk()->assertSee('Mukasa and Nakato use what you just learned');
    }

    private function practice(string $slug): array
    {
        return collect((new LessonPlan(seed: 1))->build(Lesson::where('slug', $slug)->first()))
            ->where('type', 'converse')->values()->all();
    }

    public function test_your_turn_comes_after_watching_and_uses_the_right_role(): void
    {
        $steps = collect((new LessonPlan(seed: 1))->build(Lesson::where('slug', 'okulamusa')->first()));
        $types = $steps->pluck('type');
        $this->assertSame(2, $types->filter(fn ($t) => $t === 'converse')->count());
        $this->assertGreaterThan($types->search('dialogue'), $types->search('converse'), 'watch first, then take a part');
        $this->assertSame('converse', $types->last());

        [$greeting] = $this->practice('okulamusa');
        $this->assertSame(Conversations::NAKATO, $greeting['you']);
        $this->assertSame([1], array_keys($greeting['options']));
        $this->assertEqualsCanonicalizing(['Bulungi', 'Webale', 'Ki kati?'], $greeting['options'][1]);
        $this->assertSame('Bulungi', $greeting['answer']);
    }

    public function test_morning_and_afternoon_replies_are_traps_for_each_other(): void
    {
        [$morning, $afternoon] = $this->practice('enkya-n-akawungeezi');
        $this->assertSame(Conversations::MUKASA, $morning['you']);
        $this->assertContains('Nsiibye bulungi', $morning['options'][1]);
        $this->assertContains('Nsuze bulungi', $afternoon['options'][1]);
    }

    public function test_market_has_two_turns_for_the_seller(): void
    {
        [$market] = $this->practice('okugula');
        $this->assertSame(Conversations::NAKATO, $market['you']);
        $this->assertSame([1, 3], array_keys($market['options']));
        $this->assertSame('Omutwalo … Kale', $market['answer']);
    }

    public function test_missing_wrong_replies_are_topped_up_from_the_lesson(): void
    {
        Phrase::where('luganda', 'Webale')->update(['luganda' => 'Webale (renamed)']);
        foreach (range(1, 20) as $attempt) { // backups are random, so check many draws
            [$greeting] = $this->practice('okulamusa');
            $this->assertCount(3, $greeting['options'][1]);
            $this->assertContains('Bulungi', $greeting['options'][1]);
            $this->assertNotContains('Gyendi', $greeting['options'][1], 'another real reply is never offered as wrong');
            $this->assertNotContains('Webale nnyo', $greeting['options'][1]);
        }
    }

    public function test_lesson_page_includes_your_turn(): void
    {
        $this->get('/learn/okulamusa')->assertOk()->assertSee('converseStep', false)->assertSee('Your turn');
    }
}
