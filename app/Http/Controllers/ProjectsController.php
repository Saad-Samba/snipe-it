<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class ProjectsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        parent::__construct();
    }

    public function index() : View
    {
        $this->authorize('index', Project::class);

        return view('projects/index');
    }

    public function create() : View
    {
        $this->authorize('create', Project::class);

        return view('projects/edit')->with('item', new Project);
    }

    public function store(Request $request) : RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = new Project;
        $project->fill($request->all());
        $project->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $project->created_by = auth()->id();

        if ($project->save()) {
            return redirect()->route('projects.index')->with('success', trans('admin/projects/message.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($project->getErrors());
    }

    public function show(Project $project) : View
    {
        $this->authorize('view', $project);

        return view('projects/view')->with('project', $project);
    }

    public function edit(Project $project) : View
    {
        $this->authorize('update', $project);

        return view('projects/edit')->with('item', $project);
    }

    public function update(Request $request, Project $project) : RedirectResponse
    {
        $this->authorize('update', $project);

        $project->fill($request->all());
        $project->company_id = Company::getIdForCurrentUser($request->input('company_id'));

        if ($project->save()) {
            return redirect()->route('projects.index')->with('success', trans('admin/projects/message.update.success'));
        }

        return redirect()->back()->withInput()->withErrors($project->getErrors());
    }

    public function destroy(Project $project) : RedirectResponse
    {
        $this->authorize('delete', $project);

        if ($project->assets()->count() > 0 || $project->licenses()->count() > 0) {
            return redirect()->route('projects.index')->with('error', trans('admin/projects/message.assoc_items'));
        }

        $project->delete();

        return redirect()->route('projects.index')->with('success', trans('admin/projects/message.delete.success'));
    }
}
