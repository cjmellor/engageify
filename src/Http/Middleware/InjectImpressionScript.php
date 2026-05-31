<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

class InjectImpressionScript
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config(key: 'engageify.impressions.inject_script')) {
            return $response;
        }

        if (! str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html')) {
            return $response;
        }

        $content = (string) $response->getContent();

        if (! str_contains($content, '</body>')) {
            return $response;
        }

        $this->inject(response: $response, content: $content);

        return $response;
    }

    private function inject(Response $response, string $content): void
    {
        $script = str_replace(
            ['%_ENGAGEIFY_ENDPOINT_%', '%_ENGAGEIFY_THRESHOLD_%', '%_ENGAGEIFY_DWELL_%'],
            [
                '/'.ltrim((string) config(key: 'engageify.impressions.endpoint'), '/'),
                (string) config(key: 'engageify.impressions.threshold'),
                (string) config(key: 'engageify.impressions.dwell'),
            ],
            (string) file_get_contents(__DIR__.'/../../../resources/js/dist/engageify.iife.js'),
        );

        $original = $response instanceof IlluminateResponse ? $response->original : null;

        $response->setContent(str_replace('</body>', '<script>'.$script.'</script></body>', $content));

        if ($response instanceof IlluminateResponse) {
            $response->original = $original;
        }
    }
}
