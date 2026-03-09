<?php

declare(strict_types=1);

namespace Relova\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Relova\Concerns\HasRelovaData;
use Relova\Services\RelovaEnrichmentService;
use Symfony\Component\HttpFoundation\Response;

/**
 * RelovaEnrichmentMiddleware
 *
 * Runs on every web request and transparently enriches Eloquent model
 * instances with data from remote sources configured in RelovaFieldMappings.
 *
 * The result is that any model that uses the HasRelovaData trait — whether
 * it was loaded via route model binding, View::share(), or added to a
 * response view — will automatically have its remote-mapped fields merged in
 * as if the data came from the local database.
 *
 * ## What it enriches
 *
 * On every request it inspects the following locations for enrichable values:
 *   - Route parameters (route model binding: single models and collections)
 *   - View shared data (data passed via View::share() before the response)
 *   - The response view data (variables passed to the rendered Blade view)
 *
 * ## How to register
 *
 * Add the middleware to your `web` group (or a specific route group) in
 * `bootstrap/app.php` (Laravel 11+) or `app/Http/Kernel.php` (Laravel 10):
 *
 * ```php
 * // bootstrap/app.php  (Laravel 11+)
 * ->withMiddleware(function (Middleware $middleware) {
 *     $middleware->web(append: [
 *         \Relova\Http\Middleware\RelovaEnrichmentMiddleware::class,
 *     ]);
 * })
 * ```
 *
 * Or use the pre-registered alias:
 *
 * ```php
 * Route::middleware(['relova.enrich'])->group(function () { ... });
 * ```
 *
 * ## Opt-out per route
 *
 * Set `RELOVA_ENRICHMENT_ENABLED=false` to disable globally, or list route
 * name prefixes in `config/relova.php` under `enrichment.excluded_routes`.
 *
 * ## Model requirements
 *
 * The Eloquent model must use the `HasRelovaData` trait:
 *
 * ```php
 * class Machine extends Model {
 *     use \Relova\Concerns\HasRelovaData;
 * }
 * ```
 *
 * The corresponding RelovaFieldMapping must be enabled and have either:
 *   - `local_join_key` + `remote_join_key` columns set, OR
 *   - at least one column_mapping entry whose `local_field` exists on the model.
 */
class RelovaEnrichmentMiddleware
{
    public function __construct(
        private readonly RelovaEnrichmentService $enrichmentService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Honour global kill-switch
        if (! config('relova.enrichment.enabled', true)) {
            return $next($request);
        }

        // Skip excluded route names / prefixes
        $routeName = $request->route()?->getName() ?? '';
        foreach (config('relova.enrichment.excluded_routes', []) as $excluded) {
            if (Str::is($excluded, $routeName)) {
                return $next($request);
            }
        }

        // Reset the per-request mapping cache so each request starts fresh
        $this->enrichmentService->resetCache();

        // 1. Enrich route-bound models BEFORE the request is handled, so
        //    controllers and views receive pre-enriched instances.
        if (config('relova.enrichment.enrich_route_parameters', true)) {
            $this->enrichRouteParameters($request);
        }

        /** @var Response $response */
        $response = $next($request);

        // 2. After the response is built, attempt to enrich any models that
        //    were shared into views during request handling.
        if (config('relova.enrichment.enrich_view_data', true)) {
            $this->enrichViewData($response);
        }

        return $response;
    }

    // ─── Route parameters ─────────────────────────────────────────────────────────

    /**
     * Iterate over all bound route parameters and enrich any Model or
     * Collection<Model> that uses HasRelovaData.
     */
    private function enrichRouteParameters(Request $request): void
    {
        $route = $request->route();
        if ($route === null) {
            return;
        }

        foreach ($route->parameters() as $key => $value) {
            $enriched = $this->enrichmentService->enrichAny($value);
            // Overwrite the route parameter with the enriched value so
            // controllers injecting it via DI receive the enriched instance.
            if ($enriched !== $value) {
                $route->setParameter($key, $enriched);
            }
        }
    }

    // ─── View data ────────────────────────────────────────────────────────────────

    /**
     * Walk through the view data of the response (if it is a view response)
     * and enrich any Model or Collection<Model> values found there.
     *
     * Also enriches anything registered via View::share().
     */
    private function enrichViewData(Response $response): void
    {
        // Illuminate view responses expose getOriginalContent() → Illuminate\View\View
        $original = method_exists($response, 'getOriginalContent')
            ? $response->getOriginalContent()
            : null;

        if ($original instanceof \Illuminate\Contracts\View\View) {
            $this->enrichViewInstance($original);
        }

        // Also enrich View::shared() data (affects all views rendered after this)
        $sharedData = View::getShared();
        foreach ($sharedData as $key => $value) {
            if (is_object($value) || $value instanceof Collection) {
                $enriched = $this->enrichmentService->enrichAny($value);
                if ($enriched !== $value) {
                    View::share($key, $enriched);
                }
            }
        }
    }

    /**
     * Walk the data bag of a concrete view instance and enrich each value.
     */
    private function enrichViewInstance(\Illuminate\Contracts\View\View $view): void
    {
        $data = $view->getData();

        foreach ($data as $key => $value) {
            $enriched = $this->enrichmentService->enrichAny($value);
            if ($enriched !== $value) {
                $view->with($key, $enriched);
            }
        }
    }
}


