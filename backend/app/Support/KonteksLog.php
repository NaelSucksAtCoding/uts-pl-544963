<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Konteks untuk log terstruktur. Isinya hanya nilai yang aman dicatat:
 * pesan asli dari basis data tidak boleh masuk log karena memuat isi query
 * (email, nama penyewa, token).
 */
class KonteksLog
{
    /** Kode error driver MySQL yang berarti basis data tidak bisa dihubungi. */
    private const GAGAL_TERHUBUNG = [
        1040 => 'Koneksi ke basis data sudah penuh',
        1044 => 'Kredensial basis data ditolak',
        1045 => 'Kredensial basis data ditolak',
        1049 => 'Nama basis data tidak ditemukan',
        2002 => 'Tidak bisa terhubung ke server basis data',
        2003 => 'Tidak bisa terhubung ke server basis data',
        2005 => 'Tidak bisa terhubung ke server basis data',
        2006 => 'Koneksi ke server basis data terputus',
        2013 => 'Koneksi ke server basis data terputus',
    ];

    /**
     * Identitas pelaku tanpa data pribadi, misalnya "user:1" atau "anonim".
     * Hanya membaca user yang sudah dimuat, supaya tidak memicu query baru saat basis data mati.
     */
    public static function siapa(): string
    {
        $guard = Auth::guard('sanctum');

        return $guard->hasUser() ? 'user:'.$guard->id() : 'anonim';
    }

    public static function sebabBasisData(Throwable $e): string
    {
        return self::GAGAL_TERHUBUNG[self::kodeDriver($e)] ?? 'Query ke basis data gagal';
    }

    public static function gagalTerhubung(Throwable $e): bool
    {
        return isset(self::GAGAL_TERHUBUNG[self::kodeDriver($e)]);
    }

    public static function kodeDriver(Throwable $e): int
    {
        return (int) ($e->errorInfo[1] ?? $e->getCode());
    }
}
