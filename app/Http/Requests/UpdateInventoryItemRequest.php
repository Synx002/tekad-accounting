<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
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
        /** @var \App\Models\InventoryItem|null $item */
        $item = $this->route('inventoryItem');

        return [
            'sku' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('inventory_items', 'sku')->ignore($item?->getKey()),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:32'],
            'reorder_level' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
