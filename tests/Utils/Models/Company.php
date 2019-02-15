<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MatrixLab\LaravelAdvancedSearch\AdvancedSearchTrait;

class Company extends Model
{
    use AdvancedSearchTrait;

    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeName($query, $value)
    {
        $query->where('name', $value);

        return $query;
    }
}
