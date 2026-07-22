<?php

namespace App\Http\Requests\Community;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A comment can be text, media, or both — but not empty.
            'body'       => ['nullable', 'required_without:media_url', 'string', 'max:1000'],
            'media_url'  => ['nullable', 'required_without:body', 'string', 'max:2048'],
            'media_type' => ['nullable', 'in:image,gif'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without'      => 'Write something or attach an image.',
            'media_url.required_without' => 'Write something or attach an image.',
        ];
    }
}
