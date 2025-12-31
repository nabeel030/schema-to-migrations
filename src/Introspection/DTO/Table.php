<?php

namespace Nabeel030\SchemaToMigrations\Introspection\DTO;

class Table
{
    public function __construct(
        public string $name,
        /** @var Column[] */
        public array $columns = [],
        public array $foreignKeys = [],
    ) {}
}
