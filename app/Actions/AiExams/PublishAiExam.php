<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamPublicationStatus;
use App\Models\AiExam;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishAiExam
{
    public function execute(
        AiExam $exam,
        string $externalTeacherId
    ): AiExam {
        return DB::transaction(function () use (
            $exam,
            $externalTeacherId
        ): AiExam {
            $lockedExam = AiExam::query()
                ->whereKey($exam->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Return 404 instead of 403 so another teacher
             * cannot confirm that the exam exists.
             */
            abort_unless(
                $lockedExam->external_teacher_id === $externalTeacherId,
                404
            );

            $generationStatus =
                $lockedExam->generation_status instanceof BackedEnum
                    ? $lockedExam->generation_status->value
                    : (string) $lockedExam->generation_status;

            if ($generationStatus !== 'ready') {
                throw ValidationException::withMessages([
                    'exam' =>
                        'Only a successfully generated exam can be published.',
                ]);
            }

            /*
             * Publishing the same exam twice is safe.
             */
            if (
                $lockedExam->publication_status
                === AiExamPublicationStatus::Published
            ) {
                return $lockedExam->load('questions.options');
            }

            if (
                $lockedExam->publication_status
                === AiExamPublicationStatus::Archived
            ) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'An archived exam cannot be published.',
                ]);
            }

            $questions = $lockedExam
                ->questions()
                ->with('options')
                ->orderBy('position')
                ->get();

            if ($questions->isEmpty()) {
                throw ValidationException::withMessages([
                    'questions' =>
                        'The exam must contain at least one question.',
                ]);
            }

            $questionsPoints = $questions->sum(
                fn ($question): float => (float) $question->points
            );

            if (
                abs(
                    $questionsPoints
                    - (float) $lockedExam->total_points
                ) > 0.001
            ) {
                throw ValidationException::withMessages([
                    'total_points' =>
                        'The questions points do not match the exam total points.',
                ]);
            }

            foreach ($questions as $question) {
                if ($question->options->isEmpty()) {
                    throw ValidationException::withMessages([
                        'questions' =>
                            "Question {$question->id} has no options.",
                    ]);
                }

                $hasCorrectOption = $question->options->contains(
                    fn ($option): bool => (bool) $option->is_correct
                );

                if (! $hasCorrectOption) {
                    throw ValidationException::withMessages([
                        'questions' =>
                            "Question {$question->id} has no correct option.",
                    ]);
                }
            }

            $lockedExam->forceFill([
                'publication_status' =>
                    AiExamPublicationStatus::Published,
                'published_at' => now(),
            ])->save();

            return $lockedExam
                ->refresh()
                ->load('questions.options');
        });
    }
}


// ماذا يتحقق قبل النشر؟

// هذا الـ Action يمنع النشر في الحالات التالية:

// المعلم ليس مالك الاختبار
// التوليد ليس ready
// الاختبار مؤرشف
// لا توجد أسئلة
// مجموع علامات الأسئلة لا يساوي total_points
// يوجد سؤال بلا خيارات
// يوجد سؤال بلا إجابة صحيحة