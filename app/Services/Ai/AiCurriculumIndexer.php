<?php

namespace App\Services\Ai;

use App\Enums\AiDocumentIndexingStatus;
use App\Models\AiCurriculumDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class AiCurriculumIndexer
{
    public function __construct(
        private readonly GeminiFileSearchUploader $uploader
    ) {
    }

    public function index(
        AiCurriculumDocument $document
    ): AiCurriculumDocument {
        // Avoid uploading an already indexed document again.
        if (
            $document->indexing_status
            === AiDocumentIndexingStatus::Indexed
        ) {
            return $document;
        }

        $provider = strtolower(
            trim((string) config('ai_curriculum.provider'))
        );

        $storeId = trim(
            (string) config('ai_curriculum.vector_store_id')
        );

        $document->update([
            'ai_provider' => $provider,
            'provider_vector_store_id' => $storeId,
            'indexing_status' => AiDocumentIndexingStatus::Processing,
            'indexing_error' => null,
            'indexed_at' => null,
        ]);

        try {
            if ($provider !== 'gemini') {
                throw new RuntimeException(
                    'Gemini is the only supported curriculum provider.'
                );
            }

            if ($storeId === '') {
                throw new RuntimeException(
                    'AI curriculum vector store ID is not configured.'
                );
            }

            $disk = Storage::disk(
                $document->storage_disk
            );

            if (! $disk->exists($document->storage_path)) {
                throw new RuntimeException(
                    'Curriculum PDF does not exist in local storage.'
                );
            }

            $absolutePath = $disk->path(
                $document->storage_path
            );

            $providerDocumentId = $this->uploader->upload(
                document: $document,
                absolutePath: $absolutePath,
                storeId: $storeId
            );

            $document->update([
                // Direct upload does not create a temporary Files API ID.
                'provider_file_id' => null,
                'provider_vector_store_document_id' => $providerDocumentId,
                'indexing_status' => AiDocumentIndexingStatus::Indexed,
                'indexing_error' => null,
                'indexed_at' => now(),
            ]);

            return $document->refresh();
        } catch (Throwable $exception) {
            $document->update([
                'indexing_status' => AiDocumentIndexingStatus::Failed,
                'indexing_error' => Str::limit(
                    $exception->getMessage(),
                    5000,
                    ''
                ),
                'indexed_at' => null,
            ]);

            throw $exception;
        }
    }
}