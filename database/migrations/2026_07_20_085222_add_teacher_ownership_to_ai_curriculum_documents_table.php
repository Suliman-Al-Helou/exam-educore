<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'ai_curriculum_documents',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'ai_document_version_unique'
                );

                // Nullable only for the existing test books.
                $table->string(
                    'external_teacher_id',
                    100
                )->nullable()->after('id');

                $table->index(
                    [
                        'external_teacher_id',
                        'indexing_status',
                        'created_at',
                    ],
                    'ai_documents_teacher_status_index'
                );

                $table->unique(
                    [
                        'sha256',
                        'external_teacher_id',
                        'grade_level',
                        'subject_name',
                        'term',
                        'curriculum_year',
                    ],
                    'ai_document_teacher_version_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'ai_curriculum_documents',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'ai_document_teacher_version_unique'
                );

                $table->dropIndex(
                    'ai_documents_teacher_status_index'
                );

                $table->dropColumn(
                    'external_teacher_id'
                );

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
            }
        );
    }
};