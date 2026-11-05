<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MencatatValidasiGagal;
use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingRequest extends FormRequest
{
    use MencatatValidasiGagal;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama_penyewa' => ['sometimes', 'required', 'string', 'max:100'],
            'nomor_lapangan' => ['sometimes', 'required', 'integer', 'between:1,10'],
            'jam_mulai' => ['sometimes', 'required', 'date_format:Y-m-d H:i'],
            'jam_selesai' => ['sometimes', 'required', 'date_format:Y-m-d H:i'],
            'total_harga' => ['sometimes', 'required', 'integer', 'min:0'],
            'status_pembayaran' => ['sometimes', 'required', 'string', Rule::in(Booking::STATUS_PEMBAYARAN)],
        ];
    }

    /**
     * Gabungkan data lama dengan data baru, lalu cek urutan jam dan jadwal bentrok.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Booking $booking */
                $booking = $this->route('booking');

                $nomorLapangan = $this->integer('nomor_lapangan', $booking->nomor_lapangan);
                $jamMulai = $this->input('jam_mulai', $booking->jam_mulai->format('Y-m-d H:i'));
                $jamSelesai = $this->input('jam_selesai', $booking->jam_selesai->format('Y-m-d H:i'));

                if ($jamSelesai <= $jamMulai) {
                    $validator->errors()->add('jam_selesai', 'Jam selesai harus setelah jam mulai.');

                    return;
                }

                if (Booking::isBentrok($nomorLapangan, $jamMulai, $jamSelesai, $booking->id)) {
                    $this->sebabValidasi = 'jadwal_bentrok';
                    $validator->errors()->add('jam_mulai', 'Lapangan sudah dibooking pada jam tersebut.');
                }
            },
        ];
    }
}
