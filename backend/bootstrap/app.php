<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAgreementsAccepted;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\RejectRevokedSession;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApiLocale;
use App\Support\RequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(prepend: [SetApiLocale::class], append: [RejectRevokedSession::class]);
        $middleware->web(append: [RejectRevokedSession::class]);
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'verified.api' => EnsureEmailVerified::class,
            'agreements' => EnsureAgreementsAccepted::class,
            'permission' => RequirePermission::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ApiException::class]);

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }
            if ($request->is('api/*')) {
                // Errors raised before route middleware (e.g. unknown routes) still use the API locale.
                $requested = strtolower((string) $request->headers->get('X-Locale', ''));
                app()->setLocale(in_array($requested, config('onushondhan.supported_locales'), true) ? $requested : (string) config('onushondhan.api_locale'));
            }

            [$status, $code, $message, $errors] = match (true) {
                $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage(), $e->errors],
                $e instanceof ValidationException => [422, 'validation_failed', __('The submitted data is invalid.'), $e->errors()],
                $e instanceof AuthenticationException => [401, 'unauthenticated', __('Authentication is required.'), []],
                $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => [403, 'forbidden', __('You are not allowed to perform this action.'), []],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not_found', __('The requested resource was not found.'), []],
                $e instanceof ThrottleRequestsException => [429, 'rate_limited', __('Too many requests. Please slow down and try again later.'), []],
                $e instanceof TokenMismatchException => [419, 'csrf_mismatch', __('Your session expired. Please reload the page and try again.'), []],
                $e instanceof PostTooLargeException => [413, 'payload_too_large', __('The uploaded content is too large.'), []],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error', $e->getStatusCode() >= 500 ? __('Server error.') : (__($e->getMessage()) ?: __('Request failed.')), []],
                default => [500, 'server_error', __('An unexpected error occurred.'), []],
            };

            $body = [
                'message' => $message,
                'code' => $code,
                'errors' => (object) $errors,
                'request_id' => RequestId::get(),
            ];
            if ($status === 500 && config('app.debug')) {
                $body['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
            }

            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json($body, $status, $headers);
        });
    })->create();
