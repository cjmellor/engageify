<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use Illuminate\Support\Facades\File;

class ImpressionScript
{
    private ?string $raw = null;

    public function render(): string
    {
        return str_replace(
            ['%_ENGAGEIFY_ENDPOINT_%', '%_ENGAGEIFY_THRESHOLD_%', '%_ENGAGEIFY_DWELL_%'],
            [
                '/'.ltrim((string) config(key: 'engageify.impressions.endpoint'), '/'),
                (string) config(key: 'engageify.impressions.threshold'),
                (string) config(key: 'engageify.impressions.dwell'),
            ],
            $this->raw(),
        );
    }

    private function path(): string
    {
        return __DIR__.'/../../resources/js/dist/engageify.iife.js';
    }

    private function raw(): string
    {
        return $this->raw ??= File::get(path: $this->path());
    }
}
