<?php

namespace App\Services\Ai;

use App\Enums\AiDocumentIndexingStatus;
use App\Models\AiCurriculumDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AiCurriculumDocumentService
{
    public function store(
        UploadedFile $file,
        array $attributes
    ): AiCurriculumDocument {
        $temporaryPath = $file->getRealPath();

        if (
            ! is_string($temporaryPath) ||
            $temporaryPath === ''
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'تعذر قراءة ملف الكتاب.',
                ],
            ]);
        }

        $checksum = hash_file(
            'sha256',
            $temporaryPath
        );

        if (! is_string($checksum)) {
            throw new RuntimeException(
                'Failed to calculate file checksum.'
            );
        }

        $duplicateExists =
            AiCurriculumDocument::withTrashed()
                ->where(
                    'external_teacher_id',
                    $attributes['external_teacher_id']
                )
                ->where('sha256', $checksum)
                ->where(
                    'grade_level',
                    $attributes['grade_level']
                )
                ->where(
                    'subject_name',
                    $attributes['subject_name']
                )
                ->where(
                    'term',
                    $attributes['term']
                )
                ->where(
                    'curriculum_year',
                    $attributes['curriculum_year']
                )
                ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'file' => [
                    'هذا الكتاب موجود مسبقًا في مكتبتك لنفس الصف والمادة والفصل.',
                ],
            ]);
        }

        $documentId = (string) Str::ulid();

        $directory = sprintf(
            'ai/curriculum-documents/%s/grade-%s/term-%s',
            $attributes['curriculum_year'],
            $attributes['grade_level'],
            $attributes['term']
        );

        $storagePath = $file->storeAs(
            $directory,
            "{$documentId}.pdf",
            'local'
        );

        if (! is_string($storagePath)) {
            throw new RuntimeException(
                'Failed to store curriculum document.'
            );
        }

        try {
            return AiCurriculumDocument::create([
                'id' => $documentId,
                'external_teacher_id' =>
                    $attributes['external_teacher_id'],
                'title' => $attributes['title'],
                'grade_level' =>
                    $attributes['grade_level'],
                'subject_name' =>
                    $attributes['subject_name'],
                'term' => $attributes['term'],
                'curriculum_year' =>
                    $attributes['curriculum_year'],
                'original_filename' =>
                    $file->getClientOriginalName(),
                'storage_disk' => 'local',
                'storage_path' => $storagePath,
                'mime_type' =>
                    $file->getMimeType()
                    ?? 'application/pdf',
                'size_bytes' =>
                    (int) $file->getSize(),
                'sha256' => $checksum,
                'indexing_status' =>
                    AiDocumentIndexingStatus::Pending,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')
                ->delete($storagePath);

            throw $exception;
        }
    }
}

// وظيفته:

// يحسب SHA-256 للملف.
// يمنع رفع الكتاب نفسه مرتين.
// يولد اسمًا آمنًا بدل استخدام اسم الملف الأصلي.
// يحفظ PDF في مساحة Laravel الخاصة.
// يحذف الملف إذا فشل الحفظ في قاعدة البيانات.