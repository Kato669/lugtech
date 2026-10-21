<?php

namespace App\Lessons;

use App\Lessons\Activities\Activity;
use App\Lessons\Activities\BuildActivity;
use App\Lessons\Activities\ChoiceActivity;
use App\Lessons\Activities\ConverseActivity;
use App\Lessons\Activities\DialogueActivity;
use App\Lessons\Activities\FindActivity;
use App\Lessons\Activities\IntroActivity;
use App\Lessons\Activities\LearnActivity;
use App\Lessons\Activities\MatchActivity;
use App\Lessons\Activities\PatternActivity;
use App\Lessons\Activities\RewardActivity;
use App\Lessons\Activities\SequenceActivity;
use App\Lessons\Activities\SortActivity;
use App\Lessons\Activities\SpeakActivity;

/**
 * Every activity a lesson can use. To add one: write a class implementing
 * Activity, list it here, and add its view in resources/views/lesson/steps/.
 */
class ActivityRegistry
{
    /** @var class-string<Activity>[] */
    public const ACTIVITIES = [
        IntroActivity::class,
        LearnActivity::class,
        SpeakActivity::class,
        ChoiceActivity::class,
        BuildActivity::class,
        FindActivity::class,
        MatchActivity::class,
        SequenceActivity::class,
        DialogueActivity::class,
        ConverseActivity::class,
        PatternActivity::class,  // Phase 16: grammar card
        SortActivity::class,     // Phase 16: sort into baskets
        RewardActivity::class,
    ];

    /** @var array<string, Activity> */
    private array $byType = [];

    public function __construct()
    {
        foreach (self::ACTIVITIES as $class) {
            $activity = new $class;
            foreach ($activity->types() as $type) {
                $this->byType[$type] = $activity;
            }
        }
    }

    public function has(string $type): bool
    {
        return isset($this->byType[$type]);
    }

    public function get(string $type): Activity
    {
        return $this->byType[$type] ?? throw new \InvalidArgumentException("Unknown lesson activity \"{$type}\".");
    }

    /** @return string[] */
    public function types(): array
    {
        return array_keys($this->byType);
    }
}
