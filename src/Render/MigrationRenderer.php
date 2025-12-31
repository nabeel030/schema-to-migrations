<?php

namespace Nabeel030\SchemaToMigrations\Render;

use Illuminate\Support\Str;
use Nabeel030\SchemaToMigrations\Introspection\DTO\Table;
use Nabeel030\SchemaToMigrations\Support\Path;

class MigrationRenderer
{
    public function render(array $schema, string $outputDir, string $fkMode = 'separate'): void
    {
        $mapper = new TypeMapper();

        $ts = date('Y_m_d_His');
        $counter = 1;

        // ----------------------------
        // 1) Create table migrations
        // ----------------------------
        foreach ($schema as $table) {
            if (!$table instanceof Table) {
                continue;
            }

            $fileName  = sprintf('%s_%06d_create_%s_table.php', $ts, $counter++, $table->name);
            $path      = Path::join($outputDir, $fileName);

            $lines = [];
            foreach ($table->columns as $col) {
                [$method, $args] = $mapper->map($col);

                $argStr = $this->argsToPhp($args);
                $call = $argStr === '' ? "\$table->{$method}()" : "\$table->{$method}({$argStr})";
                $line = $call;

                // auto_increment for non-id columns
                if ($col->autoIncrement && $col->name !== 'id') {
                    $line .= "->autoIncrement()";
                }

                if ($col->nullable) {
                    $line .= "->nullable()";
                }

                // default values
                if ($col->default !== null) {
                    if (strtoupper($col->default) === 'CURRENT_TIMESTAMP') {
                        $line .= "->useCurrent()";
                    } else {
                        $line .= "->default(" . var_export($col->default, true) . ")";
                    }
                }

                $line .= ";";
                $lines[] = "            " . $line;
            }

            $bodyColumns = implode("\n", $lines);

            $content = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table->name}', function (Blueprint \$table) {
{$bodyColumns}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table->name}');
    }
};

PHP;

            file_put_contents($path, $content);
        }

        // ----------------------------
        // 2) Foreign keys migration (separate)
        // ----------------------------
        if ($fkMode === 'separate') {
            $fileName = sprintf('%s_%06d_add_foreign_keys.php', $ts, $counter++);
            $path = Path::join($outputDir, $fileName);

            $upChunks = [];
            $downChunks = [];

            foreach ($schema as $table) {
                if (!$table instanceof Table) {
                    continue;
                }

                if (empty($table->foreignKeys)) {
                    continue;
                }

                $upLines = [];
                $downLines = [];

                foreach ($table->foreignKeys as $fk) {
                    // columns argument: 'col' OR ['a','b']
                    $colsPhp = count($fk->columns) === 1
                        ? var_export($fk->columns[0], true)
                        : var_export($fk->columns, true);

                    // referenced columns: 'id' OR ['id1','id2']
                    $refColsPhp = count($fk->referencedColumns) === 1
                        ? var_export($fk->referencedColumns[0], true)
                        : var_export($fk->referencedColumns, true);

                    $constraintPhp = var_export($fk->constraintName, true);
                    $refTablePhp = var_export($fk->referencedTable, true);

                    // Build the FK statement
                    $line = "\$table->foreign({$colsPhp}, {$constraintPhp})"
                        . "->references({$refColsPhp})"
                        . "->on({$refTablePhp})";

                    if (!empty($fk->onUpdate)) {
                        $line .= "->onUpdate(" . var_export($this->normalizeFkRule($fk->onUpdate), true) . ")";
                    }
                    if (!empty($fk->onDelete)) {
                        $line .= "->onDelete(" . var_export($this->normalizeFkRule($fk->onDelete), true) . ")";
                    }

                    $line .= ";";
                    $upLines[] = "            " . $line;

                    // down: always drop by constraint name (most reliable)
                    $downLines[] = "            \$table->dropForeign({$constraintPhp});";
                }

                $upChunks[] =
                    "        Schema::table('{$table->name}', function (Blueprint \$table) {\n"
                    . implode("\n", $upLines)
                    . "\n        });";

                $downChunks[] =
                    "        Schema::table('{$table->name}', function (Blueprint \$table) {\n"
                    . implode("\n", $downLines)
                    . "\n        });";
            }

            $upBody = !empty($upChunks) ? implode("\n\n", $upChunks) : "        // No foreign keys detected.";
            $downBody = !empty($downChunks) ? implode("\n\n", $downChunks) : "        // No foreign keys detected.";

            $content = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
{$upBody}
    }

    public function down(): void
    {
{$downBody}
    }
};

PHP;

            file_put_contents($path, $content);
        }
    }

    /**
     * Convert information_schema rules to Laravel-accepted strings.
     * info_schema returns: CASCADE, RESTRICT, SET NULL, NO ACTION, SET DEFAULT
     */
    private function normalizeFkRule(string $rule): string
    {
        $r = strtolower(trim($rule));

        return match ($r) {
            'cascade' => 'cascade',
            'restrict' => 'restrict',
            'set null' => 'set null',
            'no action' => 'no action',
            'set default' => 'set default',
            default => $r,
        };
    }

    private function argsToPhp(array $args): string
    {
        if (count($args) === 0) {
            return '';
        }

        $parts = [];
        foreach ($args as $a) {
            if (is_string($a)) {
                $parts[] = var_export($a, true);
            } elseif (is_array($a)) {
                $parts[] = var_export($a, true);
            } else {
                $parts[] = (string) $a;
            }
        }

        return implode(', ', $parts);
    }
}
