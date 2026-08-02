<?php

use App\Enums\DifficultyLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('exam_id')
                ->constrained('exams')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('position');

            $table->string('type', 30);

            $table->text('question_text');

            $table->json('options')->nullable();

            $table->json('correct_answer');

            $table->text('explanation')->nullable();

            $table->string('difficulty', 20)
                ->default(DifficultyLevel::Medium->value);

            $table->decimal('points', 5, 2)
                ->default(1);

            $table->json('source_reference')->nullable();

            $table->timestamps();

            $table->unique([
                'exam_id',
                'position',
            ]);

            $table->index([
                'exam_id',
                'type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
    }
};