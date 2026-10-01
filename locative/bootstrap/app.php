<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SubscriptionMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
    // Langue des réponses (messages, validation) : voir SetLocale.
    $middleware->api(append: [SetLocale::class]);

    $middleware->alias([
        'subscription' => SubscriptionMiddleware::class,
        'role' => RoleMiddleware::class,
    ]);

    // 🔐 Le frontend appelle l'API via son proxy serveur (/backend) : sans
    // confiance dans ce proxy, $request->ip() serait toujours l'IP du serveur
    // Next et les limites de débit (login, inscription…) deviendraient
    // globales. Ne lister QUE les IP des proxys réellement placés devant
    // l'API (sinon X-Forwarded-For devient falsifiable par le client).
    $middleware->trustProxies(at: array_filter(array_map(
        'trim',
        explode(',', env('TRUSTED_PROXIES', '127.0.0.1,::1'))
    )));

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 🔹 Format JSON unique pour toute l'API — avant chaque contrôleur
        // renvoyait un format différent (Helpers::success/error, réponses
        // brutes, etc.). Toutes les exceptions non interceptées finissent ici.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null; // laisse Laravel gérer le rendu HTML classique (routes web)
            }

            $status = 500;
            $message = __('messages.errors.generic');
            $errors = null;

            if ($e instanceof ValidationException) {
                $status = 422;
                $message = __('messages.errors.invalid_data');
                $errors = $e->errors();
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
                $message = __('messages.auth.unauthenticated');
            } elseif ($e instanceof AuthorizationException) {
                $status = 403;
                $message = __('messages.auth.forbidden');
            } elseif ($e instanceof ModelNotFoundException) {
                $status = 404;
                $message = __('messages.errors.not_found');
            } elseif ($e instanceof NotFoundHttpException) {
                $status = 404;
                $message = __('messages.errors.route_not_found');
            } elseif ($e instanceof HttpExceptionInterface) {
                // Message déduit du code (traduit) plutôt que le texte anglais
                // du framework ("This action is unauthorized.", "Too Many Attempts.").
                $status = $e->getStatusCode();
                $message = match ($status) {
                    401 => __('messages.auth.unauthenticated'),
                    403 => __('messages.auth.forbidden'),
                    404 => __('messages.errors.not_found'),
                    429 => __('messages.errors.too_many_attempts'),
                    default => $message,
                };
            }

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'errors' => $errors,
                // Détails techniques uniquement hors production (voir aussi
                // AppServiceProvider, qui force APP_DEBUG=false en production).
                'debug' => config('app.debug') && !app()->isProduction() ? [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ] : null,
            ], $status);
        });
    })->create();
