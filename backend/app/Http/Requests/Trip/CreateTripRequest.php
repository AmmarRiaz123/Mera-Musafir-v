<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class CreateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'          => 'required|string|max:150',
            'cover_image'    => 'nullable|string|max:2048',
            'destination_id' => 'required|exists:destinations,id',
            'description'    => 'nullable|string|max:2000',
            'start_date'     => 'required|date|after_or_equal:today',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'max_travelers'  => 'required|integer|min:2|max:100',
            'budget_min'     => 'nullable|integer|min:0',
            'budget_max'     => 'nullable|integer|min:0|gte:budget_min',
            'type'           => 'required|in:adventure,cultural,budget,luxury,backpacking',
            'visibility'     => 'required|in:public,women_only,invite_only',
        ];
    }
}