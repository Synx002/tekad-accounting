<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:64'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'subtotal' => ['required', 'numeric', 'gte:0'],
            'tax_percent' => ['nullable', 'numeric', 'between:0,100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,posted,void'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
