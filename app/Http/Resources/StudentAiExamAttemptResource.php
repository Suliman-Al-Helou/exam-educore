<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StudentAiExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assignment = $this->assignment;
        $exam = $assignment->exam;

        $answersByQuestion = $this->answers
            ->keyBy('question_id');

        $remainingSeconds = max(
            0,
            $this->expires_at->getTimestamp()
                - now()->getTimestamp()
        );

        return [
            'attempt' => [
                'id' => $this->id,

                'assignment_id' =>
                    $this->assignment_id,

                'external_student_id' =>
                    $this->external_student_id,

                'attempt_number' =>
                    $this->attempt_number,

                'status' =>
                    $this->status instanceof BackedEnum
                        ? $this->status->value
                        : $this->status,

                'started_at' =>
                    $this->started_at
                        ?->toIso8601String(),

                'expires_at' =>
                    $this->expires_at
                        ?->toIso8601String(),

                'remaining_seconds' =>
                    $remainingSeconds,

                'max_score' =>
                    $this->max_score,
            ],

            'server_time' =>
                now()->toIso8601String(),

            'assignment' => [
                'id' => $assignment->id,

                'starts_at' =>
                    $assignment->starts_at
                        ?->toIso8601String(),

                'ends_at' =>
                    $assignment->ends_at
                        ?->toIso8601String(),

                'attempt_limit' =>
                    $assignment->attempt_limit,
            ],

            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,

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
                        function ($question) use (
                            $answersByQuestion
                        ): array {
                            $answer = $answersByQuestion
                                ->get($question->id);

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
                                    $question
                                        ->question_text,

                                'position' =>
                                    $question->position,

                                'points' =>
                                    $question->points,

                                'selected_option_id' =>
                                    $answer
                                        ?->selected_option_id,

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