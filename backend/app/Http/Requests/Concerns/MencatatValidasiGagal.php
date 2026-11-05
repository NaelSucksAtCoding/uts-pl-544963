<?php

namespace App\Http\Requests\Concerns;

use App\Support\KonteksLog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

trait MencatatValidasiGagal
{
    /** Sebab khusus yang diisi oleh pengecekan di after(), misalnya jadwal bentrok. */
    protected ?string $sebabValidasi = null;

    /**
     * Catat validasi yang gagal. Yang dicatat hanya nama field, bukan nilai yang dikirim.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::warning('Validasi booking gagal', [
            'siapa' => KonteksLog::siapa(),
            'aksi' => $this->isMethod('POST') ? 'booking.tambah' : 'booking.ubah',
            'sebab' => $this->sebabValidasi ?? 'input_tidak_valid',
            'field' => $validator->errors()->keys(),
        ]);

        parent::failedValidation($validator);
    }
}
