<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamPublicationStatus;
use App\Models\AiExamAssignment;
use BackedEnum;
use Illuminate\Validation\ValidationException;

final class GetStudentAiExam
{
    public function execute(
        AiExamAssignment $assignment,
        string $externalStudentId
    ): AiExamAssignment {
        $assignment = AiExamAssignment::query()
            ->whereKey($assignment->getKey())
            ->with([
                'exam.questions.options',
            ])
            ->firstOrFail();

        /*
         * Do not reveal the assignment to a student
         * who is not included in it.
         */
        $studentIsAssigned = $assignment
            ->students()
            ->where(
                'external_student_id',
                $externalStudentId
            )
            ->exists();

        abort_unless($studentIsAssigned, 404);

        if ($assignment->cancelled_at !== null) {
            throw ValidationException::withMessages([
                'assignment' =>
                    'تم إلغاء إسناد هذا الاختبار.',
            ]);
        }

        $exam = $assignment->exam;

        abort_unless($exam !== null, 404);

        $generationStatus =
            $exam->generation_status instanceof BackedEnum
                ? $exam->generation_status->value
                : (string) $exam->generation_status;

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

        if ($currentTime->lt($assignment->starts_at)) {
            throw ValidationException::withMessages([
                'assignment' =>
                    'لم يبدأ موعد الاختبار بعد.',
            ]);
        }

        if ($currentTime->gt($assignment->ends_at)) {
            throw ValidationException::withMessages([
                'assignment' =>
                    'انتهى موعد الاختبار.',
            ]);
        }

        return $assignment;
    }
}