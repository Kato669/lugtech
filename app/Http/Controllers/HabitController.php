<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\DailyHabit;
use App\Services\WebPush;
use Illuminate\Http\Request;

/** Phase 12: streak freezes and the daily reminder. */
class HabitController extends Controller
{
    public function buyFreeze(Request $request, DailyHabit $habit)
    {
        $problem = $habit->buyFreeze($request->user());

        return redirect()->route('course')->with('status', $problem ?? '🧊 Streak freeze bought. If you miss a day, it keeps your streak going.');
    }

    /** Switch the reminder on (or change its hour), for this browser. */
    public function remind(Request $request)
    {
        abort_unless(WebPush::configured(), 404, 'Reminders are not set up on this site yet.');
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['nullable', 'string', 'max:255'],
            'keys.auth' => ['nullable', 'string', 'max:255'],
            'hour' => ['required', 'integer', 'min:0', 'max:23'],
            'timezone' => ['nullable', 'timezone:all'],
        ]);
        $user = $request->user();
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            ['user_id' => $user->id, 'endpoint' => $data['endpoint'], 'p256dh' => $data['keys']['p256dh'] ?? null, 'auth' => $data['keys']['auth'] ?? null, 'failures' => 0],
        );
        $user->forceFill(['reminder_hour' => $data['hour'], 'timezone' => $data['timezone'] ?? $user->timezone])->save();

        return response()->json(['ok' => true, 'hour' => $user->reminder_hour]);
    }

    /** Switch reminders off (everywhere, or just this browser's subscription). */
    public function stopReminding(Request $request)
    {
        $data = $request->validate(['endpoint' => ['nullable', 'string', 'max:2000']]);
        $user = $request->user();
        if (filled($data['endpoint'] ?? null)) {
            $user->pushSubscriptions()->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();
        }
        if (! filled($data['endpoint'] ?? null) || ! $user->pushSubscriptions()->exists()) {
            $user->pushSubscriptions()->delete();
            $user->forceFill(['reminder_hour' => null])->save();
        }

        return response()->json(['ok' => true]);
    }
}
