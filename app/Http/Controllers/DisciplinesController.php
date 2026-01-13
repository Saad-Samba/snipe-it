<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDisciplineRequest;
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

    public function index(Request $request): View
    {
        $this->authorize('index', Discipline::class);

        return view('disciplines/index');
    }

    public function create(): View
    {
        $this->authorize('create', Discipline::class);

        return view('disciplines/edit')->with('item', new Discipline);
    }

    public function store(StoreDisciplineRequest $request): RedirectResponse
    {
        $this->authorize('create', Discipline::class);

        $discipline = new Discipline();
        $discipline->fill($request->validated());
        $discipline->created_by = auth()->id();

        if ($discipline->save()) {
            return redirect()->route('disciplines.index')
                ->with('success', trans('admin/disciplines/message.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($discipline->getErrors());
    }

    public function show(Discipline $discipline): View
    {
        $this->authorize('view', $discipline);

        return view('disciplines/view', compact('discipline'));
    }

    public function edit(Discipline $discipline): View
    {
        $this->authorize('update', $discipline);

        return view('disciplines/edit')->with('item', $discipline);
    }

    public function update(StoreDisciplineRequest $request, Discipline $discipline): RedirectResponse
    {
        $this->authorize('update', $discipline);

        $discipline->fill($request->validated());

        if ($discipline->save()) {
            return redirect()->route('disciplines.index')
                ->with('success', trans('admin/disciplines/message.update.success'));
        }

        return redirect()->back()->withInput()->withErrors($discipline->getErrors());
    }

    public function destroy(Discipline $discipline): RedirectResponse
    {
        $this->authorize('delete', $discipline);

        if (! $discipline->isDeletable()) {
            return redirect()->route('disciplines.index')
                ->with('error', trans('admin/disciplines/message.assoc_assets'));
        }

        $discipline->delete();

        return redirect()->route('disciplines.index')
            ->with('success', trans('admin/disciplines/message.delete.success'));
    }
}
