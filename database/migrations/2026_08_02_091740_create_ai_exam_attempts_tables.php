<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'ai_exam_attempts',
            function (Blueprint $table): void {
                $table->char('id', 26)->primary();

                $table->char(
                    'assignment_id',
                    26
                );

                $table->string(
                    'external_student_id',
                    191
                );

                $table
                    ->unsignedSmallInteger(
                        'attempt_number'
                    );

                /*
                 * Supported values:
                 * in_progress, submitted, expired
                 */
                $table
                    ->string('status', 32)
                    ->default('in_progress');

                $table->timestamp('started_at');

                /*
                 * The attempt may expire before the
                 * assignment ends because of exam duration.
                 */
                $table->timestamp('expires_at');

                $table
                    ->timestamp('submitted_at')
                    ->nullable();

                $table
                    ->decimal('score', 8, 2)
                    ->nullable();

                $table
                    ->decimal('max_score', 8, 2);

                $table
                    ->unsignedSmallInteger(
                        'correct_answers_count'
                    )
                    ->nullable();

                $table->timestamps();

                $table
                    ->foreign(
                        'assignment_id',
                        'ai_exam_attempts_assignment_fk'
                    )
                    ->references('id')
                    ->on('ai_exam_assignments')
                    ->cascadeOnDelete();

                /*
                 * A student cannot have two attempts
                 * with the same attempt number.
                 */
                $table->unique(
                    [
                        'assignment_id',
                        'external_student_id',
                        'attempt_number',
                    ],
                    'ai_exam_attempt_student_number_unique'
                );

                $table->index(
                    [
                        'external_student_id',
                        'status',
                    ],
                    'ai_exam_attempt_student_status_idx'
                );

                $table->index(
                    [
                        'assignment_id',
                        'status',
                    ],
                    'ai_exam_attempt_assignment_status_idx'
                );
            }
        );

        Schema::create(
            'ai_exam_attempt_answers',
            function (Blueprint $table): void {
                $table->char('id', 26)->primary();

                $table->char(
                    'attempt_id',
                    26
                );

                $table->char(
                    'question_id',
                    26
                );

                /*
                 * Nullable because the student may leave
                 * the question unanswered.
                 */
                $table
                    ->char('selected_option_id', 26)
                    ->nullable();

                /*
                 * Filled when the attempt is submitted.
                 */
                $table
                    ->boolean('is_correct')
                    ->nullable();

                $table
                    ->decimal(
                        'points_awarded',
                        8,
                        2
                    )
                    ->default(0);

                $table
                    ->timestamp('answered_at')
                    ->nullable();

                $table->timestamps();

                $table
                    ->foreign(
                        'attempt_id',
                        'ai_exam_attempt_answers_attempt_fk'
                    )
                    ->references('id')
                    ->on('ai_exam_attempts')
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'question_id',
                        'ai_exam_attempt_answers_question_fk'
                    )
                    ->references('id')
                    ->on('ai_exam_questions')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'selected_option_id',
                        'ai_exam_attempt_answers_option_fk'
                    )
                    ->references('id')
                    ->on('ai_exam_options')
                    ->nullOnDelete();

                /*
                 * One stored answer for each question
                 * inside the attempt.
                 */
                $table->unique(
                    [
                        'attempt_id',
                        'question_id',
                    ],
                    'ai_exam_attempt_answer_unique'
                );

                $table->index(
                    'selected_option_id',
                    'ai_exam_attempt_answer_option_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'ai_exam_attempt_answers'
        );

        Schema::dropIfExists(
            'ai_exam_attempts'
        );
    }
};

