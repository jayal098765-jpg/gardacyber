<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scan extends Model
{
    use HasFactory;

    public const TYPE_FULL = 'full';

    public const TYPE_VULNERABILITY = 'vulnerability';

    public const TYPE_INCIDENT = 'incident';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_STOPPED = 'stopped';

    public const MODES = [
        self::TYPE_FULL => 'Full ASM',
        self::TYPE_VULNERABILITY => 'Vulnerability Scan',
        self::TYPE_INCIDENT => 'Incident Check',
    ];

    protected $fillable = [
        'asset_group_id',
        'type',
        'status',
        'options',
        'stage_count',
        'started_at',
        'finished_at',
        'stopped_at',
    ];

    protected $casts = [
        'options' => 'array',
        'stage_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AssetGroup::class, 'asset_group_id');
    }

    public function modeBadge(): string
    {
        return self::MODES[$this->type] ?? ucfirst($this->type);
    }

    public function isLive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }
}
