<?php

declare(strict_types=1);

use Cjmellor\Engageify\Support\Fingerprint;

test('Fingerprint uses the user id when authenticated', function (): void {
    expect(Fingerprint::make(42, '1.2.3.4', 'Mozilla/5.0'))->toBe('user:42');
});

test('Fingerprint hashes an anonymous viewer\'s IP and user agent, never storing them raw', function (): void {
    $fingerprint = Fingerprint::make(null, '1.2.3.4', 'Mozilla/5.0');

    expect($fingerprint)->toStartWith('anon:')
        ->and($fingerprint)->not->toContain('1.2.3.4')
        ->and($fingerprint)->toBe('anon:'.hash('sha256', '1.2.3.4|Mozilla/5.0'));
});

test('Fingerprint tolerates a missing IP and user agent', function (): void {
    expect(Fingerprint::make(null, null, null))->toBe('anon:'.hash('sha256', '|'));
});
