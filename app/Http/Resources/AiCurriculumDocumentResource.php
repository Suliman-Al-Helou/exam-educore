<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiCurriculumDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'grade_level' => (int) $this->grade_level,
            'subject_name' => $this->subject_name,
            'term' => $this->term,
            'curriculum_year' => $this->curriculum_year,

            'file' => [
                'original_name' => $this->original_filename,
                'mime_type' => $this->mime_type,
                'size_bytes' => $this->size_bytes,
            ],

            'indexing' => [
'status' => $this->indexing_status->value,
                'error' => $this->indexing_error,
                'indexed_at' => $this->indexed_at?->toIso8601String(),
            ],

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}