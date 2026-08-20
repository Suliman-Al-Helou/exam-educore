<?php

use App\Enums\DifficultyLevel;
use App\Enums\ExamStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('book_id')
                ->constrained('ai_curriculum_documents')
                ->restrictOnDelete();

            $table->string('external_teacher_id')->index();

            $table->string('title', 160);

            $table->string('status', 30)
                ->default(ExamStatus::Draft->value)
                ->index();

            $table->string('difficulty', 20)
                ->default(DifficultyLevel::Mixed->value);

            $table->unsignedSmallInteger('questions_count');

            $table->json('question_types');

            $table->json('generation_settings')->nullable();

            $table->text('generation_error')->nullable();

            $table->timestamp('generated_at')->nullable();

            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'external_teacher_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};