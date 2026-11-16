<?php

namespace App\Actions\Users;

use App\Models\RegionalAssetCoordinatorAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncUserRacAssignmentsAction
{
    public static function run(User $user, Request $request): void
    {
        $hasExistingAssignments = $user->racAssignments()->exists();
        $hasRacInput = $request->hasAny([
            'rac_enabled',
            'rac_discipline_id',
            'rac_discipline_ids',
        ]) || ($request->has('company_id') && $hasExistingAssignments);

        if ($request->isMethod('patch') && ! $hasRacInput) {
            return;
        }

        $racEnabled = $request->has('rac_enabled')
            ? $request->boolean('rac_enabled')
            : $hasExistingAssignments;

        if (! $racEnabled) {
            $user->racAssignments()->delete();

            return;
        }

        $disciplineIds = self::disciplineIdsFrom($request, $user);

        DB::transaction(function () use ($user, $disciplineIds) {
            $user->racAssignments()
                ->where(function ($query) use ($user, $disciplineIds) {
                    $query
                        ->where('company_id', '!=', $user->company_id)
                        ->orWhereNotIn('discipline_id', $disciplineIds);
                })
                ->delete();

            foreach ($disciplineIds as $disciplineId) {
                RegionalAssetCoordinatorAssignment::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'company_id' => $user->company_id,
                        'discipline_id' => $disciplineId,
                    ],
                    [
                        'created_by' => auth()->id(),
                    ]
                );
            }
        });
    }

    private static function disciplineIdsFrom(Request $request, User $user): array
    {
        $rawDisciplineIds = $request->input('rac_discipline_ids');

        if (! is_array($rawDisciplineIds) && $request->filled('rac_discipline_id')) {
            $rawDisciplineIds = [$request->input('rac_discipline_id')];
        }

        if (! is_array($rawDisciplineIds)) {
            $rawDisciplineIds = $user->racAssignments()->pluck('discipline_id')->all();
        }

        return collect($rawDisciplineIds)
            ->filter(fn ($disciplineId) => is_numeric($disciplineId) && (int) $disciplineId > 0)
            ->map(fn ($disciplineId) => (int) $disciplineId)
            ->unique()
            ->values()
            ->all();
    }
}
