<?php

namespace Database\Factories;

use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PushSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return ['participant_id' => Participant::factory(), 'owner_key' => fake()->uuid(), 'endpoint_hash' => hash('sha256', fake()->uuid()), 'subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/example', 'keys' => ['p256dh' => 'test', 'auth' => 'test']]];
    }
}
