<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ProjectsTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectsController extends Controller
{
    public function index(Request $request) : JsonResponse | array
    {
        $this->authorize('view', Project::class);

        $allowed_columns = ['id', 'name', 'notes', 'created_at'];

        $projects = Project::with('company', 'creator')->withCount(['assets', 'licenses']);

        if ($request->filled('search')) {
            $projects = $projects->TextSearch($request->input('search'));
        }

        if ($request->filled('name')) {
            $projects->where('name', '=', $request->input('name'));
        }

        if ($request->filled('company_id')) {
            $projects->where('company_id', '=', $request->input('company_id'));
        }

        $offset = ($request->input('offset') > $projects->count()) ? $projects->count() : app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';

        $projects->orderBy($sort, $order);

        $total = $projects->count();
        $projects = $projects->skip($offset)->take($limit)->get();

        return (new ProjectsTransformer)->transformProjects($projects, $total);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = new Project;
        $project->fill($request->all());
        $project->company_id = Company::getIdForCurrentUser($request->input('company_id'));
        $project->created_by = auth()->id();

        if ($project->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', (new ProjectsTransformer)->transformProject($project), trans('admin/projects/message.create.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $project->getErrors()));
    }

    public function show(Project $project) : array
    {
        $this->authorize('view', Project::class);
        $project->loadCount(['assets', 'licenses']);

        return (new ProjectsTransformer)->transformProject($project);
    }

    public function update(Request $request, Project $project) : JsonResponse
    {
        $this->authorize('update', Project::class);

        $project->fill($request->all());
        $project->company_id = Company::getIdForCurrentUser($request->input('company_id'));

        if ($project->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', (new ProjectsTransformer)->transformProject($project), trans('admin/projects/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $project->getErrors()));
    }

    public function destroy(Project $project) : JsonResponse
    {
        $this->authorize('delete', $project);

        if ($project->assets()->count() > 0 || $project->licenses()->count() > 0) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/projects/message.assoc_items')));
        }

        $project->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/projects/message.delete.success')));
    }

    public function selectlist(Request $request) : array
    {
        $this->authorize('view.selectlists');

        $projects = Project::select([
            'id',
            'name',
            'company_id',
        ]);

        if ($request->filled('search')) {
            $projects = $projects->where('name', 'LIKE', '%'.$request->get('search').'%');
        }

        if ($request->filled('company_id')) {
            $projects->where('company_id', '=', $request->input('company_id'));
        }

        $projects = $projects->orderBy('name', 'ASC')->paginate(50);

        foreach ($projects as $project) {
            $project->use_image = null;
        }

        return (new SelectlistTransformer)->transformSelectlist($projects);
    }
}
