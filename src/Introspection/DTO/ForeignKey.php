<?php

namespace Nabeel030\SchemaToMigrations\Introspection\DTO;

class ForeignKey
{
    /**
     * @param string[] $columns
     * @param string[] $referencedColumns
     */
    public function __construct(
        public string $constraintName,
        public string $tableName,
        public array $columns,
        public string $referencedTable,
        public array $referencedColumns,
        public ?string $onUpdate,
        public ?string $onDelete,
    ) {}
}
