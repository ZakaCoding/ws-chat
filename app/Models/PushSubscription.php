<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable(['participant_id', 'personal_access_token_id', 'owner_key', 'endpoint_hash', 'subscription', 'last_success_at', 'last_error_code'])]
#[Hidden(['subscription', 'endpoint_hash'])]
class PushSubscription extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['subscription' => 'encrypted:array', 'last_success_at' => 'datetime'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }

    public function isActive(): bool
    {
        if ($this->participant_id !== null) {
            return $this->participant !== null;
        }
        $token = $this->personalAccessToken;

        return $token !== null && ($token->expires_at === null || $token->expires_at->isFuture())
         && $token->can('chat:read') && $token->tokenable instanceof User && $token->tokenable->is_operator;
    }
}
