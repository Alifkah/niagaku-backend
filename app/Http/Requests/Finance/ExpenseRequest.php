<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:OPERATIONAL,SALARY,RENT,UTILITIES,RAW_MATERIAL,MARKETING,OTHER'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:CASH,BANK_TRANSFER,E_WALLET,OTHER'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Deskripsi pengeluaran wajib diisi.',
            'category.required' => 'Kategori pengeluaran wajib dipilih.',
            'amount.required' => 'Jumlah pengeluaran wajib diisi.',
            'amount.gt' => 'Jumlah pengeluaran harus lebih dari 0.',
        ];
    }
}
