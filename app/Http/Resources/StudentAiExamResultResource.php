<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StudentAiExamResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assignment = $this->assignment;
        $exam = $assignment->exam;

        $showResult = (bool)
            $assignment
                ->show_result_after_submission;

        $showCorrectAnswers =
            $showResult
            && (bool)
                $assignment
                    ->show_correct_answers;

        $answersByQuestion = $this->answers
            ->keyBy('question_id');

        return [
            'attempt' => [
                'id' => $this->id,

                'assignment_id' =>
                    $this->assignment_id,

                'attempt_number' =>
                    $this->attempt_number,

                'status' =>
                    $this->status instanceof BackedEnum
                        ? $this->status->value
                        : $this->status,

                'started_at' =>
                    $this->started_at
                        ?->toIso8601String(),

                'submitted_at' =>
                    $this->submitted_at
                        ?->toIso8601String(),
            ],

            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,

                'question_count' =>
                    $exam->question_count,

                'total_points' =>
                    $exam->total_points,
            ],

            'result_available' =>
                $showResult,

            /*
             * The score is returned only when the teacher
             * allows immediate result visibility.
             */
            'result' => $this->when(
                $showResult,
                fn (): array => [
                    'score' => $this->score,

                    'max_score' =>
                        $this->max_score,

                    'correct_answers_count' =>
                        $this
                            ->correct_answers_count,

                    'question_count' =>
                        $exam->question_count,

                    'percentage' =>
                        (float) $this->max_score > 0
                            ? round(
                                (
                                    (float) $this->score
                                    / (float)
                                        $this->max_score
                                ) * 100,
                                2
                            )
                            : 0,
                ]
            ),

            /*
             * Question-level correction appears only
             * when show_correct_answers is enabled.
             */
            'review' => $this->when(
                $showCorrectAnswers,
                fn () => $exam->questions
                    ->sortBy('position')
                    ->values()
                    ->map(
                        function ($question) use (
                            $answersByQuestion
                        ): array {
                            $answer =
                                $answersByQuestion->get(
                                    $question->id
                                );

                            $correctOption =
                                $question->options
                                    ->firstWhere(
                                        'is_correct',
                                        true
                                    );

                            return [
                                'question_id' =>
                                    $question->id,

                                'question_text' =>
                                    $question
                                        ->question_text,

                                'selected_option_id' =>
                                    $answer
                                        ?->selected_option_id,

                                'correct_option_id' =>
                                    $correctOption?->id,

                                'is_correct' =>
                                    $answer
                                        ?->is_correct,

                                'points_awarded' =>
                                    $answer
                                        ?->points_awarded,

                                'question_points' =>
                                    $question->points,

                                'explanation' =>
                                    $question
                                        ->explanation,
                            ];
                        }
                    )
            ),
        ];
    }
}