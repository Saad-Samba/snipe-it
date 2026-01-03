<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use Illuminate\Http\Request;

class DisciplinesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        abort_unless(auth()->user()->isSuperUser(), 403);

        $disciplines = Discipline::orderBy('name')->get();

        return view('disciplines.index', compact('disciplines'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isSuperUser(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:disciplines,name'],
        ]);

        Discipline::create($validated);

        return redirect()->back()->with('success', __('Discipline created.'));
    }

    public function destroy(Discipline $discipline)
    {
        abort_unless(auth()->user()->isSuperUser(), 403);

        $discipline->delete();

        return redirect()->back()->with('success', __('Discipline deleted.'));
    }
}
