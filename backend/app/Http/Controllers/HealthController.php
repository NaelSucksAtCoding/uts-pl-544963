<?php

namespace App\Http\Controllers;

use App\Support\KonteksLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HealthController extends Controller
{
    /**
     * Periksa basis data dan cache. Balas 200 kalau semuanya sehat, 503 kalau ada yang bermasalah.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->cekBasisData(),
            'cache' => $this->cekCache(),
        ];

        $bermasalah = array_keys(array_filter($checks, fn (array $check) => $check['status'] !== 'ok'));

        return response()->json([
            'status' => $bermasalah === [] ? 'healthy' : 'unhealthy',
            'bermasalah' => $bermasalah,
            'checks' => $checks,
        ], $bermasalah === [] ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    /**
     * Jalankan query sungguhan ke basis data.
     *
     * @return array<string, string>
     */
    private function cekBasisData(): array
    {
        try {
            DB::select('select 1');

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            $sebab = KonteksLog::sebabBasisData($e);

            Log::error('Health check: basis data bermasalah', [
                'siapa' => 'sistem:healthcheck',
                'aksi' => 'health.database',
                'sebab' => $sebab,
                'kode_driver' => KonteksLog::kodeDriver($e),
                'koneksi' => config('database.default'),
            ]);

            return ['status' => 'error', 'pesan' => $sebab];
        }
    }

    /**
     * Tulis, baca, lalu hapus satu nilai di cache.
     *
     * @return array<string, string>
     */
    private function cekCache(): array
    {
        try {
            $kunci = 'health:'.Str::random(16);
            $nilai = Str::random(16);

            Cache::put($kunci, $nilai, 10);
            $terbaca = Cache::get($kunci);
            Cache::forget($kunci);

            if ($terbaca !== $nilai) {
                throw new RuntimeException('Nilai cache yang terbaca tidak sama dengan yang ditulis.');
            }

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            $sebab = 'Cache tidak bisa ditulis atau dibaca';

            Log::error('Health check: cache bermasalah', [
                'siapa' => 'sistem:healthcheck',
                'aksi' => 'health.cache',
                'sebab' => $sebab,
                'jenis_error' => class_basename($e),
                'store' => config('cache.default'),
            ]);

            return ['status' => 'error', 'pesan' => $sebab];
        }
    }
}
