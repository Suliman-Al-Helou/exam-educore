<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamPublicationStatus;
use App\Models\AiExam;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AddAiExamQuestion
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
                        'لا يمكن إضافة سؤال قبل اكتمال توليد الاختبار.',
                ]);
            }

            if (
                $lockedExam->publication_status
                !== AiExamPublicationStatus::Draft
            ) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'لا يمكن إضافة سؤال إلى اختبار منشور أو مؤرشف.',
                ]);
            }

            $questionId = (string) Str::ulid();

            $lastPosition = (int) DB::table(
                'ai_exam_questions'
            )
                ->where('exam_id', $lockedExam->id)
                ->max('position');

            $timestamp = now();

            DB::table('ai_exam_questions')->insert([
                'id' => $questionId,
                'exam_id' => $lockedExam->id,

                'type' => $data['type'],

                'question_text' =>
                    $data['question_text'],

                'explanation' =>
                    $data['explanation'] ?? null,

                'source_reference' =>
                    $data['source_reference'] ?? null,

                'position' => $lastPosition + 1,
                'points' => $data['points'],

                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $options = collect($data['options'])
                ->sortBy('position')
                ->values()
                ->map(
                    fn (array $option): array => [
                        'id' => (string) Str::ulid(),
                        'question_id' => $questionId,

                        'option_text' =>
                            $option['option_text'],

                        'is_correct' =>
                            (bool) $option['is_correct'],

                        'position' =>
                            (int) $option['position'],

                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]
                )
                ->all();

            DB::table('ai_exam_options')->insert(
                $options
            );

            $questionCount = DB::table(
                'ai_exam_questions'
            )
                ->where('exam_id', $lockedExam->id)
                ->count();

            $totalPoints = DB::table(
                'ai_exam_questions'
            )
                ->where('exam_id', $lockedExam->id)
                ->sum('points');

            $lockedExam->forceFill([
                'question_count' => $questionCount,
                'total_points' => $totalPoints,
            ])->save();

            return $lockedExam
                ->refresh()
                ->load('questions.options');
        });
    }
}

// هذا الـ Action ينفذ
// التحقق من ملكية المعلم
// → التأكد أن التوليد ready
// → التأكد أن الاختبار draft
// → إضافة السؤال في آخر ترتيب
// → إضافة خيارات السؤال
// → تحديث question_count
// → تحديث total_points