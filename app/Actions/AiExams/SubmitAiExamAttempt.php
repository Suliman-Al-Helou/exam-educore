<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamAttemptStatus;
use App\Models\AiExamAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitAiExamAttempt
{
    /**
     * @return array{
     *     attempt: AiExamAttempt,
     *     already_submitted: bool
     * }
     */
    public function execute(
        AiExamAttempt $attempt,
        string $externalStudentId
    ): array {
        return DB::transaction(function () use (
            $attempt,
            $externalStudentId
        ): array {
            $lockedAttempt = AiExamAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->with([
                    'assignment.exam.questions.options',
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

            /*
             * Repeating the submit request must not
             * correct the attempt twice.
             */
            if (
                $lockedAttempt->status
                === AiExamAttemptStatus::Submitted
            ) {
                return [
                    'attempt' =>
                        $lockedAttempt->load([
                            'assignment.exam.questions.options',
                            'answers',
                        ]),

                    'already_submitted' => true,
                ];
            }

            if (
                ! in_array(
                    $lockedAttempt->status,
                    [
                        AiExamAttemptStatus::InProgress,
                        AiExamAttemptStatus::Expired,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'attempt' =>
                        'لا يمكن تسليم هذه المحاولة.',
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

            $exam = $assignment->exam;

            abort_unless($exam !== null, 404);

            $questions = $exam->questions
                ->keyBy('id');

            $answers = DB::table(
                'ai_exam_attempt_answers'
            )
                ->where(
                    'attempt_id',
                    $lockedAttempt->id
                )
                ->lockForUpdate()
                ->get();

            if (
                $answers->count()
                !== $questions->count()
            ) {
                throw ValidationException::withMessages([
                    'attempt' =>
                        'بيانات أسئلة المحاولة غير مكتملة.',
                ]);
            }

            $score = 0.0;
            $correctAnswersCount = 0;
            $submittedAt = now();

            foreach ($answers as $answer) {
                $question = $questions->get(
                    $answer->question_id
                );

                if ($question === null) {
                    throw ValidationException::withMessages([
                        'attempt' =>
                            'يوجد سؤال غير صالح داخل المحاولة.',
                    ]);
                }

                $isCorrect = false;
                $pointsAwarded = 0.0;

                if (
                    $answer->selected_option_id
                    !== null
                ) {
                    $selectedOption =
                        $question->options->firstWhere(
                            'id',
                            $answer->selected_option_id
                        );

                    if ($selectedOption === null) {
                        throw ValidationException::withMessages([
                            'attempt' =>
                                'يوجد خيار غير صالح داخل المحاولة.',
                        ]);
                    }

                    $isCorrect = (bool)
                        $selectedOption->is_correct;

                    if ($isCorrect) {
                        $pointsAwarded =
                            (float) $question->points;

                        $score += $pointsAwarded;
                        $correctAnswersCount++;
                    }
                }

                DB::table(
                    'ai_exam_attempt_answers'
                )
                    ->where('id', $answer->id)
                    ->update([
                        'is_correct' => $isCorrect,

                        'points_awarded' =>
                            number_format(
                                $pointsAwarded,
                                2,
                                '.',
                                ''
                            ),

                        'updated_at' =>
                            $submittedAt,
                    ]);
            }

            $lockedAttempt->forceFill([
                'status' =>
                    AiExamAttemptStatus::Submitted,

                'submitted_at' =>
                    $submittedAt,

                'score' =>
                    number_format(
                        $score,
                        2,
                        '.',
                        ''
                    ),

                'max_score' =>
                    $exam->total_points,

                'correct_answers_count' =>
                    $correctAnswersCount,
            ])->save();

            return [
                'attempt' =>
                    $lockedAttempt
                        ->refresh()
                        ->load([
                            'assignment.exam.questions.options',
                            'answers',
                        ]),

                'already_submitted' => false,
            ];
        });
    }
}