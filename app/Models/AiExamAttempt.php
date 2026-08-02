<?php

namespace App\Models;

use App\Enums\AiExamAttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AiExamAttempt extends Model
{
    use HasUlids;

    protected $fillable = [
        'assignment_id',
        'external_student_id',
        'attempt_number',
        'status',
        'started_at',
        'expires_at',
        'submitted_at',
        'score',
        'max_score',
        'correct_answers_count',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',

            'status' =>
                AiExamAttemptStatus::class,

            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',

            'score' => 'decimal:2',
            'max_score' => 'decimal:2',

            'correct_answers_count' =>
                'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(
            AiExamAssignment::class,
            'assignment_id'
        );
    }

    public function answers(): HasMany
    {
        return $this->hasMany(
            AiExamAttemptAnswer::class,
            'attempt_id'
        );
    }
}