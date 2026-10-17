<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StageController extends Controller
{
    public function index()
    {
        return view('admin.stages.index', ['stages' => Stage::withCount('lessons')->orderBy('position')->get()]);
    }

    public function create()
    {
        return view('admin.stages.form', ['stage' => new Stage(['position' => Stage::max('position') + 1])]);
    }

    public function store(Request $request)
    {
        Stage::create($this->validated($request));

        return redirect()->route('admin.stages.index')->with('status', 'Stage created.');
    }

    public function edit(Stage $stage)
    {
        return view('admin.stages.form', ['stage' => $stage]);
    }

    public function update(Request $request, Stage $stage)
    {
        $stage->update($this->validated($request, $stage));

        return redirect()->route('admin.stages.index')->with('status', 'Stage saved.');
    }

    public function destroy(Stage $stage)
    {
        $stage->delete();

        return redirect()->route('admin.stages.index')->with('status', 'Stage deleted.');
    }

    private function validated(Request $request, ?Stage $stage = null): array
    {
        return $request->validate([
            'position' => ['required', 'integer', 'min:0'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('stages')->ignore($stage)],
            'title_lg' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'greeting' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
