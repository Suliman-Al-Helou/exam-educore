<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreAiExamAssignmentRequest extends FormRequest
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

            'starts_at' => [
                'required',
                'date',
            ],

            'ends_at' => [
                'required',
                'date',
                'after:starts_at',
            ],

            'attempt_limit' => [
                'required',
                'integer',
                'min:1',
                'max:10',
            ],

            'show_result_after_submission' => [
                'required',
                'boolean',
            ],

            'show_correct_answers' => [
                'required',
                'boolean',
            ],

            'student_ids' => [
                'required',
                'array',
                'min:1',
                'max:1000',
            ],

            'student_ids.*' => [
                'required',
                'string',
                'max:191',
                'distinct',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->boolean('show_correct_answers')
                    && ! $this->boolean(
                        'show_result_after_submission'
                    )
                ) {
                    $validator->errors()->add(
                        'show_correct_answers',
                        'لا يمكن إظهار الإجابات الصحيحة مع إخفاء النتيجة.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'external_teacher_id.required' =>
                'معرف المعلم مطلوب.',

            'starts_at.required' =>
                'وقت بداية الاختبار مطلوب.',

            'starts_at.date' =>
                'وقت بداية الاختبار غير صالح.',

            'ends_at.required' =>
                'وقت نهاية الاختبار مطلوب.',

            'ends_at.after' =>
                'وقت النهاية يجب أن يكون بعد وقت البداية.',

            'attempt_limit.required' =>
                'عدد المحاولات مطلوب.',

            'attempt_limit.min' =>
                'يجب السماح بمحاولة واحدة على الأقل.',

            'attempt_limit.max' =>
                'لا يمكن تجاوز عشر محاولات.',

            'student_ids.required' =>
                'يجب تحديد طالب واحد على الأقل.',

            'student_ids.min' =>
                'يجب تحديد طالب واحد على الأقل.',

            'student_ids.*.distinct' =>
                'لا يمكن تكرار الطالب نفسه في الإسناد.',
        ];
    }
}