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
            'type'           => ['nullable', 'in:story,tip,review,announcement,companion,question,alert,gear,budget,safety'],
            'destination_id' => ['nullable', 'exists:destinations,id'],
            'media_url'      => ['nullable', 'string', 'max:2048'],
            'media_type'     => ['nullable', 'in:image,video,gif'],
            'audio'          => ['nullable', 'array'],
            'gallery'        => ['nullable', 'array', 'max:10'],
            'gallery.*.url'  => ['required', 'string', 'max:2048'],
            'gallery.*.type' => ['required', 'in:image,video,gif'],
            // A poster frame captured client-side for videos (image URL).
            'gallery.*.poster' => ['nullable', 'string', 'max:2048'],
            'audio.provider' => ['nullable', 'string', 'max:40'],
            'audio.id'       => ['nullable', 'string', 'max:80'],
            'audio.title'    => ['nullable', 'string', 'max:200'],
            'audio.artist'   => ['nullable', 'string', 'max:200'],
            'audio.audio_url'=> ['nullable', 'url', 'max:2048'],
            'audio.cover'    => ['nullable', 'url', 'max:2048'],
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
