<?php

namespace App\Auth;

use App\Models\Participant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use SensitiveParameter;

class ConversationGuestGuard implements Guard
{
    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        $participant = request()->attributes->get('conversation_participant');

        return $participant instanceof Participant ? $participant : null;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(#[SensitiveParameter] array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user() !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        request()->attributes->set('conversation_participant', $user);

        return $this;
    }
}
