<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GeneratePushKeysCommand extends Command
{
    protected $signature = 'chat:push-keys {--output= : Write a private environment file at this path}';

    protected $description = 'Generate the stable VAPID key pair for browser push';

    public function handle(): int
    {
        $path = $this->option('output');
        if (! $path) {
            $this->error('Pass --output to save keys privately without printing them.');

            return self::FAILURE;
        }
        if (file_exists($path)) {
            $this->error('The output file already exists. Keep existing production keys.');

            return self::FAILURE;
        }
        $keys = VAPID::createVapidKeys();
        $oldMask = umask(0077);
        try {
            $file = fopen($path, 'x');
            if ($file === false) {
                return self::FAILURE;
            }
            fwrite($file, "WEB_PUSH_ENABLED=true\nWEB_PUSH_PUBLIC_KEY=".$keys['publicKey']."\nWEB_PUSH_PRIVATE_KEY=".$keys['privateKey']."\nWEB_PUSH_SUBJECT=mailto:zakanoor@outlook.co.id\n");
            fclose($file);
        } finally {
            umask($oldMask);
        }
        $this->info('Keys saved. Import this file into the backend environment and keep it out of source control.');

        return self::SUCCESS;
    }
}
