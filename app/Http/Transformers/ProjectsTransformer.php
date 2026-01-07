<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ProjectsTransformer
{
    public function transformProjects(Collection $projects, $total)
    {
        $array = [];
        foreach ($projects as $project) {
            $array[] = self::transformProject($project);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformProject(Project $project)
    {
        $array = [
            'id' => (int) $project->id,
            'name' => e($project->name),
            'assets_count' => (int) $project->assets_count,
            'licenses_count' => (int) $project->licenses_count,
            'notes' => Helper::parseEscapedMarkedownInline($project->notes),
            'created_at' => Helper::getFormattedDateObject($project->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($project->updated_at, 'datetime'),
            'created_by' => $project->creator ? [
                'id' => (int) $project->creator->id,
                'name' => e($project->creator->display_name),
            ] : null,
        ];

        $permissions_array['available_actions'] = [
            'update' => Gate::allows('update', Project::class),
            'delete' => Gate::allows('delete', $project),
        ];

        $array += $permissions_array;

        return $array;
    }
}
