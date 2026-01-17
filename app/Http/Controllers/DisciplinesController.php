<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisciplinesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        parent::__construct();
    }

    public function index() : View
    {
        $this->authorize('index', Discipline::class);

        return view('disciplines/index');
    }

    public function create() : View
    {
        $this->authorize('create', Discipline::class);

        return view('disciplines/edit')->with('item', new Discipline);
    }

    public function store(Request $request) : RedirectResponse
    {
        $this->authorize('create', Discipline::class);

        $discipline = new Discipline;
        $discipline->fill($request->all());
        $discipline->created_by = auth()->id();

        if ($discipline->save()) {
            return redirect()->route('disciplines.index')->with('success', trans('admin/disciplines/message.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($discipline->getErrors());
    }

    public function show(Discipline $discipline) : View
    {
        $this->authorize('view', $discipline);

        $discipline->loadCount(['assets', 'licenses']);

        return view('disciplines/view')->with('discipline', $discipline);
    }

    public function edit(Discipline $discipline) : View
    {
        $this->authorize('update', $discipline);

        return view('disciplines/edit')->with('item', $discipline);
    }

    public function update(Request $request, Discipline $discipline) : RedirectResponse
    {
        $this->authorize('update', $discipline);

        $discipline->fill($request->all());

        if ($discipline->save()) {
            return redirect()->route('disciplines.index')->with('success', trans('admin/disciplines/message.update.success'));
        }

        return redirect()->back()->withInput()->withErrors($discipline->getErrors());
    }

    public function destroy(Discipline $discipline) : RedirectResponse
    {
        $this->authorize('delete', $discipline);

        if ($discipline->assets()->count() > 0 || $discipline->licenses()->count() > 0) {
            return redirect()->route('disciplines.index')->with('error', trans('admin/disciplines/message.assoc_items'));
        }

        $discipline->delete();

        return redirect()->route('disciplines.index')->with('success', trans('admin/disciplines/message.delete.success'));
    }
}
