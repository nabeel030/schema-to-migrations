<?php

namespace Nabeel030\SchemaToMigrations;

use Illuminate\Support\ServiceProvider;
use Nabeel030\SchemaToMigrations\Commands\ImportSqlCommand;

class SchemaToMigrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportSqlCommand::class,
            ]);
        }
    }
}
