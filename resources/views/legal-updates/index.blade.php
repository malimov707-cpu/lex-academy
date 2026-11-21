@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')

@section('title', __('Qonunchilik yangilanishlari monitoringi (Legal Update Center)'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#0f1e3d] dark:text-white sm:text-3xl">
                {{ __('Qonunchilik monitoringi markazi') }}
            </h1>
            <p class="mt-1 text-sm text-[#0f1e3d]/70 dark:text-white/70">
                {{ __('Tasdiqlangan Lex.uz manbalaridagi sahifa o‘zgarishlarini kuzatadi. Sahifadagi o‘zgarish qonun o‘zgarganini bildirmaydi — huquqshunos tekshiruvi zarur.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                @if ($latestCheck)
                    {{ __('Oxirgi tekshiruv: :date', ['date' => $latestCheck->format('d.m.Y H:i')]) }}
                @else
                    {{ __('Avtomatik tekshiruv hali ishga tushmagan') }}
                @endif
            </span>
        </div>
    </div>

    <div class="edu-card border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200">
        {{ __('Manbalar kuniga bir marta tekshirish jadvaliga qo‘yilgan. Bu serverda Laravel scheduler ishlayotganida bajariladi. O‘zgarish topilsa, tizim faqat ko‘rib chiqish talabini belgilaydi; huquqiy yangilanishni inson tasdiqlaydi.') }}
    </div>

    <section class="edu-card p-5 sm:p-6" aria-labelledby="legal-topic-subscriptions-title">
        <div class="max-w-3xl">
            <h2 id="legal-topic-subscriptions-title" class="text-lg font-bold text-[#0f1e3d] dark:text-white">{{ __('Qiziqqan mavzularingiz bo‘yicha xabar oling') }}</h2>
            <p class="mt-1 text-sm text-[#0f1e3d]/70 dark:text-white/70">{{ __('Rasmiy manba sahifasida o‘zgarish aniqlansa, tanlagan mavzularingiz bo‘yicha bildirishnoma olasiz. Bu qonun o‘zgargani tasdiqlandi degani emas; manbani mutaxassis ko‘rib chiqishi kerak.') }}</p>
        </div>
        <form method="POST" action="{{ route('legal-updates.subscriptions.update') }}" class="mt-4">
            @csrf
            @method('PUT')
            @if ($topics->isNotEmpty())
                <fieldset>
                    <legend class="sr-only">{{ __('Huquqiy mavzular') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($topics as $topic)
                            <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 dark:border-slate-700 dark:text-slate-100">
                                <input type="checkbox" name="topics[]" value="{{ $topic }}" @checked(in_array($topic, $subscribedTopics, true)) class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span>{{ $topic }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <button type="submit" class="edu-btn edu-btn-primary mt-4">{{ __('Obunalarni saqlash') }}</button>
            @else
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">{{ __('Hozircha obuna bo‘lish mumkin bo‘lgan tasdiqlangan mavzu yo‘q.') }}</p>
            @endif
        </form>
    </section>

    <!-- Metrikalar paneli -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="edu-card p-5">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Tasdiqlangan manbalar') }}</span>
            <div class="mt-2 text-3xl font-extrabold text-[#0f1e3d] dark:text-white">{{ $verifiedSources }}</div>
            <p class="mt-1 text-xs text-slate-500">{{ __('Manba administrator tomonidan tekshirilgan') }}</p>
        </div>
        <div class="edu-card p-5">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Inson ko‘rigi kutilmoqda') }}</span>
            <div class="mt-2 text-3xl font-extrabold text-amber-600 dark:text-amber-400">{{ $pendingReviewCount }}</div>
            <p class="mt-1 text-xs text-slate-500">{{ __('Sahifa o‘zgarishi aniqlandi, huquqiy mazmun tasdiqlanmagan') }}</p>
        </div>
        <div class="edu-card p-5">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Manbaga bog‘langan kurslar') }}</span>
            <div class="mt-2 text-3xl font-extrabold text-blue-600 dark:text-blue-400">{{ $linkedCourses }}</div>
            <p class="mt-1 text-xs text-slate-500">{{ __('Manba yozuvida kurs tanlangan') }}</p>
        </div>
    </div>

    <!-- Qonunchilik manbalari ro'yxati -->
    <div class="space-y-4">
        @forelse ($sources as $source)
            <div class="edu-card p-6 transition hover:shadow-md">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-[#0f1e3d] dark:text-white">
                                {{ $source->title }}
                            </h2>
                            @if ($source->status === 'verified')
                                <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                    ✓ {{ __('Manba qo‘lda tekshirilgan') }}
                                </span>
                            @else
                                <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                    {{ __('Tasdiqlanmagan manba') }}
                                </span>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                            <span>{{ __('Havola:') }} <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer" class="font-mono text-blue-600 hover:underline dark:text-blue-400">{{ $source->url }} &nearr;</a></span>
                            @if ($source->verified_at)
                                <span>•</span>
                                <span>{{ __('Tekshirilgan sana:') }} {{ $source->verified_at->format('d.m.Y H:i') }}</span>
                            @endif
                        </div>
                    </div>

                    <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer" class="edu-btn edu-btn-secondary edu-btn-sm shrink-0">
                        {{ __('Lex.uz orqali ochish') }} &nearr;
                    </a>
                </div>

                @if ($source->excerpt)
                    <div class="mt-4 rounded-xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                        <strong class="text-slate-900 dark:text-white">{{ __('Qonun hujjati mazmuni / Asosiy qoidalar:') }}</strong>
                        <p class="mt-1 line-clamp-3">{{ $source->excerpt }}</p>
                    </div>
                @endif

                @foreach ($source->checks as $check)
                    <div class="mt-3 rounded-lg border p-3 text-xs {{ $check->status === 'changed' && ! $check->reviewed_at ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200' : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <strong>
                                @switch($check->status)
                                    @case('baseline') {{ __('Boshlang‘ich nusxa olindi') }} @break
                                    @case('unchanged') {{ __('Sahifa matni o‘zgarmagan') }} @break
                                    @case('changed') {{ $check->reviewed_at ? __('Sahifa o‘zgarishi ko‘rib chiqilgan') : __('Sahifa o‘zgargan — inson ko‘rigi kerak') }} @break
                                    @case('unavailable') {{ __('Manbani tekshirib bo‘lmadi') }} @break
                                    @default {{ __('Manba tekshiruv uchun ruxsat etilmagan') }}
                                @endswitch
                            </strong>
                            <time datetime="{{ $check->checked_at->toIso8601String() }}">{{ $check->checked_at->format('d.m.Y H:i') }}</time>
                        </div>
                        @if ($check->impact_status)
                            <p class="mt-2">{{ __('O‘quv materialiga ta’sir:') }}
                                @switch($check->impact_status)
                                    @case('no_known_impact') {{ __('Hozircha ta’sir aniqlanmadi') }} @break
                                    @case('review_required') {{ __('Qo‘shimcha ko‘rib chiqish kerak') }} @break
                                    @case('verified_outdated') {{ __('Material eskirgani tekshirildi') }} @break
                                    @case('updated') {{ __('Material yangilandi') }} @break
                                    @case('confirmed_current') {{ __('Material amaldagi manba bilan tekshirildi') }} @break
                                @endswitch
                            </p>
                            @if ($check->review_note)
                                <p class="mt-1">{{ $check->review_note }}</p>
                            @endif
                        @endif
                        @if ($check->status === 'changed' && ! $check->reviewed_at && (auth()->user()->isAdmin() || ($source->course && $source->course->isManagedBy(auth()->user()))))
                            <form method="POST" action="{{ route('admin.legal-sources.checks.review', [$source, $check]) }}" class="mt-2">
                                @csrf
                                <label class="block text-xs font-semibold" for="impact-status-{{ $check->id }}">{{ __('O‘quv materialiga ta’sir') }}</label>
                                <select id="impact-status-{{ $check->id }}" name="impact_status" required class="mt-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                    <option value="">{{ __('Natijani tanlang') }}</option>
                                    <option value="no_known_impact">{{ __('Hozircha ta’sir aniqlanmadi') }}</option>
                                    <option value="review_required">{{ __('Qo‘shimcha ko‘rib chiqish kerak') }}</option>
                                    <option value="verified_outdated">{{ __('Material eskirgani tekshirildi') }}</option>
                                    <option value="updated">{{ __('Material yangilandi') }}</option>
                                    <option value="confirmed_current">{{ __('Material amaldagi manba bilan tekshirildi') }}</option>
                                </select>
                                <label class="mt-2 block text-xs font-semibold" for="impact-note-{{ $check->id }}">{{ __('Ko‘rib chiqish izohi (ixtiyoriy)') }}</label>
                                <textarea id="impact-note-{{ $check->id }}" name="review_note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                                <button class="edu-btn edu-btn-secondary edu-btn-sm mt-2">{{ __('Ta’sirni qayd etish') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach

                @if ($source->course)
                    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400">{{ __('Bog‘langan o‘quv kursi:') }}</span>
                            <span class="font-semibold text-blue-700 dark:text-blue-400">{{ $source->course->title }}</span>
                        </div>
                        <span class="text-slate-500">
                            {{ $source->course->modules->sum('lessons_count') }} {{ __('ta darsda foydalanilmoqda') }}
                        </span>
                    </div>
                    @if ($source->course->modules->isNotEmpty())
                        <details class="mt-3 text-xs text-slate-600 dark:text-slate-300">
                            <summary class="cursor-pointer font-semibold">{{ __('Kurs doirasida ko‘rib chiqilishi mumkin bo‘lgan darslar') }}</summary>
                            <ul class="mt-2 list-inside list-disc space-y-1">
                                @foreach ($source->course->modules as $module)
                                    @foreach ($module->lessons as $lesson)
                                        <li>{{ $module->title }} — {{ $lesson->title }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @endif
            </div>
        @empty
            <div class="edu-card p-12 text-center text-slate-500">
                {{ __('Hozircha rasmiy huquqiy manbalar kiritilmagan.') }}
            </div>
        @endforelse

        <div class="mt-4">
            {{ $sources->links() }}
        </div>
    </div>
</div>
@endsection
