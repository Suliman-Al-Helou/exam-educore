<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamGenerationStatus;
use App\Models\AiCurriculumDocument;
use App\Models\AiExam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class CreatePendingAiExam
{
    /**
     * Create an exam record before AI generation starts.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        AiCurriculumDocument $document,
        string $externalTeacherId,
        array $data
    ): AiExam {
        $this->ensureTeacherOwnsDocument(
            $document,
            $externalTeacherId
        );

        $this->ensureDocumentIsIndexed($document);

        return $document->exams()->create([
            'external_teacher_id' => $externalTeacherId,

            'title' => $data['title'],

            // Copy trusted curriculum metadata from the document.
            'grade_level' => $document->grade_level,
            'subject_name' => $document->subject_name,
            'term' => $document->term,
            'curriculum_year' => $document->curriculum_year,

            'lesson_title' => $data['lesson_title'],
            'difficulty' => $data['difficulty'],
            'question_types' => $data['question_types'],
            'generation_prompt' => $data['generation_prompt'] ?? null,
            'question_count' => $data['question_count'],
            'total_points' => $data['total_points'],
            'duration_minutes' => $data['duration_minutes'] ?? null,

            'ai_provider' => $document->ai_provider,
            'ai_model' => null,

            'generation_status' => AiExamGenerationStatus::Pending,
            'generation_error' => null,
            'generated_at' => null,
        ]);
    }

    /**
     * Prevent teachers from using another teacher's document.
     *
     * @throws AuthorizationException
     */
    private function ensureTeacherOwnsDocument(
        AiCurriculumDocument $document,
        string $externalTeacherId
    ): void {
        if ($document->external_teacher_id !== $externalTeacherId) {
            throw new AuthorizationException(
                'لا تملك صلاحية استخدام هذا الكتاب.'
            );
        }
    }

    /**
     * Prevent exam creation before document indexing is complete.
     *
     * @throws ValidationException
     */
    private function ensureDocumentIsIndexed(
        AiCurriculumDocument $document
    ): void {
        if ($document->indexing_status->value !== 'indexed') {
            throw ValidationException::withMessages([
                'curriculum_document_id' => [
                    'يجب اكتمال فهرسة الكتاب قبل إنشاء الاختبار.',
                ],
            ]);
        }
    }
}

// وظيفة الملف

// ينفذ أربع مسؤوليات فقط:

// التأكد أن الكتاب تابع للمعلم.
// التأكد أن الكتاب مفهرس.
// نسخ بيانات الصف والمادة من الكتاب.
// إنشاء سجل داخل ai_exams بحالة pending.

// لن يتصل بـ Gemini ولن ينشئ أسئلة في هذه الخطوة.