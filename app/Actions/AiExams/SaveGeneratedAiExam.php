<?php

namespace App\Actions\AiExams;

use App\Enums\AiExamGenerationStatus;
use App\Models\AiExam;
use App\Models\AiExamOption;
use App\Models\AiExamQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class SaveGeneratedAiExam
{
    /**
     * Save generated questions and mark the exam as ready.
     *
     * @param array<string, mixed> $generatedData
     */
    public function execute(
        AiExam $exam,
        array $generatedData
    ): AiExam {
        return DB::transaction(
            function () use (
                $exam,
                $generatedData
            ): AiExam {
                $lockedExam = AiExam::query()
                    ->lockForUpdate()
                    ->findOrFail($exam->getKey());

                // A completed exam should not be saved twice.
                if (
                    $lockedExam->generation_status
                    === AiExamGenerationStatus::Ready
                ) {
                    return $lockedExam->load(
                        'questions.options'
                    );
                }

                if (
                    $lockedExam->generation_status
                    !== AiExamGenerationStatus::Generating
                ) {
                    throw new RuntimeException(
                        'The AI exam must be generating before its questions can be saved.'
                    );
                }

                $questions =
                    $generatedData['questions']
                    ?? null;

                if (! is_array($questions)) {
                    throw new RuntimeException(
                        'Generated exam questions are missing.'
                    );
                }

                if (
                    count($questions)
                    !== (int) $lockedExam->question_count
                ) {
                    throw new RuntimeException(
                        'The generated question count does not match the requested question count.'
                    );
                }

                $this->deleteExistingQuestions(
                    $lockedExam
                );

                $points = $this->distributePoints(
                    totalPoints:
                        (float) $lockedExam->total_points,

                    questionCount:
                        count($questions)
                );

                foreach (
                    $questions
                    as $questionIndex => $questionData
                ) {
                    if (! is_array($questionData)) {
                        throw new RuntimeException(
                            sprintf(
                                'Generated question %d is invalid.',
                                $questionIndex + 1
                            )
                        );
                    }

                    $options =
                        $questionData['options']
                        ?? null;

                    if (
                        ! is_array($options)
                        || $options === []
                    ) {
                        throw new RuntimeException(
                            sprintf(
                                'Generated question %d has no options.',
                                $questionIndex + 1
                            )
                        );
                    }

                    $question = AiExamQuestion::query()
                        ->create([
                            'id' =>
                                (string) Str::ulid(),

                            'exam_id' =>
                                $lockedExam->id,

                            'type' =>
                                $questionData['type'],

                            'question_text' =>
                                trim(
                                    (string) $questionData[
                                        'question_text'
                                    ]
                                ),

                            'explanation' =>
                                trim(
                                    (string) $questionData[
                                        'explanation'
                                    ]
                                ),

                            'source_reference' =>
                                trim(
                                    (string) $questionData[
                                        'source_reference'
                                    ]
                                ),

                            'position' =>
                                $questionIndex + 1,

                            'points' =>
                                $points[$questionIndex],
                        ]);

                    foreach (
                        $options
                        as $optionIndex => $optionData
                    ) {
                        if (! is_array($optionData)) {
                            throw new RuntimeException(
                                sprintf(
                                    'Option %d in question %d is invalid.',
                                    $optionIndex + 1,
                                    $questionIndex + 1
                                )
                            );
                        }

                        AiExamOption::query()->create([
                            'id' =>
                                (string) Str::ulid(),

                            'question_id' =>
                                $question->id,

                            'option_text' =>
                                trim(
                                    (string) $optionData[
                                        'option_text'
                                    ]
                                ),

                            'is_correct' =>
                                (bool) $optionData[
                                    'is_correct'
                                ],

                            'position' =>
                                $optionIndex + 1,
                        ]);
                    }
                }

                $lockedExam->update([
                    'ai_model' => trim(
                        (string) config(
                            'ai.providers.gemini.model'
                        )
                    ),

                    'generation_status' =>
                        AiExamGenerationStatus::Ready,

                    'generation_error' => null,

                    'generated_at' => now(),
                ]);

                return $lockedExam
                    ->refresh()
                    ->load('questions.options');
            },

            attempts: 3
        );
    }

    /**
     * Remove previous generated records before a retry.
     */
    private function deleteExistingQuestions(
        AiExam $exam
    ): void {
        $questionIds = AiExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->pluck('id');

        if ($questionIds->isNotEmpty()) {
            AiExamOption::query()
                ->whereIn(
                    'question_id',
                    $questionIds
                )
                ->delete();
        }

        AiExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->delete();
    }

    /**
     * Distribute the total points exactly between questions.
     *
     * @return array<int, string>
     */
    private function distributePoints(
        float $totalPoints,
        int $questionCount
    ): array {
        if ($questionCount < 1) {
            throw new RuntimeException(
                'Question count must be greater than zero.'
            );
        }

        $totalCents = (int) round(
            $totalPoints * 100
        );

        if ($totalCents < 1) {
            throw new RuntimeException(
                'Exam total points must be greater than zero.'
            );
        }

        $basePoints = intdiv(
            $totalCents,
            $questionCount
        );

        $remainingCents =
            $totalCents % $questionCount;

        $points = [];

        for (
            $index = 0;
            $index < $questionCount;
            $index++
        ) {
            $questionCents =
                $basePoints
                + ($index < $remainingCents ? 1 : 0);

            $points[] = number_format(
                $questionCents / 100,
                2,
                '.',
                ''
            );
        }

        return $points;
    }
}

