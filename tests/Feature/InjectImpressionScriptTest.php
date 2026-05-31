<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('engageify-test/html', fn (): Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('<html><body><p>hi</p></body></html>'));
    Route::get('engageify-test/json', fn () => response()->json(['ok' => true]));
    Route::get('engageify-test/nobody', fn (): Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('<html>no closing body</html>'));
});

test('the tracking script is injected before </body> when enabled', function (): void {
    config(['engageify.impressions.inject_script' => true]);

    $content = (string) $this->get('engageify-test/html')->assertOk()->getContent();

    expect($content)
        ->toContain('<script>')
        ->toContain('data-engageify-impression')
        ->toContain('/engageify/impressions')
        ->toContain('</script></body>');
});

test('the script is not injected when disabled by default', function (): void {
    $content = (string) $this->get('engageify-test/html')->getContent();

    expect($content)->not->toContain('<script>');
});

test('non-HTML responses are left untouched', function (): void {
    config(['engageify.impressions.inject_script' => true]);

    $content = (string) $this->get('engageify-test/json')->getContent();

    expect($content)->not->toContain('<script>');
});

test('HTML without a closing body tag is left untouched', function (): void {
    config(['engageify.impressions.inject_script' => true]);

    $content = (string) $this->get('engageify-test/nobody')->getContent();

    expect($content)->not->toContain('<script>');
});
