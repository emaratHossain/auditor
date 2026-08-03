<?php

namespace App\Http\Requests;

use App\Models\Rewrite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRewriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:255'],
            'element' => ['required', 'string', Rule::in(Rewrite::ELEMENTS)],
        ];
    }

    public function messages(): array
    {
        return [
            'element.in' => 'We can rewrite a headline, a supporting line or a button label.',
        ];
    }
}
