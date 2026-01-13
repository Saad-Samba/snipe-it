<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDisciplineRequest;
use App\Http\Transformers\DisciplinesTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Discipline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplinesController extends Controller
{
    public function index(Request $request): JsonResponse|array
    {
        $this->authorize('view', Discipline::class);

        $allowed_columns = ['id', 'name', 'assets_count', 'licenses_count', 'created_at', 'created_by'];

        $disciplines = Discipline::select([
            'disciplines.id',
            'disciplines.name',
            'disciplines.created_at',
            'disciplines.updated_at',
            'disciplines.deleted_at',
            'disciplines.created_by',
        ])->with('adminuser')
            ->withCount(['assets', 'licenses']);

        if ($request->filled('search')) {
            $disciplines = $disciplines->TextSearch($request->input('search'));
        }

        if ($request->filled('name')) {
            $disciplines->where('disciplines.name', '=', $request->input('name'));
        }

        $offset = ($request->input('offset') > $disciplines->count()) ? $disciplines->count() : app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';

        switch ($request->input('sort')) {
            case 'created_by':
                $disciplines->OrderByCreatedBy($order);
                break;
            default:
                $disciplines->orderBy($sort, $order);
                break;
        }

        $total = $disciplines->count();
        $disciplines = $disciplines->skip($offset)->take($limit)->get();

        return (new DisciplinesTransformer)->transformDisciplines($disciplines, $total);
    }

    public function store(StoreDisciplineRequest $request): JsonResponse
    {
        $discipline = new Discipline();
        $discipline->fill($request->validated());
        $discipline->created_by = auth()->id();

        if ($discipline->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', (new DisciplinesTransformer)->transformDiscipline($discipline), trans('admin/disciplines/message.create.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $discipline->getErrors()));
    }

    public function show($id): array
    {
        $this->authorize('view', Discipline::class);
        $discipline = Discipline::withCount(['assets', 'licenses'])->findOrFail($id);
        return (new DisciplinesTransformer)->transformDiscipline($discipline);
    }

    public function update(StoreDisciplineRequest $request, $id): JsonResponse
    {
        $this->authorize('update', Discipline::class);

        $discipline = Discipline::findOrFail($id);
        $discipline->fill($request->validated());

        if ($discipline->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', (new DisciplinesTransformer)->transformDiscipline($discipline), trans('admin/disciplines/message.update.success')));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $discipline->getErrors()));
    }

    public function destroy($id): JsonResponse
    {
        $discipline = Discipline::findOrFail($id);

        $this->authorize('delete', $discipline);

        if (! $discipline->isDeletable()) {
            return response()->json(Helper::formatStandardApiResponse('error', null, trans('admin/disciplines/message.assoc_assets')));
        }

        $discipline->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, trans('admin/disciplines/message.delete.success')));
    }

    public function selectlist(Request $request): array
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

        return (new SelectlistTransformer)->transformSelectlist($disciplines);
    }
}
