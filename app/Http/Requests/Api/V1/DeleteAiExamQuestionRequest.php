<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteAiExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_teacher_id' => [
                'required',
                'string',
                'max:191',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'external_teacher_id.required' =>
                'معرف المعلم مطلوب.',
        ];
    }
}