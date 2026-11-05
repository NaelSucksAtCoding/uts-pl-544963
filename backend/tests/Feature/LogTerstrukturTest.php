<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Monolog\Formatter\JsonFormatter;
use PDOException;
use Tests\TestCase;

class LogTerstrukturTest extends TestCase
{
    use RefreshDatabase;

    private const NAMA_PENYEWA = 'Penyewa Rahasia';

    private const PASSWORD = 'password-rahasia';

    /**
     * @return array<string, mixed>
     */
    private function dataBooking(): array
    {
        return [
            'nama_penyewa' => self::NAMA_PENYEWA,
            'nomor_lapangan' => 1,
            'jam_mulai' => '2026-11-10 10:00',
            'jam_selesai' => '2026-11-10 11:00',
            'total_harga' => 150000,
            'status_pembayaran' => 'dp',
        ];
    }

    public function test_menyimpan_booking_mencatat_log_info_berkonteks(): void
    {
        Log::spy();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', $this->dataBooking())->assertCreated();

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $pesan, array $konteks) => $konteks['siapa'] === 'user:'.$user->id
                && $konteks['aksi'] === 'booking.tambah'
                && $konteks['sebab'] === 'permintaan_admin'
                && ! str_contains(json_encode($konteks), self::NAMA_PENYEWA))
            ->once();
    }

    public function test_validasi_gagal_mencatat_nama_field_tanpa_nilainya(): void
    {
        Log::spy();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [...$this->dataBooking(), 'nomor_lapangan' => 99])
            ->assertStatus(422);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $pesan, array $konteks) => $konteks['aksi'] === 'booking.tambah'
                && $konteks['sebab'] === 'input_tidak_valid'
                && $konteks['field'] === ['nomor_lapangan']
                && ! str_contains(json_encode($konteks), self::NAMA_PENYEWA))
            ->once();
    }

    public function test_jadwal_bentrok_dicatat_dengan_sebab_khusus(): void
    {
        Log::spy();
        Sanctum::actingAs(User::factory()->create());
        Booking::factory()->create([
            'nomor_lapangan' => 1,
            'jam_mulai' => '2026-11-10 10:00',
            'jam_selesai' => '2026-11-10 11:00',
        ]);

        $this->postJson('/api/bookings', $this->dataBooking())->assertStatus(422);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $pesan, array $konteks) => $konteks['sebab'] === 'jadwal_bentrok')
            ->once();
    }

    public function test_login_gagal_tidak_mencatat_email_dan_password(): void
    {
        Log::spy();
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertStatus(422);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $pesan, array $konteks) use ($user) {
                $isi = json_encode($konteks);

                return $konteks['sebab'] === 'password_salah'
                    && ! str_contains($isi, $user->email)
                    && ! str_contains($isi, self::PASSWORD);
            })
            ->once();
    }

    public function test_basis_data_mati_dibalas_503_dan_log_tidak_memuat_isi_query(): void
    {
        $log = $this->rekamLog();
        Sanctum::actingAs(User::factory()->create());

        Booking::creating(function () {
            throw new QueryException(
                'mysql',
                'insert into bookings (nama_penyewa) values (?)',
                [self::NAMA_PENYEWA],
                new PDOException('SQLSTATE[HY000] [2002] Connection refused', 2002),
            );
        });

        $this->postJson('/api/bookings', $this->dataBooking())
            ->assertStatus(503)
            ->assertJsonMissingPath('exception');

        $this->assertCount(1, $log->semua);
        $this->assertSame('error', $log->semua[0]->level);
        $this->assertSame('Tidak bisa terhubung ke server basis data', $log->semua[0]->context['sebab']);
        $this->assertSame(2002, $log->semua[0]->context['kode_driver']);
        $this->assertSame('POST api/bookings', $log->semua[0]->context['aksi']);
        $this->assertStringNotContainsString(self::NAMA_PENYEWA, json_encode($log->semua));
    }

    public function test_channel_stdout_dipakai_untuk_menulis_json_ke_stdout(): void
    {
        $channel = config('logging.channels.stdout');

        $this->assertSame('php://stdout', $channel['handler_with']['stream']);
        $this->assertSame(JsonFormatter::class, $channel['formatter']);
    }

    /**
     * Rekam log sungguhan, termasuk yang dicatat oleh pencatatan bawaan exception handler.
     */
    private function rekamLog(): object
    {
        $rekaman = new class
        {
            public array $semua = [];
        };

        Event::listen(MessageLogged::class, function ($pesan) use ($rekaman) {
            $rekaman->semua[] = $pesan;
        });

        return $rekaman;
    }
}
