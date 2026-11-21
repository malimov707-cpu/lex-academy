@extends('layouts.app')
@section('title', __('Dalillar boshqaruvi'))
@section('content')
<a href="{{ route('admin.courtroom.edit', $scenario) }}">← {{ __('Ssenariyga qaytish') }}</a>
<h1 class="edu-page-title mt-3">{{ __('Dalillar boshqaruvi') }}</h1><p class="edu-page-lead">{{ $scenario->title }} · v{{ $version->version }}</p>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="space-y-4">@foreach($version->evidence as $item)<article class="edu-card p-5"><h2 class="text-lg font-semibold">{{ $item->title }}</h2><p>{{ $item->summary }}</p><p class="mt-2 whitespace-pre-wrap">{{ $item->body }}</p><small>{{ $item->source_label }} · v{{ $item->version }} · {{ $item->status }}</small><p>{{ $item->visibility === 'all' ? __('Barcha rollarga ochiq') : implode(', ', $item->allowed_role_keys ?? []) }}</p>@if($item->storage_path)<a href="{{ route('admin.courtroom.evidence.show', [$scenario, $item]) }}" class="edu-btn edu-btn-secondary mt-3" target="_blank" rel="noopener">{{ __('Faylni ochish') }}</a>@endif @if($item->status === 'active')<form method="POST" action="{{ route('admin.courtroom.evidence.destroy', [$scenario, $item]) }}" class="mt-3">@csrf @method('DELETE')<button class="edu-btn edu-btn-ghost">{{ __('Qoralamada arxivlash') }}</button></form>@endif</article>@endforeach</section>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.courtroom.evidence.store', $scenario) }}" class="edu-card space-y-4 p-5">@csrf<h2 class="text-xl font-semibold">{{ __('Yangi o‘quv dalili') }}</h2>
        <label class="edu-field"><span>{{ __('Nomi') }}</span><input name="title" class="edu-input" value="{{ old('title') }}" required></label>
        <label class="edu-field"><span>{{ __('Qisqa tavsif') }}</span><textarea name="summary" class="edu-input" required>{{ old('summary') }}</textarea></label>
        <label class="edu-field"><span>{{ __('Dalil matni') }}</span><textarea name="body" rows="6" class="edu-input">{{ old('body') }}</textarea></label>
        <label class="edu-field"><span>{{ __('Fayl: PDF, rasm, audio yoki video, :size MB gacha', ['size' => $uploadLimitMb]) }}</span><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp3,.wav,.ogg,.mp4,.webm" class="edu-input"></label>
        <label class="edu-field"><span>{{ __('Manba va foydalanish huquqi') }}</span><input name="source_label" value="{{ old('source_label') }}" class="edu-input" required></label>
        <label class="edu-field"><span>{{ __('Ko‘rinishi') }}</span><select name="visibility" class="edu-input"><option value="all">{{ __('Barcha rollar') }}</option><option value="roles" @selected(old('visibility') === 'roles')>{{ __('Faqat tanlangan rollar') }}</option></select></label>
        <label class="edu-field"><span>{{ __('Maxsus rollar') }}</span><select name="allowed_role_keys[]" multiple class="edu-input">@foreach($version->roles as $role)<option value="{{ $role->key }}" @selected(in_array($role->key, old('allowed_role_keys', []), true))>{{ $role->name }}</option>@endforeach</select></label>
        <label class="flex items-start gap-3"><input type="checkbox" name="educational_use_confirmed" value="1" required><span>{{ __('Ushbu materialdan ta’lim maqsadida foydalanish huquqini va shaxsiy ma’lumotlar himoyasini tekshirdim.') }}</span></label>
        <button class="edu-btn edu-btn-primary">{{ __('Dalilni qoralamaga qo‘shish') }}</button>
    </form>
</div>
@endsection
