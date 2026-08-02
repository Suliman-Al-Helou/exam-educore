<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AiExamAssignment extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'exam_id',
        'external_teacher_id',
        'starts_at',
        'ends_at',
        'attempt_limit',
        'show_result_after_submission',
        'show_correct_answers',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',

            'attempt_limit' => 'integer',

            'show_result_after_submission' =>
                'boolean',

            'show_correct_answers' =>
                'boolean',

            'cancelled_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(
            AiExam::class,
            'exam_id'
        );
    }

    public function students(): HasMany
    {
        return $this->hasMany(
            AiExamAssignmentStudent::class,
            'assignment_id'
        );
    }
    public function attempts(): HasMany
{
    return $this->hasMany(
        AiExamAttempt::class,
        'assignment_id'
    );
}
}