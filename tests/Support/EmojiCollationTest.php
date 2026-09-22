<?php

declare(strict_types=1);

use Cjmellor\Engageify\Support\EmojiCollation;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;

test('isRequired answers for the connection in use', function (): void {
    expect(EmojiCollation::isRequired())
        ->toBe(in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true));
});

test('pin puts a binary collation on the column', function (): void {
    $column = EmojiCollation::pin(column: new ColumnDefinition(['name' => 'type']));

    expect($column->collation)->toBe(EmojiCollation::BINARY);
});
