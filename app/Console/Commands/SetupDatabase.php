<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

#[Signature('scraper:database')]
#[Description('Crea la base configurada si no existe y ejecuta las migraciones, sin borrar datos.')]
class SetupDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = config('database.default');
        $connection = config('database.connections.'.$driver);
        try {
            if ($driver === 'mysql') {
                $database = $connection['database'];
                if (! preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
                    $this->error('El nombre de la base solo puede usar letras, números y guiones bajos.');

                    return self::FAILURE;
                }
                $pdo = new PDO(
                    'mysql:host='.$connection['host'].';port='.$connection['port'].';charset=utf8mb4',
                    $connection['username'], $connection['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                );
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $this->info('Base MySQL disponible: '.$database);
            } elseif ($driver === 'sqlite') {
                $database = $connection['database'];
                if ($database !== ':memory:' && ! is_file($database)) {
                    if (! is_dir(dirname($database))) {
                        $this->error('El directorio de la base SQLite no existe.');

                        return self::FAILURE;
                    }
                    touch($database);
                }
                $this->info('Base SQLite disponible.');
            } else {
                $this->error('Este comando admite MySQL y SQLite.');

                return self::FAILURE;
            }

            DB::purge();

            return $this->call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            $this->error('No se pudo preparar la base. Revisá DB_HOST, DB_PORT y las credenciales de .env.');
            report($exception);

            return self::FAILURE;
        }
    }
}
