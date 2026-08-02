<?php

namespace Tests\Feature\Services\Ai;

use App\Enums\AiDocumentIndexingStatus;
use App\Models\AiCurriculumDocument;
use App\Services\Ai\AiCurriculumIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiCurriculumIndexerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uploads_a_curriculum_document_and_marks_it_as_indexed(): void
    {
        config()->set(
            'ai.providers.gemini.key',
            'test-gemini-key'
        );

        config()->set(
            'ai_curriculum.provider',
            'gemini'
        );

        config()->set(
            'ai_curriculum.vector_store_id',
            'fileSearchStores/test-store'
        );

        Storage::fake('local');

        $pdfContents = "%PDF-1.4\nTest curriculum PDF";
        $storagePath = 'tests/curriculum.pdf';

        Storage::disk('local')->put(
            $storagePath,
            $pdfContents
        );

        $providerDocumentId =
            'fileSearchStores/test-store/documents/test-document';

        Http::fakeSequence()
            // Start resumable upload.
            ->push(
                [],
                200,
                [
                    'X-Goog-Upload-URL' =>
                        'https://uploads.example.test/session',
                ]
            )
            // Finalize file upload.
            ->push(
                [
                    'name' =>
                        'fileSearchStores/test-store/upload/operations/test-operation',
                    'response' => [
                        'documentName' => $providerDocumentId,
                    ],
                ],
                200
            )
            // Confirm that the document is searchable.
            ->push(
                [
                    'name' => $providerDocumentId,
                    'state' => 'STATE_ACTIVE',
                ],
                200
            );

        $document = new AiCurriculumDocument();

        $document->forceFill([
            'title' => 'كتاب الرياضيات التجريبي',
            'grade_level' => '4',
            'subject_name' => 'الرياضيات',
            'term' => 1,
            'curriculum_year' => '2025-2026',
            'original_filename' => 'test-curriculum.pdf',
            'storage_disk' => 'local',
            'storage_path' => $storagePath,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdfContents),
            'sha256' => hash('sha256', $pdfContents),
            'ai_provider' => 'gemini',
            'indexing_status' =>
                AiDocumentIndexingStatus::Pending,
        ]);

        $document->save();

        $result = app(AiCurriculumIndexer::class)
            ->index($document);

        $this->assertSame(
            AiDocumentIndexingStatus::Indexed,
            $result->indexing_status
        );

        $this->assertSame(
            $providerDocumentId,
            $result->provider_vector_store_document_id
        );

        $this->assertNull($result->provider_file_id);
        $this->assertNull($result->indexing_error);
        $this->assertNotNull($result->indexed_at);

        $this->assertDatabaseHas(
            'ai_curriculum_documents',
            [
                'id' => $document->id,
                'indexing_status' => 'indexed',
                'provider_vector_store_document_id' =>
                    $providerDocumentId,
            ]
        );

        Http::assertSentCount(3);

        Http::assertSent(
            fn (Request $request): bool =>
                $request->method() === 'POST'
                && str_contains(
                    $request->url(),
                    'uploadToFileSearchStore'
                )
                && $request->hasHeader(
                    'X-Goog-Upload-Protocol',
                    'resumable'
                )
        );
    }
}