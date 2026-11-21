@extends('layouts.app')
@section('title', __('Foydalanuvchilar'))
@section('content')
<h1 class="text-2xl font-semibold">{{ __('Talabalar va xodimlar') }}</h1>
@if ($errors->any())<ul role="alert" class="mt-4 text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<form method="GET" class="my-5 flex flex-wrap gap-3"><input name="search" aria-label="{{ __('Qidirish') }}" placeholder="{{ __('Ism yoki email') }}" value="{{ request('search') }}" class="rounded border p-2"><select name="role" aria-label="{{ __('Rol') }}" class="rounded border p-2"><option value="">{{ __('Barcha rollar') }}</option>@foreach ($roles as $value => $label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ __($label) }}</option>@endforeach</select><button class="rounded bg-blue-700 px-4 py-2 text-white">{{ __('Qidirish') }}</button></form>
<div class="grid items-start gap-6 lg:grid-cols-3">
<div class="flex flex-col gap-3 lg:col-span-2">
@foreach ($users as $user)
    <details class="rounded-xl bg-white p-4"><summary>{{ $user->name }} · {{ __($user->role->value) }} · {{ $user->email }}</summary>
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-4 flex flex-col gap-3">@csrf @method('PUT')
            <label>{{ __('Ism') }}<input name="name" value="{{ $user->name }}" required class="block w-full rounded border p-2"></label>
            <label>{{ __('Email') }}<input name="email" type="email" value="{{ $user->email }}" required class="block w-full rounded border p-2"></label>
            <label>{{ __('Rol') }}<select name="role" class="block w-full rounded border p-2">@foreach ($roles as $value => $label)<option value="{{ $value }}" @selected($user->role->value === $value)>{{ __($label) }}</option>@endforeach</select></label>
            <label>{{ __('Yangi parol (ixtiyoriy)') }}<input name="password" type="password" autocomplete="new-password" class="block w-full rounded border p-2"></label>
            <button class="self-start rounded bg-blue-700 px-4 py-2 text-white">{{ __('Saqlash') }}</button>
        </form>
    </details>
@endforeach
{{ $users->links() }}
</div>
<div class="flex flex-col gap-5">
<form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-col gap-3 rounded-xl bg-white p-5">@csrf
    <h2 class="font-semibold">{{ __('Yangi foydalanuvchi') }}</h2>
    <label>{{ __('Ism') }}<input name="name" required class="block w-full rounded border p-2"></label>
    <label>{{ __('Email') }}<input name="email" type="email" required class="block w-full rounded border p-2"></label>
    <label>{{ __('Parol (12+ belgi, katta/kichik harf, raqam)') }}<input name="password" type="password" required autocomplete="new-password" class="block w-full rounded border p-2"></label>
    <label>{{ __('Rol') }}<select name="role" class="block w-full rounded border p-2">@foreach ($roles as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach</select></label>
    <button class="rounded bg-blue-700 p-2 text-white">{{ __('Yaratish') }}</button>
</form>
<form method="POST" action="{{ route('admin.mentor-assignments.store') }}" class="flex flex-col gap-3 rounded-xl bg-white p-5">@csrf
    <h2 class="font-semibold">{{ __('Mentor biriktirish') }}</h2>
    <label>{{ __('Talaba') }}<select name="student_id" required class="block w-full rounded border p-2">@foreach ($students as $student)<option value="{{ $student->id }}">{{ $student->name }} · {{ $student->email }}</option>@endforeach</select></label>
    <label>{{ __('Mentor') }}<select name="mentor_id" class="block w-full rounded border p-2">@foreach ($mentors as $mentor)<option value="{{ $mentor->id }}">{{ $mentor->name }}</option>@endforeach</select></label>
    <label>{{ __('Kurs') }}<select name="course_id" class="block w-full rounded border p-2">@foreach ($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label>
    <button class="rounded bg-blue-700 p-2 text-white">{{ __('Biriktirish') }}</button>
</form>
</div>
</div>
@endsection
