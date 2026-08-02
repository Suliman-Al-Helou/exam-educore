<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreAiCurriculumDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (
            [
                'external_teacher_id',
                'title',
                'subject_name',
                'curriculum_year',
            ] as $field
        ) {
            if (is_string($this->input($field))) {
                $normalized[$field] = Str::squish(
                    $this->input($field)
                );
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'external_teacher_id' => [
                'required',
                'string',
                'max:100',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'grade_level' => [
                'required',
                'integer',
                'between:1,12',
            ],
            'subject_name' => [
                'required',
                'string',
                'max:120',
            ],
            'term' => [
                'required',
                'integer',
                'in:1,2',
            ],
            'curriculum_year' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
            ],
            'file' => [
                'required',
                File::types(['pdf'])->max('100mb'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $year = $this->input('curriculum_year');

                if (
                    ! is_string($year) ||
                    ! preg_match(
                        '/^(\d{4})-(\d{4})$/',
                        $year,
                        $matches
                    )
                ) {
                    return;
                }

                $startYear = (int) $matches[1];
                $endYear = (int) $matches[2];

                if ($endYear !== $startYear + 1) {
                    $validator->errors()->add(
                        'curriculum_year',
                        'يجب أن تكون سنة المنهاج مثل 2025-2026.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'external_teacher_id.required' =>
                'رقم المعلم مطلوب.',
            'external_teacher_id.max' =>
                'رقم المعلم طويل جدًا.',
            'title.required' =>
                'عنوان الكتاب مطلوب.',
            'grade_level.required' =>
                'الصف مطلوب.',
            'grade_level.between' =>
                'يجب أن يكون الصف بين 1 و12.',
            'subject_name.required' =>
                'اسم المادة مطلوب.',
            'term.required' =>
                'الفصل الدراسي مطلوب.',
            'term.in' =>
                'الفصل الدراسي يجب أن يكون 1 أو 2.',
            'curriculum_year.required' =>
                'سنة المنهاج مطلوبة.',
            'curriculum_year.regex' =>
                'استخدم صيغة السنة 2025-2026.',
            'file.required' =>
                'ملف الكتاب مطلوب.',
        ];
    }
}


//  وظيفته:

// يقبل PDF فقط.
// يمنع الملفات الأكبر من 100 MB.
// يقبل الصفوف من 1 إلى 12.
// يتحقق أن الفصل 1 أو 2.
// يمنع سنة خاطئة مثل 2025-2028. 