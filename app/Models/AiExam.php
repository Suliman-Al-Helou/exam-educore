<?php

namespace App\Models;

use App\Enums\AiExamGenerationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\AiExamDifficulty;
use App\Enums\AiExamPublicationStatus;
class AiExam extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'curriculum_document_id',
        'external_teacher_id',
        'title',
        'grade_level',
        'subject_name',
        'term',
        'curriculum_year',
        'lesson_title',
        'generation_prompt',
        "difficulty",
        "question_types",
        'question_count',
        'total_points',
        'duration_minutes',
        'ai_provider',
        'ai_model',
        'generation_status',
        'generation_error',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'term' => 'integer',
            'question_types' => 'array',
            'question_count' => 'integer',
            'total_points' => 'decimal:2',
            'duration_minutes' => 'integer',
            'generation_status' => AiExamGenerationStatus::class,
            'generated_at' => 'datetime',
            'difficulty' => AiExamDifficulty::class,
                 'publication_status' => AiExamPublicationStatus::class,
        'published_at' => 'datetime',
        ];
    }

    public function curriculumDocument(): BelongsTo
    {
        return $this->belongsTo(
            AiCurriculumDocument::class,
            'curriculum_document_id'
        );
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AiExamQuestion::class, 'exam_id')
            ->orderBy('position');
    }
}