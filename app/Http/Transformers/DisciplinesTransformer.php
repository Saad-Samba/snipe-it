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

    public function transformDiscipline(Discipline $discipline)
    {
        $array = [
            'id' => (int) $discipline->id,
            'name' => e($discipline->name),
            'assets_count' => (int) $discipline->assets_count,
            'licenses_count' => (int) $discipline->licenses_count,
            'notes' => Helper::parseEscapedMarkedownInline($discipline->notes),
            'created_at' => Helper::getFormattedDateObject($discipline->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($discipline->updated_at, 'datetime'),
            'created_by' => $discipline->creator ? [
                'id' => (int) $discipline->creator->id,
                'name' => e($discipline->creator->display_name),
            ] : null,
        ];

        $permissions_array['available_actions'] = [
            'update' => Gate::allows('update', Discipline::class),
            'delete' => Gate::allows('delete', $discipline),
        ];

        $array += $permissions_array;

        return $array;
    }
}
