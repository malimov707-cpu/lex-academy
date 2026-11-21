<?php

use App\Models\AiTutorMessage;
use App\Models\AiTutorSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LegalSource;
use App\Models\User;
use App\Services\Ai\AiHealthChecker;
use App\Services\Ai\AITaskExecutor;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\TutorContextBuilder;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('allows students to create, list, view, update and delete standalone AI tutor conversations', function () {
    $student = User::factory()->student()->create();

    // 1. Create standalone session
    $createRes = actingAs($student)->postJson(route('ai.conversations.store'), [
        'title' => 'Fuqarolik Huquqi Savollari',
        'mode' => 'explain',
    ])->assertCreated();

    $sessionId = $createRes->json('session.id');
    expect($sessionId)->toBeInt();

    // 2. List sessions
    actingAs($student)->getJson(route('ai.conversations.index'))
        ->assertOk()
        ->assertJsonCount(1, 'sessions')
        ->assertJsonPath('sessions.0.id', $sessionId)
        ->assertJsonPath('sessions.0.title', 'Fuqarolik Huquqi Savollari');

    // 3. View session details
    actingAs($student)->getJson(route('ai.conversations.show', $sessionId))
        ->assertOk()
        ->assertJsonPath('session.id', $sessionId)
        ->assertJsonPath('session.title', 'Fuqarolik Huquqi Savollari');

    // 4. Update session title and mode
    actingAs($student)->patchJson(route('ai.conversations.update', $sessionId), [
        'title' => 'Yangi Tahrirlangan Suhbat',
        'mode' => 'quiz_mode',
    ])->assertOk();

    expect(AiTutorSession::find($sessionId)->title)->toBe('Yangi Tahrirlangan Suhbat')
        ->and(AiTutorSession::find($sessionId)->mode)->toBe('quiz_mode');

    // 5. Delete session
    actingAs($student)->deleteJson(route('ai.conversations.destroy', $sessionId))
        ->assertOk();

    expect(AiTutorSession::find($sessionId))->toBeNull();
});

it('prevents students from accessing or deleting other students sessions', function () {
    $studentA = User::factory()->student()->create();
    $studentB = User::factory()->student()->create();

    $sessionA = AiTutorSession::query()->create([
        'student_id' => $studentA->id,
        'title' => 'Maxfiy Suhbat A',
        'mode' => 'explain',
    ]);

    actingAs($studentB)->getJson(route('ai.conversations.show', $sessionA))
        ->assertForbidden();

    actingAs($studentB)->patchJson(route('ai.conversations.update', $sessionA), [
        'title' => 'Hacked',
    ])->assertForbidden();

    actingAs($studentB)->deleteJson(route('ai.conversations.destroy', $sessionA))
        ->assertForbidden();
});

it('executes EXPLAIN_LEGAL_TOPIC and provides statutory references without stray tokens', function () {
    $student = User::factory()->student()->create();
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create([
        'title' => 'Rasmiy sinov manbasi', 'excerpt' => 'Huquqiy ta’lim manba parchasi.',
        'status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now(),
    ]);
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Manbadagi parchaga ko‘ra, ushbu mavzuni shu doirada tushuntirish mumkin. SOURCE-'.$source->id]]]], 200)]);
    $executor = app(AITaskExecutor::class);

    $result = $executor->execute($student, AITaskExecutor::ACTION_EXPLAIN_TOPIC, [
        'topic' => 'Bitimlar va ularning haqiqiyligi',
    ]);

    expect($result['action'])->toBe(AITaskExecutor::ACTION_EXPLAIN_TOPIC)
        ->and($result['content'])->toContain('Manbadagi parchaga ko‘ra')
        ->and($result['metadata']['sources'][0]['reference'])->toBe('SOURCE-'.$source->id)
        ->and($result['metadata']['source_status'])->toBe('references_validated');
});

it('does not invent an article interpretation when its statute text is missing', function () {
    $student = User::factory()->student()->create();
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Manba matni berilmagani sababli modda mazmunini tekshira olmayman.']]]], 200)]);
    $executor = app(AITaskExecutor::class);

    $result = $executor->execute($student, AITaskExecutor::ACTION_EXPLAIN_ARTICLE, [
        'article' => '168-modda',
    ]);

    expect($result['action'])->toBe(AITaskExecutor::ACTION_EXPLAIN_ARTICLE)
        ->and($result['content'])->toContain('tekshira olmayman')
        ->and($result['metadata']['legal_review_status'])->toBe('not_verified')
        ->and($result['confidence'])->toBeNull();
});

it('creates a structured multi-day study plan', function () {
    $student = User::factory()->student()->create();
    $executor = app(AITaskExecutor::class);

    $result = $executor->execute($student, AITaskExecutor::ACTION_CREATE_STUDY_PLAN, [
        'goal' => 'Davlat va huquq nazariyasi',
        'days' => 7,
    ]);

    expect($result['action'])->toBe(AITaskExecutor::ACTION_CREATE_STUDY_PLAN)
        ->and($result['metadata'])->toHaveKey('study_plan')
        ->and($result['metadata']['study_plan']['days'])->toHaveCount(7);
});

it('creates interactive legal quiz questions and evaluates answers', function () {
    $student = User::factory()->student()->create();
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create(['excerpt' => 'Asl manba parchasi.', 'status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'questions' => [[
            'question' => 'Manba parchasida qanday qoida bayon etilgan?',
            'options' => ['A' => 'Birinchi', 'B' => 'Ikkinchi', 'C' => 'Uchinchi', 'D' => 'To‘rtinchi'],
            'correct_option' => 'A',
            'explanation' => 'Parchada birinchi qoida keltirilgan.',
            'source_refs' => ['SOURCE-1'],
        ]],
    ], JSON_UNESCAPED_UNICODE)]]]], 200)]);
    $session = AiTutorSession::query()->create([
        'student_id' => $student->id,
        'title' => 'Test Seansi',
        'mode' => 'quiz_mode',
    ]);

    $executor = app(AITaskExecutor::class);

    // 1. Create Quiz
    $quizResult = $executor->execute($student, AITaskExecutor::ACTION_CREATE_QUIZ, [
        'topic' => 'Jinoyat huquqi',
    ], $session);

    expect($quizResult['action'])->toBe(AITaskExecutor::ACTION_CREATE_QUIZ)
        ->and($quizResult['metadata']['quiz']['questions'])->toBeArray()
        ->and(count($quizResult['metadata']['quiz']['questions']))->toBe(1)
        ->and($quizResult['metadata']['quiz']['questions'][0])->not->toHaveKey('correct_option');

    actingAs($student)->getJson(route('ai.conversations.show', $session))
        ->assertOk()
        ->assertJsonMissingPath('session.metadata.active_quiz');
    actingAs($student)->patchJson(route('ai.conversations.update', $session), ['title' => 'Yangilangan test'])
        ->assertOk()
        ->assertJsonMissingPath('session.metadata.active_quiz');

    // 2. Grade Quiz - correct answer
    $correctGrade = $executor->execute($student, AITaskExecutor::ACTION_GRADE_QUIZ, [
        'question_id' => 1,
        'selected_option' => 'A',
    ], $session);

    expect($correctGrade['metadata']['is_correct'])->toBeTrue()
        ->and($correctGrade['content'])->toContain('To\'g\'ri javob');

    // 3. Grade Quiz - incorrect answer
    $wrongGrade = $executor->execute($student, AITaskExecutor::ACTION_GRADE_QUIZ, [
        'question_id' => 1,
        'selected_option' => 'C',
    ], $session);

    expect($wrongGrade['metadata']['is_correct'])->toBeFalse()
        ->and($wrongGrade['content'])->toContain('Noto\'g\'ri');

    $source->update(['excerpt' => 'Tahrirlangan manba parchasi.']);
    expect(fn () => $executor->execute($student, AITaskExecutor::ACTION_GRADE_QUIZ, [
        'question_id' => 1,
        'selected_option' => 'A',
    ], $session))->toThrow(AiUnavailableException::class);
});

it('creates flashcards from approved legal source context', function () {
    $student = User::factory()->student()->create();
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Savol: huquqiy tushuncha nima? Javob: manbadagi ta’rifga qarang. SOURCE-'.$source->id]]]], 200)]);
    $executor = app(AITaskExecutor::class);

    $result = $executor->execute($student, AITaskExecutor::ACTION_CREATE_FLASHCARDS, [
        'topic' => 'Konstitutsiyaviy huquq',
    ]);

    expect($result['action'])->toBe(AITaskExecutor::ACTION_CREATE_FLASHCARDS)
        ->and($result['content'])->toContain('Savol: huquqiy tushuncha')
        ->and($result['metadata']['sources'][0]['reference'])->toBe('SOURCE-'.$source->id);
});

it('creates practical legal case exercises and avoids fabricated legal conclusions', function () {
    $student = User::factory()->student()->create();
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => 'O‘quv uchun xayoliy faktlar. SOURCE-'.$source->id]]]], 200)
        ->push(['choices' => [['message' => ['content' => 'Manba yetarli emas, huquqiy xulosani tekshirish kerak.']]]], 200)]);
    $executor = app(AITaskExecutor::class);

    $practice = $executor->execute($student, AITaskExecutor::ACTION_CREATE_PRACTICE, [
        'topic' => 'Oldi-sotdi shartnomasi',
    ]);

    expect($practice['action'])->toBe(AITaskExecutor::ACTION_CREATE_PRACTICE)
        ->and($practice['content'])->toContain('xayoliy faktlar')
        ->and($practice['metadata']['sources'][0]['reference'])->toBe('SOURCE-'.$source->id);

    $caseAnalysis = $executor->execute($student, AITaskExecutor::ACTION_ANALYZE_CASE, [
        'facts' => 'Avtomobil oldi-sotdisi bo\'yicha nizo',
    ]);

    expect($caseAnalysis['action'])->toBe(AITaskExecutor::ACTION_ANALYZE_CASE)
        ->and($caseAnalysis['content'])->toContain('Manba yetarli emas')
        ->and($caseAnalysis['metadata']['legal_review_status'])->toBe('not_verified');
});

it('routes article questions through the configured AI provider and rejects citations outside available sources', function () {
    $student = User::factory()->student()->create();
    $verifier = User::factory()->academyAdmin()->create();
    $source = LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => 'Ushbu modda haqida faqat berilgan parchaga tayanib izoh beraman. SOURCE-'.$source->id]]]], 200)
        ->push(['choices' => [['message' => ['content' => 'Manba SOURCE-999 tasdiqlaydi.']]]], 200)
        ->push(['choices' => [['message' => ['content' => 'Asossiz havola: https://fake.example/legal']]]], 200)]);

    actingAs($student)->postJson(route('ai.chat.send'), ['message' => '168-modda nimani anglatadi?'])
        ->assertOk()
        ->assertJsonPath('action', AITaskExecutor::ACTION_EXPLAIN_ARTICLE)
        ->assertJsonPath('metadata.source_status', 'references_validated');

    actingAs($student)->postJson(route('ai.chat.send'), ['message' => '169-modda haqida'])
        ->assertStatus(503)
        ->assertJsonPath('error', 'AI javobida taqdim etilmagan manba havolasi bor. Iltimos, qayta urinib ko‘ring.');

    actingAs($student)->postJson(route('ai.chat.send'), ['message' => '169-modda haqida'])
        ->assertStatus(503)
        ->assertJsonPath('error', 'AI javobida taqdim etilmagan manba havolasi bor. Iltimos, qayta urinib ko‘ring.');
});

it('excludes sources with a changed or unavailable latest page from AI legal evidence', function () {
    $student = User::factory()->student()->create();
    $verifier = User::factory()->academyAdmin()->create();
    $usable = LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    $changed = LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    $unavailable = LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    $changed->checks()->create(['status' => 'changed', 'checked_at' => now()->addMinute()]);
    $changed->checks()->create(['status' => 'unchanged', 'checked_at' => now()->addMinutes(2)]);
    $unavailable->checks()->create(['status' => 'unavailable', 'checked_at' => now()]);

    expect(app(TutorContextBuilder::class)->verifiedSourceIds($student))
        ->toContain($usable->id)
        ->not->toContain($changed->id)
        ->not->toContain($unavailable->id);

    $changed->update(['verified_at' => now()->addMinutes(3)]);
    expect(app(TutorContextBuilder::class)->verifiedSourceIds($student))->toContain($changed->id);
});

it('does not create a legal quiz when approved source context is unavailable', function () {
    $student = User::factory()->student()->create();
    $session = AiTutorSession::query()->create(['student_id' => $student->id, 'title' => 'Manbasiz test', 'mode' => 'quiz_mode']);
    config()->set('ai_tutor.api_key', null);

    actingAs($student)->postJson(route('ai.conversations.messages', $session), ['message' => 'Mehnat huquqi bo‘yicha test tuz'])
        ->assertStatus(503)
        ->assertJsonPath('error', 'Test tuzish uchun holati yangiligi tekshirilgan, tasdiqlangan huquqiy manba mavjud emas.');

    expect($session->fresh()->metadata)->not->toHaveKey('active_quiz')
        ->and($session->messages()->where('role', 'assistant')->exists())->toBeFalse();
});

it('checks student learning progress statistics', function () {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    CourseEnrollment::query()->create(['student_id' => $student->id, 'course_id' => $course->id]);

    $executor = app(AITaskExecutor::class);
    $result = $executor->execute($student, AITaskExecutor::ACTION_CHECK_PROGRESS);

    expect($result['action'])->toBe(AITaskExecutor::ACTION_CHECK_PROGRESS)
        ->and($result['metadata']['enrolled_courses'])->toBe(1);
});

it('executes actions via HTTP endpoint /ai/conversations/{session}/actions', function () {
    $student = User::factory()->student()->create();
    $verifier = User::factory()->academyAdmin()->create();
    LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Savol: huquqiy ta’rif? Javob: manbadagi ta’rif. SOURCE-1']]]], 200)]);
    $session = AiTutorSession::query()->create([
        'student_id' => $student->id,
        'title' => 'Action Seansi',
        'mode' => 'quiz_mode',
    ]);

    actingAs($student)->postJson(route('ai.conversations.actions', $session), [
        'action' => 'CREATE_FLASHCARDS',
        'params' => ['topic' => 'Mehnat huquqi'],
    ])->assertOk()
        ->assertJsonPath('action', 'CREATE_FLASHCARDS')
        ->assertJsonStructure(['action', 'content', 'metadata']);

    expect(AiTutorMessage::where('session_id', $session->id)->count())->toBe(1);
});

it('processes chat messages and detects task execution with metadata in standalone sessions', function () {
    $student = User::factory()->student()->create();
    $verifier = User::factory()->academyAdmin()->create();
    LegalSource::factory()->create(['status' => 'verified', 'verified_by' => $verifier->id, 'verified_at' => now()]);
    config()->set('ai_tutor.api_key', 'test-key');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'questions' => [[
            'question' => 'Manba bo‘yicha savol?',
            'options' => ['A' => 'Bir', 'B' => 'Ikki', 'C' => 'Uch', 'D' => 'To‘rt'],
            'correct_option' => 'A', 'explanation' => 'Parcha asosida.', 'source_refs' => ['SOURCE-1'],
        ]],
    ], JSON_UNESCAPED_UNICODE)]]]], 200)]);
    $session = AiTutorSession::query()->create([
        'student_id' => $student->id,
        'title' => 'Savol-Javob',
        'mode' => 'quiz_mode',
    ]);

    // Requesting a quiz via chat message
    $res = actingAs($student)->postJson(route('ai.conversations.messages', $session), [
        'message' => 'Menga jinoyat huquqi bo\'yicha test tuzib ber',
        'mode' => 'quiz_mode',
    ])->assertOk();

    expect($res->json('action'))->toBe(AITaskExecutor::ACTION_CREATE_QUIZ)
        ->and($res->json('metadata.quiz.questions'))->toBeArray();

    // Verify messages saved in DB
    expect(AiTutorMessage::where('session_id', $session->id)->count())->toBe(2);
    $lastMsg = AiTutorMessage::where('session_id', $session->id)->where('role', 'assistant')->first();
    expect($lastMsg->metadata)->toHaveKey('quiz');
});

it('correctly reports AI Health status with mocked HTTP responses without consuming real tokens', function () {
    $checker = app(AiHealthChecker::class);

    Http::fake([
        '*/chat/completions' => Http::sequence()
            ->push([
                'choices' => [
                    ['message' => ['content' => 'Salom! Qanday yordam bera olaman?']],
                ],
                'usage' => ['total_tokens' => 10],
            ], 200)
            ->push([
                'error' => ['message' => 'Incorrect API key provided'],
            ], 401)
            ->push([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
    ]);

    // 1. Success check -> CONNECTED
    $healthy = $checker->check();
    expect($healthy['status'])->toBe('CONNECTED')
        ->and($healthy['latency_ms'])->toBeInt();

    // 2. 401 Unauthorized -> INVALID_API_KEY
    $invalidKey = $checker->check();
    expect($invalidKey['status'])->toBe('INVALID_API_KEY')
        ->and($invalidKey['message'])->toContain('Incorrect API key provided');

    // 3. 429 Rate Limit -> RATE_LIMITED
    $rateLimited = $checker->check();
    expect($rateLimited['status'])->toBe('RATE_LIMITED');
});
