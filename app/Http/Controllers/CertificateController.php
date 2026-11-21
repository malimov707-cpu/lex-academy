<?php

namespace App\Http\Controllers;

use App\Models\AcademySetting;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Services\CertificateEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $certificates = Certificate::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('student_id', $request->user()->id))->latest()->paginate(20);

        return view('certificates.index', compact('certificates'));
    }

    public function store(Request $request, Course $course, CertificateEligibility $eligibility): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['student_id' => ['required', 'exists:users,id']]);
        DB::transaction(function () use ($data, $course, $request, $eligibility): void {
            $student = User::query()->lockForUpdate()->findOrFail($data['student_id']);
            $result = $eligibility->result($course, $student);
            Certificate::query()->firstOrCreate(['course_id' => $course->id, 'student_id' => $student->id], [
                'issued_by' => $request->user()->id, 'verification_code' => Str::random(48),
                'student_name' => $student->name, 'course_title' => $course->title,
                'issuer' => AcademySetting::query()->find(1)?->certificate_issuer ?? 'HUQUQ AI Academy', 'final_result' => $result, 'completed_at' => now(), 'issued_at' => now(),
            ]);
        });

        return back()->with('status', __('Sertifikat berildi.'));
    }

    public function show(Request $request, Certificate $certificate): View
    {
        abort_unless($request->user()->isAdmin() || $certificate->student_id === $request->user()->id, 403);

        return view('certificates.show', compact('certificate'));
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $certificate->update(['revoked_at' => now()]);

        return back()->with('status', __('Sertifikat bekor qilindi.'));
    }

    public function verify(string $code): View
    {
        $certificate = Certificate::query()->where('verification_code', $code)->firstOrFail();
        DB::table('certificate_verifications')->insert(['certificate_id' => $certificate->id, 'verified_at' => now()]);

        return view('certificates.verify', compact('certificate'));
    }
}
