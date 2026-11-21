@extends('layouts.app')
@section('title', __('Kurs sozlamalari'))
@section('content')
<h1 class="text-2xl font-semibold">{{ $course->title }} — {{ __('Kurs sozlamalari') }}</h1>
@if ($errors->any())<ul role="alert" class="my-4 text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<form method="POST" action="{{ route('admin.course-settings.update', $course) }}" enctype="multipart/form-data" class="mt-6 flex max-w-3xl flex-col gap-4 rounded-2xl bg-white p-6">@csrf @method('PUT')
@foreach (['title' => 'Kurs nomi', 'slug' => 'Slug', 'education_level' => 'Ta’lim darajasi'] as $field => $label)<label>{{ __($label) }}<input name="{{ $field }}" value="{{ old($field, $course->$field) }}" @required(in_array($field, ['title', 'slug'])) class="block w-full rounded border p-2"></label>@endforeach
<label>{{ __('Tavsif') }}<textarea name="description" rows="4" class="block w-full rounded border p-2">{{ old('description', $course->description) }}</textarea></label>
<label>{{ __('Kategoriya') }}<select name="category_id" required class="block w-full rounded border p-2">@foreach ($categories as $category)<option value="{{ $category->id }}" @selected($course->category_id === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label>{{ __('Qiyinlik') }}<select name="difficulty" class="block w-full rounded border p-2">@foreach (['beginner' => 'Boshlang‘ich', 'intermediate' => 'O‘rta', 'advanced' => 'Yuqori'] as $value => $label)<option value="{{ $value }}" @selected($course->difficulty === $value)>{{ __($label) }}</option>@endforeach</select></label>
<label>{{ __('Kurs tili') }}<select name="language" class="block w-full rounded border p-2">@foreach (['uz-latin' => 'O‘zbek lotin', 'uz-cyr' => 'Ўзбек кирилл', 'ru' => 'Русский'] as $value => $label)<option value="{{ $value }}" @selected($course->language === $value)>{{ $label }}</option>@endforeach</select></label>
<label>{{ __('Davomiylik (hafta)') }}<input type="number" min="1" max="520" name="duration_weeks" value="{{ $course->duration_weeks }}" class="block w-full rounded border p-2"></label>
@foreach (['learning_objectives' => 'O‘quv maqsadlari', 'prerequisites' => 'Boshlang‘ich talablar'] as $field => $label)<label>{{ __($label) }}<textarea name="{{ $field }}" rows="4" class="block w-full rounded border p-2">{{ old($field, implode("
", $course->$field ?? [])) }}</textarea></label>@endforeach
<label>{{ __('Nashr sanasi') }}<input name="published_at" type="datetime-local" value="{{ $course->published_at?->format('Y-m-d\TH:i') }}" class="block w-full rounded border p-2"></label>
<label>{{ __('Kurs muqovasi') }}<input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="block w-full rounded border p-2"></label>
<button class="self-start rounded bg-blue-700 px-5 py-2 text-white">{{ __('Saqlash') }}</button></form>
@if (auth()->user()->isAdmin())<section class="mt-6 max-w-3xl rounded-2xl bg-white p-6"><h2 class="font-semibold">{{ __('Biriktirilgan o‘qituvchilar') }}</h2>
@foreach ($course->teacherAssignments as $assignment)<form method="POST" action="{{ route('admin.course-settings.teachers.destroy', [$course, $assignment->teacher]) }}" class="mt-3 flex gap-4">@csrf @method('DELETE')<span>{{ $assignment->teacher->name }}</span><button class="text-red-700">{{ __('Olib tashlash') }}</button></form>@endforeach
<form method="POST" action="{{ route('admin.course-settings.teachers.store', $course) }}" class="mt-4 flex flex-wrap gap-3">@csrf<select name="teacher_id" aria-label="{{ __('O‘qituvchi') }}" class="rounded border p-2">@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select><button class="rounded bg-blue-700 px-4 py-2 text-white">{{ __('Biriktirish') }}</button></form></section>@endif
@endsection
