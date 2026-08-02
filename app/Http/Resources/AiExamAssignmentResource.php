<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AiExamAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,

            'external_teacher_id' =>
                $this->external_teacher_id,

            'starts_at' =>
                $this->starts_at?->toIso8601String(),

            'ends_at' =>
                $this->ends_at?->toIso8601String(),

            'attempt_limit' => $this->attempt_limit,

            'show_result_after_submission' =>
                $this->show_result_after_submission,

            'show_correct_answers' =>
                $this->show_correct_answers,

            'cancelled_at' =>
                $this->cancelled_at?->toIso8601String(),

            'exam' => $this->whenLoaded(
                'exam',
                fn (): array => [
                    'id' => $this->exam->id,
                    'title' => $this->exam->title,

                    'publication_status' =>
                        $this->exam
                            ->publication_status
                            ->value,

                    'question_count' =>
                        $this->exam->question_count,

                    'total_points' =>
                        $this->exam->total_points,

                    'duration_minutes' =>
                        $this->exam->duration_minutes,
                ]
            ),

            'student_count' => $this->whenLoaded(
                'students',
                fn (): int => $this->students->count()
            ),

            'students' => $this->whenLoaded(
                'students',
                fn () => $this->students
                    ->map(
                        fn ($student): array => [
                            'id' => $student->id,

                            'external_student_id' =>
                                $student
                                    ->external_student_id,

                            'assigned_at' =>
                                $student
                                    ->assigned_at
                                    ?->toIso8601String(),
                        ]
                    )
                    ->values()
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}