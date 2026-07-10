<style>
    /* Dark-slate sidebar + tree guide-lines. Injected via panels::head.end (see AdminPanelProvider). */
    :root {
        --ph-sb-bg: #1e293b;
        --ph-sb-bg-header: #172033;
        --ph-sb-text: #cbd5e1;
        --ph-sb-muted: #94a3b8;
        --ph-sb-hover-bg: rgba(255, 255, 255, .06);
        --ph-sb-hover-text: #f1f5f9;
        --ph-sb-active-bg: rgba(59, 130, 246, .16);
        --ph-sb-active-icon: #60a5fa;
        --ph-sb-accent: #3b82f6;
        --ph-sb-guide: rgba(148, 163, 184, .28);
    }

    /* ---- surface ---- */
    .fi-sidebar,
    .fi-sidebar .fi-sidebar-nav {
        background-color: var(--ph-sb-bg) !important;
    }

    .fi-sidebar {
        border-right: 1px solid rgba(0, 0, 0, .25) !important;
    }

    .fi-sidebar-header {
        background-color: var(--ph-sb-bg-header) !important;
        border-bottom: 1px solid rgba(255, 255, 255, .06) !important;
    }

    .fi-sidebar-header .fi-logo {
        color: #f8fafc !important;
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

    /* ---- sub-items: indentation + gray guide line (labeled groups only) ---- */
    .fi-sidebar-group:has(> .fi-sidebar-group-button) > .fi-sidebar-group-items {
        margin-left: 1.15rem;
        padding-left: .6rem;
        border-left: 1.5px solid var(--ph-sb-guide);
    }

    /* ---- item link ---- */
    .fi-sidebar-item-button {
        color: var(--ph-sb-text) !important;
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
        color: #ffffff !important;
        box-shadow: inset 2px 0 0 var(--ph-sb-accent);
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-button .fi-sidebar-item-icon {
        color: var(--ph-sb-active-icon) !important;
    }

    /* ---- thin scrollbar ---- */
    .fi-sidebar-nav {
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, .35) transparent;
    }

    .fi-sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .fi-sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, .3);
        border-radius: 3px;
    }
</style>
