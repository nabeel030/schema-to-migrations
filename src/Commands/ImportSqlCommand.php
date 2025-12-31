<?php

namespace Nabeel030\SchemaToMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nabeel030\SchemaToMigrations\Import\SqlImporter;
use Nabeel030\SchemaToMigrations\Introspection\SchemaReader;
use Nabeel030\SchemaToMigrations\Render\MigrationRenderer;
use Nabeel030\SchemaToMigrations\Support\Path;

class ImportSqlCommand extends Command
{
    protected $signature = 'migrate:import-schema
        {sql : Path to .sql file}
        {--connection=mysql : DB connection to use}
        {--database=legacy_tmp : Temp database name}
        {--output=database/migrations/imported : Output directory}
        {--fk=separate : separate|inline}
        {--drop-temp : Drop temp DB after generation}
        {--except= : Comma-separated tables to exclude (e.g. migrations,password_resets)}
        {--mysql-bin= : Full path to mysql executable (optional)}';

    protected $description = 'Import a .sql dump into a temp DB and generate Laravel migrations via schema introspection';

    public function handle(): int
    {
        $mysqlBin = $this->option('mysql-bin') ?: env('STM_MYSQL_BIN', 'mysql');

        $sqlPath = Path::absolute($this->argument('sql'));
        if (!is_file($sqlPath)) {
            $this->error("SQL file not found: {$sqlPath}");
            return self::FAILURE;
        }

        $connection = (string) $this->option('connection');
        $dbName     = (string) $this->option('database');
        $outputDir  = Path::absolute((string) $this->option('output'));
        $fkMode     = (string) $this->option('fk');
        $dropTemp   = (bool) $this->option('drop-temp');

        if (!in_array($fkMode, ['separate', 'inline'], true)) {
            $this->error("Invalid --fk option. Use separate or inline.");
            return self::FAILURE;
        }

        $this->info("1) Creating temp database: {$dbName}");
        DB::connection($connection)->statement("DROP DATABASE IF EXISTS `{$dbName}`");
        DB::connection($connection)->statement("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $this->info("2) Importing SQL dump into temp database...");
        $importer = new SqlImporter();
        $importer->importUsingMysqlCli(
            connectionName: $connection,
            databaseName: $dbName,
            sqlFilePath: $sqlPath,
            mysqlBin: $mysqlBin
        );

        $this->info("3) Introspecting schema from information_schema...");
        $reader = new SchemaReader();
        $schema = $reader->read(connectionName: $connection, databaseName: $dbName);

        $this->info("4) Generating migrations into: {$outputDir}");
        Path::ensureDirectory($outputDir);

        $except = array_filter(array_map('trim', explode(',', (string)($this->option('except') ?? ''))));
        $defaultExcept = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
        $exceptTables = array_values(array_unique(array_merge($defaultExcept, $except)));

        $schema = array_values(array_filter($schema, fn($t) => !in_array($t->name, $exceptTables, true)));


        $renderer = new MigrationRenderer();
        $renderer->render(
            schema: $schema,
            outputDir: $outputDir,
            fkMode: $fkMode
        );

        if ($dropTemp) {
            $this->info("5) Dropping temp database: {$dbName}");
            DB::connection($connection)->statement("DROP DATABASE IF EXISTS `{$dbName}`");
        } else {
            $this->warn("Temp DB kept: {$dbName} (use --drop-temp to remove it automatically)");
        }

        $this->info("Done ✅");
        return self::SUCCESS;
    }
}
