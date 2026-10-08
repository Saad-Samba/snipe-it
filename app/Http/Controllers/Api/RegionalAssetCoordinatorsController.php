<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\RegionalAssetCoordinatorsTransformer;
use App\Models\RegionalAssetCoordinatorAssignment;
use Illuminate\Http\Request;

class RegionalAssetCoordinatorsController extends Controller
{
    public function index(Request $request): array
    {
        $allowedColumns = [
            'coordinator',
            'company',
            'discipline',
            'email',
            'phone',
        ];

        $sortColumns = [
            'coordinator' => 'users.display_name',
            'company' => 'companies.name',
            'discipline' => 'disciplines.name',
            'email' => 'users.email',
            'phone' => 'users.phone',
        ];

        $assignments = RegionalAssetCoordinatorAssignment::query()
            ->join('users', 'users.id', '=', 'regional_asset_coordinator_assignments.user_id')
            ->join('companies', 'companies.id', '=', 'regional_asset_coordinator_assignments.company_id')
            ->join('disciplines', 'disciplines.id', '=', 'regional_asset_coordinator_assignments.discipline_id')
            ->where('users.activated', 1)
            ->whereNull('users.deleted_at')
            ->whereNull('disciplines.deleted_at')
            ->select([
                'regional_asset_coordinator_assignments.id',
                'users.id as coordinator_id',
                'users.first_name',
                'users.last_name',
                'users.display_name',
                'users.username',
                'users.email',
                'users.phone',
                'companies.name as company',
                'disciplines.name as discipline',
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $assignments->where(function ($query) use ($search) {
                $query
                    ->where('users.display_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('users.first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('users.email', 'LIKE', '%'.$search.'%')
                    ->orWhere('companies.name', 'LIKE', '%'.$search.'%')
                    ->orWhere('disciplines.name', 'LIKE', '%'.$search.'%');
            });
        }

        $offset = ($request->input('offset') > $assignments->count())
            ? $assignments->count()
            : app('api_offset_value');
        $limit = app('api_limit_value');
        $order = $request->input('order') === 'desc' ? 'desc' : 'asc';
        $sort = in_array($request->input('sort'), $allowedColumns, true)
            ? $request->input('sort')
            : 'company';

        $assignments->orderBy($sortColumns[$sort], $order);

        if ($sort !== 'discipline') {
            $assignments->orderBy('disciplines.name', 'asc');
        }

        $total = $assignments->count();
        $assignments = $assignments->skip($offset)->take($limit)->get();

        return (new RegionalAssetCoordinatorsTransformer)->transformAssignments($assignments, $total);
    }
}
