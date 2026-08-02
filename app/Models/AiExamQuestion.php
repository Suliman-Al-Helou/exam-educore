<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\AiExamQuestionType;
class AiExamQuestion extends Model
{
    use HasUlids;

    protected $fillable = [
        'exam_id',
        'type',
        'question_text',
        'explanation',
        'source_reference',
        'position',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'points' => 'decimal:2',
            'type' => AiExamQuestionType::class,
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(AiExam::class, 'exam_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(AiExamOption::class, 'question_id')
            ->orderBy('position');
    }
}