<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamAttemptStatus;
use App\Models\AiExamAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListStudentAiExamAssignments
{
    public function execute(
        string $externalStudentId,
        ?string $status = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = AiExamAssignment::query()
            ->whereNull('cancelled_at')
            ->whereHas(
                'exam',
                fn ($query) => $query
                    ->where('generation_status', 'ready')
                    ->where('publication_status', 'published')
            )
            ->whereHas(
                'students',
                fn ($query) => $query->where(
                    'external_student_id',
                    $externalStudentId
                )
            )
            ->with([
                'exam',
                'attempts' => fn ($query) => $query
                    ->where('external_student_id', $externalStudentId)
                    ->latest('attempt_number'),
            ]);

        if ($status === 'in_progress') {
            $query->whereHas(
                'attempts',
                fn ($query) => $query
                    ->where('external_student_id', $externalStudentId)
                    ->where('status', AiExamAttemptStatus::InProgress->value)
            );
        } elseif ($status === 'submitted') {
            $query
                ->whereDoesntHave(
                    'attempts',
                    fn ($query) => $query
                        ->where('external_student_id', $externalStudentId)
                        ->where('status', AiExamAttemptStatus::InProgress->value)
                )
                ->whereHas(
                    'attempts',
                    fn ($query) => $query
                        ->where('external_student_id', $externalStudentId)
                        ->where('status', AiExamAttemptStatus::Submitted->value)
                );
        } elseif ($status === 'not_started') {
            $query->whereDoesntHave(
                'attempts',
                fn ($query) => $query
                    ->where('external_student_id', $externalStudentId)
                    ->whereIn('status', [
                        AiExamAttemptStatus::InProgress->value,
                        AiExamAttemptStatus::Submitted->value,
                    ])
            );
        }

        return $query
            ->orderByDesc('starts_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
