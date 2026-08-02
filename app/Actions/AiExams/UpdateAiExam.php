<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamPublicationStatus;
use App\Models\AiExam;
use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAiExam
{
    public function execute(
        AiExam $exam,
        string $externalTeacherId,
        array $data
    ): AiExam {
        return DB::transaction(function () use (
            $exam,
            $externalTeacherId,
            $data
        ): AiExam {
            $lockedExam = AiExam::query()
                ->whereKey($exam->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Hide exam existence from another teacher.
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
                        'لا يمكن تعديل الاختبار قبل اكتمال التوليد.',
                ]);
            }

            if (
                $lockedExam->publication_status
                !== AiExamPublicationStatus::Draft
            ) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'لا يمكن تعديل اختبار منشور أو مؤرشف.',
                ]);
            }

            $editableData = Arr::only(
                $data,
                [
                    'title',
                    'lesson_title',
                    'difficulty',
                    'duration_minutes',
                ]
            );

            if ($editableData === []) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'لم يتم إرسال بيانات قابلة للتعديل.',
                ]);
            }

            $lockedExam->forceFill(
                $editableData
            )->save();

            return $lockedExam
                ->refresh()
                ->load('questions.options');
        });
    }
}

// ما الذي يتحقق منه؟
// المعلم مالك الاختبار
// → generation_status = ready
// → publication_status = draft
// → توجد بيانات قابلة للتعديل
// → تحديث بيانات الاختبار
// → إعادة الاختبار مع الأسئلة