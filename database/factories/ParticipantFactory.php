<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use App\ParticipantType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => null,
            'type' => ParticipantType::Guest,
            'name' => null,
            'token_hash' => hash('sha256', Str::random(43)),
        ];
    }

    public function operator(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user ?? User::factory(),
            'type' => ParticipantType::Operator,
            'name' => $user?->name ?? fake()->name(),
            'token_hash' => null,
        ]);
    }
}
