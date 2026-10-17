<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\AudioClip;
use Illuminate\Http\Request;

/**
 * Reviewers listen to each recording and approve, flag or reject it.
 * This is separate from approving the Luganda text.
 */
class AudioReviewController extends Controller
{
    public function update(Request $request, AudioClip $clip)
    {
        abort_unless($clip->is_current, 404);
        $data = $request->validate([
            'action' => ['required', 'in:approve,flag,reject'],
            'note' => ['required_if:action,flag', 'nullable', 'string', 'max:1000'],
        ]);

        if ($data['action'] === 'approve' && $clip->provider === 'fake') {
            return response()->json(['message' => 'Test beeps are not Luganda and can never be approved.'], 422);
        }

        match ($data['action']) {
            'approve' => $clip->approve($request->user()),
            'flag' => $clip->flag($request->user(), $data['note']),
            'reject' => $clip->reject($request->user(), $data['note'] ?? null),
        };

        return response()->json([
            'status' => $clip->status,
            'is_current' => $clip->is_current,
            'note' => $clip->review_note,
            'recorded_for' => $clip->text_snapshot,
        ]);
    }

    /** Admins can remove a flagged (or rejected) recording completely: file and record. */
    public function destroy(Request $request, AudioClip $clip)
    {
        abort_unless($request->user()->is_admin, 403);
        if (! in_array($clip->status, ['flagged', 'rejected'], true)) {
            return response()->json(['message' => 'Only flagged or rejected recordings can be deleted. Flag it first.'], 422);
        }
        $clip->deletePermanently();

        return response()->json(['deleted' => true]);
    }
}
