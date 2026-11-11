<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetGroup extends Model
{
    use HasFactory;

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_LOW = 'low';

    protected $fillable = [
        'user_id',
        'root_domain',
        'slug',
        'single_host',
        'last_scanned_at',
    ];

    protected $casts = [
        'single_host' => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function latestScan(): HasMany
    {
        return $this->hasMany(Scan::class)->latest('started_at')->limit(1);
    }

    public function openVulnerabilities(): HasMany
    {
        return $this->findings()->where('kind', Finding::KIND_VULNERABILITY)->where('status', Finding::STATUS_OPEN);
    }

    public function openIncidents(): HasMany
    {
        return $this->findings()->where('kind', Finding::KIND_INCIDENT)->where('status', Finding::STATUS_OPEN);
    }
}
