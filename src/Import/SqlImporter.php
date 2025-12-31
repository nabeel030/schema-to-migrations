<?php

namespace Nabeel030\SchemaToMigrations\Import;

use Illuminate\Support\Facades\Config;
use RuntimeException;
use Symfony\Component\Process\Process;

class SqlImporter
{
    public function importUsingMysqlCli(string $connectionName, string $databaseName, string $sqlFilePath, string $mysqlBin = 'mysql'): void
    {
        $cfg = Config::get("database.connections.{$connectionName}");
        if (!$cfg) {
            throw new RuntimeException("DB connection not found: {$connectionName}");
        }

        $host   = $cfg['host'] ?? '127.0.0.1';
        $port   = (string)($cfg['port'] ?? '3306');
        $user   = $cfg['username'] ?? null;
        $pass   = (string)($cfg['password'] ?? '');
        $socket = $cfg['unix_socket'] ?? null;

        if (!$user) {
            throw new RuntimeException("Connection {$connectionName} is missing username.");
        }

        if (!is_file($sqlFilePath)) {
            throw new RuntimeException("SQL file not found: {$sqlFilePath}");
        }

        // Normalize Windows path for MySQL "source" command:
        // MySQL client accepts forward slashes best.
        $normalizedPath = str_replace('\\', '/', realpath($sqlFilePath) ?: $sqlFilePath);

        // $cmd = ['mysql'];
        $cmd = [$mysqlBin];

        if ($socket) {
            $cmd[] = "--socket={$socket}";
        } else {
            $cmd[] = "-h{$host}";
            $cmd[] = "-P{$port}";
        }

        $cmd[] = "-u{$user}";

        // Avoid putting password in the command line if possible.
        // We'll use MYSQL_PWD env var (works for mysql client).
        $env = null;
        if ($pass !== '') {
            $env = array_merge($_ENV, ['MYSQL_PWD' => $pass]);
        }

        $cmd[] = $databaseName;

        // Let mysql read the file itself (no PHP piping).
        // This avoids broken pipes on large dumps.
        $cmd[] = "--default-character-set=utf8mb4";
        $cmd[] = "--execute=source {$normalizedPath}";

        $process = new Process($cmd, null, $env);
        $process->setTimeout(1800); // 30 mins for large dumps
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                "mysql import failed.\n" .
                "Command: " . $process->getCommandLine() . "\n" .
                "Error: " . $process->getErrorOutput()
            );
        }
    }
}
