<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorised via the policy in the controller
    }

    public function rules(): array
    {
        return [
            'body'           => ['sometimes', 'required', 'string', 'max:2000'],
            'type'           => ['sometimes', 'in:story,tip,review,announcement'],
            'destination_id' => ['sometimes', 'nullable', 'exists:destinations,id'],
            'image'          => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
