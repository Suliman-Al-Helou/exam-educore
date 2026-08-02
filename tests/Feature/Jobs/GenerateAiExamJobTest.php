<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateAiExamJob;
use App\Models\AiExam;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GenerateAiExamJobTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Ensure the job generates and saves the exam successfully.
     */
    public function test_job_generates_and_saves_exam_successfully(): void
    {
        config()->set([
            'ai.providers.gemini.key' => 'test-gemini-key',

            'ai.providers.gemini.url' =>
                'https://gemini.test/v1beta',

            'ai.providers.gemini.model' =>
                'gemini-3.5-flash',
        ]);

        Http::fake([
            'https://gemini.test/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode(
                                        [
                                            'questions' => [
                                                [
                                                    'type' =>
                                                        'true_false',

                                                    'question_text' =>
                                                        'الخلية هي وحدة البناء الأساسية للكائن الحي.',

                                                    'explanation' =>
                                                        'العبارة صحيحة لأن أجسام الكائنات الحية تتكون من خلايا.',

                                                    'source_reference' =>
                                                        'صفحة 12',

                                                    'options' => [
                                                        [
                                                            'option_text' =>
                                                                'صح',

                                                            'is_correct' =>
                                                                true,
                                                        ],
                                                        [
                                                            'option_text' =>
                                                                'خطأ',

                                                            'is_correct' =>
                                                                false,
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        JSON_UNESCAPED_UNICODE
                                        | JSON_THROW_ON_ERROR
                                    ),
                                ],
                            ],
                        ],

                        'finishReason' => 'STOP',
                    ],
                ],
            ], 200),
        ]);

        $documentId = $this->createIndexedDocument();

        $examId = $this->createPendingExam(
            $documentId
        );

        $job = new GenerateAiExamJob($examId);

        // Execute the job with dependencies from Laravel container.
        app()->call([
            $job,
            'handle',
        ]);

        $exam = AiExam::query()
            ->with('questions.options')
            ->findOrFail($examId);

        $this->assertSame(
            'ready',
            $exam->generation_status->value
        );

        $this->assertSame(
            'gemini-3.5-flash',
            $exam->ai_model
        );

        $this->assertNull(
            $exam->generation_error
        );

        $this->assertNotNull(
            $exam->generated_at
        );

        $this->assertCount(
            1,
            $exam->questions
        );

        $question = $exam->questions->first();

        $this->assertNotNull($question);

        $this->assertSame(
            'true_false',
            $question->type->value
        );

        $this->assertSame(
            'الخلية هي وحدة البناء الأساسية للكائن الحي.',
            $question->question_text
        );

        $this->assertEquals(
            2.00,
            (float) $question->points
        );

        $this->assertCount(
            2,
            $question->options
        );

        $this->assertSame(
            1,
            $question->options
                ->where('is_correct', true)
                ->count()
        );

        $this->assertDatabaseHas(
            'ai_exam_options',
            [
                'question_id' => $question->id,
                'option_text' => 'صح',
                'is_correct' => true,
            ]
        );

        Http::assertSentCount(1);
    }

    /**
     * Create an indexed document for the job test.
     */
    private function createIndexedDocument(): string
    {
        $documentId = (string) Str::ulid();

        DB::table(
            'ai_curriculum_documents'
        )->insert([
            'id' => $documentId,

            'external_teacher_id' =>
                'teacher-001',

            'title' =>
                'كتاب العلوم للاختبار',

            'grade_level' => '5',

            'subject_name' =>
                'العلوم والحياة',

            'term' => 1,

            'curriculum_year' =>
                '2025-2026',

            'original_filename' =>
                'science-test.pdf',

            'storage_disk' => 'local',

            'storage_path' =>
                'curriculum/science-test.pdf',

            'mime_type' =>
                'application/pdf',

            'size_bytes' => 100000,

            'sha256' => hash(
                'sha256',
                $documentId
            ),

            'ai_provider' => 'gemini',

            'provider_file_id' =>
                'test-file-'.$documentId,

            'provider_vector_store_id' =>
                'fileSearchStores/test-store',

            'provider_vector_store_document_id' =>
                'fileSearchStores/test-store/documents/'
                .$documentId,

            'indexing_status' => 'indexed',

            'indexing_error' => null,

            'indexed_at' => now(),

            'created_at' => now(),

            'updated_at' => now(),
        ]);

        return $documentId;
    }

    /**
     * Create a pending exam for the job test.
     */
    private function createPendingExam(
        string $documentId
    ): string {
        $examId = (string) Str::ulid();

        DB::table('ai_exams')->insert([
            'id' => $examId,

            'curriculum_document_id' =>
                $documentId,

            'external_teacher_id' =>
                'teacher-001',

            'title' =>
                'اختبار Job آلي',

            'grade_level' => '5',

            'subject_name' =>
                'العلوم والحياة',

            'term' => 1,

            'curriculum_year' =>
                '2025-2026',

            'lesson_title' =>
                'أجزاء الخلية',

            'difficulty' => 'medium',

            'question_types' => json_encode(
                [
                    'true_false',
                ],
                JSON_THROW_ON_ERROR
            ),

            'generation_prompt' => null,

            'question_count' => 1,

            'total_points' => 2,

            'duration_minutes' => 10,

            'ai_provider' => 'gemini',

            'ai_model' => null,

            'generation_status' =>
                'pending',

            'generation_error' => null,

            'generated_at' => null,

            'created_at' => now(),

            'updated_at' => now(),
        ]);

        return $examId;
    }
}