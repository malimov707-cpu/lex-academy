@extends('layouts.app')
@section('title', __('Davomat'))
@section('content')
<h1 class="text-2xl font-semibold">{{ $schedule->title }} {{ __('— davomat') }}</h1>
<p class="mt-2">{{ $schedule->starts_at->format('d.m.Y H:i') }}</p>
@if ($errors->any())<p role="alert" class="text-red-700">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('schedule.attendance.store', $schedule) }}" class="mt-6 flex flex-col gap-4 rounded-2xl bg-white p-6">@csrf
@forelse ($students as $enrollment)
    @php $record = $records->get($enrollment->student_id); @endphp
    <div class="grid items-center gap-3 border-b pb-4 sm:grid-cols-3">
        <span>{{ $enrollment->student->name }}</span>
        <label>{{ __('Holat') }}<select name="records[{{ $enrollment->student_id }}][status]" class="block w-full rounded border p-2">
            @foreach (['present' => 'Qatnashdi', 'absent' => 'Kelmagan', 'late' => 'Kechikdi', 'excused' => 'Uzrli'] as $value => $label)<option value="{{ $value }}" @selected($record?->status === $value)>{{ __($label) }}</option>@endforeach
        </select></label>
        <label>{{ __('Sabab') }}<input name="records[{{ $enrollment->student_id }}][reason]" value="{{ $record?->reason }}" class="block w-full rounded border p-2"></label>
    </div>
@empty <p>{{ __('Aktiv talaba yo‘q.') }}</p> @endforelse
@if ($students->isNotEmpty())<button class="self-start rounded bg-blue-700 px-4 py-2 text-white">{{ __('Davomatni saqlash') }}</button>@endif
</form>
@endsection
