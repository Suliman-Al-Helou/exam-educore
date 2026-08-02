<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiExamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'curriculum_document_id' =>
                $this->curriculum_document_id,

            'title' => $this->title,
            'grade_level' => $this->grade_level,
            'subject_name' => $this->subject_name,
            'term' => $this->term,
            'curriculum_year' => $this->curriculum_year,
            'lesson_title' => $this->lesson_title,

            'difficulty' => $this->difficulty->value,
            'question_types' => $this->question_types,

            'question_count' => $this->question_count,
            'total_points' => (float) $this->total_points,
            'duration_minutes' => $this->duration_minutes,

            'generation' => [
                'status' => $this->generation_status->value,
                'error' => $this->generation_error,
                'generated_at' =>
                    $this->generated_at?->toIso8601String(),
            ],

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}

// وظيفته

// توحيد JSON الذي يرجع من API ومنع إرجاع بيانات داخلية غير مطلوبة.