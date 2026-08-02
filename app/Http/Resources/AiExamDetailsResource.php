<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AiExamDetailsResource extends JsonResource
{
    /**
     * Transform the AI exam into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'curriculum_document_id' =>
                $this->curriculum_document_id,

            'external_teacher_id' =>
                $this->external_teacher_id,

            'title' => $this->title,
            'publication_status' => $this->publication_status->value,
'published_at' => $this->published_at?->toIso8601String(),

            'grade_level' => $this->grade_level,

            'subject_name' => $this->subject_name,

            'term' => $this->term,

            'curriculum_year' =>
                $this->curriculum_year,

            'lesson_title' => $this->lesson_title,

            'difficulty' =>
                $this->difficulty->value,

            'question_types' =>
                $this->question_types,

            'question_count' =>
                $this->question_count,

            'total_points' =>
                $this->total_points,

            'duration_minutes' =>
                $this->duration_minutes,

            'generation_prompt' =>
                $this->generation_prompt,

            'generation' => [
                'status' =>
                    $this->generation_status->value,

                'error' =>
                    $this->generation_error,

                'provider' =>
                    $this->ai_provider,

                'model' =>
                    $this->ai_model,

                'generated_at' =>
                    $this->generated_at?->toIso8601String(),
            ],

            'questions' => $this->whenLoaded(
                'questions',
                fn () => $this->questions
                    ->sortBy('position')
                    ->values()
                    ->map(
                        fn ($question) => [
                            'id' => $question->id,

                            'type' =>
                                $question->type->value,

                            'question_text' =>
                                $question->question_text,

                            'explanation' =>
                                $question->explanation,

                            'source_reference' =>
                                $question->source_reference,

                            'position' =>
                                $question->position,

                            'points' =>
                                $question->points,

                            'options' =>
                                $question->options
                                    ->sortBy('position')
                                    ->values()
                                    ->map(
                                        fn ($option) => [
                                            'id' =>
                                                $option->id,

                                            'option_text' =>
                                                $option->option_text,

                                            'is_correct' =>
                                                $option->is_correct,

                                            'position' =>
                                                $option->position,
                                        ]
                                    ),
                        ]
                    ),
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}