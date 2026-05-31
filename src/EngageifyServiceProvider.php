<?php

declare(strict_types=1);

namespace Cjmellor\Engageify;

use Cjmellor\Engageify\Commands\RecountCommand;
use Cjmellor\Engageify\Http\Controllers\ImpressionController;
use Cjmellor\Engageify\Http\Middleware\InjectImpressionScript;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EngageifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/engageify.php',
            key: 'engageify',
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(commands: [
                RecountCommand::class,
            ]);
        }

        $this->registerImpressionRoute();

        $this->app->make(Kernel::class)->pushMiddleware(InjectImpressionScript::class);

        Blade::directive('impression', fn (string $expression): string => "<?php echo \Cjmellor\Engageify\Support\ImpressionToken::attribute({$expression}); ?>");

        $this->publishes([
            __DIR__.'/../config/engageify.php' => config_path(path: 'engageify.php'),
        ], groups: 'engageify-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path(path: 'migrations'),
        ], groups: 'engageify-migrations');
    }

    protected function registerImpressionRoute(): void
    {
        Route::middleware('throttle:'.config(key: 'engageify.impressions.throttle'))
            ->post((string) config(key: 'engageify.impressions.endpoint'), ImpressionController::class)
            ->name('engageify.impressions');
    }
}
