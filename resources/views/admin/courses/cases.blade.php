@extends('layouts.app')
@section('title', __('Kazuslar kutubxonasi'))
@section('content')
<h1 class="text-2xl font-semibold">{{ $course->title }} {{ __('— kazuslar') }}</h1>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="flex flex-col gap-4">
        @forelse ($cases as $legalCase)
            <article class="rounded-2xl border border-[#0f1e3d]/10 bg-white p-5">
                <h2 class="font-semibold">{{ $legalCase->assignment->title }}</h2>
                <p class="text-sm">{{ __($categories[$legalCase->category]) }} · {{ $legalCase->status }}</p>
                <a class="text-blue-700 underline" href="{{ route('admin.cases.edit', $legalCase) }}">{{ __('Tahrirlash / nashr qilish') }}</a>
            </article>
        @empty
            <p>{{ __('Hozircha kazus yo‘q.') }}</p>
        @endforelse
        {{ $cases->links() }}
    </div>
    <section class="rounded-2xl bg-white p-6">
        <h2 class="text-lg font-semibold">{{ __('Yangi kazus') }}</h2>
        <form method="POST" action="{{ route('admin.courses.cases.store', $course) }}" class="mt-4 flex flex-col gap-4">
            @csrf
            @include('admin.courses.case-fields', ['legalCase' => null])
            <label class="flex flex-col gap-1">{{ __('Kursning joriy talabalariga mentor biriktirish') }}
                <select name="mentor_id" class="rounded-lg border p-2">
                    <option value="">{{ __('Mavjud biriktirishlardan foydalanish') }}</option>
                    @foreach ($mentors as $mentor)<option value="{{ $mentor->id }}" @selected(old('mentor_id') == $mentor->id)>{{ $mentor->name }}</option>@endforeach
                </select>
            </label>
            <button class="rounded-lg bg-blue-700 px-4 py-2 text-white">{{ __('Yaratish') }}</button>
        </form>
    </section>
</div>
@endsection
