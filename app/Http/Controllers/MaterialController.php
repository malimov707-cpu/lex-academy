<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LessonMaterial;
use App\Models\MentorAssignment;
use App\Models\SubmissionFile;
use App\Services\EducationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialController extends Controller
{
    public function lesson(Request $request, LessonMaterial $material, EducationAccess $access): StreamedResponse
    {
        $user = $request->user();
        $course = $material->lesson->module->course;
        if (! $user->isAdmin() && ! ($user->hasRole(UserRole::Teacher) && $course->isManagedBy($user))) {
            $access->learn($user, $course, $material->lesson);
        }
        abort_unless($material->file_path && Storage::disk('local')->exists($material->file_path), 404);

        if ($request->boolean('inline')) {
            $mime = Storage::disk('local')->mimeType($material->file_path);
            abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg'], true), 415);

            return Storage::disk('local')->response($material->file_path, headers: ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff']);
        }

        return Storage::disk('local')->download($material->file_path);
    }

    public function cover(Request $request, Course $course, EducationAccess $access): StreamedResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $course->isManagedBy($user)) {
            $access->learn($user, $course);
        }
        abort_unless($course->cover_image && Storage::disk('local')->exists($course->cover_image), 404);
        $mime = Storage::disk('local')->mimeType($course->cover_image);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        return Storage::disk('local')->response($course->cover_image, headers: ['Content-Type' => $mime]);
    }

    public function submission(Request $request, SubmissionFile $file): StreamedResponse
    {
        $user = $request->user();
        $submission = $file->submission;
        $assigned = $user->hasRole(UserRole::Mentor) && MentorAssignment::query()->where('mentor_id', $user->id)
            ->where('student_id', $submission->student_id)->where('course_id', $submission->assignment->course_id)->exists();
        abort_unless($user->isAdmin() || $user->id === $submission->student_id || $assigned, 403);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);

        return Storage::disk('local')->download($file->file_path, $file->original_name);
    }
}
