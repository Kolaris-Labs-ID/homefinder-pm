@php $current = auth()->user()?->sidebar_skin ?? 'navy'; @endphp

<div
    x-data="{
        skin: @js($current),
        opts: [
            { key: 'light', label: 'Light' },
            { key: 'slate', label: 'Slate' },
            { key: 'navy', label: 'Navy' },
        ],
        set(s) {
            if (this.skin === s) return;
            this.skin = s;
            document.documentElement.dataset.phSkin = s;
            fetch(@js(route('preferences.sidebar-skin')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': @js(csrf_token()),
                },
                body: JSON.stringify({ skin: s }),
            }).catch(() => {});
        },
    }"
    class="ph-skin-switcher"
    role="group"
    aria-label="{{ __('Sidebar theme') }}"
>
    <template x-for="opt in opts" :key="opt.key">
        <button
            type="button"
            class="ph-skin-btn"
            :class="{ 'is-active': skin === opt.key }"
            :data-skin="opt.key"
            :aria-pressed="skin === opt.key"
            @click="set(opt.key)"
        >
            <span class="ph-skin-dot" :data-skin="opt.key"></span>
            <span x-text="opt.label"></span>
        </button>
    </template>
</div>
