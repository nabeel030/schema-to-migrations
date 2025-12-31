<?php

namespace Nabeel030\SchemaToMigrations\Introspection\DTO;

class Column
{
    public function __construct(
        public string $name,
        public string $dataType,        // e.g. int, varchar
        public ?int $length,            // varchar length
        public ?int $precision,         // decimal precision
        public ?int $scale,             // decimal scale
        public bool $nullable,
        public bool $unsigned,
        public bool $autoIncrement,
        public ?string $default,
        public ?string $columnType,     // e.g. int(10) unsigned
    ) {}
}
