<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MencatatValidasiGagal;
use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
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
            'nama_penyewa' => ['required', 'string', 'max:100'],
            'nomor_lapangan' => ['required', 'integer', 'between:1,10'],
            'jam_mulai' => ['required', 'date_format:Y-m-d H:i'],
            'jam_selesai' => ['required', 'date_format:Y-m-d H:i', 'after:jam_mulai'],
            'total_harga' => ['required', 'integer', 'min:0'],
            'status_pembayaran' => ['sometimes', 'string', Rule::in(Booking::STATUS_PEMBAYARAN)],
        ];
    }

    /**
     * Tolak booking yang jadwalnya bentrok dengan booking lain di lapangan yang sama.
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

                if (Booking::isBentrok($this->integer('nomor_lapangan'), $this->input('jam_mulai'), $this->input('jam_selesai'))) {
                    $this->sebabValidasi = 'jadwal_bentrok';
                    $validator->errors()->add('jam_mulai', 'Lapangan sudah dibooking pada jam tersebut.');
                }
            },
        ];
    }
}
