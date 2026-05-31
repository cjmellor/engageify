<?php

declare(strict_types=1);

use Cjmellor\Engageify\EngageifyServiceProvider;
use Cjmellor\Engageify\Http\Middleware\InjectImpressionScript;
use Cjmellor\Engageify\Support\ImpressionScript;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('engageify-test/html', fn (): Response => response('<html><body><p>hi</p></body></html>'));
    Route::get('engageify-test/json', fn (): JsonResponse => response()->json(['ok' => true]));
    Route::get('engageify-test/nobody', fn (): Response => response('<html>no closing body</html>'));
});

test('the injection middleware is off the global stack by default and registered only when enabled', function (): void {
    expect(resolve(Kernel::class)->hasMiddleware(InjectImpressionScript::class))->toBeFalse();

    config(['engageify.impressions.inject_script' => true]);
    (new EngageifyServiceProvider($this->app))->registerInjectionMiddleware();

    expect(resolve(Kernel::class)->hasMiddleware(InjectImpressionScript::class))->toBeTrue();
});

test('once enabled, the tracking script is injected before </body> on an HTML response', function (): void {
    config(['engageify.impressions.inject_script' => true]);
    (new EngageifyServiceProvider($this->app))->registerInjectionMiddleware();

    $content = (string) $this->get('engageify-test/html')->assertOk()->getContent();

    expect($content)
        ->toContain('<script>')
        ->toContain('data-engageify-impression')
        ->toContain('/engageify/impressions')
        ->toContain('</script></body>');
});

test('a non-HTML response is left untouched', function (): void {
    config(['engageify.impressions.inject_script' => true]);
    (new EngageifyServiceProvider($this->app))->registerInjectionMiddleware();

    $content = (string) $this->get('engageify-test/json')->getContent();

    expect($content)->not->toContain('<script>');
});

test('HTML without a closing body tag is left untouched', function (): void {
    config(['engageify.impressions.inject_script' => true]);
    (new EngageifyServiceProvider($this->app))->registerInjectionMiddleware();

    $content = (string) $this->get('engageify-test/nobody')->getContent();

    expect($content)->not->toContain('<script>');
});

test('the injected script reflects the current endpoint config', function (): void {
    config([
        'engageify.impressions.inject_script' => true,
        'engageify.impressions.endpoint' => 'custom/impressions',
    ]);
    (new EngageifyServiceProvider($this->app))->registerInjectionMiddleware();

    $content = (string) $this->get('engageify-test/html')->assertOk()->getContent();

    expect($content)->toContain('/custom/impressions');
});

test('the built script is read from disk only once across renders', function (): void {
    File::partialMock()->shouldReceive('get')->once()->andReturn('console.log("engageify");');

    $script = resolve(ImpressionScript::class);

    $script->render();
    $script->render();
});
