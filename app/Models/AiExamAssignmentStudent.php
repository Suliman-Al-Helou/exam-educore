<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AiExamAssignmentStudent extends Model
{
    use HasUlids;

    protected $fillable = [
        'assignment_id',
        'external_student_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(
            AiExamAssignment::class,
            'assignment_id'
        );
    }
}