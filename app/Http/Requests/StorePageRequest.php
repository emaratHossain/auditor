<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'url'  => ['required', 'url:http,https', 'max:2048'],
            'section_selectors'   => ['nullable', 'array', 'max:6'],
            'section_selectors.*' => ['string', 'max:120'],
        ];
    }

    /** Plain words, because these appear under the input the user just typed in. */
    public function messages(): array
    {
        return [
            'name.required' => 'Give this page a name so you can find it later.',
            'url.required'  => 'Paste the web address of the page you want audited.',
            'url.url'       => 'That does not look like a web address. It needs to start with http:// or https://.',
        ];
    }
}
