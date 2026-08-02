<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamAttemptStatus;
use App\Models\AiExamAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveAiExamAttemptAnswer
{
    public function execute(
        AiExamAttempt $attempt,
        string $questionId,
        string $externalStudentId,
        array $data
    ): AiExamAttempt {
        $result = DB::transaction(function () use (
            $attempt,
            $questionId,
            $externalStudentId,
            $data
        ): array {
            $lockedAttempt = AiExamAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->with([
                    'assignment.exam',
                ])
                ->firstOrFail();

            /*
             * Do not reveal another student's attempt.
             */
            abort_unless(
                $lockedAttempt->external_student_id
                    === $externalStudentId,
                404
            );

            if (
                $lockedAttempt->status
                !== AiExamAttemptStatus::InProgress
            ) {
                throw ValidationException::withMessages([
                    'attempt' =>
                        'لا يمكن تعديل إجابات محاولة منتهية أو مسلمة.',
                ]);
            }

            $assignment = $lockedAttempt->assignment;

            abort_unless($assignment !== null, 404);

            if ($assignment->cancelled_at !== null) {
                throw ValidationException::withMessages([
                    'assignment' =>
                        'تم إلغاء إسناد هذا الاختبار.',
                ]);
            }

            $currentTime = now();

            /*
             * Save the expired status first, then return
             * normally so the transaction is committed.
             */
            if (
                $currentTime->gte(
                    $lockedAttempt->expires_at
                )
            ) {
                $lockedAttempt->forceFill([
                    'status' =>
                        AiExamAttemptStatus::Expired,
                ])->save();

                return [
                    'expired' => true,
                    'attempt' => $lockedAttempt,
                ];
            }

            $exam = $assignment->exam;

            abort_unless($exam !== null, 404);

            /*
             * The question must belong to the exam
             * used by this attempt.
             */
            $questionExists = DB::table(
                'ai_exam_questions'
            )
                ->where('id', $questionId)
                ->where('exam_id', $exam->id)
                ->exists();

            abort_unless($questionExists, 404);

            $answer = DB::table(
                'ai_exam_attempt_answers'
            )
                ->where(
                    'attempt_id',
                    $lockedAttempt->id
                )
                ->where(
                    'question_id',
                    $questionId
                )
                ->lockForUpdate()
                ->first();

            abort_unless($answer !== null, 404);

            $selectedOptionId =
                $data['selected_option_id'];

            /*
             * When an option is selected, it must belong
             * to the same question.
             */
            if ($selectedOptionId !== null) {
                $optionExists = DB::table(
                    'ai_exam_options'
                )
                    ->where('id', $selectedOptionId)
                    ->where(
                        'question_id',
                        $questionId
                    )
                    ->exists();

                if (! $optionExists) {
                    throw ValidationException::withMessages([
                        'selected_option_id' =>
                            'الخيار المحدد لا ينتمي إلى هذا السؤال.',
                    ]);
                }
            }

            DB::table('ai_exam_attempt_answers')
                ->where('id', $answer->id)
                ->update([
                    'selected_option_id' =>
                        $selectedOptionId,

                    /*
                     * Correction happens only when the
                     * student submits the attempt.
                     */
                    'is_correct' => null,
                    'points_awarded' => 0,

                    'answered_at' =>
                        $selectedOptionId !== null
                            ? $currentTime
                            : null,

                    'updated_at' => $currentTime,
                ]);

            return [
                'expired' => false,

                'attempt' =>
                    $lockedAttempt
                        ->refresh()
                        ->load([
                            'assignment.exam.questions.options',
                            'answers',
                        ]),
            ];
        });

        if ($result['expired']) {
            throw ValidationException::withMessages([
                'attempt' =>
                    'انتهى الوقت المسموح لهذه المحاولة.',
            ]);
        }

        return $result['attempt'];
    }
}