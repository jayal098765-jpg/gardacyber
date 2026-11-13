<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Cve extends Model
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_LOW = 'low';

    protected $fillable = [
        'cve_id',
        'description',
        'severity',
        'cvss_score',
        'exploited',
        'vendor',
        'product',
        'cpes',
        'version_ranges',
        'published_at',
        'modified_at',
    ];

    protected $casts = [
        'cvss_score' => 'float',
        'exploited' => 'boolean',
        'cpes' => 'array',
        'version_ranges' => 'array',
        'published_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    public function scopeOfProduct(Builder $query, string $vendor, string $product): Builder
    {
        return $query->where('vendor', $vendor)->where('product', $product);
    }

    public function scopeExploited(Builder $query): Builder
    {
        return $query->where('exploited', true);
    }
}
