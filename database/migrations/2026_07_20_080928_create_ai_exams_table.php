<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_exams', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('curriculum_document_id')
                ->constrained('ai_curriculum_documents')
                ->restrictOnDelete();

            // ID received from the main EduCore project.
            $table->string('external_teacher_id', 100);

            $table->string('title');

            // Curriculum snapshot at generation time.
            $table->string('grade_level', 50);
            $table->string('subject_name', 120);
            $table->unsignedTinyInteger('term');
            $table->string('curriculum_year', 9);
            $table->string('lesson_title');

            // Optional additional teacher instructions.
            $table->text('generation_prompt')->nullable();

            $table->unsignedTinyInteger('question_count')
                ->default(10);

            $table->decimal('total_points', 8, 2);

            // Null means the exam has no timer.
            $table->unsignedSmallInteger('duration_minutes')
                ->nullable();

            // AI generation tracking.
            $table->string('ai_provider', 30)
                ->default('gemini');

            $table->string('ai_model', 100)
                ->nullable();

            $table->string('generation_status', 20)
                ->default('pending');

            $table->text('generation_error')
                ->nullable();

            $table->timestamp('generated_at')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['external_teacher_id', 'generation_status'],
                'ai_exams_teacher_status_index'
            );

            $table->index(
                [
                    'grade_level',
                    'subject_name',
                    'term',
                    'curriculum_year',
                ],
                'ai_exams_curriculum_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_exams');
    }
};
// وظيفة الأعمدة المهمة:

// curriculum_document_id: الكتاب الذي استُخدم لتوليد الامتحان.
// external_teacher_id: معرّف المعلم القادم من مشروع Next/Laravel الرئيسي.
// بيانات الصف والمادة: Snapshot حتى لا تتغير معلومات الامتحان إذا عُدّل الكتاب لاحقًا.
// total_points: العلامة التي يحددها المعلم.
// duration_minutes: null يعني دون مؤقت.
// generation_status: ستكون pending / generating / ready / failed.
// generation_error: يحفظ سبب فشل Gemini.
// restrictOnDelete(): يمنع حذف الكتاب نهائيًا إذا كانت هناك امتحانات مرتبطة به.