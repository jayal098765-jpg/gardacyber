<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    public const SCOPE_ORGANIZATION = 'organization';

    public const SCOPE_GROUP = 'group';

    public const SCOPE_VULNERABILITIES = 'vulnerabilities';

    public const SCOPE_INCIDENTS = 'incidents';

    public const KIND_CSV = 'csv';

    public const KIND_HTML = 'html';

    protected $fillable = [
        'user_id',
        'asset_group_id',
        'scope',
        'kind',
        'item_count',
        'payload',
        'generated_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'item_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AssetGroup::class, 'asset_group_id');
    }
}
