<?php

namespace App\Jobs;

use App\Actions\AiExams\SaveGeneratedAiExam;
use App\Enums\AiDocumentIndexingStatus;
use App\Enums\AiExamGenerationStatus;
use App\Models\AiExam;
use App\Services\Ai\Exams\GeminiExamGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class GenerateAiExamJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Maximum execution attempts.
     */
    public int $tries = 3;

    /**
     * Maximum execution time in seconds.
     */
    public int $timeout = 300;

    /**
     * Mark the job as failed when it times out.
     */
    public bool $failOnTimeout = true;

    /**
     * Keep the unique lock for 30 minutes.
     */
    public int $uniqueFor = 1800;

    public function __construct(
        public string $examId,
    ) {}

    /**
     * Prevent duplicate generation jobs for the same exam.
     */
    public function uniqueId(): string
    {
        return $this->examId;
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
     * Generate and save the AI exam.
     */
    public function handle(
        GeminiExamGenerator $generator,
        SaveGeneratedAiExam $saveGeneratedExam
    ): void {
        $exam = AiExam::query()
            ->with('curriculumDocument')
            ->findOrFail($this->examId);

        // The exam was already completed successfully.
        if (
            $exam->generation_status
            === AiExamGenerationStatus::Ready
        ) {
            return;
        }

        // Only pending or generating exams can continue.
        if (
            ! in_array(
                $exam->generation_status,
                [
                    AiExamGenerationStatus::Pending,
                    AiExamGenerationStatus::Generating,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'AI exam is not in a generatable state.'
            );
        }

        $document = $exam->curriculumDocument;

        if ($document === null) {
            throw new RuntimeException(
                'The curriculum document could not be found.'
            );
        }

        if (
            $document->indexing_status
            !== AiDocumentIndexingStatus::Indexed
        ) {
            throw new RuntimeException(
                'The curriculum document is not indexed.'
            );
        }

        // Mark the exam as currently being generated.
        if (
            $exam->generation_status
            === AiExamGenerationStatus::Pending
        ) {
            $exam->update([
                'generation_status' =>
                    AiExamGenerationStatus::Generating,

                'generation_error' => null,

                'generated_at' => null,
            ]);
        }

        // Generate structured questions from Gemini.
        $generatedData = $generator->generate(
            $exam->fresh([
                'curriculumDocument',
            ])
        );

        // Save questions, options, points, and mark the exam ready.
        $saveGeneratedExam->execute(
            $exam->fresh(),
            $generatedData
        );
    }

    /**
     * Store the final generation failure.
     */
    public function failed(
        ?Throwable $exception
    ): void {
        $exam = AiExam::query()
            ->find($this->examId);

        if ($exam === null) {
            return;
        }

        // Do not overwrite a successfully completed exam.
        if (
            $exam->generation_status
            === AiExamGenerationStatus::Ready
        ) {
            return;
        }

        $exam->update([
            'generation_status' =>
                AiExamGenerationStatus::Failed,

            'generation_error' => Str::limit(
                $exception?->getMessage()
                    ?? 'AI exam generation failed.',
                5000,
                ''
            ),

            'generated_at' => null,
        ]);
    }
}
// وظيفة الملف
// جلب الاختبار بواسطة ID.
// التأكد أن حالته تسمح بالتوليد.
// إعادة التحقق أن الكتاب ما زال مفهرسًا.
// تحويل الحالة إلى generating.
// منع وجود أكثر من Job للاختبار نفسه.
// تسجيل الحالة failed عند الفشل النهائي.