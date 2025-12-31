<?php

namespace Nabeel030\SchemaToMigrations\Introspection;

use Illuminate\Support\Facades\DB;
use Nabeel030\SchemaToMigrations\Introspection\DTO\Table;
use Nabeel030\SchemaToMigrations\Introspection\DTO\Column;
use Nabeel030\SchemaToMigrations\Introspection\DTO\ForeignKey;

class SchemaReader
{
    /**
     * @return Table[]
     */
    public function read(string $connectionName, string $databaseName): array
    {
        $tablesRaw = DB::connection($connectionName)->select(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME",
            [$databaseName]
        );

        $tables = [];
        foreach ($tablesRaw as $t) {
            $tableName = (string)$t->TABLE_NAME;

            $colsRaw = DB::connection($connectionName)->select(
                "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH,
                        NUMERIC_PRECISION, NUMERIC_SCALE,
                        IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION",
                [$databaseName, $tableName]
            );

            $columns = [];
            foreach ($colsRaw as $c) {
                $extra = (string) ($c->EXTRA ?? '');
                $colType = (string) ($c->COLUMN_TYPE ?? '');

                $default = $c->COLUMN_DEFAULT !== null ? (string) $c->COLUMN_DEFAULT : null;
                if (is_string($default)) {
                    if (strtoupper($default) !== 'CURRENT_TIMESTAMP') {
                        $default = trim($default, "'");
                    }
                }

                $columns[] = new Column(
                    name: (string) $c->COLUMN_NAME,
                    dataType: (string) $c->DATA_TYPE,
                    length: $c->CHARACTER_MAXIMUM_LENGTH !== null ? (int) $c->CHARACTER_MAXIMUM_LENGTH : null,
                    precision: $c->NUMERIC_PRECISION !== null ? (int) $c->NUMERIC_PRECISION : null,
                    scale: $c->NUMERIC_SCALE !== null ? (int) $c->NUMERIC_SCALE : null,
                    nullable: ((string) $c->IS_NULLABLE) === 'YES',
                    unsigned: str_contains($colType, 'unsigned'),
                    autoIncrement: str_contains($extra, 'auto_increment'),
                    default: $default,
                    columnType: $colType ?: null
                );
            }

            $fkRows = DB::connection($connectionName)->select(
                "SELECT
                    kcu.CONSTRAINT_NAME,
                    kcu.TABLE_NAME,
                    kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    kcu.ORDINAL_POSITION,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
                 FROM information_schema.KEY_COLUMN_USAGE kcu
                 JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                   ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                  AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                 WHERE kcu.TABLE_SCHEMA = ?
                   AND kcu.TABLE_NAME = ?
                   AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                 ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION",
                [$databaseName, $tableName]
            );

            $fkMap = [];
            foreach ($fkRows as $r) {
                $constraint = (string) $r->CONSTRAINT_NAME;

                if (!isset($fkMap[$constraint])) {
                    $fkMap[$constraint] = [
                        'constraintName' => $constraint,
                        'tableName' => (string) $r->TABLE_NAME,
                        'columns' => [],
                        'referencedTable' => (string) $r->REFERENCED_TABLE_NAME,
                        'referencedColumns' => [],
                        'onUpdate' => $r->UPDATE_RULE ? strtolower((string) $r->UPDATE_RULE) : null,
                        'onDelete' => $r->DELETE_RULE ? strtolower((string) $r->DELETE_RULE) : null,
                    ];
                }

                $fkMap[$constraint]['columns'][] = (string) $r->COLUMN_NAME;
                $fkMap[$constraint]['referencedColumns'][] = (string) $r->REFERENCED_COLUMN_NAME;
            }

            $foreignKeys = [];
            foreach ($fkMap as $fk) {
                $foreignKeys[] = new ForeignKey(
                    constraintName: $fk['constraintName'],
                    tableName: $fk['tableName'],
                    columns: $fk['columns'],
                    referencedTable: $fk['referencedTable'],
                    referencedColumns: $fk['referencedColumns'],
                    onUpdate: $fk['onUpdate'],
                    onDelete: $fk['onDelete']
                );
            }

            $tables[] = new Table(
                name: $tableName,
                columns: $columns,
                foreignKeys: $foreignKeys
            );
        }

        return $tables;
    }
}
