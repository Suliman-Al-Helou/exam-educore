<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamAttemptStatus;
use App\Models\AiExamAttempt;
use Illuminate\Validation\ValidationException;

final class GetStudentAiExamResult
{
    public function execute(
        AiExamAttempt $attempt,
        string $externalStudentId
    ): AiExamAttempt {
        $attempt = AiExamAttempt::query()
            ->whereKey($attempt->getKey())
            ->with([
                'assignment.exam.questions.options',
                'answers',
            ])
            ->firstOrFail();

        /*
         * Do not reveal another student's attempt.
         */
        abort_unless(
            $attempt->external_student_id
                === $externalStudentId,
            404
        );

        if (
            $attempt->status
            !== AiExamAttemptStatus::Submitted
        ) {
            throw ValidationException::withMessages([
                'attempt' =>
                    'لا يمكن عرض النتيجة قبل تسليم المحاولة.',
            ]);
        }

        $assignment = $attempt->assignment;

        abort_unless($assignment !== null, 404);
        abort_unless($assignment->exam !== null, 404);

        return $attempt;
    }
}