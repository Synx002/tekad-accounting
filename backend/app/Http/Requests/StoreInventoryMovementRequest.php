<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:in,out,adjustment'],
            'quantity' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $qty = $this->input('quantity');

            if ($type === 'adjustment') {
                if ($qty == 0) {
                    $validator->errors()->add('quantity', 'Quantity untuk penyesuaian tidak boleh nol.');
                }
            } elseif (! is_numeric($qty) || (float) $qty <= 0) {
                $validator->errors()->add('quantity', 'Quantity harus lebih dari 0 untuk tipe masuk atau keluar.');
            }
        });
    }
}
