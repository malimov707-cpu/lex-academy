@extends('layouts.app')
@section('title', __('Ssenariy tuzilmasi'))
@php
    $roles = old('roles', $version->roles->toArray());
    $stages = old('stages', $version->stages->map(fn ($stage) => [...$stage->toArray(), 'actions' => $stage->actionRules->toArray()])->all());
    $criteria = old('criteria', $version->rubric?->criteria->toArray() ?? []);
    $roleKeys = collect($roles)->pluck('key')->all();
    $stageKeys = collect($stages)->pluck('key')->all();
@endphp
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><div><a href="{{ route('admin.courtroom.edit', $scenario) }}">← {{ __('Asosiy ma’lumotlar') }}</a><h1 class="edu-page-title mt-3">{{ $scenario->title }}</h1><p class="edu-page-lead">{{ __('Rollar, bosqichlar va baholash. Nashr qilingan versiya saqlanadi; o‘zgarishlar qoralamaga yoziladi.') }}</p></div><a class="edu-btn edu-btn-secondary" href="{{ route('admin.courtroom.preview', $scenario) }}">{{ __('Oldindan ko‘rish') }}</a></div>
<form method="POST" action="{{ route('admin.courtroom.definition.update', $scenario) }}" data-courtroom-builder class="mt-6 space-y-6">@csrf @method('PUT')
    <section data-repeater="roles" class="edu-card space-y-4 p-5"><h2 class="text-xl font-semibold">{{ __('Ishtirokchilar va AI yo‘riqnomalari') }}</h2><div data-rows class="space-y-4">@foreach($roles as $index => $role)@include('admin.courtroom.definition-role', ['prefix' => 'roles['.$index.']', 'role' => $role])@endforeach</div><button type="button" data-add-row="role-template" class="edu-btn edu-btn-secondary">{{ __('Rol qo‘shish') }}</button></section>
    <section data-repeater="stages" class="edu-card space-y-4 p-5"><h2 class="text-xl font-semibold">{{ __('Bosqichlar va harakatlar') }}</h2><p>{{ __('Birinchi bosqichdan boshlanadi. Har bir yo‘l yakuniy bosqichga olib borishi kerak.') }}</p><div data-rows class="space-y-4">@foreach($stages as $index => $stage)@include('admin.courtroom.definition-stage', ['prefix' => 'stages['.$index.']', 'stage' => $stage])@endforeach</div><button type="button" data-add-row="stage-template" class="edu-btn edu-btn-secondary">{{ __('Bosqich qo‘shish') }}</button></section>
    <section data-repeater="criteria" class="edu-card space-y-4 p-5"><h2 class="text-xl font-semibold">{{ __('Baholash rubrikasi') }}</h2><p>{{ __('Vaznlar jami 100% bo‘lishi kerak. Yangi mezonlarning mazmuniy bahosini o‘qituvchi tekshiradi.') }}</p><div data-rows class="space-y-4">@foreach($criteria as $index => $criterion)@include('admin.courtroom.definition-criterion', ['prefix' => 'criteria['.$index.']', 'criterion' => $criterion])@endforeach</div><button type="button" data-add-row="criterion-template" class="edu-btn edu-btn-secondary">{{ __('Mezon qo‘shish') }}</button></section>
    <button class="edu-btn edu-btn-primary">{{ __('Tuzilmani qoralamada saqlash') }}</button>
</form>
<template id="role-template">@include('admin.courtroom.definition-role', ['prefix' => 'roles[NEW]', 'role' => []])</template>
<template id="stage-template">@include('admin.courtroom.definition-stage', ['prefix' => 'stages[NEW]', 'stage' => []])</template>
<template id="action-template">@include('admin.courtroom.definition-action', ['prefix' => 'stages[NEW][actions][NEW]', 'action' => []])</template>
<template id="criterion-template">@include('admin.courtroom.definition-criterion', ['prefix' => 'criteria[NEW]', 'criterion' => []])</template>
@endsection
