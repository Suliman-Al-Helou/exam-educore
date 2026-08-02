<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamPublicationStatus;
use App\Models\AiExam;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UpdateAiExamQuestion
{
    public function execute(
        AiExam $exam,
        string $questionId,
        string $externalTeacherId,
        array $data
    ): AiExam {
        return DB::transaction(function () use (
            $exam,
            $questionId,
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
                        'لا يمكن تعديل الأسئلة قبل اكتمال توليد الاختبار.',
                ]);
            }

            if (
                $lockedExam->publication_status
                !== AiExamPublicationStatus::Draft
            ) {
                throw ValidationException::withMessages([
                    'exam' =>
                        'لا يمكن تعديل أسئلة اختبار منشور أو مؤرشف.',
                ]);
            }

            $question = DB::table('ai_exam_questions')
                ->where('id', $questionId)
                ->where('exam_id', $lockedExam->id)
                ->lockForUpdate()
                ->first();

            abort_unless($question !== null, 404);

            DB::table('ai_exam_questions')
                ->where('id', $questionId)
                ->update([
                    'question_text' =>
                        $data['question_text'],

                    'explanation' =>
                        $data['explanation'] ?? null,

                    'source_reference' =>
                        $data['source_reference'] ?? null,

                    'points' => $data['points'],
                    'updated_at' => now(),
                ]);

            /*
             * Replace old options with the edited options.
             */
            DB::table('ai_exam_options')
                ->where('question_id', $questionId)
                ->delete();

            $timestamp = now();

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

            DB::table('ai_exam_options')->insert($options);

            /*
             * Recalculate the exam total after points change.
             */
            $totalPoints = DB::table('ai_exam_questions')
                ->where('exam_id', $lockedExam->id)
                ->sum('points');

            $questionCount = DB::table('ai_exam_questions')
                ->where('exam_id', $lockedExam->id)
                ->count();

            $lockedExam->forceFill([
                'total_points' => $totalPoints,
                'question_count' => $questionCount,
            ])->save();

            return $lockedExam
                ->refresh()
                ->load('questions.options');
        });
    }
}