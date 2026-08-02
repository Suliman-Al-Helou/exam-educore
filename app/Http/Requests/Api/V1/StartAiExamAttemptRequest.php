<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StartAiExamAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_student_id' => [
                'required',
                'string',
                'max:191',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'external_student_id.required' =>
                'معرف الطالب مطلوب.',
        ];
    }
}