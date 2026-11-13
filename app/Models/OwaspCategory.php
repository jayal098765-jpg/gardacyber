<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OwaspCategory extends Model
{
    protected $fillable = [
        'year',
        'code',
        'name',
        'description',
        'sort',
    ];

    protected static ?Collection $labelsCache = null;

    /**
     * All loaded labels keyed by code, cached for the request lifetime.
     *
     * @return Collection<string, string>
     */
    public static function labels(): Collection
    {
        return static::$labelsCache ??= static::query()
            ->orderBy('year')
            ->orderBy('sort')
            ->pluck('name', 'code');
    }
}
