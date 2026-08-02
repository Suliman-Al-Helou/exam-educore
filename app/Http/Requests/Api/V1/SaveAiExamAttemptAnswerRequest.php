<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class SaveAiExamAttemptAnswerRequest extends FormRequest
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

            /*
             * Nullable allows the student to clear
             * a previously selected answer.
             */
            'selected_option_id' => [
                'present',
                'nullable',
                'ulid',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'external_student_id.required' =>
                'معرف الطالب مطلوب.',

            'selected_option_id.present' =>
                'يجب إرسال معرف الخيار، ويمكن أن تكون قيمته null.',

            'selected_option_id.ulid' =>
                'معرف الخيار غير صالح.',
        ];
    }
}