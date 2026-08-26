<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\EnsureAiServiceKey;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StudentAiExamAssignmentsApiTest extends TestCase
{
    use DatabaseMigrations;

    public function test_student_can_list_only_their_assignments_with_navigation_status(): void
    {
        $this->withoutMiddleware(EnsureAiServiceKey::class);

        $notStarted = $this->createAssignment('student-001', 'Not started');
        $inProgress = $this->createAssignment('student-001', 'In progress');
        $submitted = $this->createAssignment('student-001', 'Submitted');
        $this->createAssignment('student-999', 'Another student');

        $activeAttempt = $this->createAttempt($inProgress, 'student-001', 'in_progress');
        $submittedAttempt = $this->createAttempt($submitted, 'student-001', 'submitted');

        $response = $this->getJson(
            '/api/v1/ai-exam-assignments?external_student_id=student-001'
        );

        $response->assertOk()->assertJsonCount(3, 'data');
        $items = collect($response->json('data'))->keyBy('assignment_id');

        $this->assertSame('not_started', $items[$notStarted]['student_status']);
        $this->assertSame('in_progress', $items[$inProgress]['student_status']);
        $this->assertSame($activeAttempt, $items[$inProgress]['active_attempt_id']);
        $this->assertSame('submitted', $items[$submitted]['student_status']);
        $this->assertSame($submittedAttempt, $items[$submitted]['submitted_attempt_id']);
        $this->assertSame(1, $items[$submitted]['attempts_used']);
        $this->assertSame(1, $items[$submitted]['attempts_remaining']);
    }

    public function test_student_can_filter_assignments_by_status(): void
    {
        $this->withoutMiddleware(EnsureAiServiceKey::class);

        $notStarted = $this->createAssignment('student-001', 'Not started');
        $inProgress = $this->createAssignment('student-001', 'In progress');
        $this->createAttempt($inProgress, 'student-001', 'in_progress');

        $response = $this->getJson(
            '/api/v1/ai-exam-assignments?external_student_id=student-001'
            .'&status=not_started&per_page=1'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.assignment_id', $notStarted)
            ->assertJsonPath('data.0.student_status', 'not_started')
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_external_student_id_is_required(): void
    {
        $this->withoutMiddleware(EnsureAiServiceKey::class);

        $this->getJson('/api/v1/ai-exam-assignments')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('external_student_id');
    }

    private function createAssignment(string $studentId, string $title): string
    {
        $documentId = (string) Str::ulid();
        $examId = (string) Str::ulid();
        $assignmentId = (string) Str::ulid();
        $now = now();

        DB::table('ai_curriculum_documents')->insert([
            'id' => $documentId,
            'external_teacher_id' => 'teacher-001',
            'title' => 'Curriculum',
            'grade_level' => '5',
            'subject_name' => 'Science',
            'term' => 1,
            'curriculum_year' => '2025-2026',
            'original_filename' => 'curriculum.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'curriculum/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'sha256' => hash('sha256', $documentId),
            'ai_provider' => 'gemini',
            'indexing_status' => 'indexed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('ai_exams')->insert([
            'id' => $examId,
            'curriculum_document_id' => $documentId,
            'external_teacher_id' => 'teacher-001',
            'title' => $title,
            'grade_level' => '5',
            'subject_name' => 'Science',
            'term' => 1,
            'curriculum_year' => '2025-2026',
            'lesson_title' => 'Cells',
            'difficulty' => 'medium',
            'question_types' => json_encode(['true_false']),
            'question_count' => 1,
            'total_points' => 10,
            'duration_minutes' => 30,
            'ai_provider' => 'gemini',
            'generation_status' => 'ready',
            'publication_status' => 'published',
            'generated_at' => $now,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('ai_exam_assignments')->insert([
            'id' => $assignmentId,
            'exam_id' => $examId,
            'external_teacher_id' => 'teacher-001',
            'starts_at' => $now->copy()->subHour(),
            'ends_at' => $now->copy()->addHour(),
            'attempt_limit' => 2,
            'show_result_after_submission' => true,
            'show_correct_answers' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('ai_exam_assignment_students')->insert([
            'id' => (string) Str::ulid(),
            'assignment_id' => $assignmentId,
            'external_student_id' => $studentId,
            'assigned_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $assignmentId;
    }

    private function createAttempt(
        string $assignmentId,
        string $studentId,
        string $status
    ): string {
        $attemptId = (string) Str::ulid();
        $now = now();

        DB::table('ai_exam_attempts')->insert([
            'id' => $attemptId,
            'assignment_id' => $assignmentId,
            'external_student_id' => $studentId,
            'attempt_number' => 1,
            'status' => $status,
            'started_at' => $now->copy()->subMinutes(5),
            'expires_at' => $now->copy()->addMinutes(25),
            'submitted_at' => $status === 'submitted' ? $now : null,
            'score' => $status === 'submitted' ? 8 : null,
            'max_score' => 10,
            'correct_answers_count' => $status === 'submitted' ? 1 : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $attemptId;
    }
}
