<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AiDocumentIndexingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiCurriculumDocumentRequest;
use App\Http\Resources\AiCurriculumDocumentResource;
use App\Jobs\IndexAiCurriculumDocument;
use App\Models\AiCurriculumDocument;
use App\Services\Ai\AiCurriculumDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\ListAiCurriculumDocumentsRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
class AiCurriculumDocumentController extends Controller
{
    /**
 * List the current teacher's books.
 */
public function listForTeacher(
    ListAiCurriculumDocumentsRequest $request
): AnonymousResourceCollection {
    $validated = $request->validated();

    $documents = AiCurriculumDocument::query()
        ->where(
            'external_teacher_id',
            $validated['external_teacher_id']
        )
        ->when(
            $validated['status'] ?? null,
            function ($query, string $status): void {
                $query->where(
                    'indexing_status',
                    $status
                );
            }
        )
        ->when(
            $validated['search'] ?? null,
            function ($query, string $search): void {
                $query->where(
                    function ($query) use ($search): void {
                        $query
                            ->where(
                                'title',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'subject_name',
                                'like',
                                "%{$search}%"
                            );
                    }
                );
            }
        )
        ->latest('created_at')
        ->paginate(
            $validated['per_page'] ?? 15
        )
        ->withQueryString();

    return AiCurriculumDocumentResource::collection(
        $documents
    );
}
    /**
     * Store a curriculum PDF locally.
     */
    public function store(
        StoreAiCurriculumDocumentRequest $request,
        AiCurriculumDocumentService $service
    ): JsonResponse {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => ['ملف الكتاب مطلوب.'],
            ]);
        }

        $document = $service->store(
            file: $file,
            attributes: $request->safe()->except(['file'])
        );

IndexAiCurriculumDocument::dispatch($document->id)
    ->onQueue('ai-indexing');

return $this->documentResponse(
    document: $document->refresh(),
    message: 'Document uploaded and indexing has been queued.',
    status: Response::HTTP_CREATED,
);
    }

    /**
     * Queue the document for AI indexing.
     */
    public function index(
        AiCurriculumDocument $document
    ): JsonResponse {
        $document->refresh();

        if (
            $document->indexing_status
            === AiDocumentIndexingStatus::Indexed
        ) {
            return $this->documentResponse(
                document: $document,
                message: 'Document is already indexed.',
                status: Response::HTTP_OK,
            );
        }

        if (
            $document->indexing_status
            === AiDocumentIndexingStatus::Processing
        ) {
            return $this->documentResponse(
                document: $document,
                message: 'Document indexing is already in progress.',
                status: Response::HTTP_ACCEPTED,
            );
        }

        // Reset failed documents before retrying.
        $document->update([
            'indexing_status' => AiDocumentIndexingStatus::Pending,
            'indexing_error' => null,
            'indexed_at' => null,
        ]);

        IndexAiCurriculumDocument::dispatch($document->id)
            ->onQueue('ai-indexing');

        return $this->documentResponse(
            document: $document->refresh(),
            message: 'Document indexing has been queued.',
            status: Response::HTTP_ACCEPTED,
        );
    }

    /**
     * Build a consistent API response.
     */
    private function documentResponse(
        AiCurriculumDocument $document,
        string $message,
        int $status
    ): JsonResponse {
        return (new AiCurriculumDocumentResource($document))
            ->additional([
                'meta' => [
                    'message' => $message,
                ],
            ])
            ->response()
            ->setStatusCode($status);
    }
}