<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAgreementsAccepted;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\RejectRevokedSession;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
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
        $middleware->trustProxies(at: explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')));
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(append: [RejectRevokedSession::class]);
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

            [$status, $code, $message, $errors] = match (true) {
                $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage(), $e->errors],
                $e instanceof ValidationException => [422, 'validation_failed', 'The submitted data is invalid.', $e->errors()],
                $e instanceof AuthenticationException => [401, 'unauthenticated', 'Authentication is required.', []],
                $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => [403, 'forbidden', 'You are not allowed to perform this action.', []],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not_found', 'The requested resource was not found.', []],
                $e instanceof ThrottleRequestsException => [429, 'rate_limited', 'Too many requests. Please slow down and try again later.', []],
                $e instanceof TokenMismatchException => [419, 'csrf_mismatch', 'Your session expired. Please reload the page and try again.', []],
                $e instanceof PostTooLargeException => [413, 'payload_too_large', 'The uploaded content is too large.', []],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error', $e->getStatusCode() >= 500 ? 'Server error.' : ($e->getMessage() ?: 'Request failed.'), []],
                default => [500, 'server_error', 'An unexpected error occurred.', []],
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
