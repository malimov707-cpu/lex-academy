@extends('layouts.app')
@section('title', __('Kazusni tahrirlash'))
@section('content')
<h1 class="text-2xl font-semibold">{{ __('Kazusni tahrirlash') }}</h1>
<form method="POST" action="{{ route('admin.cases.update', $legalCase) }}" class="mt-6 flex max-w-3xl flex-col gap-4 rounded-2xl bg-white p-6">
    @csrf @method('PUT')
    @include('admin.courses.case-fields')
    <button class="rounded-lg bg-blue-700 px-4 py-2 text-white">{{ __('Saqlash') }}</button>
</form>
@endsection
