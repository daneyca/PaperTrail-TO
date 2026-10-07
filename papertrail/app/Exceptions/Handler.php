<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (HttpException $e, $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            $message = 'The form expired. Please try again.';

            if ($request->hasSession()) {
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()
                    ->json([
                        'message' => $message,
                        'redirect' => route('login'),
                    ], 419)
                    ->withHeaders($this->noCacheHeaders());
            }

            return redirect()
                ->route('login')
                ->with('warning', $message)
                ->withHeaders($this->noCacheHeaders());
        });
    }

    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()
                ->json(['message' => $exception->getMessage()], 401)
                ->withHeaders($this->noCacheHeaders());
        }

        return redirect()
            ->guest($exception->redirectTo($request) ?? route('login'))
            ->with('warning', 'Your session has expired due to inactivity. Please log in again.')
            ->withHeaders($this->noCacheHeaders());
    }

    private function noCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
