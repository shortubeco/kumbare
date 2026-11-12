<?php

use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsureBranchManager;
use App\Http\Middleware\EnsureBranchStaff;
use App\Http\Middleware\EnsureBusinessOwner;
use App\Http\Middleware\EnsurePanelUser;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Support\TurkishText;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'customer' => EnsureCustomer::class,
            'panel' => EnsurePanelUser::class,
            'platform' => EnsurePlatformAdmin::class,
            'branch.manager' => EnsureBranchManager::class,
            'panel.staff' => EnsureBranchStaff::class,
            'business.owner' => EnsureBusinessOwner::class,
        ]);
        // API isteklerinde giriş sayfasına yönlendirme yapılmaz.
        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('api/*') => null,
            $request->is('panel', 'panel/*', 'yonetim', 'yonetim/*') => route('panel.login'),
            default => '/',
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Doğrulama hataları: Türkçe, ilk harfi büyük; özet mesaj İngilizce "(and N more errors)" içermez.
        $exceptions->render(function (ValidationException $e, Request $request) {
            $errors = collect($e->errors())->map(
                fn (array $messages) => array_map([TurkishText::class, 'ucfirst'], $messages)
            )->all();

            // Web formları: Laravel'in varsayılan davranışı (geri dön + girdileri koru), mesajlar büyük harfle
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return redirect()->to($e->redirectTo ?? url()->previous())
                    ->withInput(Arr::except($request->input(), ['password', 'password_confirmation', 'current_password']))
                    ->withErrors($errors, $e->errorBag);
            }
            $first = collect($errors)->flatten()->first() ?? 'Girilen bilgiler geçersiz.';

            return response()->json(['message' => $first, 'errors' => $errors], $e->status);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Oturum açmanız gerekiyor.', 'code' => 'unauthenticated'], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getPrevious() instanceof ModelNotFoundException ? 'Kayıt bulunamadı.' : 'Adres bulunamadı.';

                return response()->json(['message' => $message, 'code' => 'not_found'], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Bu adres bu istek türünü ('.$request->method().') desteklemiyor.',
                    'code' => 'method_not_allowed',
                ], 405, $e->getHeaders());
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Çok fazla istek gönderildi. Lütfen biraz sonra tekrar deneyin.',
                    'code' => 'too_many_requests',
                ], 429, $e->getHeaders());
            }
        });
    })->create();
