@php $skin = auth()->user()?->sidebar_skin ?? 'navy'; @endphp

{{-- Stamp the skin on <html> before <body> paints → no flash of wrong skin. --}}
<script>document.documentElement.dataset.phSkin = @js($skin);</script>

<style>
    /*
     * Switchable sidebar skins (light / slate / navy) + tree guide-lines.
     * Injected via panels::head.end. Skin is chosen per-user (users.sidebar_skin)
     * and applied through html[data-ph-skin]. Independent of Filament light/dark mode.
     */

    /* ---- navy (default, image #3 / growyze) ---- */
    html[data-ph-skin="navy"] {
        --ph-sb-bg: #1a1a5e;
        --ph-sb-bg-header: #14144d;
        --ph-sb-text: #c9cbf0;
        --ph-sb-muted: #9698d4;
        --ph-sb-hover-bg: rgba(255, 255, 255, .08);
        --ph-sb-hover-text: #f4f4ff;
        --ph-sb-active-bg: rgba(99, 102, 241, .32);
        --ph-sb-active-text: #ffffff;
        --ph-sb-active-icon: #a5b4fc;
        --ph-sb-accent: #6366f1;
        --ph-sb-guide: rgba(255, 255, 255, .20);
        --ph-sb-border: rgba(255, 255, 255, .08);
    }

    /* ---- slate (image #2 / Finalytic) ---- */
    html[data-ph-skin="slate"] {
        --ph-sb-bg: #1e293b;
        --ph-sb-bg-header: #172033;
        --ph-sb-text: #cbd5e1;
        --ph-sb-muted: #94a3b8;
        --ph-sb-hover-bg: rgba(255, 255, 255, .06);
        --ph-sb-hover-text: #f1f5f9;
        --ph-sb-active-bg: rgba(59, 130, 246, .16);
        --ph-sb-active-text: #ffffff;
        --ph-sb-active-icon: #60a5fa;
        --ph-sb-accent: #3b82f6;
        --ph-sb-guide: rgba(148, 163, 184, .28);
        --ph-sb-border: rgba(255, 255, 255, .06);
    }

    /* ---- light (image #1) ---- */
    html[data-ph-skin="light"] {
        --ph-sb-bg: #f6f5f7;
        --ph-sb-bg-header: #ffffff;
        --ph-sb-text: #1f2937;
        --ph-sb-muted: #6b7280;
        --ph-sb-hover-bg: rgba(15, 23, 42, .05);
        --ph-sb-hover-text: #0f172a;
        --ph-sb-active-bg: rgba(37, 99, 235, .10);
        --ph-sb-active-text: #111827;
        --ph-sb-active-icon: #2563eb;
        --ph-sb-accent: #2563eb;
        --ph-sb-guide: rgba(15, 23, 42, .14);
        --ph-sb-border: rgba(15, 23, 42, .08);
    }

    /* ============================================================
       Everything below is skin-agnostic: it reads the vars above.
       ============================================================ */

    /* ---- surface ---- */
    .fi-sidebar,
    .fi-sidebar .fi-sidebar-nav {
        background-color: var(--ph-sb-bg) !important;
    }

    .fi-sidebar {
        border-right: 1px solid var(--ph-sb-border) !important;
    }

    .fi-sidebar-header {
        background-color: var(--ph-sb-bg-header) !important;
        border-bottom: 1px solid var(--ph-sb-border) !important;
    }

    .fi-sidebar-header .fi-logo {
        color: var(--ph-sb-hover-text) !important;
    }

    /* ---- group header (collapse toggle) ---- */
    .fi-sidebar-group-label {
        color: var(--ph-sb-muted) !important;
        letter-spacing: .02em;
    }

    .fi-sidebar-group-icon,
    .fi-sidebar-group-collapse-button {
        color: var(--ph-sb-muted) !important;
    }

    .fi-sidebar-group-button {
        border-radius: .5rem;
    }

    .fi-sidebar-group-button:hover {
        background-color: var(--ph-sb-hover-bg);
    }

    /* subtle divider between labeled groups (image #2) */
    .fi-sidebar-nav .fi-sidebar-group + .fi-sidebar-group {
        border-top: 1px solid var(--ph-sb-border);
        margin-top: .35rem;
        padding-top: .35rem;
    }

    /* ---- tree: vertical guide + L-shaped tick per item (labeled groups only) ---- */
    .fi-sidebar-group:has(> .fi-sidebar-group-button) > .fi-sidebar-group-items {
        margin-left: 1.15rem;
        padding-left: .75rem;
        border-left: 1.5px solid var(--ph-sb-guide);
    }

    .fi-sidebar-group:has(> .fi-sidebar-group-button) > .fi-sidebar-group-items > .fi-sidebar-item {
        position: relative;
    }

    .fi-sidebar-group:has(> .fi-sidebar-group-button) > .fi-sidebar-group-items > .fi-sidebar-item::before {
        content: "";
        position: absolute;
        left: -0.75rem;
        top: 50%;
        width: .6rem;
        height: 1.5px;
        background: var(--ph-sb-guide);
        transform: translateY(-50%);
    }

    /* ---- item link ---- */
    .fi-sidebar-item-button {
        color: var(--ph-sb-text) !important;
        border-radius: .5rem;
    }

    .fi-sidebar-item-icon {
        color: var(--ph-sb-muted) !important;
    }

    .fi-sidebar-item-button:hover {
        background-color: var(--ph-sb-hover-bg) !important;
        color: var(--ph-sb-hover-text) !important;
    }

    .fi-sidebar-item-button:hover .fi-sidebar-item-icon {
        color: var(--ph-sb-hover-text) !important;
    }

    /* ---- active item ---- */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-button {
        background-color: var(--ph-sb-active-bg) !important;
        color: var(--ph-sb-active-text) !important;
        box-shadow: inset 2px 0 0 var(--ph-sb-accent);
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-button .fi-sidebar-item-icon {
        color: var(--ph-sb-active-icon) !important;
    }

    /* ---- badges (image #2/#3 counts) ---- */
    .fi-sidebar-item-badge {
        background-color: var(--ph-sb-accent) !important;
        color: #ffffff !important;
    }

    /* ---- thin scrollbar ---- */
    .fi-sidebar-nav {
        scrollbar-width: thin;
        scrollbar-color: var(--ph-sb-guide) transparent;
    }

    .fi-sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .fi-sidebar-nav::-webkit-scrollbar-thumb {
        background: var(--ph-sb-guide);
        border-radius: 3px;
    }

    /* ============================================================
       Skin switcher (injected at panels::sidebar.nav.end)
       ============================================================ */
    .ph-skin-switcher {
        display: flex;
        gap: .25rem;
        margin: .5rem .5rem 0;
        padding: .35rem;
        border-top: 1px solid var(--ph-sb-border);
        border-radius: .5rem;
    }

    .ph-skin-btn {
        display: flex;
        align-items: center;
        gap: .35rem;
        flex: 1;
        justify-content: center;
        padding: .3rem .35rem;
        border-radius: .4rem;
        font-size: .7rem;
        font-weight: 500;
        color: var(--ph-sb-muted);
        cursor: pointer;
        transition: background-color .12s ease, color .12s ease;
    }

    .ph-skin-btn:hover {
        background-color: var(--ph-sb-hover-bg);
        color: var(--ph-sb-hover-text);
    }

    .ph-skin-btn.is-active {
        background-color: var(--ph-sb-active-bg);
        color: var(--ph-sb-active-text);
    }

    .ph-skin-dot {
        width: .7rem;
        height: .7rem;
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, .35);
        flex: none;
    }

    .ph-skin-dot[data-skin="light"] { background: #f6f5f7; }
    .ph-skin-dot[data-skin="slate"] { background: #1e293b; }
    .ph-skin-dot[data-skin="navy"] { background: #1a1a5e; }
</style>
