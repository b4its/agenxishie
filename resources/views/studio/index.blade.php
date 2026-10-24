<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>The Open Studio Handbook — Microsoft Word (Naskah Studio)</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Aptos:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- KaTeX math typesetting (CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full flex flex-col antialiased selection:bg-blue-100 selection:text-blue-900" x-data="{
    toasts: [],
    addToast(title, body, type = 'success') {
        const id = Date.now();
        this.toasts.push({ id, title, body, type });
        setTimeout(() => {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }, 4000);
    }
}" @studio-toast.window="addToast($event.detail.title, $event.detail.body, $event.detail.type)">

    <!-- Livewire Standalone Naskah Studio Component -->
    @livewire('naskah-studio-app', ['documentId' => $documentId])

    <!-- Fluent Word Notification Toasts -->
    <div style="position:fixed; bottom:2rem; right:1.5rem; z-index:150; display:flex; flex-direction:column; gap:0.5rem; pointer-events:none; max-width:380px; width:100%;">
        <template x-for="toast in toasts" :key="toast.id">
            <div style="pointer-events:auto; border-radius:6px; border:1px solid #e1dfdd; box-shadow:0 4px 16px rgba(0,0,0,0.12); padding:0.75rem 1rem; font-size:12px; display:flex; align-items:flex-start; justify-content:space-between; background:#ffffff; font-family:var(--ms-font-ui);"
                :style="toast.type === 'success' ? 'border-left:4px solid #107c41;' : (toast.type === 'danger' ? 'border-left:4px solid #c50f1f;' : (toast.type === 'warning' ? 'border-left:4px solid #d83b01;' : 'border-left:4px solid #0f6cbd;'))">
                <div>
                    <strong style="font-weight:600; display:block; color:#242424;" x-text="toast.title"></strong>
                    <span x-show="toast.body" x-text="toast.body" style="margin-top:2px; display:block; color:#616161;"></span>
                </div>
                <button type="button" @click="toasts = toasts.filter(t => t.id !== toast.id)" style="margin-left:0.75rem; background:none; border:none; color:#8a8886; cursor:pointer; font-size:1.1rem; line-height:1;">&times;</button>
            </div>
        </template>
    </div>

    @livewireScripts
    <script>
        function renderLatexEquations() {
            if (typeof window.katex === 'undefined') {
                return;
            }
            document.querySelectorAll('.latex-math-render').forEach(el => {
                const latex = el.getAttribute('data-latex') || el.textContent;
                if (!latex) return;
                try {
                    window.katex.render(latex, el, {
                        throwOnError: false,
                        displayMode: true
                    });
                } catch (e) {
                    console.debug('KaTeX render notice:', e);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(renderLatexEquations, 300);
        });

        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ fail }) => {
                fail(({ preventDefault }) => {
                    preventDefault();
                });
            });

            Livewire.hook('morph.updated', () => {
                renderLatexEquations();
            });

            Livewire.hook('element.updated', () => {
                renderLatexEquations();
            });
        });

        window.addEventListener('livewire:error', (e) => {
            e.preventDefault();
        });
    </script>
</body>
</html>
