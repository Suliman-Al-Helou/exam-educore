<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamAttemptStatus;
use App\Enums\AiExamPublicationStatus;
use App\Models\AiExamAssignment;
use App\Models\AiExamAttempt;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StartAiExamAttempt
{
    /**
     * @return array{
     *     attempt: AiExamAttempt,
     *     created: bool
     * }
     */
    public function execute(
        AiExamAssignment $assignment,
        string $externalStudentId
    ): array {
        return DB::transaction(function () use (
            $assignment,
            $externalStudentId
        ): array {
            $lockedAssignment =
                AiExamAssignment::query()
                    ->whereKey(
                        $assignment->getKey()
                    )
                    ->lockForUpdate()
                    ->with([
                        'exam.questions.options',
                    ])
                    ->firstOrFail();

            $studentIsAssigned =
                $lockedAssignment
                    ->students()
                    ->where(
                        'external_student_id',
                        $externalStudentId
                    )
                    ->exists();

            abort_unless(
                $studentIsAssigned,
                404
            );

            if (
                $lockedAssignment->cancelled_at
                !== null
            ) {
                throw ValidationException::withMessages([
                    'assignment' =>
                        'تم إلغاء إسناد هذا الاختبار.',
                ]);
            }

            $exam = $lockedAssignment->exam;

            abort_unless($exam !== null, 404);

            $generationStatus =
                $exam->generation_status
                    instanceof BackedEnum
                        ? $exam
                            ->generation_status
                            ->value
                        : (string)
                            $exam->generation_status;

            if ($generationStatus !== 'ready') {
                throw ValidationException::withMessages([
                    'exam' =>
                        'الاختبار غير جاهز حاليًا.',
                ]);
            }

            if (
                $exam->publication_status
                !== AiExamPublicationStatus::Published
            ) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'الاختبار غير متاح للطلاب.',
                ]);
            }

            $currentTime = now();

            if (
                $currentTime->lt(
                    $lockedAssignment->starts_at
                )
            ) {
                throw ValidationException::withMessages([
                    'assignment' =>
                        'لم يبدأ موعد الاختبار بعد.',
                ]);
            }

            if (
                $currentTime->gte(
                    $lockedAssignment->ends_at
                )
            ) {
                throw ValidationException::withMessages([
                    'assignment' =>
                        'انتهى موعد الاختبار.',
                ]);
            }

            /*
             * Return the current active attempt instead
             * of creating a duplicate attempt.
             */
            $activeAttempt =
                AiExamAttempt::query()
                    ->where(
                        'assignment_id',
                        $lockedAssignment->id
                    )
                    ->where(
                        'external_student_id',
                        $externalStudentId
                    )
                    ->where(
                        'status',
                        AiExamAttemptStatus
                            ::InProgress
                            ->value
                    )
                    ->lockForUpdate()
                    ->first();

            if ($activeAttempt !== null) {
                if (
                    $currentTime->lt(
                        $activeAttempt->expires_at
                    )
                ) {
                    return [
                        'attempt' =>
                            $activeAttempt->load([
                                'assignment.exam.questions.options',
                                'answers',
                            ]),

                        'created' => false,
                    ];
                }

                $activeAttempt->forceFill([
                    'status' =>
                        AiExamAttemptStatus::Expired,
                ])->save();
            }

            $attemptsCount =
                AiExamAttempt::query()
                    ->where(
                        'assignment_id',
                        $lockedAssignment->id
                    )
                    ->where(
                        'external_student_id',
                        $externalStudentId
                    )
                    ->count();

            if (
                $attemptsCount
                >= $lockedAssignment->attempt_limit
            ) {
                throw ValidationException::withMessages([
                    'attempt' =>
                        'لقد استنفدت عدد المحاولات المسموح بها.',
                ]);
            }

            $lastAttemptNumber =
                (int) AiExamAttempt::query()
                    ->where(
                        'assignment_id',
                        $lockedAssignment->id
                    )
                    ->where(
                        'external_student_id',
                        $externalStudentId
                    )
                    ->max('attempt_number');

            $durationExpiresAt =
                CarbonImmutable::instance(
                    $currentTime
                )->addMinutes(
                    (int) $exam->duration_minutes
                );

            $assignmentEndsAt =
                CarbonImmutable::instance(
                    $lockedAssignment->ends_at
                );

            $expiresAt =
                $durationExpiresAt->lessThan(
                    $assignmentEndsAt
                )
                    ? $durationExpiresAt
                    : $assignmentEndsAt;

            $attempt = AiExamAttempt::query()
                ->create([
                    'assignment_id' =>
                        $lockedAssignment->id,

                    'external_student_id' =>
                        $externalStudentId,

                    'attempt_number' =>
                        $lastAttemptNumber + 1,

                    'status' =>
                        AiExamAttemptStatus::InProgress,

                    'started_at' =>
                        $currentTime,

                    'expires_at' =>
                        $expiresAt,

                    'submitted_at' => null,
                    'score' => null,

                    'max_score' =>
                        $exam->total_points,

                    'correct_answers_count' =>
                        null,
                ]);

            $timestamp = now();

            $answerRows = $exam->questions
                ->map(
                    fn ($question): array => [
                        'id' => (string) Str::ulid(),

                        'attempt_id' =>
                            $attempt->id,

                        'question_id' =>
                            $question->id,

                        'selected_option_id' =>
                            null,

                        'is_correct' =>
                            null,

                        'points_awarded' =>
                            0,

                        'answered_at' =>
                            null,

                        'created_at' =>
                            $timestamp,

                        'updated_at' =>
                            $timestamp,
                    ]
                )
                ->all();

            DB::table(
                'ai_exam_attempt_answers'
            )->insert($answerRows);

            return [
                'attempt' =>
                    $attempt->refresh()->load([
                        'assignment.exam.questions.options',
                        'answers',
                    ]),

                'created' => true,
            ];
        });
    }
}