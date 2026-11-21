@extends('layouts.app')
@section('title', __('Akademiya sozlamalari'))
@section('content')
<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ __('Akademiya sozlamalari') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('Tashkilot nomi, sertifikat beruvchi organ va rasmiy aloqa ma’lumotlarini boshqarish.') }}</p>
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

    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label for="academy-name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('Akademiya nomi') }}</label>
                <input id="academy-name" name="name" value="{{ old('name', $settings?->name ?? 'HUQUQ AI Academy') }}" required maxlength="255" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
            </div>

            <div>
                <label for="certificate-issuer" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('Sertifikat beruvchi tashkilot') }}</label>
                <input id="certificate-issuer" name="certificate_issuer" value="{{ old('certificate_issuer', $settings?->certificate_issuer ?? 'HUQUQ AI Academy') }}" required maxlength="255" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="support-email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('Aloqa emaili') }}</label>
                    <input id="support-email" name="support_email" type="email" value="{{ old('support_email', $settings?->support_email) }}" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                </div>
                <div>
                    <label for="support-phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">{{ __('Aloqa telefoni') }}</label>
                    <input id="support-phone" name="support_phone" value="{{ old('support_phone', $settings?->support_phone) }}" maxlength="50" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600">
                </div>
            </div>

            <div class="rounded-xl bg-blue-50/70 p-4 text-xs text-blue-900 ring-1 ring-inset ring-blue-600/10">
                <strong>{{ __('Eslatma:') }}</strong> {{ __('Ushbu sozlamalar yangi generatsiya qilinadigan sertifikatlarga qo‘llanadi. Avval berilgan rasmiy sertifikatlar o‘z tarixiy identifikatorlarini saqlab qoladi.') }}
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                    {{ __('Sozlamalarni saqlash') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
