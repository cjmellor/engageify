<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use Illuminate\Database\Eloquent\Model;

class ImpressionToken
{
    public static function generate(Model $model, int $ttl): string
    {
        $payload = $model->getMorphClass().'|'.$model->getKey().'|'.(now()->getTimestamp() + $ttl);

        return self::encode(value: $payload).'.'.self::sign(payload: $payload);
    }

    public static function attribute(Model $model): string
    {
        $token = self::generate(model: $model, ttl: (int) config(key: 'engageify.impressions.token_ttl'));

        return 'data-engageify-impression="'.e($token).'"';
    }

    /**
     * @return array{type: string, id: string}|null
     */
    public static function verify(string $token): ?array
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, '');

        $payload = base64_decode(strtr($encoded, '-_', '+/')) ?: '';

        if (! hash_equals(self::sign(payload: $payload), $signature)) {
            return null;
        }

        [$type, $id, $expiresAt] = array_pad(explode('|', $payload, 3), 3, '');

        if ($type === '' || $id === '' || $expiresAt === '' || (int) $expiresAt < now()->getTimestamp()) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config(key: 'app.key'));
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
