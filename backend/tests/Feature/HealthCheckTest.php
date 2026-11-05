<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_membalas_200_saat_semua_sehat(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'healthy',
                'bermasalah' => [],
                'checks' => [
                    'database' => ['status' => 'ok'],
                    'cache' => ['status' => 'ok'],
                ],
            ]);
    }

    public function test_health_membalas_503_dan_menyebut_database_saat_database_gagal(): void
    {
        Log::spy();
        $this->rusakkanBasisData();

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unhealthy')
            ->assertJsonPath('bermasalah', ['database'])
            ->assertJsonPath('checks.database.status', 'error')
            ->assertJsonPath('checks.cache.status', 'ok');

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $pesan, array $konteks) => $konteks['aksi'] === 'health.database'
                && $konteks['siapa'] === 'sistem:healthcheck'
                && $konteks['sebab'] !== '')
            ->once();
    }

    public function test_health_membalas_503_dan_menyebut_cache_saat_cache_gagal(): void
    {
        Log::spy();
        Cache::shouldReceive('put')->andThrow(new RuntimeException('Disk penuh'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('bermasalah', ['cache'])
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'error');

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $pesan, array $konteks) => $konteks['aksi'] === 'health.cache')
            ->once();
    }

    public function test_health_melapor_cache_gagal_saat_nilai_terbaca_tidak_sama(): void
    {
        Cache::shouldReceive('put')->andReturn(true);
        Cache::shouldReceive('get')->andReturn('nilai-lain');
        Cache::shouldReceive('forget')->andReturn(true);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('bermasalah', ['cache']);
    }

    private function rusakkanBasisData(): void
    {
        config(['database.connections.sqlite.database' => '/folder/tidak/ada/database.sqlite']);
        DB::purge('sqlite');
    }
}
