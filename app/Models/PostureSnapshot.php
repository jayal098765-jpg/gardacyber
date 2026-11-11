<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostureSnapshot extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'asset_group_id',
        'assets',
        'open_vulns',
        'open_incidents',
        'vuln_score',
        'incident_score',
    ];

    protected $casts = [
        'assets' => 'integer',
        'open_vulns' => 'integer',
        'open_incidents' => 'integer',
        'vuln_score' => 'integer',
        'incident_score' => 'integer',
        'created_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AssetGroup::class, 'asset_group_id');
    }
}
