<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateAiExamQuestionRequest extends FormRequest
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

            'question_text' => [
                'required',
                'string',
                'max:5000',
            ],

            'explanation' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'source_reference' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'points' => [
                'required',
                'numeric',
                'min:0.25',
                'max:1000',
            ],

            'options' => [
                'required',
                'array',
                'min:2',
                'max:6',
            ],

            'options.*.option_text' => [
                'required',
                'string',
                'max:1000',
                'distinct',
            ],

            'options.*.is_correct' => [
                'required',
                'boolean',
            ],

            'options.*.position' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $options = $this->input('options', []);

                $correctOptions = collect($options)
                    ->filter(
                        fn (array $option): bool =>
                            filter_var(
                                $option['is_correct'] ?? false,
                                FILTER_VALIDATE_BOOLEAN
                            )
                    )
                    ->count();

                if ($correctOptions !== 1) {
                    $validator->errors()->add(
                        'options',
                        'يجب تحديد إجابة صحيحة واحدة فقط.'
                    );
                }

                $positions = collect($options)
                    ->pluck('position');

                if ($positions->duplicates()->isNotEmpty()) {
                    $validator->errors()->add(
                        'options',
                        'يجب أن يكون ترتيب الخيارات غير مكرر.'
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

            'question_text.required' =>
                'نص السؤال مطلوب.',

            'points.required' =>
                'علامة السؤال مطلوبة.',

            'options.required' =>
                'خيارات السؤال مطلوبة.',

            'options.min' =>
                'يجب أن يحتوي السؤال على خيارين على الأقل.',

            'options.*.option_text.required' =>
                'نص كل خيار مطلوب.',

            'options.*.option_text.distinct' =>
                'لا يمكن تكرار نص الخيار.',

            'options.*.is_correct.required' =>
                'يجب تحديد حالة كل خيار.',

            'options.*.position.required' =>
                'ترتيب كل خيار مطلوب.',
        ];
    }
}