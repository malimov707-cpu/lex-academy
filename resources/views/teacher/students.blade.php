@extends('layouts.app')
@section('title', __('Talabalar'))
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="edu-page-title">{{ __('Talabalar') }}</h1>
    @if(request()->filled('student'))
        <a class="edu-btn edu-btn-secondary" href="{{ route('teacher.students.index') }}">{{ __('Barcha talabalar') }}</a>
    @endif
</div>
<div class="edu-panel overflow-x-auto">
    <table class="edu-table">
        <thead><tr><th scope="col">{{ __('Talaba') }}</th><th scope="col">{{ __('Kurslar') }}</th></tr></thead>
        <tbody>
            @forelse($students as $student)
                <tr>
                    <td><strong>{{ $student->name }}</strong><small class="block text-[var(--edu-text-muted)]">{{ $student->email }}</small></td>
                    <td>
                        <ul class="space-y-1">
                            @foreach($student->enrollments as $enrollment)
                                <li><a class="underline" href="{{ route('admin.courses.modules.index', $enrollment->course) }}">{{ $enrollment->course->title }}</a> · {{ __($enrollment->status) }}</li>
                            @endforeach
                        </ul>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2">{{ __('Talabalar topilmadi.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $students->links() }}
@endsection
