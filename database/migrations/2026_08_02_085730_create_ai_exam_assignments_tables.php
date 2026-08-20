<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'ai_exam_assignments',
            function (Blueprint $table): void {
                $table->char('id', 26)->primary();

                $table->char('exam_id', 26);

                $table->string(
                    'external_teacher_id',
                    191
                );

                /*
                 * The assignment is available to students
                 * only between these two timestamps.
                 */
           $table->dateTime('starts_at');
           $table->dateTime('ends_at');

                $table
                    ->unsignedSmallInteger('attempt_limit')
                    ->default(1);

                $table
                    ->boolean('show_result_after_submission')
                    ->default(false);

                $table
                    ->boolean('show_correct_answers')
                    ->default(false);

                /*
                 * A cancelled assignment remains stored
                 * for audit and reports.
                 */
                $table
                    ->timestamp('cancelled_at')
                    ->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table
                    ->foreign(
                        'exam_id',
                        'ai_exam_assignments_exam_fk'
                    )
                    ->references('id')
                    ->on('ai_exams')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'external_teacher_id',
                        'starts_at',
                    ],
                    'ai_exam_assignments_teacher_start_idx'
                );

                $table->index(
                    [
                        'exam_id',
                        'cancelled_at',
                    ],
                    'ai_exam_assignments_exam_cancelled_idx'
                );
            }
        );

        Schema::create(
            'ai_exam_assignment_students',
            function (Blueprint $table): void {
                $table->char('id', 26)->primary();

                $table->char(
                    'assignment_id',
                    26
                );

                /*
                 * Student identity comes from the main
                 * platform, like external_teacher_id.
                 */
                $table->string(
                    'external_student_id',
                    191
                );

                $table
                    ->timestamp('assigned_at')
                    ->nullable();

                $table->timestamps();

                $table
                    ->foreign(
                        'assignment_id',
                        'ai_exam_assignment_students_assignment_fk'
                    )
                    ->references('id')
                    ->on('ai_exam_assignments')
                    ->cascadeOnDelete();

                /*
                 * The same student cannot be assigned
                 * twice to the same assignment.
                 */
                $table->unique(
                    [
                        'assignment_id',
                        'external_student_id',
                    ],
                    'ai_exam_assignment_student_unique'
                );

                $table->index(
                    'external_student_id',
                    'ai_exam_assignment_students_student_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'ai_exam_assignment_students'
        );

        Schema::dropIfExists(
            'ai_exam_assignments'
        );
    }
};