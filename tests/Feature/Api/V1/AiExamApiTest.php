<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\EnsureAiServiceKey;
use App\Jobs\GenerateAiExamJob;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AiExamApiTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Ensure creating an exam stores it and dispatches generation.
     */
    public function test_teacher_can_create_ai_exam_and_dispatch_generation_job(): void
    {
        Queue::fake();

        $this->withoutMiddleware(
            EnsureAiServiceKey::class
        );

        $documentId = (string) Str::ulid();

        DB::table('ai_curriculum_documents')->insert([
            'id' => $documentId,
            'external_teacher_id' => 'teacher-001',

            'title' =>
                'كتاب العلوم والحياة للصف الخامس',

            'grade_level' => '5',
            'subject_name' => 'العلوم والحياة',
            'term' => 1,
            'curriculum_year' => '2025-2026',

            'original_filename' => 'science-grade-5.pdf',
            'storage_disk' => 'local',

            'storage_path' =>
                'curriculum/science-grade-5.pdf',

            'mime_type' => 'application/pdf',
            'size_bytes' => 100000,

            'sha256' => hash(
                'sha256',
                'science-grade-5-test-document'
            ),

            'ai_provider' => 'gemini',

            'provider_file_id' =>
                'test-provider-file-id',

            'provider_vector_store_id' =>
                'test-vector-store-id',

            'provider_vector_store_document_id' =>
                'test-vector-store-document-id',

            'indexing_status' => 'indexed',
            'indexing_error' => null,
            'indexed_at' => now(),

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/v1/curriculum-documents/{$documentId}/exams",
            [
                'external_teacher_id' => 'teacher-001',

                'title' =>
                    'اختبار إنشاء آلي',

                'lesson_title' =>
                    'أجزاء الخلية',

                'difficulty' => 'medium',

                'question_types' => [
                    'multiple_choice',
                    'true_false',
                ],

                'question_count' => 4,
                'total_points' => 8,
                'duration_minutes' => 15,

                'generation_prompt' =>
                    'أنشئ أسئلة واضحة ومباشرة.',
            ]
        );

        $response
            ->assertAccepted()
            ->assertJsonPath(
                'data.curriculum_document_id',
                $documentId
            )
            ->assertJsonPath(
                'data.title',
                'اختبار إنشاء آلي'
            )
            ->assertJsonPath(
                'data.generation.status',
                'pending'
            );

        $examId = $response->json('data.id');

        $this->assertDatabaseHas('ai_exams', [
            'id' => $examId,
            'curriculum_document_id' => $documentId,
            'external_teacher_id' => 'teacher-001',
            'generation_status' => 'pending',
            'question_count' => 4,
            'total_points' => 8,
        ]);

        Queue::assertPushed(
            GenerateAiExamJob::class,
            fn (GenerateAiExamJob $job): bool =>
                $job->examId === $examId
        );
    }
    /**
 * Ensure a teacher cannot create an exam from another teacher's document.
 */
public function test_teacher_cannot_create_exam_from_another_teachers_document(): void
{
    Queue::fake();

    $this->withoutMiddleware(
        EnsureAiServiceKey::class
    );

    $documentId = (string) Str::ulid();

    DB::table('ai_curriculum_documents')->insert([
        'id' => $documentId,

        // The document belongs to another teacher.
        'external_teacher_id' => 'teacher-002',

        'title' =>
            'كتاب العلوم الخاص بمعلم آخر',

        'grade_level' => '5',
        'subject_name' => 'العلوم والحياة',
        'term' => 1,
        'curriculum_year' => '2025-2026',

        'original_filename' =>
            'another-teacher-science.pdf',

        'storage_disk' => 'local',

        'storage_path' =>
            'curriculum/another-teacher-science.pdf',

        'mime_type' => 'application/pdf',
        'size_bytes' => 100000,

        'sha256' => hash(
            'sha256',
            'another-teacher-document'
        ),

        'ai_provider' => 'gemini',

        'provider_file_id' =>
            'another-teacher-provider-file',

        'provider_vector_store_id' =>
            'another-teacher-vector-store',

        'provider_vector_store_document_id' =>
            'another-teacher-vector-document',

        'indexing_status' => 'indexed',
        'indexing_error' => null,
        'indexed_at' => now(),

        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson(
        "/api/v1/curriculum-documents/{$documentId}/exams",
        [
            // This teacher does not own the document.
            'external_teacher_id' => 'teacher-001',

            'title' =>
                'اختبار غير مصرح به',

            'lesson_title' =>
                'أجزاء الخلية',

            'difficulty' => 'medium',

            'question_types' => [
                'multiple_choice',
                'true_false',
            ],

            'question_count' => 4,
            'total_points' => 8,
            'duration_minutes' => 15,
        ]
    );

$response->assertForbidden();
    $this->assertDatabaseMissing('ai_exams', [
        'curriculum_document_id' => $documentId,
        'external_teacher_id' => 'teacher-001',
    ]);

    Queue::assertNotPushed(
        GenerateAiExamJob::class
    );
}
/**
 * Ensure the teacher can retrieve a ready exam with its questions.
 */
public function test_teacher_can_retrieve_ready_exam_with_questions(): void
{
    $this->withoutMiddleware(
        EnsureAiServiceKey::class
    );

    
    $documentId = $this->createIndexedDocument(
        'teacher-001'
    );

    $examId = (string) Str::ulid();
    $questionId = (string) Str::ulid();

    DB::table('ai_exams')->insert([
        'id' => $examId,
        'curriculum_document_id' => $documentId,
        'external_teacher_id' => 'teacher-001',

        'title' => 'اختبار جاهز',
        'grade_level' => '5',
        'subject_name' => 'العلوم والحياة',
        'term' => 1,
        'curriculum_year' => '2025-2026',
        'lesson_title' => 'أجزاء الخلية',

        'difficulty' => 'medium',

        'question_types' => json_encode([
            'true_false',
        ]),

        'generation_prompt' => null,
        'question_count' => 1,
        'total_points' => 2,
        'duration_minutes' => 10,

        'ai_provider' => 'gemini',
        'ai_model' => 'gemini-3.5-flash',
        'generation_status' => 'ready',
        'generation_error' => null,
        'generated_at' => now(),

        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ai_exam_questions')->insert([
        'id' => $questionId,
        'exam_id' => $examId,
        'type' => 'true_false',

        'question_text' =>
            'الخلية هي وحدة البناء الأساسية للكائن الحي.',

        'explanation' =>
            'العبارة صحيحة لأن أجسام الكائنات الحية تتكون من خلايا.',

        'source_reference' => 'صفحة 12',
        'position' => 1,
        'points' => 2,

        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ai_exam_options')->insert([
        [
            'id' => (string) Str::ulid(),
            'question_id' => $questionId,
            'option_text' => 'صح',
            'is_correct' => true,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::ulid(),
            'question_id' => $questionId,
            'option_text' => 'خطأ',
            'is_correct' => false,
            'position' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->getJson(
        "/api/v1/ai-exams/{$examId}"
        . '?external_teacher_id=teacher-001'
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $examId
        )
        ->assertJsonPath(
            'data.generation.status',
            'ready'
        )
        ->assertJsonPath(
            'data.questions.0.question_text',
            'الخلية هي وحدة البناء الأساسية للكائن الحي.'
        )
        ->assertJsonPath(
            'data.questions.0.options.0.option_text',
            'صح'
        )
        ->assertJsonPath(
            'data.questions.0.options.0.is_correct',
            true
        )
        ->assertJsonCount(
            1,
            'data.questions'
        )
        ->assertJsonCount(
            2,
            'data.questions.0.options'
        );
}
/**
 * Ensure an exam cannot be created before document indexing is complete.
 */
public function test_teacher_cannot_create_exam_from_unindexed_document(): void
{
    Queue::fake();

    $this->withoutMiddleware(
        EnsureAiServiceKey::class
    );

    $documentId = $this->createIndexedDocument(
        'teacher-001'
    );

    // Simulate a document whose indexing is not complete.
    DB::table('ai_curriculum_documents')
        ->where('id', $documentId)
        ->update([
            'indexing_status' => 'pending',
            'indexed_at' => null,
            'updated_at' => now(),
        ]);

    $response = $this->postJson(
        "/api/v1/curriculum-documents/{$documentId}/exams",
        [
            'external_teacher_id' => 'teacher-001',
            'title' => 'اختبار قبل اكتمال الفهرسة',
            'lesson_title' => 'أجزاء الخلية',
            'difficulty' => 'medium',

            'question_types' => [
                'multiple_choice',
                'true_false',
            ],

            'question_count' => 4,
            'total_points' => 8,
            'duration_minutes' => 15,
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'curriculum_document_id',
        ])
        ->assertJsonPath(
            'errors.curriculum_document_id.0',
            'يجب اكتمال فهرسة الكتاب قبل إنشاء الاختبار.'
        );

    $this->assertDatabaseMissing('ai_exams', [
        'curriculum_document_id' => $documentId,
    ]);

    Queue::assertNotPushed(
        GenerateAiExamJob::class
    );
}
/**
 * Ensure a teacher cannot retrieve another teacher's exam.
 */
public function test_teacher_cannot_retrieve_another_teachers_exam(): void
{
    $this->withoutMiddleware(
        EnsureAiServiceKey::class
    );

    $documentId = $this->createIndexedDocument(
        'teacher-001'
    );

    $examId = (string) Str::ulid();

    DB::table('ai_exams')->insert([
        'id' => $examId,
        'curriculum_document_id' => $documentId,
        'external_teacher_id' => 'teacher-001',

        'title' => 'اختبار خاص بالمعلم الأول',
        'grade_level' => '5',
        'subject_name' => 'العلوم والحياة',
        'term' => 1,
        'curriculum_year' => '2025-2026',
        'lesson_title' => 'أجزاء الخلية',

        'difficulty' => 'medium',

        'question_types' => json_encode([
            'multiple_choice',
            'true_false',
        ]),

        'generation_prompt' => null,
        'question_count' => 4,
        'total_points' => 8,
        'duration_minutes' => 15,

        'ai_provider' => 'gemini',
        'ai_model' => 'gemini-3.5-flash',
        'generation_status' => 'ready',
        'generation_error' => null,
        'generated_at' => now(),

        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson(
        "/api/v1/ai-exams/{$examId}"
        . '?external_teacher_id=teacher-999'
    );

    $response->assertNotFound();
}
/**
 * Ensure the owner can publish a ready draft exam.
 */
public function test_teacher_can_publish_ready_exam(): void
{
    $this->withoutMiddleware(
        EnsureAiServiceKey::class
    );

    $examId = $this->createReadyDraftExam(
        'teacher-001'
    );

    $response = $this->postJson(
        "/api/v1/ai-exams/{$examId}/publish",
        [
            'external_teacher_id' => 'teacher-001',
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $examId
        )
        ->assertJsonPath(
            'data.publication_status',
            'published'
        );

    $this->assertNotNull(
        $response->json('data.published_at')
    );

    $this->assertDatabaseHas('ai_exams', [
        'id' => $examId,
        'external_teacher_id' => 'teacher-001',
        'generation_status' => 'ready',
        'publication_status' => 'published',
    ]);

    $publishedAt = DB::table('ai_exams')
        ->where('id', $examId)
        ->value('published_at');

    $this->assertNotNull($publishedAt);
}

/**
 * Ensure another teacher cannot publish the exam.
 */
public function test_teacher_cannot_publish_another_teachers_exam(): void
{
    $this->withoutMiddleware(
        EnsureAiServiceKey::class
    );

    $examId = $this->createReadyDraftExam(
        'teacher-001'
    );

    $response = $this->postJson(
        "/api/v1/ai-exams/{$examId}/publish",
        [
            'external_teacher_id' => 'teacher-999',
        ]
    );

    $response->assertNotFound();

    $this->assertDatabaseHas('ai_exams', [
        'id' => $examId,
        'external_teacher_id' => 'teacher-001',
        'publication_status' => 'draft',
        'published_at' => null,
    ]);
}
/**
 * Create a ready draft exam with one valid question.
 */
private function createReadyDraftExam(
    string $teacherId
): string {
    $documentId = $this->createIndexedDocument(
        $teacherId
    );

    $examId = (string) Str::ulid();
    $questionId = (string) Str::ulid();

    DB::table('ai_exams')->insert([
        'id' => $examId,
        'curriculum_document_id' => $documentId,
        'external_teacher_id' => $teacherId,

        'title' => 'اختبار جاهز للنشر',
        'grade_level' => '5',
        'subject_name' => 'العلوم والحياة',
        'term' => 1,
        'curriculum_year' => '2025-2026',
        'lesson_title' => 'أجزاء الخلية',

        'difficulty' => 'medium',

        'question_types' => json_encode([
            'true_false',
        ]),

        'generation_prompt' => null,
        'question_count' => 1,
        'total_points' => 2,
        'duration_minutes' => 10,

        'ai_provider' => 'gemini',
        'ai_model' => 'gemini-3.5-flash',

        'generation_status' => 'ready',
        'generation_error' => null,
        'generated_at' => now(),

        'publication_status' => 'draft',
        'published_at' => null,

        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ai_exam_questions')->insert([
        'id' => $questionId,
        'exam_id' => $examId,
        'type' => 'true_false',

        'question_text' =>
            'الخلية هي وحدة البناء الأساسية للكائن الحي.',

        'explanation' =>
            'العبارة صحيحة.',

        'source_reference' => 'صفحة 12',
        'position' => 1,
        'points' => 2,

        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('ai_exam_options')->insert([
        [
            'id' => (string) Str::ulid(),
            'question_id' => $questionId,
            'option_text' => 'صح',
            'is_correct' => true,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::ulid(),
            'question_id' => $questionId,
            'option_text' => 'خطأ',
            'is_correct' => false,
            'position' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    return $examId;
}
/**
 * Create an indexed curriculum document for tests.
 */
private function createIndexedDocument(
    string $teacherId
): string {
    $documentId = (string) Str::ulid();

    DB::table('ai_curriculum_documents')->insert([
        'id' => $documentId,
        'external_teacher_id' => $teacherId,

        'title' => 'كتاب العلوم للاختبار',
        'grade_level' => '5',
        'subject_name' => 'العلوم والحياة',
        'term' => 1,
        'curriculum_year' => '2025-2026',

        'original_filename' => 'test-science.pdf',
        'storage_disk' => 'local',
        'storage_path' => 'curriculum/test-science.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100000,

        'sha256' => hash(
            'sha256',
            $documentId
        ),

        'ai_provider' => 'gemini',

        'provider_file_id' =>
            'file-'.$documentId,

        'provider_vector_store_id' =>
            'store-'.$documentId,

        'provider_vector_store_document_id' =>
            'document-'.$documentId,

        'indexing_status' => 'indexed',
        'indexing_error' => null,
        'indexed_at' => now(),

        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $documentId;
}
}