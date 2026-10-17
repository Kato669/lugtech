<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Phrase;
use App\Models\User;
use App\Models\Word;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $people = User::where('is_guest', false)
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($t).'%'])
                ->orWhereRaw('LOWER(email) LIKE ?', ['%'.mb_strtolower($t).'%'])))
            ->orderByDesc('is_admin')->orderByDesc('is_reviewer')->orderBy('name')
            ->paginate(50)->withQueryString();

        $ids = $people->pluck('id');
        $recorded = Phrase::whereIn('recorded_by', $ids)->selectRaw('recorded_by, count(*) as n')->groupBy('recorded_by')->pluck('n', 'recorded_by');
        $reviewed = Phrase::whereIn('reviewed_by', $ids)->selectRaw('reviewed_by, count(*) as n')->groupBy('reviewed_by')->pluck('n', 'reviewed_by');
        foreach ([Word::class] as $class) {
            foreach ($class::whereIn('recorded_by', $ids)->selectRaw('recorded_by, count(*) as n')->groupBy('recorded_by')->pluck('n', 'recorded_by') as $id => $n) {
                $recorded[$id] = ($recorded[$id] ?? 0) + $n;
            }
            foreach ($class::whereIn('reviewed_by', $ids)->selectRaw('reviewed_by, count(*) as n')->groupBy('reviewed_by')->pluck('n', 'reviewed_by') as $id => $n) {
                $reviewed[$id] = ($reviewed[$id] ?? 0) + $n;
            }
        }

        return view('admin.people', compact('people', 'recorded', 'reviewed'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['is_reviewer' => ['required', 'boolean']]);
        abort_if($user->is_guest, 422);
        $user->forceFill(['is_reviewer' => $data['is_reviewer']])->save();

        return back()->with('status', $data['is_reviewer'] ? "{$user->name} can now record and check Luganda." : "{$user->name} is no longer a reviewer.");
    }
}
