<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

class EmojiCollation
{
    public const string BINARY = 'utf8mb4_bin';

    public static function isRequired(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public static function pin(ColumnDefinition $column): ColumnDefinition
    {
        return $column->collation(self::BINARY);
    }
}
