<?php

namespace Database\Factories;

use App\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ConversationStatus::Open,
            'last_message_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => ConversationStatus::Closed,
        ]);
    }
}
