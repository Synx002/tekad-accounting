<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFixedAssetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['nullable', 'numeric', 'gte:0'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'depreciation_method' => ['nullable', 'in:straight_line'],
            'status' => ['nullable', 'in:active,disposed,fully_depreciated'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $cost = (float) $this->input('cost', 0);
            $salvage = (float) ($this->input('salvage_value', 0) ?? 0);
            if ($salvage > $cost) {
                $validator->errors()->add('salvage_value', 'Nilai sisa tidak boleh melebihi biaya perolehan.');
            }
            if ($cost - $salvage <= 0) {
                $validator->errors()->add('cost', 'Biaya perolehan setelah nilai sisa harus dapat disusutkan.');
            }
        });
    }
}
