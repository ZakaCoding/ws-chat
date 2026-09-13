<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateOperatorCommand extends Command
{
    protected $signature = 'operator:create';

    protected $description = 'Create or update an operator account';

    public function handle(): int
    {
        $name = trim($this->ask('Name'));
        $email = strtolower(trim($this->ask('Email')));
        $password = $this->secret('Password');
        $validated = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
        ])->validate();
        $user = User::query()->firstOrNew(['email' => $validated['email']]);
        $user->fill(['name' => $validated['name'], 'password' => $validated['password'], 'is_operator' => true]);
        $user->save();
        $this->info('Operator account created or updated successfully.');

        return self::SUCCESS;
    }
}
