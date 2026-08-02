<?php

use App\Http\Controllers\Api\V1\AiCurriculumDocumentController;
use App\Http\Controllers\Api\V1\AiExamController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('ai.service')
    ->group(function (): void {
        Route::get('/health', function () {
            return response()->json([
                'data' => [
                    'status' => 'ok',
                ],
            ]);
            
        });

        Route::get(
            '/curriculum-documents',
            [AiCurriculumDocumentController::class, 'listForTeacher']
        );

        Route::post(
            '/curriculum-documents',
            [AiCurriculumDocumentController::class, 'store']
        );

        Route::post(
            '/curriculum-documents/{document}/index',
            [AiCurriculumDocumentController::class, 'index']
        );

        Route::post(
            '/curriculum-documents/{document}/exams',
            [AiExamController::class, 'store']
        );

        Route::get(
            '/ai-exams/{exam}',
            [AiExamController::class, 'show']
        );

        Route::post(
            '/ai-exams/{exam}/publish',
            [AiExamController::class, 'publish']
        );
        Route::patch(
    '/ai-exams/{exam}/questions/{question}',
    [AiExamController::class, 'updateQuestion']
);
Route::delete(
    '/ai-exams/{exam}/questions/{question}',
    [AiExamController::class, 'deleteQuestion']
);
Route::post(
    '/ai-exams/{exam}/questions',
    [AiExamController::class, 'addQuestion']
);
Route::patch(
    '/ai-exams/{exam}',
    [AiExamController::class, 'update']
);
Route::post(
    '/ai-exams/{exam}/assignments',
    [AiExamController::class, 'assign']
);
Route::get(
    '/ai-exam-assignments/{assignment}/student-exam',
    [AiExamController::class, 'showStudentExam']
);
Route::post(
    '/ai-exam-assignments/{assignment}/attempts',
    [AiExamController::class, 'startAttempt']
);
Route::put(
    '/ai-exam-attempts/{attempt}/answers/{question}',
    [AiExamController::class, 'saveAttemptAnswer']
);
Route::post(
    '/ai-exam-attempts/{attempt}/submit',
    [AiExamController::class, 'submitAttempt']
);
Route::get(
    '/ai-exam-attempts/{attempt}/result',
    [AiExamController::class, 'showAttemptResult']
);
Route::get(
    '/ai-exam-assignments/{assignment}/teacher-report',
    [AiExamController::class, 'showAssignmentReport']
);
    });

   