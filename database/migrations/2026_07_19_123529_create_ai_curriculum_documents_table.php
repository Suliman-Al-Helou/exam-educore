<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_curriculum_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Curriculum metadata
            $table->string('title');
            $table->string('grade_level', 50);
            $table->string('subject_name', 120);
            $table->unsignedTinyInteger('term');
            $table->string('curriculum_year', 9);

            // Local PDF information
            $table->string('original_filename');
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);

            // OpenAI indexing information
            $table->string('openai_file_id', 100)->nullable()->unique();
            $table->string('openai_vector_store_id', 100)->nullable();
            $table->string('indexing_status', 20)->default('pending');
            $table->text('indexing_error')->nullable();
            $table->timestamp('indexed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Fast curriculum lookup
            $table->index(
                ['grade_level', 'subject_name', 'term', 'curriculum_year'],
                'ai_documents_lookup_index'
            );

            $table->index(
                'openai_vector_store_id',
                'ai_documents_vector_store_index'
            );

            $table->index(
                'indexing_status',
                'ai_documents_status_index'
            );

            // Prevent duplicate uploads for the same curriculum
            $table->unique(
                [
                    'sha256',
                    'grade_level',
                    'subject_name',
                    'term',
                    'curriculum_year',
                ],
                'ai_document_version_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_curriculum_documents');
    }
};


// وظيفة الأعمدة المهمة
// id: معرف ULID، مناسب عند نقل البيانات إلى المشروع الرئيسي.
// grade_level: مثل الصف الرابع.
// subject_name: مثل الرياضيات.
// term: الفصل 1 أو 2.
// curriculum_year: مثل 2025-2026.
// storage_path: مكان PDF داخل Laravel.
// sha256: بصمة الملف لمنع رفع الكتاب نفسه مرتين.
// openai_file_id: معرف الملف بعد رفعه إلى OpenAI.
// openai_vector_store_id: مجموعة الملفات التي سيبحث AI داخلها.
// indexing_status: حالة المعالجة مثل pending أو processing أو ready أو failed.
// softDeletes: يسمح بحذف الكتاب بشكل قابل للاسترجاع.