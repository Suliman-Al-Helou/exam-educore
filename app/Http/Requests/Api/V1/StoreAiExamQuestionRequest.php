<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreAiExamQuestionRequest extends FormRequest
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

            'type' => [
                'required',
                Rule::in([
                    'multiple_choice',
                    'true_false',
                ]),
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
                $options = collect(
                    $this->input('options', [])
                );

                $correctOptionsCount = $options
                    ->filter(
                        fn (array $option): bool =>
                            filter_var(
                                $option['is_correct'] ?? false,
                                FILTER_VALIDATE_BOOLEAN
                            )
                    )
                    ->count();

                if ($correctOptionsCount !== 1) {
                    $validator->errors()->add(
                        'options',
                        'يجب تحديد إجابة صحيحة واحدة فقط.'
                    );
                }

                $positions = $options->pluck('position');

                if ($positions->duplicates()->isNotEmpty()) {
                    $validator->errors()->add(
                        'options',
                        'ترتيب الخيارات يجب ألا يكون مكررًا.'
                    );
                }

                if (
                    $this->input('type') === 'true_false'
                    && $options->count() !== 2
                ) {
                    $validator->errors()->add(
                        'options',
                        'سؤال الصح والخطأ يجب أن يحتوي على خيارين فقط.'
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

            'type.required' =>
                'نوع السؤال مطلوب.',

            'type.in' =>
                'نوع السؤال غير مدعوم.',

            'question_text.required' =>
                'نص السؤال مطلوب.',

            'points.required' =>
                'علامة السؤال مطلوبة.',

            'options.required' =>
                'خيارات السؤال مطلوبة.',

            'options.min' =>
                'يجب إضافة خيارين على الأقل.',

            'options.*.option_text.required' =>
                'نص كل خيار مطلوب.',

            'options.*.option_text.distinct' =>
                'لا يمكن تكرار نص الخيار.',

            'options.*.is_correct.required' =>
                'يجب تحديد صحة كل خيار.',

            'options.*.position.required' =>
                'ترتيب كل خيار مطلوب.',
        ];
    }
}