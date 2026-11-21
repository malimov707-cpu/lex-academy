<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LegalDocumentDraft;
use App\Services\Ai\DocumentAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTrainerController extends Controller
{
    public function __construct(private DocumentAnalyzer $analyzer) {}

    public function index(Request $request): View
    {
        $drafts = LegalDocumentDraft::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return view('student.document-trainer.index', [
            'drafts' => $drafts,
            'types' => LegalDocumentDraft::typeLabels(),
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('type', 'claim');
        abort_unless(array_key_exists($type, LegalDocumentDraft::typeLabels()), 404);

        $templates = [
            'claim' => "____________________ sudiga\n\nDa’vogar: ____________________\nAloqa ma’lumoti: ____________________\n\nJavobgar: ____________________\nAloqa ma’lumoti: ____________________\n\nDA’VO ARIZASI\n(Mavzu: ____________________)\n\n1. Ish holatlari va voqea bayoni:\n...\n\n2. Ko‘rsatilayotgan huquqiy asoslar va manbalar:\n...\n\n3. So‘rov / talab:\n...\n\nIlovalar (mavjud bo‘lsa):\n1. ...\n\nSana: __.__.____    Imzo: __________",
            'application' => "____________________ ga\n\nAriza beruvchi: ____________________\n\nARIZA\n\nMurojaat mazmuni:\n...\n\nSo‘rov / talab:\n...\n\nSana: __.__.____\nImzo: __________",
            'complaint' => "____________________ ga\n\nShikoyat beruvchi: ____________________\n\nSHIKOYAT\n(Turi va ko‘rib chiqish tartibini amaldagi rasmiy manbadan tekshiring)\n\n1. Shikoyat qilinayotgan qaror yoki harakat:\n...\n\n2. Holatlar va e’tirozlar:\n...\n\n3. So‘rov:\n...\n\nIlovalar (mavjud bo‘lsa):\n...\n\nSana: __.__.____    Imzo: __________",
            'contract' => "SHARTNOMA № __\n\nTuzilgan joy: __________     Sana: __.__.____\n\nTaraflar:\n1. ____________________\n2. ____________________\n\n1. Shartnoma predmeti\n...\n\n2. Taraflarning huquq va majburiyatlari\n...\n\n3. Narx va hisob-kitob (agar qo‘llansa)\n...\n\n4. Muddat va shartnomani bekor qilish tartibi\n...\n\n5. Nizolarni hal etish tartibi\n...\n\n6. Rekvizitlar va imzolar\n...",
            'demand' => "____________________ ga\n\nTALABNOMA\n\nAsos bo‘layotgan munosabat yoki hujjat: ____________________\n\nHolatlar:\n...\n\nTalab:\n...\n\nJavob / bajarish muddati: ____________________\n(Muddat shartnoma yoki amaldagi rasmiy qoidalardan tekshiriladi.)\n\nSana: __.__.____    Imzo: __________",
        ];

        return view('student.document-trainer.create', [
            'type' => $type,
            'typeLabel' => LegalDocumentDraft::typeLabels()[$type] ?? $type,
            'initialTemplate' => $templates[$type] ?? '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:claim,application,complaint,contract,demand'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:50000'],
        ]);

        $analysis = $this->analyzer->analyze($data['document_type'], $data['content']);

        $draft = LegalDocumentDraft::query()->create([
            'user_id' => $request->user()->id,
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'content' => $data['content'],
            'version' => 1,
            'analysis' => $analysis,
            'status' => 'analyzed',
        ]);

        return redirect()->route('student.document-trainer.show', $draft)
            ->with('status', __('Loyiha saqlandi va matn checklisti tayyor.'));
    }

    public function show(Request $request, LegalDocumentDraft $draft): View
    {
        abort_unless($draft->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return view('student.document-trainer.show', [
            'draft' => $draft,
            'typeLabel' => LegalDocumentDraft::typeLabels()[$draft->document_type] ?? $draft->document_type,
        ]);
    }

    public function analyze(Request $request, LegalDocumentDraft $draft): RedirectResponse
    {
        abort_unless($draft->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
            'title' => ['sometimes', 'string', 'max:255'],
        ]);

        $analysis = $this->analyzer->analyze($draft->document_type, $data['content']);

        $draft->update([
            'title' => $data['title'] ?? $draft->title,
            'content' => $data['content'],
            'version' => $draft->version + 1,
            'analysis' => $analysis,
            'status' => 'analyzed',
        ]);

        return redirect()->route('student.document-trainer.show', $draft)
            ->with('status', __('Hujjat qayta tahlil qilindi (Versiya :v).', ['v' => $draft->version]));
    }
}
