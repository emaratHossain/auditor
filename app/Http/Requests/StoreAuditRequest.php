<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Three fields are required. The other four are optional on purpose: a blank
 * one switches off the rules that need it, and must never become a guess.
 */
class StoreAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $percent = ['numeric', 'min:0', 'max:100'];

        return [
            'visitors'        => ['required', 'integer', 'min:1'],
            'bounce_rate'     => array_merge(['required'], $percent),
            'conversion_rate' => array_merge(['required'], $percent),

            'cta_click_rate'     => array_merge(['nullable'], $percent),
            'mobile_share'       => array_merge(['nullable'], $percent),
            'mobile_bounce_rate' => array_merge(['nullable'], $percent),

            'section_reach'   => ['nullable', 'array'],
            'section_reach.*' => $percent,
        ];
    }

    public function messages(): array
    {
        return [
            'visitors.required'        => 'How many visitors did this page get? Any recent period will do.',
            'bounce_rate.required'     => 'What share of visitors arrive and leave without doing anything?',
            'conversion_rate.required' => 'What share of visitors do the thing you wanted?',
            '*.max'                    => 'A percentage cannot be higher than 100.',
            '*.min'                    => 'A percentage cannot be negative.',
        ];
    }
}
