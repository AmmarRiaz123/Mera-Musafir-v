<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:sanctum
    }

    public function rules(): array
    {
        return [
            'body'           => ['required', 'string', 'max:2000'],
            'type'           => ['nullable', 'in:story,tip,review,announcement'],
            'destination_id' => ['nullable', 'exists:destinations,id'],
            'image'          => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Write something before posting.',
            'body.max'      => 'Posts are limited to 2,000 characters.',
        ];
    }
}
