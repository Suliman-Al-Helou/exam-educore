<?php

namespace App\Services\Ai;

use App\Models\AiCurriculumDocument;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiFileSearchUploader
{
    private const BASE_URL =
        'https://generativelanguage.googleapis.com';

    private const CONNECT_TIMEOUT_SECONDS = 15;

    private const UPLOAD_TIMEOUT_SECONDS = 240;

    private const MAX_INDEXING_WAIT_SECONDS = 300;

    private const POLL_INTERVAL_SECONDS = 2;

    /**
     * Upload a curriculum PDF directly to Gemini File Search.
     *
     * Returns the full Gemini document name after it becomes active.
     */
    public function upload(
        AiCurriculumDocument $document,
        string $absolutePath,
        string $storeId
    ): string {
        $apiKey = trim(
            (string) config('ai.providers.gemini.key')
        );

        if ($apiKey === '') {
            throw new RuntimeException(
                'Gemini API key is not configured.'
            );
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException(
                'Curriculum PDF is not readable.'
            );
        }

        $fileSize = filesize($absolutePath);

        if ($fileSize === false || $fileSize <= 0) {
            throw new RuntimeException(
                'Curriculum PDF is empty or its size cannot be read.'
            );
        }

        $storeId = $this->normalizeStoreId($storeId);

        $mimeType = trim((string) $document->mime_type);

        if ($mimeType === '') {
            $mimeType = 'application/pdf';
        }

        $startResponse = Http::acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(60)
            ->withQueryParameters([
                'key' => $apiKey,
            ])
            ->withHeaders([
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $fileSize,
                'X-Goog-Upload-Header-Content-Type' => $mimeType,
            ])
            ->post(
                self::BASE_URL
                .'/upload/v1beta/'
                .$storeId
                .':uploadToFileSearchStore',
                [
                    'displayName' => $document->title,
                    'customMetadata' => $this->metadata($document),
                ]
            );

        $startResponse->throw();

        $uploadUrl = trim(
            (string) $startResponse->header('X-Goog-Upload-URL')
        );

        if ($uploadUrl === '') {
            throw new RuntimeException(
                'Gemini did not return a resumable upload URL.'
            );
        }

        $stream = fopen($absolutePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException(
                'Curriculum PDF could not be opened for upload.'
            );
        }

        try {
            $finishResponse = Http::acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::UPLOAD_TIMEOUT_SECONDS)
                ->withHeaders([
                    'Content-Length' => (string) $fileSize,
                    'X-Goog-Upload-Offset' => '0',
                    'X-Goog-Upload-Command' => 'upload, finalize',
                ])
                ->withBody($stream, $mimeType)
                ->post($uploadUrl);
        } finally {
            fclose($stream);
        }

        $finishResponse->throw();

        $operation = $finishResponse->json();

        if (! is_array($operation)) {
            throw new RuntimeException(
                'Gemini returned an invalid upload response.'
            );
        }

        $deadline = microtime(true)
            + self::MAX_INDEXING_WAIT_SECONDS;

        $documentName = $this->resolveDocumentName(
            operation: $operation,
            apiKey: $apiKey,
            deadline: $deadline
        );

        $this->waitUntilActive(
            documentName: $documentName,
            apiKey: $apiKey,
            deadline: $deadline
        );

        return $documentName;
    }

    /**
     * Wait for the upload operation to return a document name.
     */
    private function resolveDocumentName(
        array $operation,
        string $apiKey,
        float $deadline
    ): string {
        while (true) {
            $errorMessage = trim(
                (string) data_get($operation, 'error.message')
            );

            if ($errorMessage !== '') {
                throw new RuntimeException(
                    'Gemini upload failed: '.$errorMessage
                );
            }

            $documentName = trim(
                (string) data_get(
                    $operation,
                    'response.documentName'
                )
            );

            if ($documentName !== '') {
                return $documentName;
            }

            $operationName = trim(
                (string) data_get($operation, 'name')
            );

            if ($operationName === '') {
                throw new RuntimeException(
                    'Gemini did not return an operation name.'
                );
            }

            $this->waitBeforeNextPoll(
                deadline: $deadline,
                timeoutMessage: 'Gemini upload operation timed out.'
            );

            $response = $this->authenticatedGet(
                path: '/v1beta/'.$operationName,
                apiKey: $apiKey
            );

            $response->throw();

            $operation = $response->json();

            if (! is_array($operation)) {
                throw new RuntimeException(
                    'Gemini returned an invalid operation response.'
                );
            }
        }
    }

    /**
     * Wait until Gemini reports that the document is searchable.
     */
    private function waitUntilActive(
        string $documentName,
        string $apiKey,
        float $deadline
    ): void {
        while (true) {
            $response = $this->authenticatedGet(
                path: '/v1beta/'.$documentName,
                apiKey: $apiKey
            );

            if ($response->status() === 404) {
                $this->waitBeforeNextPoll(
                    deadline: $deadline,
                    timeoutMessage: 'Gemini document was not created in time.'
                );

                continue;
            }

            $response->throw();

            $state = trim(
                (string) $response->json('state')
            );

            if ($state === 'STATE_ACTIVE') {
                return;
            }

            if ($state === 'STATE_FAILED') {
                throw new RuntimeException(
                    'Gemini failed to index the curriculum document.'
                );
            }

            $this->waitBeforeNextPoll(
                deadline: $deadline,
                timeoutMessage: 'Gemini document indexing timed out.'
            );
        }
    }

    /**
     * Send an authenticated GET request to Gemini.
     */
    private function authenticatedGet(
        string $path,
        string $apiKey
    ): Response {
        return Http::acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(60)
            ->withQueryParameters([
                'key' => $apiKey,
            ])
            ->get(self::BASE_URL.$path);
    }

    /**
     * Pause between status requests without exceeding the deadline.
     */
    private function waitBeforeNextPoll(
        float $deadline,
        string $timeoutMessage
    ): void {
        if (
            microtime(true) + self::POLL_INTERVAL_SECONDS
            > $deadline
        ) {
            throw new RuntimeException($timeoutMessage);
        }

        sleep(self::POLL_INTERVAL_SECONDS);
    }

    /**
     * Metadata used later to select the correct curriculum.
     */
    private function metadata(
        AiCurriculumDocument $document
    ): array {
        return [
            [
                'key' => 'document_id',
                'stringValue' => $document->id,
            ],
            [
                'key' => 'grade_level',
                'numericValue' => (int) $document->grade_level,
            ],
            [
                'key' => 'subject_name',
                'stringValue' => $document->subject_name,
            ],
            [
                'key' => 'term',
                'numericValue' => (int) $document->term,
            ],
            [
                'key' => 'curriculum_year',
                'stringValue' => $document->curriculum_year,
            ],
        ];
    }

    /**
     * Ensure the store ID contains the required Gemini prefix.
     */
    private function normalizeStoreId(
        string $storeId
    ): string {
        $storeId = trim($storeId);

        if ($storeId === '') {
            throw new RuntimeException(
                'Gemini File Search store ID is not configured.'
            );
        }

        return str_starts_with(
            $storeId,
            'fileSearchStores/'
        )
            ? $storeId
            : 'fileSearchStores/'.$storeId;
    }
}