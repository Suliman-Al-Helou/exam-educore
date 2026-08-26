<?php

namespace App\Http\Resources;

use App\Enums\AiExamAttemptStatus;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StudentAiExamAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attempts = $this->attempts;

        $activeAttempt = $attempts->first(
            fn ($attempt): bool => $this->attemptStatus($attempt)
                === AiExamAttemptStatus::InProgress->value
        );
        $submittedAttempt = $attempts->first(
            fn ($attempt): bool => $this->attemptStatus($attempt)
                === AiExamAttemptStatus::Submitted->value
        );

        $studentStatus = $activeAttempt !== null
            ? 'in_progress'
            : ($submittedAttempt !== null ? 'submitted' : 'not_started');

        $attemptsUsed = $attempts->count();
        $now = now();
        $availabilityStatus = $now->lt($this->starts_at)
            ? 'upcoming'
            : ($now->gte($this->ends_at) ? 'ended' : 'available');

        return [
            'assignment_id' => $this->id,
            'exam_id' => $this->exam_id,
            'student_status' => $studentStatus,
            'availability_status' => $availabilityStatus,
            'active_attempt_id' => $activeAttempt?->id,
            'submitted_attempt_id' => $submittedAttempt?->id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'attempt_limit' => $this->attempt_limit,
            'attempts_used' => $attemptsUsed,
            'attempts_remaining' => max(0, $this->attempt_limit - $attemptsUsed),
            'show_result_after_submission' => $this->show_result_after_submission,
            'exam' => [
                'id' => $this->exam->id,
                'title' => $this->exam->title,
                'subject_name' => $this->exam->subject_name,
                'grade_level' => $this->exam->grade_level,
                'question_count' => $this->exam->question_count,
                'total_points' => $this->exam->total_points,
                'duration_minutes' => $this->exam->duration_minutes,
            ],
        ];
    }

    private function attemptStatus(mixed $attempt): string
    {
        return $attempt->status instanceof BackedEnum
            ? $attempt->status->value
            : (string) $attempt->status;
    }
}
