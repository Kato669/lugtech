<?php

namespace App\Services;

use App\Models\DailyActivity;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Phase 12: send the daily reminders that are due this hour (`php artisan app:send-reminders`,
 * run hourly by the scheduler). A learner gets one only at the hour they chose, in their own time
 * zone, once a day, and never if they've already practised today.
 */
class DailyReminders
{
    public function __construct(private WebPush $push) {}

    /** @return array{sent: int, skipped: int, removed: int, failed: int} */
    public function run(?CarbonImmutable $now = null, bool $dryRun = false): array
    {
        $now ??= CarbonImmutable::now();
        $stats = ['sent' => 0, 'skipped' => 0, 'removed' => 0, 'failed' => 0];
        if (! WebPush::configured()) {
            return $stats;
        }

        User::whereNotNull('reminder_hour')->whereHas('pushSubscriptions')->with('pushSubscriptions')
            ->orderBy('id')->chunkById(200, function ($users) use ($now, $dryRun, &$stats) {
                foreach ($users as $user) {
                    $local = $now->setTimezone($this->timezoneOf($user));
                    if ($local->hour !== $user->reminder_hour || $this->practisedToday($user)) {
                        $stats['skipped']++;

                        continue;
                    }
                    foreach ($user->pushSubscriptions as $sub) {
                        $this->sendOne($sub, $local->toDateString(), $dryRun, $stats);
                    }
                }
            });

        return $stats;
    }

    private function sendOne(PushSubscription $sub, string $localDay, bool $dryRun, array &$stats): void
    {
        if ($sub->last_sent_on?->toDateString() === $localDay) {
            $stats['skipped']++;

            return;
        }
        if ($dryRun) {
            $stats['sent']++;

            return;
        }
        try {
            $status = $this->push->send($sub);
        } catch (\Throwable $e) {
            Log::warning('Reminder not sent: '.$e->getMessage());
            $status = 0;
        }
        if ($status >= 200 && $status < 300) {
            $sub->forceFill(['last_sent_on' => $localDay, 'failures' => 0])->save();
            $stats['sent']++;
        } elseif (in_array($status, [404, 410], true) || $sub->failures >= 4) {
            $sub->delete(); // the browser unsubscribed (or keeps failing)
            $stats['removed']++;
        } else {
            $sub->increment('failures');
            $stats['failed']++;
        }
    }

    public function timezoneOf(User $user): string
    {
        return $user->timezone && in_array($user->timezone, timezone_identifiers_list(), true) ? $user->timezone : DailyHabit::timezone();
    }

    private function practisedToday(User $user): bool
    {
        if ($user->last_active_on && DailyHabit::daysSince($user->last_active_on) === 0) {
            return true;
        }
        $row = DailyActivity::where('user_id', $user->id)->where('day', DailyHabit::today())->first();

        return $row && ($row->lessons + $row->reviews + $row->speaks + $row->games > 0);
    }
}
