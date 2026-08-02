<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TeacherAiExamAssignmentReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attempts = $this->attempts;

        $submittedAttempts = $attempts
            ->filter(
                fn ($attempt): bool =>
                    $this->statusValue($attempt->status)
                    === 'submitted'
            );

        $inProgressAttempts = $attempts
            ->filter(
                fn ($attempt): bool =>
                    $this->statusValue($attempt->status)
                    === 'in_progress'
            );

        $expiredAttempts = $attempts
            ->filter(
                fn ($attempt): bool =>
                    $this->statusValue($attempt->status)
                    === 'expired'
            );

        $assignedStudentIds = $this->students
            ->pluck('external_student_id')
            ->unique()
            ->values();

        $startedStudentIds = $attempts
            ->pluck('external_student_id')
            ->unique()
            ->values();

        $notStartedStudentIds = $assignedStudentIds
            ->diff($startedStudentIds)
            ->values();

        $submittedScores = $submittedAttempts
            ->pluck('score')
            ->filter(
                fn ($score): bool =>
                    $score !== null
            )
            ->map(
                fn ($score): float =>
                    (float) $score
            );

        return [
            'assignment' => [
                'id' => $this->id,
                'exam_id' => $this->exam_id,

                'starts_at' =>
                    $this->starts_at
                        ?->toIso8601String(),

                'ends_at' =>
                    $this->ends_at
                        ?->toIso8601String(),

                'attempt_limit' =>
                    $this->attempt_limit,

                'show_result_after_submission' =>
                    $this
                        ->show_result_after_submission,

                'show_correct_answers' =>
                    $this->show_correct_answers,

                'cancelled_at' =>
                    $this->cancelled_at
                        ?->toIso8601String(),
            ],

            'exam' => [
                'id' => $this->exam->id,
                'title' => $this->exam->title,

                'question_count' =>
                    $this->exam->question_count,

                'total_points' =>
                    $this->exam->total_points,

                'duration_minutes' =>
                    $this->exam->duration_minutes,
            ],

            'summary' => [
                'assigned_students_count' =>
                    $assignedStudentIds->count(),

                'students_started_count' =>
                    $startedStudentIds->count(),

                'students_not_started_count' =>
                    $notStartedStudentIds->count(),

                'total_attempts_count' =>
                    $attempts->count(),

                'submitted_attempts_count' =>
                    $submittedAttempts->count(),

                'in_progress_attempts_count' =>
                    $inProgressAttempts->count(),

                'expired_attempts_count' =>
                    $expiredAttempts->count(),

                'average_score' =>
                    $submittedScores->isNotEmpty()
                        ? round(
                            $submittedScores->avg(),
                            2
                        )
                        : null,

                'highest_score' =>
                    $submittedScores->isNotEmpty()
                        ? $submittedScores->max()
                        : null,

                'lowest_score' =>
                    $submittedScores->isNotEmpty()
                        ? $submittedScores->min()
                        : null,
            ],

            'students_not_started' =>
                $notStartedStudentIds,

            'attempts' => $attempts
                ->sortByDesc('started_at')
                ->values()
                ->map(
                    function ($attempt): array {
                        $percentage = null;

                        if (
                            $attempt->score !== null
                            && (float) $attempt->max_score > 0
                        ) {
                            $percentage = round(
                                (
                                    (float) $attempt->score
                                    / (float) $attempt->max_score
                                ) * 100,
                                2
                            );
                        }

                        return [
                            'id' => $attempt->id,

                            'external_student_id' =>
                                $attempt
                                    ->external_student_id,

                            'attempt_number' =>
                                $attempt->attempt_number,

                            'status' =>
                                $this->statusValue(
                                    $attempt->status
                                ),

                            'started_at' =>
                                $attempt->started_at
                                    ?->toIso8601String(),

                            'expires_at' =>
                                $attempt->expires_at
                                    ?->toIso8601String(),

                            'submitted_at' =>
                                $attempt->submitted_at
                                    ?->toIso8601String(),

                            'score' => $attempt->score,

                            'max_score' =>
                                $attempt->max_score,

                            'percentage' =>
                                $percentage,

                            'correct_answers_count' =>
                                $attempt
                                    ->correct_answers_count,
                        ];
                    }
                ),
        ];
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof BackedEnum
            ? $status->value
            : (string) $status;
    }
}