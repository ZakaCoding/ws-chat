<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
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
            'participant_id' => fn (array $attributes): string => Participant::factory()->create([
                'conversation_id' => $attributes['conversation_id'],
            ])->id,
            'body' => fake()->sentence(),
        ];
    }
}
