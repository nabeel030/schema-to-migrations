<?php

namespace Nabeel030\SchemaToMigrations\Render;

use nabeel030\SchemaToMigrations\Introspection\DTO\Column;

class TypeMapper
{
    /**
     * Returns: [methodName, argsArray]
     * Example: ['string', ["name", 255]]
     */
    public function map(Column $col): array
    {
        $name = $col->name;
        $t = strtolower($col->dataType);
        
        // 1) Special-case "id" auto increment to Laravel-native helpers
        if ($name === 'id' && $col->autoIncrement) {
            // If bigint -> $table->id()
            if (in_array($t, ['bigint'], true)) {
                return ['id', []]; // $table->id();
            }

            // If int -> $table->increments('id')
            if (in_array($t, ['int', 'integer', 'mediumint'], true)) {
                return ['increments', [$name]];
            }

            // If smallint -> smallIncrements
            if ($t === 'smallint') {
                return ['smallIncrements', [$name]];
            }

            // fallback
            return ['bigIncrements', [$name]];
        }


        // tinyint(1) -> boolean heuristic
        if ($t === 'tinyint' && $col->columnType && preg_match('/tinyint\(1\)/i', $col->columnType)) {
            return ['boolean', [$name]];
        }

        if ($t === 'enum' && $col->columnType) {
            // columnType example: enum('pending','approved')
            if (preg_match("/^enum\((.*)\)$/i", $col->columnType, $m)) {
                $raw = $m[1];
        
                // extract quoted values safely (handles escaped quotes too)
                preg_match_all("/'((?:\\\\'|[^'])*)'/", $raw, $matches);
                $values = array_map(
                    fn ($v) => str_replace("\\'", "'", $v),
                    $matches[1] ?? []
                );
        
                // If parsing fails for any reason, fallback to string
                if (!empty($values)) {
                    return ['enum', [$name, $values]];
                }
            }
        }

        return match ($t) {
            'bigint'   => [$col->unsigned ? 'unsignedBigInteger' : 'bigInteger', [$name]],
            'int', 'integer' => [$col->unsigned ? 'unsignedInteger' : 'integer', [$name]],
            'smallint' => [$col->unsigned ? 'unsignedSmallInteger' : 'smallInteger', [$name]],
            'mediumint'=> [$col->unsigned ? 'unsignedMediumInteger' : 'mediumInteger', [$name]],
            'tinyint'  => [$col->unsigned ? 'unsignedTinyInteger' : 'tinyInteger', [$name]],

            'varchar', 'char' => [$t === 'char' ? 'char' : 'string', array_values(array_filter([$name, $col->length]))],

            'text'     => ['text', [$name]],
            'mediumtext'=> ['mediumText', [$name]],
            'longtext' => ['longText', [$name]],

            'datetime' => ['dateTime', [$name]],
            'timestamp'=> ['timestamp', [$name]],
            'date'     => ['date', [$name]],
            'time'     => ['time', [$name]],

            'decimal'  => ['decimal', [$name, $col->precision ?? 10, $col->scale ?? 0]],
            'double'   => ['double', [$name]],
            'float'    => ['float', [$name]],

            'json'     => ['json', [$name]],

            default    => ['string', [$name, 255]], // fallback
        };
    }
}
