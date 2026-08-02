<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamPublicationStatus;
use App\Models\AiExam;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteAiExamQuestion
{
    public function execute(
        AiExam $exam,
        string $questionId,
        string $externalTeacherId
    ): AiExam {
        return DB::transaction(function () use (
            $exam,
            $questionId,
            $externalTeacherId
        ): AiExam {
            $lockedExam = AiExam::query()
                ->whereKey($exam->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Do not reveal the exam to another teacher.
             */
            abort_unless(
                $lockedExam->external_teacher_id
                    === $externalTeacherId,
                404
            );

            $generationStatus =
                $lockedExam->generation_status instanceof BackedEnum
                    ? $lockedExam->generation_status->value
                    : (string) $lockedExam->generation_status;

            if ($generationStatus !== 'ready') {
                throw ValidationException::withMessages([
                    'exam' =>
                        'لا يمكن حذف الأسئلة قبل اكتمال توليد الاختبار.',
                ]);
            }

            if (
                $lockedExam->publication_status
                !== AiExamPublicationStatus::Draft
            ) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'لا يمكن حذف سؤال من اختبار منشور أو مؤرشف.',
                ]);
            }

            $question = DB::table('ai_exam_questions')
                ->where('id', $questionId)
                ->where('exam_id', $lockedExam->id)
                ->lockForUpdate()
                ->first();

            abort_unless($question !== null, 404);

            $questionsCount = DB::table('ai_exam_questions')
                ->where('exam_id', $lockedExam->id)
                ->count();

            /*
             * Keep at least one question in the exam.
             */
            if ($questionsCount <= 1) {
                throw ValidationException::withMessages([
                    'question' =>
                        'لا يمكن حذف السؤال الأخير من الاختبار.',
                ]);
            }

            DB::table('ai_exam_options')
                ->where('question_id', $questionId)
                ->delete();

            DB::table('ai_exam_questions')
                ->where('id', $questionId)
                ->where('exam_id', $lockedExam->id)
                ->delete();

            /*
             * Reorder the remaining questions.
             */
            $remainingQuestions = DB::table(
                'ai_exam_questions'
            )
                ->where('exam_id', $lockedExam->id)
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id']);

            foreach (
                $remainingQuestions as $index => $remainingQuestion
            ) {
                DB::table('ai_exam_questions')
                    ->where('id', $remainingQuestion->id)
                    ->update([
                        'position' => $index + 1,
                        'updated_at' => now(),
                    ]);
            }

            $totalPoints = DB::table('ai_exam_questions')
                ->where('exam_id', $lockedExam->id)
                ->sum('points');

            $questionCount = DB::table('ai_exam_questions')
                ->where('exam_id', $lockedExam->id)
                ->count();

            $lockedExam->forceFill([
                'total_points' => $totalPoints,
                'question_count' => $questionCount,
            ])->save();

            return $lockedExam
                ->refresh()
                ->load('questions.options');
        });
    }
}

// ما الذي ينفذه؟

// عند حذف السؤال:

// يتحقق من ملكية المعلم
// → يتأكد أن التوليد ready
// → يتأكد أن الاختبار draft
// → يمنع حذف السؤال الأخير
// → يحذف خيارات السؤال
// → يحذف السؤال
// → يعيد ترتيب الأسئلة
// → يعيد حساب question_count وtotal_points