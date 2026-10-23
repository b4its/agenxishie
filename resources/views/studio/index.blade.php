<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>NASKAH STUDIO — Intelligent Academic Word Processor &amp; UX Telemetry Suite</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Space+Grotesk:wght@500;700;800&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        :root {
            --rb-ink: #0F172A;
            --rb-primary: #1E40AF;
            --rb-secondary: #2563EB;
            --rb-surface: #E0F2FE;
            --rb-blueprint: #F0F6FF;
            --rb-border: 2px solid #0F172A;
            --rb-shadow: 4px 4px 0px #0F172A;
            --rb-status-pass: #059669;
            --rb-status-alert: #DC2626;
            --rb-status-hitl: #D97706;
            --rb-font-display: 'Space Grotesk', system-ui, sans-serif;
            --rb-font-mono: 'JetBrains Mono', monospace;
            --rb-font-serif: 'Source Serif 4', Georgia, serif;
            --rb-font-sans: 'Inter', system-ui, sans-serif;
        }
        .dark {
            --rb-ink: #F8FAFC;
            --rb-primary: #3B82F6;
            --rb-secondary: #60A5FA;
            --rb-surface: #1E293B;
            --rb-blueprint: #0B0F19;
            --rb-border: 2px solid #94A3B8;
            --rb-shadow: 4px 4px 0px #020617;
        }
        body {
            background-color: var(--rb-blueprint);
            color: var(--rb-ink);
            font-family: var(--rb-font-sans);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
    </style>
</head>
<body class="h-full flex flex-col antialiased selection:bg-blue-200 selection:text-blue-900" x-data="{
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

    <!-- Floating Toast Notifications -->
    <div class="fixed bottom-6 right-6 z-50 flex flex-col space-y-2 pointer-events-none max-w-sm w-full">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="pointer-events-auto border-2 border-slate-900 shadow-[4px_4px_0px_#0f172a] p-3 text-xs flex items-start justify-between transition transform duration-200"
                :class="{
                    'bg-emerald-50 text-emerald-950 border-emerald-900': toast.type === 'success',
                    'bg-amber-50 text-amber-950 border-amber-900': toast.type === 'warning',
                    'bg-red-50 text-red-950 border-red-900': toast.type === 'danger',
                    'bg-blue-50 text-blue-950 border-blue-900': toast.type === 'info'
                }">
                <div>
                    <strong class="font-bold block" x-text="toast.title"></strong>
                    <span x-show="toast.body" x-text="toast.body" class="mt-0.5 block opacity-90"></span>
                </div>
                <button type="button" @click="toasts = toasts.filter(t => t.id !== toast.id)" class="ml-3 font-bold opacity-70 hover:opacity-100">&times;</button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
