<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AiExams\CreatePendingAiExam;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateAiExamRequest;
use App\Http\Resources\AiExamResource;
use App\Models\AiCurriculumDocument;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Jobs\GenerateAiExamJob;
use App\Http\Resources\AiExamDetailsResource;
use App\Models\AiExam;
use Illuminate\Http\Request;
use App\Actions\AiExams\PublishAiExam;
use App\Http\Requests\Api\V1\PublishAiExamRequest;
use App\Actions\AiExams\UpdateAiExamQuestion;
use App\Http\Requests\Api\V1\UpdateAiExamQuestionRequest;
use App\Actions\AiExams\DeleteAiExamQuestion;
use App\Http\Requests\Api\V1\DeleteAiExamQuestionRequest;
use App\Actions\AiExams\AddAiExamQuestion;
use App\Http\Requests\Api\V1\StoreAiExamQuestionRequest;
use App\Actions\AiExams\UpdateAiExam;
use App\Http\Requests\Api\V1\UpdateAiExamRequest;
use App\Actions\AiExams\CreateAiExamAssignment;
use App\Http\Requests\Api\V1\StoreAiExamAssignmentRequest;
use App\Http\Resources\AiExamAssignmentResource;
use App\Actions\AiExams\GetStudentAiExam;
use App\Http\Requests\Api\V1\ShowStudentAiExamRequest;
use App\Http\Resources\StudentAiExamResource;
use App\Models\AiExamAssignment;
use App\Actions\AiExams\StartAiExamAttempt;
use App\Http\Requests\Api\V1\StartAiExamAttemptRequest;
use App\Http\Resources\StudentAiExamAttemptResource;
use App\Actions\AiExams\SaveAiExamAttemptAnswer;
use App\Http\Requests\Api\V1\SaveAiExamAttemptAnswerRequest;
use App\Models\AiExamAttempt;
use App\Actions\AiExams\SubmitAiExamAttempt;
use App\Http\Requests\Api\V1\SubmitAiExamAttemptRequest;
use App\Http\Resources\StudentAiExamResultResource;
use App\Actions\AiExams\GetStudentAiExamResult;
use App\Http\Requests\Api\V1\ShowStudentAiExamResultRequest;
use App\Actions\AiExams\GetTeacherAiExamAssignmentReport;
use App\Http\Requests\Api\V1\ShowTeacherAiExamAssignmentReportRequest;
use App\Http\Resources\TeacherAiExamAssignmentReportResource;
class AiExamController extends Controller
{
    /**
     * Create a pending AI exam.
     */
    public function store(
        GenerateAiExamRequest $request,
        AiCurriculumDocument $document,
        CreatePendingAiExam $action
    ): JsonResponse {
        $validated = $request->validated();

        $exam = $action->execute(
            document: $document,
            externalTeacherId:
                $validated['external_teacher_id'],
            data: $request->safe()->except([
                'external_teacher_id',
            ]),
        );
        GenerateAiExamJob::dispatch(
    $exam->id
)->afterCommit();

        return (new AiExamResource($exam->refresh()))
            ->additional([
                'meta' => [
                    'message' =>
                        'تم إنشاء طلب توليد الاختبار',
                ],
            ])
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
    /**
 * Display the AI exam status and generated questions.
 */
public function show(
    Request $request,
    AiExam $exam
): AiExamDetailsResource {
    $validated = $request->validate([
        'external_teacher_id' => [
            'required',
            'string',
            'max:100',
        ],
    ]);

    // Prevent teachers from reading another teacher's exam.
    abort_unless(
        $exam->external_teacher_id
            === $validated['external_teacher_id'],
        404
    );

    // Load questions and options in their saved order.
    $exam->load([
        'questions' => fn ($query) =>
            $query->orderBy('position'),

        'questions.options' => fn ($query) =>
            $query->orderBy('position'),
    ]);

    return new AiExamDetailsResource($exam);
}

public function publish(
    PublishAiExamRequest $request,
    AiExam $exam,
    PublishAiExam $publishAiExam
): AiExamDetailsResource {
    $validated = $request->validated();

    $publishedExam = $publishAiExam->execute(
        exam: $exam,
        externalTeacherId: $validated['external_teacher_id'],
    );

    return new AiExamDetailsResource($publishedExam);
}
// وظيفة الدالة

// تستقبل:

// {
//   "external_teacher_id": "teacher-001"
// }

// ثم:

// تتحقق من البيانات
// → ترسل الاختبار إلى PublishAiExam
// → يتحقق الـ Action من الملكية وصحة الاختبار
// → ينشر الاختبار
// → يرجع تفاصيل الاختبار بعد النشر

public function updateQuestion(
    UpdateAiExamQuestionRequest $request,
    AiExam $exam,
    string $question,
    UpdateAiExamQuestion $updateAiExamQuestion
): AiExamDetailsResource {
    $validated = $request->validated();

    $updatedExam = $updateAiExamQuestion->execute(
        exam: $exam,
        questionId: $question,
        externalTeacherId: $validated['external_teacher_id'],
        data: $validated,
    );

    return new AiExamDetailsResource($updatedExam);
}

public function deleteQuestion(
    DeleteAiExamQuestionRequest $request,
    AiExam $exam,
    string $question,
    DeleteAiExamQuestion $deleteAiExamQuestion
): AiExamDetailsResource {
    $validated = $request->validated();

    $updatedExam = $deleteAiExamQuestion->execute(
        exam: $exam,
        questionId: $question,
        externalTeacherId: $validated['external_teacher_id'],
    );

    return new AiExamDetailsResource($updatedExam);
}
public function addQuestion(
    StoreAiExamQuestionRequest $request,
    AiExam $exam,
    AddAiExamQuestion $addAiExamQuestion
): AiExamDetailsResource {
    $validated = $request->validated();

    $updatedExam = $addAiExamQuestion->execute(
        exam: $exam,
        externalTeacherId: $validated['external_teacher_id'],
        data: $validated,
    );

    return new AiExamDetailsResource($updatedExam);
}

public function update(
    UpdateAiExamRequest $request,
    AiExam $exam,
    UpdateAiExam $updateAiExam
): AiExamDetailsResource {
    $validated = $request->validated();

    $updatedExam = $updateAiExam->execute(
        exam: $exam,
        externalTeacherId: $validated['external_teacher_id'],
        data: $validated,
    );

    return new AiExamDetailsResource($updatedExam);
}
public function assign(
    StoreAiExamAssignmentRequest $request,
    AiExam $exam,
    CreateAiExamAssignment $createAiExamAssignment
): JsonResponse {
    $validated = $request->validated();

    $assignment = $createAiExamAssignment->execute(
        exam: $exam,
        externalTeacherId:
            $validated['external_teacher_id'],
        data: $validated,
    );

    return (new AiExamAssignmentResource(
        $assignment
    ))
        ->response()
        ->setStatusCode(201);
}
public function showStudentExam(
    ShowStudentAiExamRequest $request,
    AiExamAssignment $assignment,
    GetStudentAiExam $getStudentAiExam
): StudentAiExamResource {
    $validated = $request->validated();

    $studentExam = $getStudentAiExam->execute(
        assignment: $assignment,
        externalStudentId:
            $validated['external_student_id'],
    );

    return new StudentAiExamResource(
        $studentExam
    );
}
public function startAttempt(
    StartAiExamAttemptRequest $request,
    AiExamAssignment $assignment,
    StartAiExamAttempt $startAiExamAttempt
): JsonResponse {
    $validated = $request->validated();

    $result = $startAiExamAttempt->execute(
        assignment: $assignment,

        externalStudentId:
            $validated['external_student_id'],
    );

    return (
        new StudentAiExamAttemptResource(
            $result['attempt']
        )
    )
        ->response()
        ->setStatusCode(
            $result['created'] ? 201 : 200
        );
}
public function saveAttemptAnswer(
    SaveAiExamAttemptAnswerRequest $request,
    AiExamAttempt $attempt,
    string $question,
    SaveAiExamAttemptAnswer $saveAiExamAttemptAnswer
): StudentAiExamAttemptResource {
    $validated = $request->validated();

    $updatedAttempt =
        $saveAiExamAttemptAnswer->execute(
            attempt: $attempt,
            questionId: $question,

            externalStudentId:
                $validated['external_student_id'],

            data: $validated,
        );

    return new StudentAiExamAttemptResource(
        $updatedAttempt
    );
}
public function submitAttempt(
    SubmitAiExamAttemptRequest $request,
    AiExamAttempt $attempt,
    SubmitAiExamAttempt $submitAiExamAttempt
): StudentAiExamResultResource {
    $validated = $request->validated();

    $result = $submitAiExamAttempt->execute(
        attempt: $attempt,

        externalStudentId:
            $validated['external_student_id'],
    );

    return new StudentAiExamResultResource(
        $result['attempt']
    );
}
public function showAttemptResult(
    ShowStudentAiExamResultRequest $request,
    AiExamAttempt $attempt,
    GetStudentAiExamResult $getStudentAiExamResult
): StudentAiExamResultResource {
    $validated = $request->validated();

    $studentResult =
        $getStudentAiExamResult->execute(
            attempt: $attempt,

            externalStudentId:
                $validated['external_student_id'],
        );

    return new StudentAiExamResultResource(
        $studentResult
    );
}
public function showAssignmentReport(
    ShowTeacherAiExamAssignmentReportRequest $request,
    AiExamAssignment $assignment,
    GetTeacherAiExamAssignmentReport $getReport
): TeacherAiExamAssignmentReportResource {
    $validated = $request->validated();

    $report = $getReport->execute(
        assignment: $assignment,

        externalTeacherId:
            $validated['external_teacher_id'],
    );

    return new TeacherAiExamAssignmentReportResource(
        $report
    );
}
}


// وظيفته
// استقبال الطلب المتحقق منه.
// الحصول على الكتاب عبر Route Model Binding.
// استدعاء CreatePendingAiExam.
// إرجاع الاختبار بحالة pending.