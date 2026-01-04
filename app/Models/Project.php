<?php

namespace App\Models;

use App\Http\Traits\UniqueUndeletedTrait;
use App\Models\Traits\Searchable;
use App\Presenters\Presentable;
use App\Presenters\ProjectPresenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Watson\Validating\ValidatingTrait;

class Project extends SnipeModel
{
    protected $table = 'projects';

    use HasFactory;
    use Presentable;
    use Searchable;
    use UniqueUndeletedTrait;
    use ValidatingTrait;

    protected $presenter = ProjectPresenter::class;

    protected $injectUniqueIdentifier = true;

    protected $casts = [
        'created_by' => 'integer',
    ];

    protected $rules = [
        'name' => 'required|string|max:255|unique_undeleted:projects,name',
        'notes' => 'string|nullable',
        'created_by' => 'numeric|nullable|exists:users,id',
    ];

    protected $fillable = [
        'name',
        'notes',
        'created_by',
    ];

    protected $searchableAttributes = ['name', 'notes'];

    protected $searchableRelations = [];

    public function assets()
    {
        return $this->hasMany(Asset::class, 'project_id');
    }

    public function licenses()
    {
        return $this->hasMany(License::class, 'project_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
