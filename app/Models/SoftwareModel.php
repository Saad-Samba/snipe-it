<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

class SoftwareModel extends SnipeModel
{
    use HasFactory, Searchable, SoftDeletes, ValidatingTrait;

    protected $table = 'software_models';

    protected $injectUniqueIdentifier = true;

    protected $rules = [
        'name' => 'required|string|min:3|max:255|unique:software_models,name,NULL,id,deleted_at,NULL',
        'category_id' => 'required|integer|exists:categories,id',
        'manufacturer_id' => 'nullable|integer|exists:manufacturers,id',
        'discipline_id' => 'nullable|integer|exists:disciplines,id,deleted_at,NULL',
        'notes' => 'nullable|string',
    ];

    protected $fillable = [
        'name',
        'category_id',
        'manufacturer_id',
        'discipline_id',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'category_id' => 'integer',
        'manufacturer_id' => 'integer',
        'discipline_id' => 'integer',
    ];

    protected $searchableAttributes = ['name', 'notes'];

    protected $searchableRelations = [
        'manufacturer' => ['name'],
        'category' => ['name'],
        'discipline' => ['name'],
    ];

    public function category()
    {
        return $this->belongsTo(Category::class)->where('category_type', 'license');
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }
}
