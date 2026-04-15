<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFixedAssetRequest extends FormRequest
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
        /** @var \App\Models\FixedAsset|null $asset */
        $asset = $this->route('fixedAsset');
        $hasDepreciation = $asset && $asset->depreciationEntries()->exists();

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,disposed,fully_depreciated'],
        ];

        if (! $hasDepreciation) {
            $rules['acquisition_date'] = ['sometimes', 'required', 'date'];
            $rules['cost'] = ['sometimes', 'required', 'numeric', 'gt:0'];
            $rules['salvage_value'] = ['nullable', 'numeric', 'gte:0'];
            $rules['useful_life_months'] = ['sometimes', 'required', 'integer', 'min:1'];
            $rules['depreciation_method'] = ['nullable', 'in:straight_line'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var \App\Models\FixedAsset|null $asset */
            $asset = $this->route('fixedAsset');
            if (! $asset || $asset->depreciationEntries()->exists()) {
                return;
            }

            $cost = (float) ($this->input('cost', $asset->cost));
            $salvage = (float) ($this->input('salvage_value', $asset->salvage_value) ?? 0);
            if ($salvage > $cost) {
                $validator->errors()->add('salvage_value', 'Nilai sisa tidak boleh melebihi biaya perolehan.');
            }
            if ($cost - $salvage <= 0) {
                $validator->errors()->add('cost', 'Biaya perolehan setelah nilai sisa harus dapat disusutkan.');
            }
        });
    }
}
