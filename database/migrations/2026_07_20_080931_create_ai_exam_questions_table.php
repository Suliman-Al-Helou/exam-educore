<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'ai_exam_questions',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->foreignUlid('exam_id')
                    ->constrained('ai_exams')
                    ->cascadeOnDelete();

                $table->string('type', 30)
                    ->default('multiple_choice');

                $table->text('question_text');

                // Explanation shown after grading.
                $table->text('explanation')
                    ->nullable();

                // Lesson section or page used by AI.
                $table->string('source_reference')
                    ->nullable();

                $table->unsignedSmallInteger('position');

                $table->decimal('points', 8, 2);

                $table->timestamps();

                $table->unique(
                    ['exam_id', 'position'],
                    'ai_exam_questions_position_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_exam_questions');
    }
};

// وظيفته:

// يربط كل سؤال بامتحان.
// يحفظ نص السؤال ودرجته وترتيبه.
// explanation يظهر للطالب بعد التصحيح.
// source_reference يساعدنا في معرفة مصدر السؤال.
// يمنع وجود سؤالين بالترتيب نفسه داخل الامتحان.