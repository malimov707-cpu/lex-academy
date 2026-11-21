<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademySettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['settings' => AcademySetting::query()->find(1)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'certificate_issuer' => ['required', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
        ]);
        AcademySetting::query()->updateOrCreate(['id' => 1], $data);

        return back()->with('status', __('Akademiya sozlamalari saqlandi.'));
    }
}
