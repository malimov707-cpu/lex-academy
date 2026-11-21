@extends('layouts.student')
@section('title', __('Kazuslar kutubxonasi'))
@section('content')
<h1 class="text-2xl font-semibold">{{ __('Kazuslar kutubxonasi') }}</h1>
<form class="my-6 flex flex-wrap gap-3" method="GET">
    <input name="search" aria-label="{{ __('Kazus qidirish') }}" placeholder="{{ __('Kazus qidirish') }}" value="{{ request('search') }}" class="rounded-lg border bg-white p-2">
    <select name="category" aria-label="{{ __('Yo‘nalish') }}" class="rounded-lg border bg-white p-2">
        <option value="">{{ __('Barcha yo‘nalishlar') }}</option>
        @foreach ($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ __($label) }}</option>@endforeach
    </select>
    <button class="rounded-lg bg-blue-700 px-4 py-2 text-white">{{ __('Qidirish') }}</button>
</form>
<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
    @forelse ($cases as $legalCase)
        <a href="{{ route('student.cases.show', $legalCase) }}" class="edu-panel hover:border-blue-500">
            <p class="text-sm text-blue-700">{{ __($categories[$legalCase->category]) }}</p>
            <h2 class="mt-2 text-lg font-semibold">{{ $legalCase->assignment->title }}</h2>
            <p class="mt-2 text-sm">{{ $legalCase->assignment->course->title }}</p>
        </a>
    @empty
        <p>{{ __('Kurslaringizda bu filtrga mos nashr qilingan kazus yo‘q.') }}</p>
    @endforelse
</div>
<div class="mt-6">{{ $cases->links() }}</div>
@endsection
