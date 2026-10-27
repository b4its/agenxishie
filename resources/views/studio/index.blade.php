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
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous" onload="renderLatexEquations()"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="{{ asset('js/word-studio.js') }}"></script>
    @livewireStyles
</head>
<body class="h-full flex flex-col antialiased selection:bg-blue-100 selection:text-blue-900">

    <!-- Livewire Standalone Naskah Studio Component -->
    @livewire('naskah-studio-app', ['documentId' => $documentId])

    <!-- Modern Interactive Glassmorphic Notification Toast System -->
    @include('components.studio-toast')

    @livewireScripts
    <script>
        function renderLatexEquations() {
            if (typeof window.katex === 'undefined') {
                return;
            }
            // 1. Render display equation blocks
            document.querySelectorAll('.latex-math-render').forEach(el => {
                const latex = el.getAttribute('data-latex') || el.textContent;
                if (!latex) return;
                if (el.dataset.renderedTex === latex) return;
                try {
                    window.katex.render(latex, el, {
                        throwOnError: false,
                        displayMode: true
                    });
                    el.dataset.renderedTex = latex;
                } catch (e) {
                    console.debug('KaTeX render notice:', e);
                }
            });

            // 2. Render inline math expressions ($...$) inside document paragraphs
            document.querySelectorAll('.word-paragraph-text, .word-heading-1, .word-heading-2, .word-quote-text').forEach(p => {
                const sheet = p.closest('.word-sheet-content');
                if (sheet && sheet.contentEditable === 'true' && document.activeElement === sheet) {
                    return;
                }
                if (p.textContent.includes('$') && !p.dataset.mathRendered) {
                    p.innerHTML = p.innerHTML.replace(/\$([^\$]+)\$/g, function(match, math) {
                        try {
                            return window.katex.renderToString(math, { throwOnError: false, displayMode: false });
                        } catch (err) {
                            return match;
                        }
                    });
                    p.dataset.mathRendered = 'true';
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
