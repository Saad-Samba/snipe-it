<?php

namespace App\Http\Controllers;

use App\Models\CheckoutRequest;
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
        $project->created_by = auth()->id();

        if ($project->save()) {
            return redirect()->route('projects.index')->with('success', trans('admin/projects/message.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($project->getErrors());
    }

    public function show(Project $project) : View
    {
        $activeTab = request()->query('tab', 'assets');
        $isRequestsTab = $activeTab === 'requests';
        $isRequesterProjectReview = ! auth()->user()->isSuperUser()
            && auth()->user()->hasAccess('models.request')
            && $isRequestsTab;
        $requestSummary = null;

        if ($isRequesterProjectReview) {
            $requestSummary = $this->authorizeProjectRequestsAccess($project);
        } elseif (! auth()->user()->isSuperUser() && auth()->user()->hasAccess('models.request')) {
            abort(403);
        } else {
            $this->authorize('view', $project);
        }

        $project->loadCount(['assets', 'licenses']);

        if (auth()->user()->hasAccess('models.request') && ! $requestSummary) {
            $requestSummary = CheckoutRequest::projectSummaryForUser(auth()->id(), $project->id);
        }

        return view('projects/view', [
            'project' => $project,
            'activeTab' => in_array($activeTab, ['assets', 'licenses', 'requests'], true) ? $activeTab : 'assets',
            'requestSummary' => $requestSummary,
            'showFullProjectTabs' => auth()->user()->isSuperUser(),
        ]);
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

    private function authorizeProjectRequestsAccess(Project $project): ?array
    {
        abort_unless(auth()->user()->hasAccess('models.request'), 403, 'You are not authorized to view submitted requests.');

        if (auth()->user()->isSuperUser()) {
            $this->authorize('view', $project);

            return null;
        }

        $requestSummary = CheckoutRequest::projectSummaryForUser(auth()->id(), $project->id);
        abort_if(($requestSummary['requests_count'] ?? 0) < 1, 403);

        return $requestSummary;
    }
}
