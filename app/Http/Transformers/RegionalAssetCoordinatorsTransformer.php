<?php

namespace App\Http\Transformers;

use Illuminate\Support\Collection;

class RegionalAssetCoordinatorsTransformer
{
    public function transformAssignments(Collection $assignments, int $total): array
    {
        $rows = $assignments->map(function ($assignment) {
            $name = $assignment->display_name
                ?: trim($assignment->first_name.' '.$assignment->last_name)
                ?: $assignment->username;

            return [
                'id' => (int) $assignment->id,
                'coordinator' => e($name),
                'company' => e($assignment->company),
                'discipline' => e($assignment->discipline),
                'email' => $assignment->email ? e($assignment->email) : null,
                'phone' => $assignment->phone ? e($assignment->phone) : null,
            ];
        })->values()->all();

        return (new DatatablesTransformer)->transformDatatables($rows, $total);
    }
}
