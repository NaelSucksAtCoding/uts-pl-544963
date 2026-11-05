<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\ForceJsonResponse;
use App\Support\KonteksLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Tanpa middleware web, supaya pemeriksaan tidak bergantung pada session di basis data.
        then: function () {
            Route::get('/health', HealthController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', ForceJsonResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pesan QueryException memuat isi query (email, nama penyewa, token), jadi
        // pencatatan bawaannya dihentikan dan diganti dengan konteks yang aman.
        $exceptions->report(function (QueryException $e) {
            Log::error('Gagal menghubungi basis data', [
                'siapa' => KonteksLog::siapa(),
                'aksi' => request()->method().' '.request()->path(),
                'sebab' => KonteksLog::sebabBasisData($e),
                'kode_driver' => KonteksLog::kodeDriver($e),
                'koneksi' => $e->getConnectionName(),
            ]);
        })->stop();

        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*') && KonteksLog::gagalTerhubung($e)) {
                return response()->json(['message' => 'Basis data sedang tidak bisa dihubungi.'], 503);
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Token tidak ada atau tidak valid.'], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Data tidak ditemukan.'], 404);
            }
        });
    })->create();
