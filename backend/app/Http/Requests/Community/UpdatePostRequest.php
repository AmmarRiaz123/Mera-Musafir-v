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
            'type'           => ['sometimes', 'in:story,tip,review,announcement,companion,question,alert,gear,budget,safety'],
            'destination_id' => ['sometimes', 'nullable', 'exists:destinations,id'],
            'media_url'      => ['sometimes', 'nullable', 'string', 'max:2048'],
            'media_type'     => ['sometimes', 'nullable', 'in:image,video,gif'],
            'audio'          => ['sometimes', 'nullable', 'array'],
            'gallery'        => ['sometimes', 'nullable', 'array', 'max:10'],
            'gallery.*.url'  => ['required', 'string', 'max:2048'],
            'gallery.*.type' => ['required', 'in:image,video,gif'],
            'audio.provider' => ['nullable', 'string', 'max:40'],
            'audio.id'       => ['nullable', 'string', 'max:80'],
            'audio.title'    => ['nullable', 'string', 'max:200'],
            'audio.artist'   => ['nullable', 'string', 'max:200'],
            'audio.audio_url'=> ['nullable', 'url', 'max:2048'],
            'audio.cover'    => ['nullable', 'url', 'max:2048'],
        ];
    }
}
