@extends('layouts.app')
@section('title', __('Kutubxona materialini tahrirlash'))
@section('content')
<a href="{{ route('admin.library.index') }}" class="text-sm text-blue-700">← {{ __('Kutubxonani boshqarish') }}</a><h1 class="mt-3 text-2xl font-semibold">{{ $resource->title }}</h1><form method="POST" action="{{ route('admin.library.update', $resource) }}" class="edu-panel mt-5">@csrf @method('PUT') @include('admin.library-fields')<button class="edu-btn edu-btn-primary mt-4">{{ __('Saqlash') }}</button></form>
@endsection
