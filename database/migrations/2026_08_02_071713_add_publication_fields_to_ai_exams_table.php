<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_exams', function (Blueprint $table): void {
            $table
                ->string('publication_status', 20)
                ->default('draft')
                ->after('generation_status');

            $table
                ->timestamp('published_at')
                ->nullable()
                ->after('generated_at');

            $table->index(
                'publication_status',
                'ai_exams_publication_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_exams', function (Blueprint $table): void {
            $table->dropIndex(
                'ai_exams_publication_status_index'
            );

            $table->dropColumn([
                'publication_status',
                'published_at',
            ]);
        });
    }
};


// وظيفة الأعمدة
// publication_status

// يفصل حالة النشر عن حالة Gemini:

// generation_status = هل التوليد اكتمل؟