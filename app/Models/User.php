<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'display_name',
        'workspace_name',
        'email',
        'password',
        'terms_accepted_at',
        'ai_provider',
        'ai_base_url',
        'ai_api_key',
        'ai_model',
        'telegram_bot_token',
        'telegram_chat_id',
        'telegram_webhook_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'ai_api_key',
        'telegram_bot_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(AssetGroup::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function hasAcceptedTerms(): bool
    {
        return $this->terms_accepted_at !== null;
    }

    public function displayName(): string
    {
        return $this->display_name ?: $this->name;
    }

    public function workspaceName(): string
    {
        return $this->workspace_name ?: $this->displayName();
    }
}
