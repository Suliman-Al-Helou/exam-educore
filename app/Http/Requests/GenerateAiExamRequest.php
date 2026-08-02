<?php

namespace App\Http\Requests;

use App\Enums\AiExamDifficulty;
use App\Enums\AiExamQuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GenerateAiExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is protected by the ai.service middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (
            [
                'external_teacher_id',
                'title',
                'lesson_title',
            ] as $field
        ) {
            if (is_string($this->input($field))) {
                $normalized[$field] = Str::squish(
                    $this->input($field)
                );
            }
        }

        if (is_string($this->input('generation_prompt'))) {
            $normalized['generation_prompt'] = trim(
                $this->input('generation_prompt')
            );
        }

        $difficulty = $this->input('difficulty');

        if (
            ! is_string($difficulty) ||
            trim($difficulty) === ''
        ) {
            $difficulty = AiExamDifficulty::Mixed->value;
        } else {
            $difficulty = trim($difficulty);
        }

        $questionTypes = $this->exists('question_types')
            ? $this->input('question_types')
            : [AiExamQuestionType::MultipleChoice->value];

        if (is_array($questionTypes)) {
            $questionTypes = array_map(
                static fn (mixed $type): mixed => is_string($type)
                    ? trim($type)
                    : $type,
                $questionTypes
            );
        }

        $questionCount = $this->input('question_count');

        if (
            ! $this->exists('question_count') ||
            $questionCount === null ||
            $questionCount === ''
        ) {
            $questionCount = 10;
        }

        $totalPoints = $this->input('total_points');

        if (
            ! $this->exists('total_points') ||
            $totalPoints === null ||
            $totalPoints === ''
        ) {
            $totalPoints = $questionCount;
        }

        $this->merge(array_merge(
            $normalized,
            [
                'difficulty' => $difficulty,
                'question_types' => $questionTypes,
                'question_count' => $questionCount,
                'total_points' => $totalPoints,
            ]
        ));
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

            'lesson_title' => [
                'required',
                'string',
                'max:255',
            ],

            'difficulty' => [
                'required',
                Rule::enum(AiExamDifficulty::class),
            ],

            'question_types' => [
                'required',
                'array',
                'min:1',
                'max:2',
            ],

            'question_types.*' => [
                'required',
                'distinct',
                Rule::enum(AiExamQuestionType::class),
            ],

            'question_count' => [
                'required',
                'integer',
                'min:1',
                'max:50',
            ],

            'total_points' => [
                'required',
                'numeric',
                'min:1',
                'max:1000',
            ],

            'duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
                'max:300',
            ],

            'generation_prompt' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'external_teacher_id.required' =>
                'معرّف المعلم مطلوب.',
            'external_teacher_id.max' =>
                'معرّف المعلم يجب ألا يتجاوز 100 حرف.',

            'title.required' =>
                'عنوان الاختبار مطلوب.',
            'title.max' =>
                'عنوان الاختبار يجب ألا يتجاوز 255 حرفًا.',

            'lesson_title.required' =>
                'عنوان الدرس مطلوب.',
            'lesson_title.max' =>
                'عنوان الدرس يجب ألا يتجاوز 255 حرفًا.',

            'difficulty.enum' =>
                'مستوى الصعوبة المحدد غير مدعوم.',

            'question_types.required' =>
                'يجب تحديد نوع واحد من الأسئلة على الأقل.',
            'question_types.array' =>
                'أنواع الأسئلة يجب أن تكون مصفوفة.',
            'question_types.min' =>
                'يجب تحديد نوع واحد من الأسئلة على الأقل.',
            'question_types.max' =>
                'يمكن تحديد نوعين من الأسئلة كحد أقصى.',
            'question_types.*.distinct' =>
                'لا يمكن تكرار نوع السؤال.',
            'question_types.*.enum' =>
                'أحد أنواع الأسئلة غير مدعوم.',

            'question_count.integer' =>
                'عدد الأسئلة يجب أن يكون رقمًا صحيحًا.',
            'question_count.min' =>
                'يجب إنشاء سؤال واحد على الأقل.',
            'question_count.max' =>
                'الحد الأقصى هو 50 سؤالًا.',

            'total_points.numeric' =>
                'العلامة الكلية يجب أن تكون رقمًا.',
            'total_points.min' =>
                'العلامة الكلية يجب أن تكون أكبر من صفر.',
            'total_points.max' =>
                'العلامة الكلية يجب ألا تتجاوز 1000.',

            'duration_minutes.integer' =>
                'مدة الاختبار يجب أن تكون رقمًا صحيحًا.',
            'duration_minutes.min' =>
                'مدة الاختبار يجب ألا تقل عن دقيقة واحدة.',
            'duration_minutes.max' =>
                'مدة الاختبار يجب ألا تتجاوز 300 دقيقة.',

            'generation_prompt.max' =>
                'تعليمات التوليد يجب ألا تتجاوز 5000 حرف.',
        ];
    }
}
// وظيفة الملف

// يتحقق من بيانات المعلم قبل إنشاء سجل الاختبار أو إرسال أي طلب إلى Gemini.

