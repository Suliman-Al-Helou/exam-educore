<?php

namespace App\Jobs;

use App\Enums\AiDocumentIndexingStatus;
use App\Models\AiCurriculumDocument;
use App\Services\Ai\AiCurriculumIndexer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

final class IndexAiCurriculumDocument implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Maximum execution attempts.
     */
    public int $tries = 3;

    /**
     * Maximum execution time in seconds.
     */
    public int $timeout = 600;

    /**
     * Mark the job as failed when it times out.
     */
    public bool $failOnTimeout = true;

    /**
     * Keep the unique lock for 30 minutes.
     */
    public int $uniqueFor = 1800;

    public function __construct(
        public string $documentId,
    ) {}

    /**
     * Prevent duplicate jobs for the same document.
     */
    public function uniqueId(): string
    {
        return $this->documentId;
    }

    /**
     * Wait before retrying: 1 minute, then 3 minutes.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 180];
    }

    /**
     * Execute the indexing job.
     */
    public function handle(
        AiCurriculumIndexer $indexer
    ): void {
        $document = AiCurriculumDocument::query()
            ->findOrFail($this->documentId);

        $indexer->index($document);
    }

    /**
     * Store the final failure state.
     */
    public function failed(?Throwable $exception): void
    {
        $document = AiCurriculumDocument::query()
            ->find($this->documentId);

        if ($document === null) {
            return;
        }

        $document->update([
            'indexing_status' => AiDocumentIndexingStatus::Failed,
            'indexing_error' => Str::limit(
                $exception?->getMessage()
                    ?? 'Curriculum indexing failed.',
                5000,
                ''
            ),
            'indexed_at' => null,
        ]);
    }
}

// ماذا يفعل الـJob؟
// يستقبل رقم الكتاب فقط.
// يجلب الكتاب من MySQL عند بدء التنفيذ.
// يشغّل AiCurriculumIndexer.
// يحاول 3 مرات عند انقطاع الشبكة.
// يمنع فهرسة نفس الكتاب بالتزامن.
// عند الفشل النهائي يسجل failed ورسالة الخطأ.