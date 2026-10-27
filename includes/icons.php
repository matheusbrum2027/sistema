<?php
declare(strict_types=1);

function icon(string $name): string
{
    $paths = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'pin' => '<path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'activity' => '<path d="M3 12h4l2.2-6 3.6 12L15.2 12H21"/>',
        'folder' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H9l2 2h7.5A2.5 2.5 0 0 1 21 9.5v8A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5v-10z"/>',
        'chart' => '<path d="M4 19V5M4 19h16"/><path d="M8 15v-3M12 15V8M16 15v-5"/>',
        'list' => '<path d="M9 7h11M9 12h11M9 17h11"/><path d="M4.5 7h.01M4.5 12h.01M4.5 17h.01"/>',
        'inbox' => '<path d="M3 13l2.2-7.2A2 2 0 0 1 7.1 4.5h9.8a2 2 0 0 1 1.9 1.3L21 13"/><path d="M3 13h5l1.2 2h5.6L16 13h5v5.2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V13z"/>',
        'receipt' => '<path d="M6 3h12v18l-2-1.2L14 21l-2-1.2L10 21l-2-1.2L6 21V3z"/><path d="M9 8h6M9 12h6"/>',
        'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
        'bars' => '<path d="M5 20V10M12 20V4M19 20v-7"/>',
        'route' => '<circle cx="6" cy="6" r="2.2"/><circle cx="18" cy="18" r="2.2"/><path d="M8 7.2 15.2 16"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/>',
        'book' => '<path d="M5 4.5h9.5A3.5 3.5 0 0 1 18 8v12H8a3 3 0 0 0-3 3V4.5z"/><path d="M5 19.5A3 3 0 0 1 8 16.5h10"/>',
        'users' => '<circle cx="9" cy="9" r="3"/><path d="M3.8 19a5.2 5.2 0 0 1 10.4 0"/><circle cx="17" cy="9.5" r="2.2"/><path d="M16.2 14.2a4.2 4.2 0 0 1 4 3.6"/>',
        'user' => '<circle cx="12" cy="8" r="3.2"/><path d="M5 19.2a7 7 0 0 1 14 0"/>',
        'car' => '<path d="M4 15h16M6 15l1.2-4.2A2 2 0 0 1 9.1 9.2h5.8a2 2 0 0 1 1.9 1.6L18 15"/><circle cx="7.5" cy="16.5" r="1.4"/><circle cx="16.5" cy="16.5" r="1.4"/><path d="M4 15v2.2h2M18 15v2.2h2"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="12" r="2"/><path d="M14 10h4M14 14h3"/>',
        'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3v2.2M4.8 6.6l1.6 1.6M17.6 15.8l1.6 1.6M3.5 12h2.2M18.3 12h2.2M4.8 17.4l1.6-1.6M17.6 8.2l1.6-1.6"/>',
        'sliders' => '<path d="M4 8h16M4 16h16"/><circle cx="9" cy="8" r="2"/><circle cx="15" cy="16" r="2"/>',
        'zap' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>',
        'monitor' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'note' => '<path d="M7 3.5h7l5 5V20a1.5 1.5 0 0 1-1.5 1.5h-10.5A1.5 1.5 0 0 1 5.5 20V5A1.5 1.5 0 0 1 7 3.5z"/><path d="M14 3.8V9h5"/>',
        'logout' => '<path d="M10 7V5.5A1.5 1.5 0 0 1 11.5 4h7A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 10 18.5V17"/><path d="M4 12h10M11 9l3 3-3 3"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.2-5.5"/><path d="M20 4v5h-5"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16.5 20.5 21"/>',
        'chevron' => '<path d="M6 9.5 12 15.5 18 9.5"/>',
        'printer' => '<path d="M7 8V4h10v4"/><rect x="5" y="14" width="14" height="6" rx="1"/><path d="M5 16H4a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1"/>',
        'funnel' => '<path d="M4 5h16l-6 7.2V19l-4 1.5v-8.3L4 5z"/>',
        'zoom' => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-3.5-3.5M11 8.5v5M8.5 11h5"/>',
        'moon' => '<path d="M16.5 3.5a8.5 8.5 0 1 0 4 13.5A7 7 0 1 1 16.5 3.5z"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];
    $inner = $paths[$name] ?? $paths['file'];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
