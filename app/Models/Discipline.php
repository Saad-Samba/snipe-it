<?php

namespace App\Models;

use App\Http\Traits\UniqueUndeletedTrait;
use App\Models\Traits\Searchable;
use App\Presenters\DisciplinePresenter;
use App\Presenters\Presentable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

class Discipline extends SnipeModel
{
    protected $table = 'disciplines';

    use HasFactory;
    use Presentable;
    use Searchable;
    use SoftDeletes;
    use UniqueUndeletedTrait;
    use ValidatingTrait;

    protected $presenter = DisciplinePresenter::class;

    protected $injectUniqueIdentifier = true;

    protected $casts = [
        'created_by' => 'integer',
    ];

    protected $rules = [
        'name' => 'required|string|max:255|unique_undeleted:disciplines,name',
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
        return $this->hasMany(Asset::class, 'discipline_id');
    }

    public function licenses()
    {
        return $this->hasMany(License::class, 'discipline_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
