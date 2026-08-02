<?php

namespace App\Http\Requests;

use App\Enums\AiDocumentIndexingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListAiCurriculumDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (
            ['external_teacher_id', 'search']
            as $field
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
            'status' => [
                'nullable',
                Rule::enum(
                    AiDocumentIndexingStatus::class
                ),
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'between:1,50',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'external_teacher_id.required' =>
                'رقم المعلم مطلوب.',
            'status.enum' =>
                'حالة فهرسة الكتاب غير صحيحة.',
            'per_page.between' =>
                'عدد النتائج يجب أن يكون بين 1 و50.',
        ];
    }
}