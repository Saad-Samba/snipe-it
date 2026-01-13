<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use App\Presenters\Presentable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

class Discipline extends SnipeModel
{
    use HasFactory;

    protected $presenter = \App\Presenters\DisciplinePresenter::class;
    use Presentable;
    use SoftDeletes;

    protected $table = 'disciplines';

    protected $rules = [
        'name' => 'required|min:2|max:255|unique:disciplines,name,NULL,id,deleted_at,NULL',
    ];

    protected $injectUniqueIdentifier = true;
    use ValidatingTrait;

    protected $fillable = [
        'name',
    ];

    use Searchable;

    protected $searchableAttributes = ['name', 'created_at'];

    protected $searchableRelations = [];

    public function assets()
    {
        return $this->hasMany(Asset::class, 'discipline_id');
    }

    public function licenses()
    {
        return $this->hasMany(License::class, 'discipline_id');
    }

    public function adminuser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDeletable()
    {
        return ($this->assets_count ?? $this->assets()->count()) === 0
            && ($this->licenses_count ?? $this->licenses()->count()) === 0
            && ($this->deleted_at == '');
    }

    public function scopeOrderByCreatedBy($query, $order)
    {
        return $query->leftJoin('users as admin_sort', 'disciplines.created_by', '=', 'admin_sort.id')
            ->select('disciplines.*')
            ->orderBy('admin_sort.first_name', $order)
            ->orderBy('admin_sort.last_name', $order);
    }
}
