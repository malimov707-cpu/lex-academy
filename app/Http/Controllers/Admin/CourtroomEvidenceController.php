<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourtroomEvidenceRequest;
use App\Models\ScenarioEvidence;
use App\Models\SimulationScenario;
use App\Services\VirtualCourtroom\ScenarioDefinitionEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CourtroomEvidenceController extends Controller
{
    public function index(SimulationScenario $scenario): View
    {
        Gate::authorize('update', $scenario);
        $version = $scenario->versions()->with(['evidence', 'roles'])->latest('version')->firstOrFail();

        return view('admin.courtroom.evidence', [
            'scenario' => $scenario,
            'version' => $version,
            'uploadLimitMb' => min(12, UploadedFile::getMaxFilesize() / 1048576),
        ]);
    }

    public function store(StoreCourtroomEvidenceRequest $request, SimulationScenario $scenario, ScenarioDefinitionEditor $editor): RedirectResponse
    {
        $path = $request->file('file')?->store('courtroom-evidence', 'local');
        abort_if($request->hasFile('file') && ! $path, 503, 'Dalil faylini saqlab bo‘lmadi.');
        try {
            DB::transaction(function () use ($request, $scenario, $editor, $path): void {
                $scenario = SimulationScenario::query()->lockForUpdate()->findOrFail($scenario->id);
                $version = $editor->draft($scenario);
                $file = $request->file('file');
                $version->evidence()->create([
                    ...$request->safe()->only(['title', 'summary', 'body', 'source_label', 'visibility']),
                    'allowed_role_keys' => $request->validated('visibility') === 'roles' ? $request->validated('allowed_role_keys', []) : [],
                    'type' => $file ? 'file' : 'document',
                    'storage_path' => $path, 'original_name' => $file?->getClientOriginalName(), 'mime_type' => $file?->getMimeType(),
                    'checksum' => $file ? hash_file('sha256', $file->getRealPath()) : hash('sha256', (string) $request->validated('body')),
                    'version' => 1, 'status' => 'active', 'sort_order' => ((int) $version->evidence()->max('sort_order')) + 1,
                ]);
                $version->update(['snapshot_hash' => null]);
            }, 3);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('status', __('Dalil qoralamaga qo‘shildi.'));
    }

    public function show(SimulationScenario $scenario, ScenarioEvidence $evidence): StreamedResponse
    {
        Gate::authorize('update', $scenario);
        abort_unless($scenario->versions()->whereKey($evidence->scenario_version_id)->exists(), 404);
        abort_unless($evidence->storage_path && Storage::disk('local')->exists($evidence->storage_path), 404);
        $mime = Storage::disk('local')->mimeType($evidence->storage_path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'video/mp4', 'video/webm'], true), 415);

        return Storage::disk('local')->response($evidence->storage_path, $evidence->original_name, [
            'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(SimulationScenario $scenario, ScenarioEvidence $evidence, ScenarioDefinitionEditor $editor): RedirectResponse
    {
        Gate::authorize('update', $scenario);
        abort_unless($scenario->versions()->whereKey($evidence->scenario_version_id)->exists(), 404);
        DB::transaction(function () use ($scenario, $evidence, $editor): void {
            $locked = SimulationScenario::query()->lockForUpdate()->findOrFail($scenario->id);
            $latest = $locked->versions()->latest('version')->firstOrFail();
            abort_unless($latest->id === $evidence->scenario_version_id, 409);
            $draft = $editor->draft($locked);
            $draft->evidence()->where('sort_order', $evidence->sort_order)->update(['status' => 'archived']);
        }, 3);

        return back()->with('status', __('Dalil qoralamada arxivlandi. Oldingi sessiya dalillari saqlanadi.'));
    }
}
