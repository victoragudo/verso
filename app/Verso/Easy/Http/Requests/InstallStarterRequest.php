<?php

namespace App\Verso\Easy\Http\Requests;

use App\Verso\Easy\Starters\StarterSiteRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Checks the starter site picked on the guided start.
class InstallStarterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starter' => ['required', 'string', Rule::in(app(StarterSiteRegistry::class)->keys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starter.required' => __('Pick the kind of site you want to build.'),
            'starter.in' => __('Pick one of the sites on this page.'),
        ];
    }
}
