<?php

namespace App\Actions\AiExams;

use App\Models\AiExamAssignment;

final class GetTeacherAiExamAssignmentReport
{
    public function execute(
        AiExamAssignment $assignment,
        string $externalTeacherId
    ): AiExamAssignment {
        $assignment = AiExamAssignment::query()
            ->whereKey($assignment->getKey())
            ->firstOrFail();

        /*
         * Hide the assignment from another teacher.
         */
        abort_unless(
            $assignment->external_teacher_id
                === $externalTeacherId,
            404
        );

        return $assignment->load([
            'exam',
            'students',

            'attempts' => fn ($query) =>
                $query
                    ->orderByDesc('started_at')
                    ->orderByDesc('attempt_number'),
        ]);
    }
}