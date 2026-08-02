<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamPublicationStatus;
use App\Models\AiExam;
use App\Models\AiExamAssignment;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;
final class CreateAiExamAssignment
{
    public function execute(
        AiExam $exam,
        string $externalTeacherId,
        array $data
    ): AiExamAssignment {
        return DB::transaction(function () use (
            $exam,
            $externalTeacherId,
            $data
        ): AiExamAssignment {
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
                        'لا يمكن إسناد الاختبار قبل اكتمال توليده.',
                ]);
            }

            if (
                $lockedExam->publication_status
                !== AiExamPublicationStatus::Published
            ) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'يجب نشر الاختبار قبل إسناده للطلاب.',
                ]);
            }

            if (
                $lockedExam->questions()->count() < 1
            ) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'لا يمكن إسناد اختبار لا يحتوي على أسئلة.',
                ]);
            }
$startsAt = CarbonImmutable::parse(
    $data['starts_at']
)->utc();

$endsAt = CarbonImmutable::parse(
    $data['ends_at']
)->utc();
            $assignment = AiExamAssignment::query()
                ->create([
                    'exam_id' => $lockedExam->id,

                    'external_teacher_id' =>
                        $externalTeacherId,

                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],

                    'attempt_limit' =>
                        $data['attempt_limit'],

                    'show_result_after_submission' =>
                        $data[
                            'show_result_after_submission'
                        ],

                    'show_correct_answers' =>
                        $data['show_correct_answers'],

                    'cancelled_at' => null,
                ]);

            $timestamp = now();

            $students = collect($data['student_ids'])
                ->unique()
                ->values()
                ->map(
                    fn (string $studentId): array => [
                        'id' => (string) Str::ulid(),

                        'assignment_id' =>
                            $assignment->id,

                        'external_student_id' =>
                            $studentId,

                        'assigned_at' => $timestamp,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]
                )
                ->all();

            DB::table(
                'ai_exam_assignment_students'
            )->insert($students);

            return $assignment
                ->refresh()
                ->load([
                    'exam',
                    'students',
                ]);
        });
    }
}
