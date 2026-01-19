<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\DisciplinesTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Discipline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplinesController extends Controller
{
    public function index(Request $request) : JsonResponse | array
    {
        $this->authorize('view', Discipline::class);

        $allowed_columns = ['id', 'name', 'notes', 'created_at'];

        $disciplines = Discipline::with('creator')->withCount(['assets', 'licenses']);

        if ($request->filled('search')) {
            $disciplines = $disciplines->TextSearch($request->input('search'));
        }

        if ($request->filled('name')) {
            $disciplines->where('name', '=', $request->input('name'));
        }

        $offset = ($request->input('offset') > $disciplines->count()) ? $disciplines->count() : app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';

        $disciplines->orderBy($sort, $order);

        $total = $disciplines->count();
        $disciplines = $disciplines->skip($offset)->take($limit)->get();

        return (new DisciplinesTransformer)->transformDisciplines($disciplines, $total);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Discipline::class);

        $discipline = new Discipline;
        $discipline->fill($request->all());
        $discipline->created_by = auth()->id();

        if ($discipline->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', (new DisciplinesTransformer)->transformDiscipline($discipline), trans('admin/disciplines/message.create.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $discipline->getErrors()));
    }

    public function show(Discipline $discipline) : array
    {
        $this->authorize('view', Discipline::class);
        $discipline->loadCount(['assets', 'licenses']);

        return (new DisciplinesTransformer)->transformDiscipline($discipline);
    }

    public function update(Request $request, Discipline $discipline) : JsonResponse
    {
        $this->authorize('update', $discipline);

        $discipline->fill($request->all());

        if ($discipline->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', (new DisciplinesTransformer)->transformDiscipline($discipline), trans('admin/disciplines/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $discipline->getErrors()));
    }

    public function destroy(Discipline $discipline) : JsonResponse
    {
        $this->authorize('delete', $discipline);

        if ($discipline->assets()->count() > 0 || $discipline->licenses()->count() > 0) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/disciplines/message.assoc_items')));
        }

        $discipline->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/disciplines/message.delete.success')));
    }

    public function selectlist(Request $request) : array
    {
        $this->authorize('view.selectlists');

        $disciplines = Discipline::select([
            'id',
            'name',
        ]);

        if ($request->filled('search')) {
            $disciplines = $disciplines->where('name', 'LIKE', '%'.$request->get('search').'%');
        }

        $disciplines = $disciplines->orderBy('name', 'ASC')->paginate(50);

        foreach ($disciplines as $discipline) {
            $discipline->use_image = null;
        }

        return (new SelectlistTransformer)->transformSelectlist($disciplines);
    }
}
