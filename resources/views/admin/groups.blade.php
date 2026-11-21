@extends('layouts.app')
@section('title', __('Guruhlar'))
@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ __('Guruhlar boshqaruvi') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Akademiya o‘quv guruhlarini shakllantirish, talabalar va kurslarni biriktirish.') }}</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3.5 py-1.5 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20">
            {{ __('Jami guruhlar') }}: {{ $groups->total() }}
        </span>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800" role="status">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-8 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">{{ __('Yangi guruh yaratish') }}</h2>
        <form method="POST" action="{{ route('admin.groups.store') }}" class="mt-4 grid gap-4 sm:grid-cols-12 sm:items-end">
            @csrf
            <div class="sm:col-span-6">
                <label for="group-name" class="block text-xs font-medium text-slate-700">{{ __('Guruh nomi') }}</label>
                <input id="group-name" name="name" required placeholder="Masalan: YUR-24/01" class="mt-1 block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
            </div>
            <div class="sm:col-span-4">
                <label for="group-course" class="block text-xs font-medium text-slate-700">{{ __('Biriktiriladigan kurs') }}</label>
                <select id="group-course" name="course_id" class="mt-1 block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                    {{ __('Yaratish') }}
                </button>
            </div>
        </form>
    </div>

    @if ($groups->isEmpty())
        <div class="rounded-2xl border border-slate-200/80 bg-white p-12 text-center shadow-sm">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            </div>
            <h3 class="mt-3 text-base font-semibold text-slate-900">{{ __("Hozircha guruh yo'q") }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Yuqoridagi formadan foydalanib, birinchi o‘quv guruhini yarating.') }}</p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($groups as $group)
                <article class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">{{ $group->name }}</h3>
                                <p class="text-xs font-medium text-blue-600">{{ $group->course?->title ?? __('Kurs biriktirilmagan') }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                {{ $group->members->count() }} {{ __('talaba') }}
                            </span>
                        </div>

                        <div class="mt-4 border-t border-slate-100 pt-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Guruh a’zolari') }}</h4>
                            @if ($group->members->isEmpty())
                                <p class="mt-2 text-xs italic text-slate-400">{{ __('Guruhda talabalar hali yo‘q.') }}</p>
                            @else
                                <ul class="mt-2 max-h-48 divide-y divide-slate-100 overflow-y-auto pr-1 text-xs">
                                    @foreach ($group->members as $member)
                                        <li class="flex items-center justify-between py-1.5">
                                            <span class="font-medium text-slate-800">{{ $member->name }}</span>
                                            <form method="POST" action="{{ route('admin.groups.members.destroy', [$group, $member]) }}" onsubmit="return confirm('{{ __('Talabani guruhdan chiqarishni tasdiqlaysizmi?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-slate-400 hover:text-red-600" title="{{ __('Guruhdan chiqarish') }}">
                                                    &times;
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <form method="POST" action="{{ route('admin.groups.members', $group) }}" class="flex items-end gap-2">
                            @csrf
                            <div class="flex-1">
                                <label for="student-select-{{ $group->id }}" class="block text-xs font-medium text-slate-700">{{ __('Talaba qo‘shish') }}</label>
                                <select id="student-select-{{ $group->id }}" name="student_id" required class="mt-1 block w-full rounded-xl border border-slate-300 px-2.5 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                                    <option value="">{{ __('Talabani tanlang') }}</option>
                                    @foreach ($group->course?->enrollments ?? collect() as $enrollment)
                                        @if ($enrollment->status === 'active' && !$group->members->contains('id', $enrollment->student_id))
                                            <option value="{{ $enrollment->student_id }}">{{ $enrollment->student->name }} ({{ $enrollment->student->email }})</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700">
                                {{ __('Qo‘shish') }}
                            </button>
                        </form>

                        <details class="mt-3 text-xs text-slate-600">
                            <summary class="cursor-pointer font-medium text-slate-500 hover:text-slate-700">{{ __('Boshqaruv sozlamalari') }}</summary>
                            <div class="mt-3 space-y-3 rounded-xl bg-slate-50 p-3">
                                <form method="POST" action="{{ route('admin.groups.update', $group) }}" class="flex gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" required value="{{ $group->name }}" class="block w-full rounded-lg border border-slate-300 px-2.5 py-1 text-xs">
                                    <button type="submit" class="rounded-lg bg-slate-800 px-2.5 py-1 font-semibold text-white hover:bg-slate-900">{{ __('Saqlash') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" onsubmit="return confirm('{{ __('Guruhni o‘chirishni tasdiqlaysizmi?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-700">{{ __('Guruhni butunlay o‘chirish') }}</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $groups->links() }}
        </div>
    @endif
</div>
@endsection
