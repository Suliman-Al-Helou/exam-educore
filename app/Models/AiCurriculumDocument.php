<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\AiDocumentIndexingStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
class AiCurriculumDocument extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'ai_curriculum_documents';

    protected $fillable = [
        'title',
        'grade_level',
        'subject_name',
        'term',
        'curriculum_year',
        'original_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'sha256',
        'ai_provider',
        'provider_file_id',
        'provider_vector_store_id',
        'provider_vector_store_document_id',
        'indexing_status',
        'indexing_error',
        'indexed_at',
        'external_teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'term' => 'integer',
            'size_bytes' => 'integer',
            'indexing_status' => AiDocumentIndexingStatus::class,
            'indexed_at' => 'datetime',
        ];
        
    }
    public function exams(): HasMany
{
    return $this->hasMany(
        AiExam::class,
        'curriculum_document_id'
    );
}
}
use App\Models\AiCurriculumDocument;

