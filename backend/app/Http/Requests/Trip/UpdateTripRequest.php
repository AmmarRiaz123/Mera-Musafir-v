<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only the trip creator can update
        return $this->route('trip')->creator_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'cover_image'    => 'nullable|string|max:2048',
            'title'         => 'sometimes|string|max:150',
            'description'   => 'sometimes|nullable|string|max:2000',
            'start_date'    => 'sometimes|date|after_or_equal:today',
            'end_date'      => 'sometimes|date|after_or_equal:start_date',
            'max_travelers' => 'sometimes|integer|min:2|max:100',
            'budget_min'    => 'sometimes|nullable|integer|min:0',
            'budget_max'    => 'sometimes|nullable|integer|min:0|gte:budget_min',
            'type'          => 'sometimes|in:adventure,cultural,budget,luxury,backpacking',
            'visibility'    => 'sometimes|in:public,women_only,invite_only',
            'requires_approval' => 'sometimes|boolean',
            'status'        => 'sometimes|in:planning,active,completed,archived',
        ];
    }
}