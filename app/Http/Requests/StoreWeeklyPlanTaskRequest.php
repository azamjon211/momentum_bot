<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeeklyPlanTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'weekday' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\TaskType::class)],
            'target_value' => ['nullable', 'integer', 'min:1'],
            'target_unit' => ['nullable', 'string', 'max:50'],
            'remind_at' => ['nullable', 'date_format:H:i'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
