<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StudentAiExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exam = $this->exam;

        return [
            'assignment' => [
                'id' => $this->id,

                'starts_at' =>
                    $this->starts_at?->toIso8601String(),

                'ends_at' =>
                    $this->ends_at?->toIso8601String(),

                'attempt_limit' =>
                    $this->attempt_limit,

                'show_result_after_submission' =>
                    $this->show_result_after_submission,

                'show_correct_answers' =>
                    $this->show_correct_answers,
            ],

            'server_time' =>
                now()->toIso8601String(),

            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,

                'grade_level' =>
                    $exam->grade_level,

                'subject_name' =>
                    $exam->subject_name,

                'term' => $exam->term,

                'curriculum_year' =>
                    $exam->curriculum_year,

                'lesson_title' =>
                    $exam->lesson_title,

                'difficulty' =>
                    $exam->difficulty instanceof BackedEnum
                        ? $exam->difficulty->value
                        : $exam->difficulty,

                'question_count' =>
                    $exam->question_count,

                'total_points' =>
                    $exam->total_points,

                'duration_minutes' =>
                    $exam->duration_minutes,

                'questions' => $exam->questions
                    ->sortBy('position')
                    ->values()
                    ->map(
                        function ($question): array {
                            return [
                                'id' => $question->id,

                                'type' =>
                                    $question->type
                                        instanceof BackedEnum
                                            ? $question
                                                ->type
                                                ->value
                                            : $question->type,

                                'question_text' =>
                                    $question->question_text,

                                'position' =>
                                    $question->position,

                                'points' =>
                                    $question->points,

                                'options' =>
                                    $question->options
                                        ->sortBy('position')
                                        ->values()
                                        ->map(
                                            fn ($option): array => [
                                                'id' =>
                                                    $option->id,

                                                'option_text' =>
                                                    $option
                                                        ->option_text,

                                                'position' =>
                                                    $option
                                                        ->position,
                                            ]
                                        ),
                            ];
                        }
                    ),
            ],
        ];
    }
}