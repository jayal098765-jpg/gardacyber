<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_group_id',
        'hostname',
        'ip_address',
        'http_status',
        'title',
        'server_header',
        'cdn',
        'technologies',
        'ports',
        'screenshot_seed',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'technologies' => 'array',
        'ports' => 'array',
        'screenshot_seed' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AssetGroup::class, 'asset_group_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function openFindingsCount(): int
    {
        return $this->findings()->where('status', Finding::STATUS_OPEN)->count();
    }
}
