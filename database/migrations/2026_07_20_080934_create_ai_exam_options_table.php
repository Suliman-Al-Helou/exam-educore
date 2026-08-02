<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'ai_exam_options',
            function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->foreignUlid('question_id')
                    ->constrained('ai_exam_questions')
                    ->cascadeOnDelete();

                $table->text('option_text');

                // This field must never be exposed to students.
                $table->boolean('is_correct')
                    ->default(false);

                $table->unsignedTinyInteger('position');

                $table->timestamps();

                $table->unique(
                    ['question_id', 'position'],
                    'ai_exam_options_position_unique'
                );

                $table->index(
                    ['question_id', 'is_correct'],
                    'ai_exam_options_correct_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_exam_options');
    }
};

// مهم:

// كل سؤال سيحتوي أربعة خيارات.
// خيار واحد فقط ستكون قيمته is_correct = true.
// لن نرسل is_correct داخل API الخاص بالطالب.
// التحقق من وجود 4 خيارات وإجابة صحيحة واحدة سيتم في Laravel قبل الحفظ.
