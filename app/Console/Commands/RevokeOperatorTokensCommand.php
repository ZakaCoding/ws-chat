<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RevokeOperatorTokensCommand extends Command
{
    protected $signature = 'operator:revoke-tokens {email}';

    protected $description = 'Revoke all device tokens for an operator';

    public function handle(): int
    {
        $user = User::query()->where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user instanceof User || ! $user->is_operator) {
            $this->error('Operator account not found.');

            return self::FAILURE;
        }
        if (! $this->confirm('Revoke all active tokens for this operator?')) {
            $this->comment('No tokens were revoked.');

            return self::SUCCESS;
        }
        $count = $user->tokens()->delete();
        $this->info("Revoked {$count} operator device token(s).");

        return self::SUCCESS;
    }
}
