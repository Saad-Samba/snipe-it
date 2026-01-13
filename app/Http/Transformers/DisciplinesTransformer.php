<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Discipline;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class DisciplinesTransformer
{
    public function transformDisciplines(Collection $disciplines, $total)
    {
        $array = [];
        foreach ($disciplines as $discipline) {
            $array[] = self::transformDiscipline($discipline);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformDiscipline(Discipline $discipline = null)
    {
        if ($discipline) {
            $array = [
                'id' => (int) $discipline->id,
                'name' => e($discipline->name),
                'assets_count' => (int) $discipline->assets_count,
                'licenses_count' => (int) $discipline->licenses_count,
                'created_by' => ($discipline->adminuser) ? [
                    'id' => (int) $discipline->adminuser->id,
                    'name' => e($discipline->adminuser->display_name),
                ] : null,
                'created_at' => Helper::getFormattedDateObject($discipline->created_at, 'datetime'),
                'updated_at' => Helper::getFormattedDateObject($discipline->updated_at, 'datetime'),
                'deleted_at' => Helper::getFormattedDateObject($discipline->deleted_at, 'datetime'),
            ];

            $permissions_array['available_actions'] = [
                'update' => (($discipline->deleted_at == '') && Gate::allows('update', Discipline::class)),
                'restore' => (($discipline->deleted_at != '') && Gate::allows('create', Discipline::class)),
                'delete' => $discipline->isDeletable() && Gate::allows('delete', Discipline::class),
            ];

            $array += $permissions_array;

            return $array;
        }
    }
}
