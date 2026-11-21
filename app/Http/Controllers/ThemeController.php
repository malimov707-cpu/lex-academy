<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        // Server-side cookie: bir yil, SameSite=Lax (JS o'qishi kerak emas — Blade render qiladi)
        Cookie::queue('academy_theme', $data['theme'], 60 * 24 * 365, '/', null, false, false);

        return back();
    }

    public function bandwidth(Request $request): RedirectResponse
    {
        $current = $request->cookie('academy_bandwidth', 'standard');
        $next = $current === 'low' ? 'standard' : 'low';
        Cookie::queue('academy_bandwidth', $next, 60 * 24 * 365, '/', null, false, false);

        return back();
    }
}
