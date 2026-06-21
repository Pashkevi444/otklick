<script setup lang="ts">
// Набор линейных иконок для лендинга (без эмодзи). Все — stroke=currentColor,
// 24×24, тонкая линия со скруглениями. Цвет/размер задаются классами снаружи.
defineProps<{ name: string }>();

const icons: Record<string, string> = {
    chat: '<path d="M21 14a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/><path d="M8 9h8M8 12h5"/>',
    brain: '<path d="M9.5 3A2.5 2.5 0 0 0 7 5.5 3 3 0 0 0 5 11a3 3 0 0 0 1 5.7A2.5 2.5 0 0 0 8.5 20 2.5 2.5 0 0 0 11 17.5V5.5A2.5 2.5 0 0 0 9.5 3Z"/><path d="M14.5 3A2.5 2.5 0 0 1 17 5.5 3 3 0 0 1 19 11a3 3 0 0 1-1 5.7A2.5 2.5 0 0 1 15.5 20 2.5 2.5 0 0 1 13 17.5V5.5"/>',
    calendar: '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
    mic: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
    megaphone: '<path d="M3 11v2a1 1 0 0 0 1 1h2l9 5V5L6 10H4a1 1 0 0 0-1 1Z"/><path d="M18 8a4 4 0 0 1 0 8"/>',
    wand: '<path d="M15 4V2M15 10V8M11 6H9M21 6h-2"/><path d="M13.5 7.5 4 17l3 3 9.5-9.5z"/>',
    template: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 11h6M9 15h4"/>',
    target: '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>',
    users: '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 5.5a3 3 0 0 1 0 5M21 20a6 6 0 0 0-4.5-5.8"/>',
    chart: '<path d="M3 3v18h18"/><path d="M7 14l3-3 3 3 5-6"/>',
    report: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    hand: '<path d="M7 11V6.5a1.5 1.5 0 0 1 3 0V11M10 11V4.5a1.5 1.5 0 0 1 3 0V11M13 11V6.5a1.5 1.5 0 0 1 3 0V14a6 6 0 0 1-6 6 5 5 0 0 1-4.3-2.5L6 14a1.6 1.6 0 0 1 2.7-1.6L10 14"/>',
    gear: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
    shield: '<path d="M12 3l8 3v5c0 5-3.4 8.2-8 10-4.6-1.8-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/>',
    polish: '<path d="M10 3h4v3l1 2v11a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V8l1-2z"/><path d="M9 12h6"/>',
    scissors: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.6 7.6 20 20M8.6 16.4 20 4"/>',
    sparkle: '<path d="M12 3l1.8 4.9L19 9l-5.2 1.1L12 15l-1.8-4.9L5 9l5.2-1.1z"/><path d="M19 14l.6 1.6 1.6.6-1.6.6-.6 1.6-.6-1.6-1.6-.6 1.6-.6z"/>',
    pen: '<path d="M16 3l5 5L8 21H3v-5z"/><path d="M14 5l5 5"/>',
    bag: '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
    briefcase: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/>',
    shop: '<path d="M4 9l1.2-4h13.6L20 9M5 9v10h14V9M4 9h16"/>',
    phone: '<path d="M5 4h3l2 5-2 1a11 11 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
    link: '<path d="M9 15l6-6"/><path d="M10.5 6.5 11.5 5.5a4 4 0 0 1 6 6l-1 1M13.5 17.5l-1 1a4 4 0 0 1-6-6l1-1"/>',
    lock: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    puzzle: '<path d="M10 5a2 2 0 0 1 4 0c0 .9.8 1.4 1.5 1H17a1 1 0 0 1 1 1v1.5c-.4.7.1 1.5 1 1.5a2 2 0 0 1 0 4c-.9 0-1.4.8-1 1.5V18a1 1 0 0 1-1 1h-1.5c-.7-.4-1.5.1-1.5 1a2 2 0 0 1-4 0c0-.9-.8-1.4-1.5-1H6a1 1 0 0 1-1-1v-1.5c.4-.7-.1-1.5-1-1.5a2 2 0 0 1 0-4c.9 0 1.4-.8 1-1.5V7a1 1 0 0 1 1-1h1.5c.7.4 1.5-.1 1.5-1Z"/>',
    book: '<path d="M5 4a2 2 0 0 0-2 2v13a2 2 0 0 1 2-2h14V4z"/><path d="M3 19a2 2 0 0 0 2 2h14v-4"/>',
    bolt: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    send: '<path d="M22 3 2 11l6 2 2 6 3-4 5 4z"/><path d="m8 13 8-7"/>',
    robot: '<rect x="5" y="8" width="14" height="11" rx="3"/><path d="M9 13h.01M15 13h.01M9.5 16h5M12 8V5"/><circle cx="12" cy="4" r="1"/>',
    check: '<path d="M5 12l4 4 10-10"/>',
    close: '<path d="M6 6l12 12M18 6 6 18"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    rocket: '<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2M14 5c3-3 6-3 6-3s0 3-3 6l-5 5-4-4z"/><path d="M9 11l-4 1M13 15l-1 4"/>',
};
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="icons[name] ?? ''" />
</template>
