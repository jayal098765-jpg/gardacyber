<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    use HasFactory;

    public const KIND_VULNERABILITY = 'vulnerability';

    public const KIND_INCIDENT = 'incident';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_LOW = 'low';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_FALSE_POSITIVE = 'false_positive';

    public const SEVERITIES = [
        self::SEVERITY_CRITICAL,
        self::SEVERITY_HIGH,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_LOW,
    ];

    public const KINDS = [
        self::KIND_VULNERABILITY,
        self::KIND_INCIDENT,
    ];

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_RESOLVED,
        self::STATUS_FALSE_POSITIVE,
    ];

    protected $fillable = [
        'asset_group_id',
        'asset_id',
        'kind',
        'severity',
        'category',
        'title',
        'description',
        'evidence',
        'status',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AssetGroup::class, 'asset_group_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_FALSE_POSITIVE]);
    }

    public function scopeVulnerabilities(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_VULNERABILITY);
    }

    public function scopeIncidents(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_INCIDENT);
    }

    public function scopeOfSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }
}
