<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', 'string', 'in:IN,OUT,ADJUSTMENT'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity_change.required' => 'Jumlah perubahan stok wajib diisi.',
            'quantity_change.not_in' => 'Perubahan stok tidak boleh 0.',
            'type.in' => 'Tipe penyesuaian stok tidak valid.',
        ];
    }
}
