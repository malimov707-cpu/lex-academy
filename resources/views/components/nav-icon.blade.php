@props(['name' => 'grid'])

@php
    /*
     * Huquqiy ta'lim tematikasidagi 24×24 stroke ikonkalar.
     * SVG path'lar arc flaglar orasida bo'shliqsiz yozilishi shart ("0 0 1").
     * O'lcham va rang `.edu-nav-link svg` orqali boshqariladi (currentColor).
     */
    $paths = [
        'home' => '<path d="m2.5 10.7 9.5-8 9.5 8h-2v10h-5.7v-6.5h-3.6v6.5H4.5v-10z"/>',
        'open-book' => '<path d="M3 4.5c3.4-.7 6.7.1 9 2.3 2.3-2.2 5.6-3 9-2.3v14.2c-3.3-.6-6.4.1-9 2.2-2.6-2.1-5.7-2.8-9-2.2z"/><path d="M12 6.8v14"/>',
        'simulation' => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="m10 8 5 2.6-5 2.6zM12 17v3M8 20h8"/>',
        'mail' => '<rect x="2.5" y="4.7" width="19" height="14.6" rx="2.2"/><path d="m3.5 6 8.5 6.5L20.5 6"/>',
        'gear' => '<path d="m9.6 3 .6-1h3.6l.6 2.4 1.6.9 2.4-.7 1.8 3.1-1.8 1.8v2l1.8 1.8-1.8 3.1-2.4-.7-1.6.9-.6 2.4h-3.6l-.6-2.4-1.6-.9-2.4.7-1.8-3.1 1.8-1.8v-2L3.8 7.7l1.8-3.1 2.4.7 1.6-.9z" transform="translate(0 1)"/><circle cx="12" cy="11.5" r="3"/>',
        'moon' => '<path d="M20.5 14a8.7 8.7 0 0 1-10.6-11A9.4 9.4 0 1 0 20.5 14Z"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'academy-scale' => '<path d="M10 19h4l2.6 2.4H7.4zM11.3 5h1.4v14h-1.4z" fill="currentColor" stroke-width=".8"/><circle cx="12" cy="2.8" r="1.1" fill="currentColor" stroke-width=".6"/><path d="M2.3 6.5c2.5.7 5.3-.5 8.3-1.6m2.8 0c3 1.1 5.8 2.3 8.3 1.6" stroke-width="1.25"/><path d="m4.5 6.9-3.3 7h6.6l-3.3-7zm15 0-3.3 7h6.6l-3.3-7z" stroke="#ad8548" stroke-width=".8"/><path d="M1.1 13.9h6.8c-.6 3.2-6.2 3.2-6.8 0Zm15 0h6.8c-.6 3.2-6.2 3.2-6.8 0Z" fill="currentColor" stroke-width=".5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="7.2"/><path d="m16 16 4.5 4.5"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'courses' => '<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v14.5H6.5A2.5 2.5 0 0 0 4 21z"/><path d="M4 18.5A2.5 2.5 0 0 1 6.5 16H20"/>',
        'lesson' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'module' => '<rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/>',
        'scale' => '<path d="M12 3v18"/><path d="M5 6h14"/><path d="M8 21h8"/><path d="M5 6 2.5 12.5a3 3 0 0 0 5 0L5 6z"/><path d="M19 6l-2.5 6.5a3 3 0 0 0 5 0L19 6z"/>',
        'law' => '<path d="M6 3h9l3 3v15H6z"/><path d="M15 3v3h3"/><path d="M9 11h6"/><path d="M9 15h6"/>',
        'paragraph' => '<path d="M13 5h6"/><path d="M13 9h6"/><path d="M13 13h6"/><path d="M13 17h6"/><path d="M9 3c-2.5 0-4 1.5-4 4v5c0 2.5 1.5 4 4 4h3V3z"/>',
        'cases' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/><path d="M3 12h18"/>',
        'quiz' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.2a2.6 2.6 0 1 1 3.4 2.5c-.6.2-.9.8-.9 1.4v.4"/><path d="M12 17h.01"/>',
        'assignments' => '<rect x="4" y="6" width="16" height="15" rx="2"/><path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6"/><path d="M8.5 13l2 2 4-4"/>',
        'review' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v10a1.5 1.5 0 0 1-1.5 1.5H9l-5 4z"/><path d="M8 9h8"/><path d="M8 12.5h5"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 19.5a6.2 6.2 0 0 1 12.4 0"/><path d="M16.5 5.4a3.2 3.2 0 0 1 0 6.2"/><path d="M17.8 14.2a5.4 5.4 0 0 1 3.4 4.6"/>',
        'groups' => '<circle cx="8" cy="7" r="3"/><circle cx="17" cy="8" r="2.4"/><path d="M2.5 19a5.5 5.5 0 0 1 11 0"/><path d="M14.5 19a4.5 4.5 0 0 1 7 0"/>',
        'schedule' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'attendance' => '<path d="M5 4h14a1 1 0 0 1 1 1v15l-3-2-3 2-3-2-3 2-3-2V5a1 1 0 0 1 1-1z"/><path d="M9 9.5l2 2 4-4"/>',
        'certificate' => '<circle cx="12" cy="9" r="5.5"/><path d="M8.2 13.4L7 21l5-2.6L17 21l-1.2-7.6"/>',
        'analytics' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M2 20h20"/>',
        'reports' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M8.5 13h7"/><path d="M8.5 16.5h4.5"/>',
        'notification' => '<path d="M6 9a6 6 0 1 1 12 0c0 4.5 1.5 6 1.5 6h-15S6 13.5 6 9z"/><path d="M9.8 18.5a2.4 2.4 0 0 0 4.4 0"/>',
        'announcement' => '<path d="M4 10v4a1 1 0 0 0 1 1h2l4 3V6L7 9H5a1 1 0 0 0-1 1z"/><path d="M15 8.5a4 4 0 0 1 0 7"/><path d="M17.5 6a7.5 7.5 0 0 1 0 12"/>',
        'chat' => '<path d="M6 4h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 4V6a2 2 0 0 1 2-2z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3"/><path d="M12 19v3"/><path d="M4.2 6.2L6.4 8.4"/><path d="M17.6 15.6l2.2 2.2"/><path d="M2 12h3"/><path d="M19 12h3"/><path d="M4.2 17.8l2.2-2.2"/><path d="M17.6 8.4l2.2-2.2"/>',
        'sources' => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H11v18H5.5A1.5 1.5 0 0 1 4 19.5z"/><path d="M20 4.5A1.5 1.5 0 0 0 18.5 3H13v18h5.5a1.5 1.5 0 0 0 1.5-1.5z"/>',
        'learning' => '<path d="M3 6.5L12 3l9 3.5L12 10z"/><path d="M6.5 8.5V14c0 1.9 2.5 3.2 5.5 3.2s5.5-1.3 5.5-3.2V8.5"/>',
        'tutor' => '<path d="M8 8a4 4 0 0 1 8 0v1a4 4 0 0 1-8 0z"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/><path d="M17.5 3l2.5 1.5-2.5 1.5z"/>',
        'courtroom' => '<path d="M3 20h18"/><path d="M5 17h14"/><path d="M7 17V9h10v8"/><path d="M5 9h14L12 3z"/><path d="M10 12h4"/>',
        'logout' => '<path d="M15 4h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
        'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"
     {{ $attributes }}>
    {!! $paths[$name] ?? $paths['grid'] !!}
</svg>
