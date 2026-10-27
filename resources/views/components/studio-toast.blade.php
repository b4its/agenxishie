{{-- Modern Interactive Glassmorphic Notification Toast System --}}
<style>
    .ns-toast-container {
        position: fixed;
        top: 1.25rem;
        right: 1.5rem;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        pointer-events: none;
        max-width: 420px;
        width: calc(100vw - 2.5rem);
    }
    .ns-toast-card {
        pointer-events: auto;
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border: 1px solid rgba(226, 232, 240, 0.85);
        box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.18), 0 4px 16px -4px rgba(15, 23, 42, 0.08);
        padding: 0.95rem 1.15rem;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        font-family: 'Aptos', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .ns-toast-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.24), 0 6px 20px -4px rgba(15, 23, 42, 0.12);
    }
    .dark .ns-toast-card,
    .ns-root--dark .ns-toast-card {
        background: rgba(15, 23, 42, 0.94);
        border-color: rgba(51, 65, 85, 0.7);
        box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.5), 0 4px 16px -4px rgba(0, 0, 0, 0.3);
    }
    .dark .ns-toast-title,
    .ns-root--dark .ns-toast-title {
        color: #f8fafc !important;
    }
    .dark .ns-toast-body,
    .ns-root--dark .ns-toast-body {
        color: #cbd5e1 !important;
    }
    .dark .ns-toast-meta,
    .ns-root--dark .ns-toast-meta {
        border-top-color: rgba(51, 65, 85, 0.6) !important;
    }
    .ns-toast-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        outline: none;
    }
    .ns-toast-btn:hover {
        transform: translateY(-1px);
    }
    .ns-toast-btn:active {
        transform: translateY(0);
    }
    @keyframes nsToastPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.05); }
    }
    .ns-toast-pulse {
        animation: nsToastPulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<div x-data="{
    toasts: [],
    addToast(title, body, type = 'success', action = null) {
        const id = Date.now() + Math.random().toString(36).substring(2, 6);
        const duration = 5000;
        const toast = {
            id,
            title: title || 'Notifikasi',
            body: body || '',
            type: type || 'success',
            action: action || null,
            progress: 100,
            paused: false,
            remaining: duration,
            startTime: Date.now(),
            animFrame: null
        };

        const updateProgress = () => {
            if (toast.paused) return;
            const elapsed = Date.now() - toast.startTime;
            toast.progress = Math.max(0, 100 - (elapsed / duration * 100));
            if (toast.progress <= 0) {
                this.removeToast(id);
            } else {
                toast.animFrame = requestAnimationFrame(updateProgress);
            }
        };

        toast.animFrame = requestAnimationFrame(updateProgress);
        this.toasts.unshift(toast);
        if (this.toasts.length > 4) {
            const oldToast = this.toasts.pop();
            if (oldToast && oldToast.animFrame) cancelAnimationFrame(oldToast.animFrame);
        }
    },
    pauseToast(toast) {
        toast.paused = true;
        if (toast.animFrame) cancelAnimationFrame(toast.animFrame);
        toast.remaining = (toast.progress / 100) * 5000;
    },
    resumeToast(toast) {
        toast.paused = false;
        toast.startTime = Date.now() - (5000 - toast.remaining);
        const updateProgress = () => {
            if (toast.paused) return;
            const elapsed = Date.now() - toast.startTime;
            toast.progress = Math.max(0, 100 - (elapsed / 5000 * 100));
            if (toast.progress <= 0) {
                this.removeToast(toast.id);
            } else {
                toast.animFrame = requestAnimationFrame(updateProgress);
            }
        };
        toast.animFrame = requestAnimationFrame(updateProgress);
    },
    removeToast(id) {
        const idx = this.toasts.findIndex(t => t.id === id);
        if (idx !== -1) {
            if (this.toasts[idx].animFrame) cancelAnimationFrame(this.toasts[idx].animFrame);
            this.toasts.splice(idx, 1);
        }
    },
    executeLivewire(method, ...args) {
        if (typeof this.$wire !== 'undefined' && typeof this.$wire[method] === 'function') {
            this.$wire[method](...args);
            return;
        }
        if (window.Livewire) {
            if (typeof window.Livewire.all === 'function') {
                const components = window.Livewire.all();
                for (const comp of components) {
                    if (typeof comp[method] === 'function') {
                        comp[method](...args);
                        return;
                    }
                }
            }
            const el = document.querySelector('[wire\\:id]');
            if (el && typeof window.Livewire.find === 'function') {
                const comp = window.Livewire.find(el.getAttribute('wire:id'));
                if (comp && typeof comp[method] === 'function') {
                    comp[method](...args);
                    return;
                }
            }
        }
    }
}"
@studio-toast.window="addToast($event.detail.title, $event.detail.body, $event.detail.type, $event.detail.action)"
class="ns-toast-container"
aria-live="polite">

    <template x-for="toast in toasts" :key="toast.id">
        <div class="ns-toast-card"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 translate-x-8 scale-95"
             @mouseenter="pauseToast(toast)"
             @mouseleave="resumeToast(toast)">

            <!-- Glowing Ambient Accent Tint -->
            <div style="position:absolute; inset:0; pointer-events:none; opacity:0.045;"
                 :style="toast.type === 'success' ? 'background:linear-gradient(135deg, #10b981 0%, #059669 100%);' : (toast.type === 'danger' || toast.type === 'error' ? 'background:linear-gradient(135deg, #ef4444 0%, #dc2626 100%);' : (toast.type === 'warning' ? 'background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);' : 'background:linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);'))">
            </div>

            <!-- Top Row: Squircle Icon + Content + Dismiss Button -->
            <div style="display:flex; align-items:flex-start; gap:0.75rem; justify-content:space-between; position:relative; z-index:1;">
                <!-- Icon with Gradient Squircle -->
                <div class="ns-toast-pulse"
                     style="width:36px; height:36px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:16px; color:#ffffff; box-shadow:0 4px 12px rgba(0,0,0,0.14);"
                     :style="toast.type === 'success' ? 'background:linear-gradient(135deg, #10b981 0%, #059669 100%);' : (toast.type === 'danger' || toast.type === 'error' ? 'background:linear-gradient(135deg, #ef4444 0%, #dc2626 100%);' : (toast.type === 'warning' ? 'background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);' : 'background:linear-gradient(135deg, #6366f1 0%, #a855f7 100%);'))">
                    <template x-if="toast.type === 'success'">
                        <svg style="width:19px; height:19px; stroke:currentColor; stroke-width:2.5; fill:none; stroke-linecap:round; stroke-linejoin:round;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </template>
                    <template x-if="toast.type === 'danger' || toast.type === 'error'">
                        <svg style="width:19px; height:19px; stroke:currentColor; stroke-width:2.5; fill:none; stroke-linecap:round; stroke-linejoin:round;" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </template>
                    <template x-if="toast.type === 'warning'">
                        <svg style="width:19px; height:19px; stroke:currentColor; stroke-width:2.5; fill:none; stroke-linecap:round; stroke-linejoin:round;" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </template>
                    <template x-if="toast.type !== 'success' && toast.type !== 'danger' && toast.type !== 'error' && toast.type !== 'warning'">
                        <svg style="width:19px; height:19px; stroke:currentColor; stroke-width:2.5; fill:none; stroke-linecap:round; stroke-linejoin:round;" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    </template>
                </div>

                <!-- Text Content -->
                <div style="flex:1; min-width:0; padding-top:1px;">
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <strong class="ns-toast-title" style="font-weight:700; font-size:13px; color:#0f172a; letter-spacing:-0.01em; line-height:1.3;" x-text="toast.title"></strong>
                        <!-- Chic Badge Pill -->
                        <span style="font-size:9.5px; font-weight:700; padding:1.5px 7px; border-radius:5px; letter-spacing:0.4px; text-transform:uppercase;"
                              :style="toast.type === 'success' ? 'background:#d1fae5; color:#065f46; border:1px solid #a7f3d0;' : (toast.type === 'danger' || toast.type === 'error' ? 'background:#fee2e2; color:#991b1b; border:1px solid #fecaca;' : (toast.type === 'warning' ? 'background:#fef3c7; color:#92400e; border:1px solid #fde68a;' : 'background:#ede9fe; color:#5b21b6; border:1px solid #ddd6fe;'))"
                              x-text="toast.type === 'success' ? 'Berhasil' : (toast.type === 'danger' || toast.type === 'error' ? 'Galat' : (toast.type === 'warning' ? 'Peringatan' : 'Xishie AI'))">
                        </span>
                    </div>
                    <div class="ns-toast-body" x-show="toast.body" x-text="toast.body" style="margin-top:4px; font-size:12px; color:#475569; line-height:1.45; word-break:break-word;"></div>
                </div>

                <!-- Dismiss Button -->
                <button type="button"
                        @click="removeToast(toast.id)"
                        style="width:24px; height:24px; border-radius:6px; display:flex; align-items:center; justify-content:center; background:none; border:none; color:#94a3b8; cursor:pointer; font-size:16px; line-height:1; transition:all 0.15s ease;"
                        onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a';"
                        onmouseout="this.style.background='none'; this.style.color='#94a3b8';"
                        title="Tutup Notifikasi">
                    &times;
                </button>
            </div>

            <!-- Custom Explicit Action Button if Provided -->
            <template x-if="toast.action && toast.action.label && toast.action.method">
                <div class="ns-toast-meta" style="display:flex; align-items:center; gap:6px; margin-top:6px; padding-top:6px; border-top:1px dashed #e2e8f0; position:relative; z-index:1;">
                    <button type="button"
                            @click="executeLivewire(toast.action.method, ...(toast.action.params || [])); removeToast(toast.id);"
                            class="ns-toast-btn"
                            style="background:#4f46e5; color:#ffffff; border:1px solid #4338ca; box-shadow:0 1px 2px rgba(79,70,229,0.2);">
                        <span x-text="toast.action.label"></span>
                    </button>
                </div>
            </template>

            <!-- Contextual Interactive Action Bar: Model Execution (Generate Ulang / Terima / Batal) -->
            <template x-if="!toast.action && (toast.title.toLowerCase().includes('model') || toast.title.toLowerCase().includes('diterapkan') || toast.title.toLowerCase().includes('sub-bab') || toast.title.toLowerCase().includes('pustaka'))">
                <div class="ns-toast-meta" style="display:flex; align-items:center; gap:6px; margin-top:6px; padding-top:6px; border-top:1px dashed #e2e8f0; position:relative; z-index:1;">
                    <button type="button"
                            @click="executeLivewire('regenerateCurrentModel'); removeToast(toast.id);"
                            class="ns-toast-btn"
                            style="background:#eef2ff; color:#4338ca; border:1px solid #c7d2fe;"
                            onmouseover="this.style.background='#e0e7ff';"
                            onmouseout="this.style.background='#eef2ff';">
                        <span>🔄</span> Generate Ulang
                    </button>
                    <button type="button"
                            @click="executeLivewire('acceptModelOutput'); removeToast(toast.id);"
                            class="ns-toast-btn"
                            style="background:#16a34a; color:#ffffff; border:1px solid #15803d; box-shadow:0 1px 2px rgba(22,163,74,0.2);"
                            onmouseover="this.style.background='#15803d';"
                            onmouseout="this.style.background='#16a34a';">
                        <span>✓</span> Terima
                    </button>
                    <button type="button"
                            @click="executeLivewire('discardModelOutput'); removeToast(toast.id);"
                            class="ns-toast-btn"
                            style="background:#ffffff; color:#dc2626; border:1px solid #fecaca;"
                            onmouseover="this.style.background='#fef2f2';"
                            onmouseout="this.style.background='#ffffff';">
                        Batal
                    </button>
                </div>
            </template>

            <!-- Contextual Status: Naskah Tersimpan -->
            <template x-if="toast.title.toLowerCase().includes('tersimpan')">
                <div class="ns-toast-meta" style="display:flex; align-items:center; justify-content:space-between; margin-top:5px; padding-top:5px; border-top:1px dashed #e2e8f0; font-size:11px; position:relative; z-index:1;">
                    <span style="color:#059669; font-weight:600; display:inline-flex; align-items:center; gap:5px;">
                        <span style="width:6px; height:6px; border-radius:50%; background:#10b981; display:inline-block;"></span>
                        Tersinkronisasi otomatis
                    </span>
                    <span style="color:#94a3b8; font-size:10px; font-variant-numeric:tabular-nums;" x-text="new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'})"></span>
                </div>
            </template>

            <!-- Dynamic Animated Progress Bar (Counts Down 100% -> 0%) -->
            <div style="position:absolute; bottom:0; left:0; height:3.5px; border-radius:0 0 16px 16px; transition:width 0.1s linear;"
                 :style="(toast.type === 'success' ? 'background:linear-gradient(90deg, #10b981, #059669);' : (toast.type === 'danger' || toast.type === 'error' ? 'background:linear-gradient(90deg, #ef4444, #dc2626);' : (toast.type === 'warning' ? 'background:linear-gradient(90deg, #f59e0b, #d97706);' : 'background:linear-gradient(90deg, #6366f1, #a855f7);'))) + ' width:' + toast.progress + '%;'">
            </div>
        </div>
    </template>
</div>
