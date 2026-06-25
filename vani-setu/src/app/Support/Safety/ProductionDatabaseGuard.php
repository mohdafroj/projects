<?php

namespace App\Support\Safety;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

class ProductionDatabaseGuard
{
    /**
     * @var list<string>
     */
    private const BLOCKED_COMMANDS = [
        'db:seed',
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
    ];

    public function handleCommandStarting(CommandStarting $event): void
    {
        if (! $this->shouldProtect()) {
            return;
        }

        if (! in_array($event->command, self::BLOCKED_COMMANDS, true)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Blocked artisan command [%s] against protected production database [%s].',
            $event->command,
            $this->databaseName(),
        ));
    }

    public function assertSeedingAllowed(string $context = 'seeding'): void
    {
        if (! $this->shouldProtect()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Blocked %s against protected production database [%s].',
            $context,
            $this->databaseName(),
        ));
    }

    public function shouldProtect(): bool
    {
        return config('app.env') === 'production' && $this->databaseName() === 'vani_setu_prod';
    }

    public function databaseName(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }
}
