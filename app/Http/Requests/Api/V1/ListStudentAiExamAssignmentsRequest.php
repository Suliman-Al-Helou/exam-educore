<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListStudentAiExamAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_student_id' => ['required', 'string', 'max:191'],
            'status' => [
                'nullable',
                Rule::in(['not_started', 'in_progress', 'submitted']),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'external_student_id.required' => 'معرف الطالب مطلوب.',
            'status.in' => 'حالة التكليف غير صالحة.',
        ];
    }
}
