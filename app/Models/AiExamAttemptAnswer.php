<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiExamAttemptAnswer extends Model
{
    use HasUlids;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_id',
        'is_correct',
        'points_awarded',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',

            'points_awarded' =>
                'decimal:2',

            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(
            AiExamAttempt::class,
            'attempt_id'
        );
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(
            AiExamQuestion::class,
            'question_id'
        );
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(
            AiExamOption::class,
            'selected_option_id'
        );
    }
}