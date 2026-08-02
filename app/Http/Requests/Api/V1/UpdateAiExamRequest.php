<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateAiExamRequest extends FormRequest
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

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'lesson_title' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'difficulty' => [
                'sometimes',
                'required',
                Rule::in([
                    'easy',
                    'medium',
                    'hard',
                ]),
            ],

            'duration_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:600',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $editableFields = [
                    'title',
                    'lesson_title',
                    'difficulty',
                    'duration_minutes',
                ];

                $hasEditableField = collect($editableFields)
                    ->contains(
                        fn (string $field): bool =>
                            $this->exists($field)
                    );

                if (! $hasEditableField) {
                    $validator->errors()->add(
                        'exam',
                        'يجب إرسال حقل واحد على الأقل لتعديل الاختبار.'
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

            'title.required' =>
                'عنوان الاختبار مطلوب.',

            'difficulty.in' =>
                'درجة الصعوبة يجب أن تكون easy أو medium أو hard.',

            'duration_minutes.integer' =>
                'مدة الاختبار يجب أن تكون رقمًا صحيحًا.',

            'duration_minutes.min' =>
                'مدة الاختبار يجب ألا تقل عن دقيقة واحدة.',

            'duration_minutes.max' =>
                'مدة الاختبار يجب ألا تزيد عن 600 دقيقة.',
        ];
    }
}