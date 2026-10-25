/**
 * Naskah Studio — Microsoft Word & LibreOffice Writer Canvas Engine
 * Menangani pengetikan alami, pintasan keyboard, alur penulisan lancar tanpa jeda,
 * tipografi cerdas (Smart Typography), format instan tanpa kehilangan kursor,
 * peleburan blok (merge on backspace/delete), dan navigasi tabel Word.
 */
window.wordStudioApp = function(config) {
    return {
        zenMode: config.zenMode,
        activeTaskPane: config.activeTaskPane,
        commentsOpen: config.commentsOpen,
        revisionsOpen: config.revisionsOpen,
        assistantPane: config.assistantPane,
        navTab: config.navTab,
        activeBlock: config.activeBlock,
        activePage: 1,
        showMiniToolbar: false,
        toolbarTop: 0,
        toolbarLeft: 0,
        selectedText: '',
        copilotMenuOpen: false,
        showBlockCopilot: false,
        blockCopilotTop: 0,
        blockCopilotLeft: 0,
        savingState: 'saved', // 'saving' | 'saved' | 'unsaved'
        liveWordCount: 0,
        liveCharCount: 0,
        currentFormat: {
            bold: false,
            italic: false,
            underline: false,
            strike: false,
            subscript: false,
            superscript: false,
            align: 'left',
            style: 'normal', // 'normal' | 'heading1' | 'heading2' | 'heading3' | 'title' | 'subtitle' | 'quote' | 'nospacing'
            isBullet: false,
            isNumbered: false,
            tag: 'p',
        },
        copiedFormat: null,
        _syncTimer: null,
        _inputTimer: null,
        _selTimer: null,

        getWire() {
            return this.$wire || config.wire || (window.Livewire ? window.Livewire.find(this.$el?.closest('[wire\\:id]')?.getAttribute('wire:id')) : null);
        },

        updateCounts() {
            let fullText = '';
            document.querySelectorAll('.word-sheet-content').forEach(sheet => {
                fullText += ' ' + (sheet.innerText || sheet.textContent || '');
            });
            const clean = fullText.trim();
            this.liveWordCount = clean ? clean.split(/\s+/).length : 0;
            this.liveCharCount = clean.length;
        },

        updateCurrentFormat() {
            this.updateCounts();
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;

            try {
                this.currentFormat.bold = document.queryCommandState('bold');
                this.currentFormat.italic = document.queryCommandState('italic');
                this.currentFormat.underline = document.queryCommandState('underline');
                this.currentFormat.strike = document.queryCommandState('strikeThrough');
                this.currentFormat.subscript = document.queryCommandState('subscript');
                this.currentFormat.superscript = document.queryCommandState('superscript');
            } catch (e) {}

            const anchor = sel.anchorNode;
            if (!anchor) return;
            const el = anchor.nodeType === 1 ? anchor : anchor.parentElement;
            if (!el) return;

            const block = el.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote');
            if (block) {
                const tag = block.tagName.toLowerCase();
                this.currentFormat.tag = tag;
                this.currentFormat.align = block.style.textAlign || block.getAttribute('align') || 'left';
                this.currentFormat.isBullet = block.closest('ul') !== null;
                this.currentFormat.isNumbered = block.closest('ol') !== null;

                if (tag === 'h1' || block.classList.contains('word-title-text')) {
                    this.currentFormat.style = 'title';
                } else if (tag === 'h2' || block.classList.contains('word-heading-1')) {
                    this.currentFormat.style = 'heading1';
                } else if (tag === 'h3' || block.classList.contains('word-heading-2')) {
                    this.currentFormat.style = 'heading2';
                } else if (tag === 'h4' || block.classList.contains('word-heading-3')) {
                    this.currentFormat.style = 'heading3';
                } else if (tag === 'blockquote' || block.classList.contains('word-quote-block')) {
                    this.currentFormat.style = 'quote';
                } else if (block.classList.contains('word-subtitle-text')) {
                    this.currentFormat.style = 'subtitle';
                } else {
                    this.currentFormat.style = 'normal';
                }
            }
        },

        syncAllPages(immediate = false) {
            clearTimeout(this._syncTimer);
            this.savingState = 'saving';
            const doSync = () => {
                const pageEls = document.querySelectorAll('.word-sheet-content');
                const pageHtmls = [];
                pageEls.forEach(el => pageHtmls.push(el.innerHTML));
                if (pageHtmls.length > 0) {
                    const wire = this.getWire();
                    if (wire) {
                        wire.syncManuscriptPages(pageHtmls);
                        setTimeout(() => { this.savingState = 'saved'; }, 300);
                    } else {
                        this.savingState = 'saved';
                    }
                } else {
                    this.savingState = 'saved';
                }
                this.updateCounts();
            };
            if (immediate) {
                doSync();
            } else {
                this._syncTimer = setTimeout(doSync, 500);
            }
        },

        syncPages() {
            this.syncAllPages(true);
        },

        saveDocument() {
            this.savingState = 'saving';
            const pageEls = document.querySelectorAll('.word-sheet-content');
            const pageHtmls = [];
            pageEls.forEach(el => pageHtmls.push(el.innerHTML));
            const wire = this.getWire();
            if (wire) {
                wire.save(pageHtmls);
            }
        },

        applyBlockStyle(style) {
            const sel = window.getSelection();
            let blockEl = null;
            if (sel && sel.anchorNode) {
                blockEl = sel.anchorNode.nodeType === 1
                    ? sel.anchorNode.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote')
                    : sel.anchorNode.parentElement?.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote');
            }
            if (!blockEl && this.activeBlock !== null) {
                blockEl = document.querySelector('[data-block-index="' + this.activeBlock + '"]');
            }

            if (blockEl) {
                const innerHtml = blockEl.innerHTML;
                let newTag = 'p';
                let newClass = 'word-paragraph-text';

                switch (style) {
                    case 'heading1':
                        newTag = 'h2';
                        newClass = 'word-heading-1';
                        break;
                    case 'heading2':
                        newTag = 'h3';
                        newClass = 'word-heading-2';
                        break;
                    case 'heading3':
                        newTag = 'h4';
                        newClass = 'word-heading-3';
                        break;
                    case 'title':
                        newTag = 'h1';
                        newClass = 'word-title-text';
                        break;
                    case 'subtitle':
                        newTag = 'p';
                        newClass = 'word-subtitle-text';
                        break;
                    case 'quote':
                        newTag = 'blockquote';
                        newClass = 'word-quote-block';
                        break;
                    default:
                        newTag = 'p';
                        newClass = 'word-paragraph-text';
                        break;
                }

                const newEl = document.createElement(newTag);
                newEl.className = newClass;
                if (style === 'quote') {
                    const quoteP = document.createElement('p');
                    quoteP.className = 'word-quote-text';
                    quoteP.innerHTML = innerHtml;
                    newEl.appendChild(quoteP);
                } else {
                    newEl.innerHTML = innerHtml;
                }

                const blockIdx = blockEl.getAttribute('data-block-index');
                if (blockIdx !== null) {
                    newEl.setAttribute('data-block-index', blockIdx);
                }

                blockEl.replaceWith(newEl);
                this.focusNode(newEl, true);
                this.updateCurrentFormat();
                this.syncAllPages();
            } else if (this.activeBlock !== null) {
                const wire = this.getWire();
                if (wire) {
                    wire.applyStyle(this.activeBlock, style);
                }
            }
        },

        applyAlignment(align) {
            const sel = window.getSelection();
            let blockEl = null;
            if (sel && sel.anchorNode) {
                blockEl = sel.anchorNode.nodeType === 1
                    ? sel.anchorNode.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote')
                    : sel.anchorNode.parentElement?.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote');
            }
            if (!blockEl && this.activeBlock !== null) {
                blockEl = document.querySelector('[data-block-index="' + this.activeBlock + '"]');
            }
            if (blockEl) {
                blockEl.style.textAlign = align;
            }
            const cmd = align === 'center' ? 'justifyCenter' : (align === 'right' ? 'justifyRight' : (align === 'justify' ? 'justifyFull' : 'justifyLeft'));
            try {
                document.execCommand(cmd, false, null);
            } catch (e) {}
            this.currentFormat.align = align;
            this.syncAllPages();
        },

        applyIndent(delta) {
            const sel = window.getSelection();
            let blockEl = null;
            if (sel && sel.anchorNode) {
                blockEl = sel.anchorNode.nodeType === 1
                    ? sel.anchorNode.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote')
                    : sel.anchorNode.parentElement?.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote');
            }
            if (blockEl && blockEl.tagName !== 'LI') {
                const curMargin = parseFloat(blockEl.style.marginLeft || '0');
                const newMargin = Math.max(0, curMargin + (delta > 0 ? 1.5 : -1.5));
                blockEl.style.marginLeft = newMargin > 0 ? (newMargin + 'rem') : '';
                this.syncAllPages();
                return;
            }
            if (delta > 0) {
                document.execCommand('indent', false, null);
            } else {
                document.execCommand('outdent', false, null);
            }
            this.syncAllPages();
        },

        applyFontSize(size) {
            const sel = window.getSelection();
            if (sel && !sel.isCollapsed) {
                document.execCommand('fontSize', false, '7');
                const fontEls = document.querySelectorAll('font[size="7"]');
                fontEls.forEach(el => {
                    const span = document.createElement('span');
                    span.style.fontSize = size + 'pt';
                    span.innerHTML = el.innerHTML;
                    el.replaceWith(span);
                });
            } else {
                let blockEl = null;
                if (sel && sel.anchorNode) {
                    blockEl = sel.anchorNode.nodeType === 1
                        ? sel.anchorNode.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote')
                        : sel.anchorNode.parentElement?.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote');
                }
                if (blockEl) {
                    blockEl.style.fontSize = size + 'pt';
                }
            }
            this.currentFormat.fontSize = size;
            this.syncAllPages();
        },

        applyFontFamily(family) {
            const fontMap = {
                'sans': "'Aptos', 'Inter', system-ui, sans-serif",
                'calibri': "'Calibri', 'Carlito', sans-serif",
                'times': "'Times New Roman', 'Liberation Serif', serif",
                'arial': "'Arial', 'Liberation Sans', sans-serif",
                'serif': "'Source Serif 4', Georgia, serif",
                'mono': "'JetBrains Mono', ui-monospace, monospace"
            };
            const fontCss = fontMap[family] || family;
            const sel = window.getSelection();
            if (sel && !sel.isCollapsed) {
                document.execCommand('fontName', false, fontCss);
            } else {
                let blockEl = null;
                if (sel && sel.anchorNode) {
                    blockEl = sel.anchorNode.nodeType === 1
                        ? sel.anchorNode.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote')
                        : sel.anchorNode.parentElement?.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote');
                }
                if (blockEl) {
                    blockEl.style.fontFamily = fontCss;
                }
            }
            this.currentFormat.fontFamily = family;
            this.syncAllPages();
        },

        applyLineSpacing(spacing) {
            const sel = window.getSelection();
            let blockEl = null;
            if (sel && sel.anchorNode) {
                blockEl = sel.anchorNode.nodeType === 1
                    ? sel.anchorNode.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote')
                    : sel.anchorNode.parentElement?.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote');
            }
            if (blockEl) {
                blockEl.style.lineHeight = spacing;
            } else {
                document.querySelectorAll('.word-sheet-content').forEach(el => {
                    el.style.lineHeight = spacing;
                });
            }
            const wire = this.getWire();
            if (wire) {
                wire.setLineSpacing(parseFloat(spacing));
            }
            this.syncAllPages();
        },

        clearCurrentFormat() {
            document.execCommand('removeFormat', false, null);
            const sel = window.getSelection();
            let blockEl = null;
            if (sel && sel.anchorNode) {
                blockEl = sel.anchorNode.nodeType === 1
                    ? sel.anchorNode.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote')
                    : sel.anchorNode.parentElement?.closest('[data-block-index], p, h1, h2, h3, h4, li, blockquote');
            }
            if (blockEl) {
                blockEl.style.cssText = '';
                if (!['P', 'H1', 'H2', 'H3', 'H4', 'LI', 'BLOCKQUOTE'].includes(blockEl.tagName)) {
                    const newP = document.createElement('p');
                    newP.className = 'word-paragraph-text';
                    newP.innerHTML = blockEl.innerHTML;
                    blockEl.replaceWith(newP);
                    this.focusNode(newP, false);
                }
            }
            this.updateCurrentFormat();
            this.syncAllPages();
        },

        adjustFontSize(delta) {
            const cur = parseInt(this.currentFormat.fontSize, 10) || 16;
            const standardSizes = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48, 72];
            let newSize;
            if (delta > 0) {
                newSize = standardSizes.find(s => s > cur) || (cur + 2);
            } else {
                newSize = [...standardSizes].reverse().find(s => s < cur) || Math.max(8, cur - 2);
            }
            this.applyFontSize(newSize);
        },

        insertPageBreak() {
            const sel = window.getSelection();
            let blockEl = null;
            if (sel && sel.anchorNode) {
                blockEl = sel.anchorNode.nodeType === 1
                    ? sel.anchorNode.closest('p, h1, h2, h3, h4, li, blockquote, table, div.word-page-break-marker')
                    : sel.anchorNode.parentElement?.closest('p, h1, h2, h3, h4, li, blockquote, table, div.word-page-break-marker');
            }

            const breakEl = document.createElement('div');
            breakEl.className = 'word-page-break-marker';
            breakEl.contentEditable = 'false';
            breakEl.style.cssText = 'border-top:2px dashed #0078d4; margin:28px 0; text-align:center; user-select:none;';
            breakEl.innerHTML = '<span style="position:relative; top:-11px; background:#ffffff; padding:2px 14px; font-size:11px; color:#0078d4; font-weight:600; border:1px solid #0078d4; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">——— Pemisah Halaman (Page Break) ———</span>';

            const nextP = document.createElement('p');
            nextP.className = 'word-paragraph-text';
            nextP.innerHTML = '<br>';

            if (blockEl && blockEl.parentElement) {
                if (['P', 'H1', 'H2', 'H3', 'H4'].includes(blockEl.tagName) && sel && sel.rangeCount) {
                    const range = sel.getRangeAt(0);
                    const postRange = range.cloneRange();
                    postRange.selectNodeContents(blockEl);
                    postRange.setStart(range.endContainer, range.endOffset);
                    const postHtml = postRange.cloneContents();
                    const tempDiv = document.createElement('div');
                    tempDiv.appendChild(postHtml);
                    const postText = tempDiv.innerHTML.trim();
                    if (postText) {
                        nextP.innerHTML = postText;
                    }
                    const preRange = range.cloneRange();
                    preRange.selectNodeContents(blockEl);
                    preRange.setEnd(range.startContainer, range.startOffset);
                    const preHtml = preRange.cloneContents();
                    blockEl.innerHTML = '';
                    blockEl.appendChild(preHtml);
                    if (!blockEl.innerHTML.trim()) blockEl.innerHTML = '<br>';
                }
                blockEl.insertAdjacentElement('afterend', breakEl);
                breakEl.insertAdjacentElement('afterend', nextP);
            } else {
                const sheet = document.querySelector('.word-sheet-content');
                if (sheet) {
                    sheet.appendChild(breakEl);
                    sheet.appendChild(nextP);
                }
            }
            this.focusNode(nextP, false);
            this.syncAllPages();
        },

        insertHorizontalRule() {
            const hr = document.createElement('hr');
            hr.className = 'word-divider';
            hr.style.cssText = 'border:none; border-top:1px solid #d1d5db; margin:20px 0;';

            const nextP = document.createElement('p');
            nextP.className = 'word-paragraph-text';
            nextP.innerHTML = '<br>';

            const sel = window.getSelection();
            let blockEl = null;
            if (sel && sel.anchorNode) {
                blockEl = sel.anchorNode.nodeType === 1
                    ? sel.anchorNode.closest('p, h1, h2, h3, h4, li, blockquote, table')
                    : sel.anchorNode.parentElement?.closest('p, h1, h2, h3, h4, li, blockquote, table');
            }

            if (blockEl && blockEl.parentElement) {
                blockEl.insertAdjacentElement('afterend', hr);
                hr.insertAdjacentElement('afterend', nextP);
            } else {
                const sheet = document.querySelector('.word-sheet-content');
                if (sheet) {
                    sheet.appendChild(hr);
                    sheet.appendChild(nextP);
                }
            }
            this.focusNode(nextP, false);
            this.syncAllPages();
        },

        insertTable(rows = 3, cols = 3) {
            const sel = window.getSelection();
            let blockEl = null;
            if (sel && sel.anchorNode) {
                blockEl = sel.anchorNode.nodeType === 1
                    ? sel.anchorNode.closest('p, h1, h2, h3, h4, li, blockquote, table')
                    : sel.anchorNode.parentElement?.closest('p, h1, h2, h3, h4, li, blockquote, table');
            }
            const table = document.createElement('table');
            table.className = 'word-table';
            table.style.cssText = 'width:100%; border-collapse:collapse; margin:16px 0; border:1px solid #d1d5db;';
            const tbody = document.createElement('tbody');
            for (let r = 0; r < rows; r++) {
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid #e1dfdd';
                if (r === 0) tr.style.background = '#f8fafc';
                for (let c = 0; c < cols; c++) {
                    const cell = document.createElement(r === 0 ? 'th' : 'td');
                    cell.style.cssText = 'padding:8px 12px; border:1px solid #d1d5db; min-height:24px;';
                    cell.innerHTML = r === 0 ? `Kolom ${c + 1}` : '<br>';
                    tr.appendChild(cell);
                }
                tbody.appendChild(tr);
            }
            table.appendChild(tbody);

            const nextP = document.createElement('p');
            nextP.className = 'word-paragraph-text';
            nextP.innerHTML = '<br>';

            if (blockEl && blockEl.parentElement) {
                blockEl.insertAdjacentElement('afterend', table);
                table.insertAdjacentElement('afterend', nextP);
            } else {
                const sheet = document.querySelector('.word-sheet-content');
                if (sheet) {
                    sheet.appendChild(table);
                    sheet.appendChild(nextP);
                }
            }
            this.focusNode(table.rows[0].cells[0], true);
            this.syncAllPages();
        },

        insertTableRow(below = true) {
            const sel = window.getSelection();
            const cell = sel?.anchorNode?.nodeType === 1 ? sel.anchorNode.closest('td, th') : sel?.anchorNode?.parentElement?.closest('td, th');
            if (!cell) return;
            const row = cell.closest('tr');
            const table = row?.closest('table');
            if (!row || !table) return;
            const colCount = row.cells.length;
            const newRow = document.createElement('tr');
            newRow.style.borderBottom = '1px solid #e1dfdd';
            for (let i = 0; i < colCount; i++) {
                const td = document.createElement('td');
                td.style.cssText = 'padding:8px 12px; border:1px solid #e1dfdd; min-height:24px;';
                td.innerHTML = '<br>';
                newRow.appendChild(td);
            }
            if (below) {
                row.insertAdjacentElement('afterend', newRow);
            } else {
                row.insertAdjacentElement('beforebegin', newRow);
            }
            this.focusNode(newRow.cells[0], true);
            this.syncAllPages();
        },

        deleteTableRow() {
            const sel = window.getSelection();
            const cell = sel?.anchorNode?.nodeType === 1 ? sel.anchorNode.closest('td, th') : sel?.anchorNode?.parentElement?.closest('td, th');
            if (!cell) return;
            const row = cell.closest('tr');
            const table = row?.closest('table');
            if (!row || !table) return;
            const siblingRow = row.nextElementSibling || row.previousElementSibling;
            if (table.rows.length <= 1) {
                table.remove();
            } else {
                row.remove();
                if (siblingRow) this.focusNode(siblingRow.cells[0], true);
            }
            this.syncAllPages();
        },

        insertTableColumn(right = true) {
            const sel = window.getSelection();
            const cell = sel?.anchorNode?.nodeType === 1 ? sel.anchorNode.closest('td, th') : sel?.anchorNode?.parentElement?.closest('td, th');
            if (!cell) return;
            const row = cell.closest('tr');
            const table = row?.closest('table');
            if (!row || !table) return;
            const colIndex = Array.from(row.cells).indexOf(cell);
            for (const r of table.rows) {
                const isHeader = r.cells[colIndex]?.tagName === 'TH';
                const newCell = document.createElement(isHeader ? 'th' : 'td');
                newCell.style.cssText = 'padding:8px 12px; border:1px solid #e1dfdd; min-height:24px;';
                newCell.innerHTML = '<br>';
                const refCell = r.cells[colIndex];
                if (refCell) {
                    if (right) {
                        refCell.insertAdjacentElement('afterend', newCell);
                    } else {
                        refCell.insertAdjacentElement('beforebegin', newCell);
                    }
                }
            }
            this.syncAllPages();
        },

        deleteTableColumn() {
            const sel = window.getSelection();
            const cell = sel?.anchorNode?.nodeType === 1 ? sel.anchorNode.closest('td, th') : sel?.anchorNode?.parentElement?.closest('td, th');
            if (!cell) return;
            const row = cell.closest('tr');
            const table = row?.closest('table');
            if (!row || !table) return;
            const colIndex = Array.from(row.cells).indexOf(cell);
            if (row.cells.length <= 1) {
                table.remove();
            } else {
                for (const r of table.rows) {
                    if (r.cells[colIndex]) r.cells[colIndex].remove();
                }
            }
            this.syncAllPages();
        },

        deleteTable() {
            const sel = window.getSelection();
            const cell = sel?.anchorNode?.nodeType === 1 ? sel.anchorNode.closest('td, th') : sel?.anchorNode?.parentElement?.closest('td, th');
            const table = cell?.closest('table');
            if (table) {
                const sibling = table.nextElementSibling || table.previousElementSibling;
                table.remove();
                if (sibling) this.focusNode(sibling, false);
                this.syncAllPages();
            }
        },

        ensureRootParagraph(sheet) {
            if (!sheet) sheet = document.querySelector('.word-sheet-content');
            if (sheet && (!sheet.firstElementChild || sheet.innerHTML.trim() === '')) {
                const p = document.createElement('p');
                p.className = 'word-paragraph-text';
                p.innerHTML = '<br>';
                sheet.innerHTML = '';
                sheet.appendChild(p);
                this.focusNode(p, false);
            }
        },

        copyCurrentFormat() {
            this.copiedFormat = { ...this.currentFormat };
            const wire = this.getWire();
            if (wire) {
                wire.copyFormat(this.activeBlock);
                wire.notify('Format Painter', 'Gaya format disalin (Format Painter). Klik teks target untuk menerapkan.', 'info');
            }
        },

        pasteCurrentFormat() {
            if (!this.copiedFormat) {
                const wire = this.getWire();
                if (wire) wire.pasteFormat(this.activeBlock);
                return;
            }
            if (this.copiedFormat.bold !== document.queryCommandState('bold')) document.execCommand('bold');
            if (this.copiedFormat.italic !== document.queryCommandState('italic')) document.execCommand('italic');
            if (this.copiedFormat.underline !== document.queryCommandState('underline')) document.execCommand('underline');
            if (this.copiedFormat.align) this.applyAlignment(this.copiedFormat.align);
            if (this.copiedFormat.style) this.applyBlockStyle(this.copiedFormat.style);
            this.syncAllPages();
        },

        undo() {
            if (document.queryCommandEnabled && document.queryCommandEnabled('undo')) {
                document.execCommand('undo');
                this.updateCurrentFormat();
                this.syncAllPages();
            } else {
                const wire = this.getWire();
                if (wire) wire.undo();
            }
        },

        redo() {
            if (document.queryCommandEnabled && document.queryCommandEnabled('redo')) {
                document.execCommand('redo');
                this.updateCurrentFormat();
                this.syncAllPages();
            } else {
                const wire = this.getWire();
                if (wire) wire.redo();
            }
        },

        cut() {
            const sel = window.getSelection();
            if (sel && !sel.isCollapsed) {
                document.execCommand('cut');
                this.syncAllPages();
            }
        },

        copy() {
            const sel = window.getSelection();
            if (sel && !sel.isCollapsed) {
                document.execCommand('copy');
            }
        },

        async paste() {
            try {
                if (navigator.clipboard && navigator.clipboard.readText) {
                    const text = await navigator.clipboard.readText();
                    if (text) {
                        document.execCommand('insertText', false, text);
                        this.syncAllPages();
                    }
                } else {
                    document.execCommand('paste');
                    this.syncAllPages();
                }
            } catch (err) {
                document.execCommand('paste');
            }
        },

        updateActiveBlock(event) {
            const target = event.target;
            if (!target) return;
            const blockEl = target.closest('[data-block-index]');
            if (blockEl) {
                const idx = parseInt(blockEl.getAttribute('data-block-index'), 10);
                if (!isNaN(idx) && this.activeBlock !== idx) {
                    this.activeBlock = idx;
                    const wire = this.getWire();
                    if (wire) {
                        wire.selectBlock(idx);
                    }
                }
                try {
                    const rect = blockEl.getBoundingClientRect();
                    const rootRect = this.$el.getBoundingClientRect();
                    const maxLeft = (this.assistantPane || this.commentsOpen || this.revisionsOpen) ? (rootRect.width - 380 - 100) : (rootRect.width - 120);
                    this.blockCopilotTop = Math.max(10, (rect.top - rootRect.top) - 10);
                    this.blockCopilotLeft = Math.min(maxLeft, Math.max(10, (rect.right - rootRect.left) - 96));
                    this.showBlockCopilot = true;
                } catch (e) {
                    this.showBlockCopilot = false;
                }
            } else {
                this.showBlockCopilot = false;
            }
            this.updateCurrentFormat();
        },

        runBlockAi(tool) {
            this.showBlockCopilot = false;
            const wire = this.getWire();
            if (wire) {
                wire.runTool(tool);
            }
        },

        checkSelection() {
            const sel = window.getSelection();
            if (!sel || sel.isCollapsed) {
                this.showMiniToolbar = false;
                this.copilotMenuOpen = false;
                this.updateCurrentFormat();
                return;
            }
            const text = sel.toString().trim();
            if (!text) {
                this.showMiniToolbar = false;
                this.copilotMenuOpen = false;
                return;
            }
            const anchor = sel.anchorNode;
            if (!anchor || !anchor.parentElement || !anchor.parentElement.closest('.word-sheet-content')) {
                this.showMiniToolbar = false;
                this.copilotMenuOpen = false;
                return;
            }
            try {
                const range = sel.getRangeAt(0);
                const rect = range.getBoundingClientRect();
                const rootRect = this.$el.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                    this.selectedText = text;
                    this.toolbarTop = Math.max(10, (rect.top - rootRect.top) - 44);
                    this.toolbarLeft = Math.max(10, (rect.left - rootRect.left) + (rect.width / 2) - 150);
                    this.showMiniToolbar = true;

                    const blockEl = anchor.parentElement ? anchor.parentElement.closest('[data-block-index]') : null;
                    if (blockEl) {
                        const idx = parseInt(blockEl.getAttribute('data-block-index'), 10);
                        if (!isNaN(idx) && this.activeBlock !== idx) {
                            this.activeBlock = idx;
                            const wire = this.getWire();
                            if (wire) {
                                wire.selectBlock(idx);
                            }
                        }
                    }
                }
            } catch (err) {
                this.showMiniToolbar = false;
            }
            this.updateCurrentFormat();
        },

        format(cmd, val = null) {
            document.execCommand(cmd, false, val);
            this.updateCurrentFormat();
            this.syncAllPages();
        },

        runAi(tool) {
            const textToUse = this.selectedText;
            this.showMiniToolbar = false;
            this.copilotMenuOpen = false;
            const wire = this.getWire();
            if (wire) {
                wire.runTool(tool, textToUse);
            }
        },

        focusNode(node, atEnd = false) {
            if (!node) return;
            const sheet = node.closest('[contenteditable="true"]') || node;
            if (sheet && typeof sheet.focus === 'function') {
                sheet.focus();
            }
            const sel = window.getSelection();
            if (!sel) return;
            const range = document.createRange();
            range.selectNodeContents(node);
            range.collapse(!atEnd);
            sel.removeAllRanges();
            sel.addRange(range);
        },

        sanitizePastedHtml(rawHtml) {
            try {
                const parser = new DOMParser();
                const doc = parser.parseFromString(rawHtml, 'text/html');

                const removeSelectors = ['script', 'style', 'meta', 'link', 'xml', 'o\\:p'];
                removeSelectors.forEach(sel => {
                    doc.querySelectorAll(sel).forEach(el => el.remove());
                });

                const allowedTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'BR', 'A', 'SUB', 'SUP'];

                const sanitizeNode = (node) => {
                    if (node.nodeType === Node.COMMENT_NODE) {
                        node.remove();
                        return;
                    }
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        const tagName = node.tagName.toUpperCase();
                        if (!allowedTags.includes(tagName)) {
                            while (node.firstChild) {
                                node.parentNode.insertBefore(node.firstChild, node);
                            }
                            node.remove();
                            return;
                        }

                        const attrs = Array.from(node.attributes);
                        attrs.forEach(attr => {
                            const name = attr.name.toLowerCase();
                            if (!['href', 'src', 'alt', 'colspan', 'rowspan'].includes(name)) {
                                node.removeAttribute(name);
                            }
                        });

                        if (tagName === 'P') {
                            node.className = 'word-paragraph-text';
                        } else if (tagName === 'H1') {
                            node.className = 'word-title-text';
                        } else if (tagName === 'H2') {
                            node.className = 'word-heading-1';
                        } else if (tagName === 'H3') {
                            node.className = 'word-heading-2';
                        } else if (tagName === 'H4') {
                            node.className = 'word-heading-3';
                        } else if (tagName === 'UL') {
                            node.className = 'word-bullet-list';
                        } else if (tagName === 'OL') {
                            node.className = 'word-numbered-list';
                        } else if (tagName === 'BLOCKQUOTE') {
                            node.className = 'word-quote-block';
                        }

                        Array.from(node.childNodes).forEach(sanitizeNode);
                    }
                };

                Array.from(doc.body.childNodes).forEach(sanitizeNode);
                return doc.body.innerHTML.trim();
            } catch (err) {
                return '';
            }
        },

        handlePaste(e) {
            const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
            if (items) {
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        const blob = items[i].getAsFile();
                        if (!blob) continue;
                        e.preventDefault();
                        const reader = new FileReader();
                        reader.onload = (event) => {
                            const dataUrl = event.target.result;
                            const wire = this.getWire();
                            if (wire) {
                                wire.insertImageUrl(dataUrl, 'Gambar dari Tangkapan Layar (Clipboard)', 480, 'center');
                            }
                        };
                        reader.readAsDataURL(blob);
                        return;
                    }
                }
            }

            // Microsoft Word / LibreOffice Match Destination Formatting
            const html = (e.clipboardData || window.clipboardData)?.getData('text/html');
            const text = (e.clipboardData || window.clipboardData)?.getData('text/plain');

            if (html) {
                e.preventDefault();
                const clean = this.sanitizePastedHtml(html);
                if (clean) {
                    document.execCommand('insertHTML', false, clean);
                } else if (text) {
                    document.execCommand('insertText', false, text);
                }
                this.syncAllPages();
            } else if (text) {
                e.preventDefault();
                document.execCommand('insertText', false, text);
                this.syncAllPages();
            }
        },

        handleDrop(e) {
            const files = e.dataTransfer?.files;
            if (!files || files.length === 0) return;
            const file = files[0];
            if (file.type.indexOf('image') !== -1) {
                e.preventDefault();
                const reader = new FileReader();
                reader.onload = (event) => {
                    const dataUrl = event.target.result;
                    const fileName = file.name.replace(/\.[^/.]+$/, '');
                    const wire = this.getWire();
                    if (wire) {
                        wire.insertImageUrl(dataUrl, 'Gambar: ' + fileName, 480, 'center');
                    }
                };
                reader.readAsDataURL(file);
            }
        },

        handleContentKeydown(e) {
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;

            // 0. CTRL+ENTER / CMD+ENTER: Insert Page Break (Pemisah Halaman Word & LibreOffice)
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.insertPageBreak();
                return;
            }

            // 1. TAB & SHIFT+TAB: Navigasi tabel dan indentasi alami (Word & LibreOffice)
            if (e.key === 'Tab') {
                e.preventDefault();
                const anchor = sel.anchorNode;
                const cell = anchor?.nodeType === 1 ? anchor.closest('td, th') : anchor?.parentElement?.closest('td, th');

                if (cell) {
                    const table = cell.closest('table');
                    if (table) {
                        const cells = Array.from(table.querySelectorAll('td, th'));
                        const idx = cells.indexOf(cell);
                        if (e.shiftKey) {
                            if (idx > 0) {
                                this.focusNode(cells[idx - 1], true);
                            }
                        } else {
                            if (idx < cells.length - 1) {
                                this.focusNode(cells[idx + 1], true);
                            } else {
                                // Sel terakhir tabel: Tambah baris baru secara otomatis (Word & LibreOffice)
                                const lastRow = table.rows[table.rows.length - 1];
                                const colCount = lastRow.cells.length;
                                const tbody = table.querySelector('tbody') || table;
                                const newRow = document.createElement('tr');
                                newRow.style.borderBottom = '1px solid #e1dfdd';
                                for (let c = 0; c < colCount; c++) {
                                    const newTd = document.createElement('td');
                                    newTd.style.padding = '6px 10px';
                                    newTd.style.borderRight = '1px solid #e1dfdd';
                                    newTd.innerHTML = '<br>';
                                    newRow.appendChild(newTd);
                                }
                                tbody.appendChild(newRow);
                                this.focusNode(newRow.cells[0], true);
                                this.syncAllPages();
                            }
                        }
                    }
                    return;
                }

                // Pada daftar butir: Tab menjorok ke dalam (sub-list), Shift+Tab keluar
                const li = anchor?.nodeType === 1 ? anchor.closest('li') : anchor?.parentElement?.closest('li');
                if (li) {
                    if (e.shiftKey) {
                        document.execCommand('outdent', false, null);
                    } else {
                        document.execCommand('indent', false, null);
                    }
                    this.syncAllPages();
                    return;
                }

                // Teks umum: Sisipkan 4 spasi non-breaking (tab stop Word) tanpa memindahkan fokus keluar
                if (e.shiftKey) {
                    document.execCommand('outdent', false, null);
                } else {
                    document.execCommand('insertText', false, '\u00a0\u00a0\u00a0\u00a0');
                }
                this.syncAllPages();
                return;
            }

            // 2. SHIFT+ENTER: Line Break (Soft Break dalam paragraf yang sama)
            if (e.key === 'Enter' && e.shiftKey && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                document.execCommand('insertLineBreak');
                this.syncAllPages();
                return;
            }

            // 3. ENTER: Alur alami judul ke paragraf normal, keluar dari daftar, dan pemecahan kalimat
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey && !e.altKey) {
                const anchor = sel.anchorNode;

                // Bila di dalam sel tabel: Enter menyisipkan baris baru di dalam sel yang sama (Word / LibreOffice table behavior)
                const cell = anchor?.nodeType === 1 ? anchor.closest('td, th') : anchor?.parentElement?.closest('td, th');
                if (cell) {
                    e.preventDefault();
                    document.execCommand('insertLineBreak');
                    this.syncAllPages();
                    return;
                }

                // Bila di dalam Judul/Heading (H1, H2, H3, Judul Naskah)
                const heading = anchor?.nodeType === 1
                    ? anchor.closest('h1, h2, h3, h4, .word-title-text, .word-subtitle-text, .word-heading-1, .word-heading-2, .word-heading-3')
                    : anchor?.parentElement?.closest('h1, h2, h3, h4, .word-title-text, .word-subtitle-text, .word-heading-1, .word-heading-2, .word-heading-3');

                if (heading) {
                    e.preventDefault();
                    const range = sel.getRangeAt(0);
                    const preRange = range.cloneRange();
                    preRange.selectNodeContents(heading);
                    preRange.setEnd(range.endContainer, range.endOffset);
                    const preHtml = preRange.cloneContents();

                    const postRange = range.cloneRange();
                    postRange.selectNodeContents(heading);
                    postRange.setStart(range.endContainer, range.endOffset);
                    const postHtml = postRange.cloneContents();

                    const tempDiv = document.createElement('div');
                    tempDiv.appendChild(postHtml);
                    const postString = tempDiv.innerHTML.trim();

                    const newP = document.createElement('p');
                    newP.className = 'word-paragraph-text';
                    newP.innerHTML = postString ? postString : '<br>';

                    heading.innerHTML = '';
                    heading.appendChild(preHtml);
                    if (!heading.innerHTML.trim()) {
                        heading.innerHTML = '<br>';
                    }

                    heading.insertAdjacentElement('afterend', newP);
                    this.focusNode(newP, false);
                    this.updateCurrentFormat();
                    this.syncAllPages();
                    return;
                }

                // Bila di dalam butir daftar (li) - Alur alami Microsoft Word & LibreOffice Writer
                const li = anchor?.nodeType === 1 ? anchor.closest('li') : anchor?.parentElement?.closest('li');
                if (li) {
                    e.preventDefault();
                    if (!li.textContent.trim() || li.textContent === '') {
                        // Enter pada butir kosong mengakhiri daftar dan kembali ke paragraf normal
                        const list = li.closest('ul, ol');
                        if (list) {
                            const newP = document.createElement('p');
                            newP.className = 'word-paragraph-text';
                            newP.innerHTML = '<br>';
                            const nextSiblings = [];
                            let nextEl = li.nextElementSibling;
                            while (nextEl) {
                                nextSiblings.push(nextEl);
                                nextEl = nextEl.nextElementSibling;
                            }
                            li.remove();
                            if (list.children.length === 0) {
                                list.replaceWith(newP);
                            } else if (nextSiblings.length > 0) {
                                const nextList = document.createElement(list.tagName);
                                nextList.className = list.className;
                                nextSiblings.forEach(s => nextList.appendChild(s));
                                list.insertAdjacentElement('afterend', newP);
                                newP.insertAdjacentElement('afterend', nextList);
                            } else {
                                list.insertAdjacentElement('afterend', newP);
                            }
                            this.focusNode(newP, false);
                            this.updateCurrentFormat();
                            this.syncAllPages();
                            return;
                        }
                    } else {
                        // Enter pada butir berisi: memecah menjadi butir daftar baru di bawahnya
                        const range = sel.getRangeAt(0);
                        const preRange = range.cloneRange();
                        preRange.selectNodeContents(li);
                        preRange.setEnd(range.endContainer, range.endOffset);
                        const preHtml = preRange.cloneContents();

                        const postRange = range.cloneRange();
                        postRange.selectNodeContents(li);
                        postRange.setStart(range.endContainer, range.endOffset);
                        const postHtml = postRange.cloneContents();

                        const tempDiv = document.createElement('div');
                        tempDiv.appendChild(postHtml);
                        const postString = tempDiv.innerHTML.trim();

                        const newLi = document.createElement('li');
                        newLi.innerHTML = postString ? postString : '<br>';

                        li.innerHTML = '';
                        li.appendChild(preHtml);
                        if (!li.innerHTML.trim()) {
                            li.innerHTML = '<br>';
                        }

                        li.insertAdjacentElement('afterend', newLi);
                        this.focusNode(newLi, false);
                        this.updateCurrentFormat();
                        this.syncAllPages();
                        return;
                    }
                }

                // Bila di dalam Blockquote: Enter pada baris kutipan kosong mengakhiri kutipan (Word & LibreOffice)
                const bq = anchor?.nodeType === 1 ? anchor.closest('blockquote') : anchor?.parentElement?.closest('blockquote');
                if (bq && (!bq.textContent.trim() || bq.textContent === '')) {
                    e.preventDefault();
                    const newP = document.createElement('p');
                    newP.className = 'word-paragraph-text';
                    newP.innerHTML = '<br>';
                    bq.replaceWith(newP);
                    this.focusNode(newP, false);
                    this.updateCurrentFormat();
                    this.syncAllPages();
                    return;
                }

                // Bila di dalam Paragraf biasa: Cek garis pembatas (---) atau pecah paragraf pada posisi kursor
                const p = anchor?.nodeType === 1 ? anchor.closest('p.word-paragraph-text, p') : anchor?.parentElement?.closest('p.word-paragraph-text, p');
                if (p && !p.closest('.word-quote-block') && !cell) {
                    // Pengetikan '---' diikuti Enter membuat garis pembatas horisontal naskah (Word & LibreOffice)
                    if (p.textContent.trim() === '---') {
                        e.preventDefault();
                        const hr = document.createElement('hr');
                        hr.className = 'word-divider';
                        hr.style.border = 'none';
                        hr.style.borderTop = '1px solid #d1d5db';
                        hr.style.margin = '16px 0';
                        const newP = document.createElement('p');
                        newP.className = 'word-paragraph-text';
                        newP.innerHTML = '<br>';
                        p.replaceWith(hr);
                        hr.insertAdjacentElement('afterend', newP);
                        this.focusNode(newP, false);
                        this.updateCurrentFormat();
                        this.syncAllPages();
                        return;
                    }

                    e.preventDefault();
                    const range = sel.getRangeAt(0);

                    const preRange = range.cloneRange();
                    preRange.selectNodeContents(p);
                    preRange.setEnd(range.endContainer, range.endOffset);
                    const preHtml = preRange.cloneContents();

                    const postRange = range.cloneRange();
                    postRange.selectNodeContents(p);
                    postRange.setStart(range.endContainer, range.endOffset);
                    const postHtml = postRange.cloneContents();

                    const tempDiv = document.createElement('div');
                    tempDiv.appendChild(postHtml);
                    const postString = tempDiv.innerHTML.trim();

                    const newP = document.createElement('p');
                    newP.className = p.className || 'word-paragraph-text';
                    if (p.style.textAlign) newP.style.textAlign = p.style.textAlign;
                    newP.innerHTML = postString ? postString : '<br>';

                    p.innerHTML = '';
                    p.appendChild(preHtml);
                    if (!p.innerHTML.trim()) {
                        p.innerHTML = '<br>';
                    }

                    p.insertAdjacentElement('afterend', newP);
                    this.focusNode(newP, false);
                    this.updateCurrentFormat();
                    this.syncAllPages();
                    return;
                }
            }

            // 4. BACKSPACE: Menghapus blok kosong, peleburan paragraf (merge paragraph), atau keluar dari gaya
            if (e.key === 'Backspace') {
                const anchor = sel.anchorNode;
                const block = anchor?.nodeType === 1
                    ? anchor.closest('p, h1, h2, h3, h4, li, blockquote')
                    : anchor?.parentElement?.closest('p, h1, h2, h3, h4, li, blockquote');

                if (block && sel.isCollapsed) {
                    const range = sel.getRangeAt(0);
                    const preRange = range.cloneRange();
                    preRange.selectNodeContents(block);
                    preRange.setEnd(range.startContainer, range.startOffset);
                    const isAtStart = preRange.toString().length === 0;

                    if (isAtStart) {
                        const text = block.textContent;
                        const isEmpty = !text || text.trim() === '' || block.innerHTML === '<br>';
                        const prev = block.previousElementSibling;

                        // Case A: Blok kosong -> Hapus dan fokus ke akhir blok sebelumnya
                        if (isEmpty) {
                            if (prev && !prev.classList.contains('word-sheet-header')) {
                                e.preventDefault();
                                block.remove();
                                if (prev.tagName === 'TABLE') {
                                    const lastRow = prev.rows[prev.rows.length - 1];
                                    const lastCell = lastRow ? lastRow.cells[lastRow.cells.length - 1] : null;
                                    if (lastCell) this.focusNode(lastCell, true);
                                } else {
                                    this.focusNode(prev, true);
                                }
                                this.updateCurrentFormat();
                                this.syncAllPages();
                                return;
                            }
                        }

                        // Case B: Di awal judul/heading -> Tekan Backspace mengubah kembali ke Paragraf Normal (Word)
                        if (['H1', 'H2', 'H3', 'H4'].includes(block.tagName)) {
                            e.preventDefault();
                            this.applyBlockStyle('normal');
                            return;
                        }

                        // Case C: Di awal butir daftar (li) -> Leburkan ke butir sebelumnya atau keluar dari daftar
                        if (block.tagName === 'LI') {
                            e.preventDefault();
                            const prevLi = block.previousElementSibling;
                            if (prevLi) {
                                const preChildCount = prevLi.childNodes.length;
                                if (prevLi.innerHTML === '<br>') {
                                    prevLi.innerHTML = '';
                                }

                                const blockChildren = Array.from(block.childNodes);
                                for (const child of blockChildren) {
                                    if (child.nodeName === 'BR' && blockChildren.length > 1) continue;
                                    prevLi.appendChild(child);
                                }

                                if (!prevLi.innerHTML.trim()) {
                                    prevLi.innerHTML = '<br>';
                                }

                                block.remove();

                                // junctionMarker: Titik sambungan merger presisi tanpa merusak seleksi browser
                                const targetNode = prevLi.childNodes[preChildCount];
                                if (targetNode) {
                                    try {
                                        const newRange = document.createRange();
                                        if (targetNode.nodeType === Node.TEXT_NODE) {
                                            newRange.setStart(targetNode, 0);
                                        } else {
                                            newRange.setStartBefore(targetNode);
                                        }
                                        newRange.collapse(true);
                                        sel.removeAllRanges();
                                        sel.addRange(newRange);
                                    } catch (err) {
                                        this.focusNode(prevLi, false);
                                    }
                                } else {
                                    this.focusNode(prevLi, true);
                                }

                                this.updateCurrentFormat();
                                this.syncAllPages();
                                return;
                            } else {
                                const list = block.closest('ul, ol');
                                if (list) {
                                    const newP = document.createElement('p');
                                    newP.className = 'word-paragraph-text';
                                    newP.innerHTML = block.innerHTML || '<br>';
                                    block.remove();
                                    if (list.children.length === 0) {
                                        list.replaceWith(newP);
                                    } else {
                                        list.insertAdjacentElement('beforebegin', newP);
                                    }
                                    this.focusNode(newP, false);
                                    this.updateCurrentFormat();
                                    this.syncAllPages();
                                    return;
                                }
                            }
                        }

                        // Case D: Di awal paragraf non-kosong -> LEBURKAN (Merge) dengan blok sebelumnya (Word & LibreOffice)
                        if (prev && !prev.classList.contains('word-sheet-header')) {
                            if (prev.classList.contains('word-page-break-marker') || prev.tagName === 'HR') {
                                e.preventDefault();
                                prev.remove();
                                this.syncAllPages();
                                return;
                            }

                            if (!['TABLE', 'FIGURE'].includes(prev.tagName)) {
                                e.preventDefault();
                                const targetEl = (['UL', 'OL'].includes(prev.tagName) && prev.lastElementChild)
                                    ? prev.lastElementChild
                                    : prev;

                                const preChildCount = targetEl.childNodes.length;
                                if (targetEl.innerHTML === '<br>') {
                                    targetEl.innerHTML = '';
                                }

                                const blockChildren = Array.from(block.childNodes);
                                for (const child of blockChildren) {
                                    if (child.nodeName === 'BR' && blockChildren.length > 1) continue;
                                    targetEl.appendChild(child);
                                }

                                if (!targetEl.innerHTML.trim()) {
                                    targetEl.innerHTML = '<br>';
                                }

                                block.remove();

                                const targetNode = targetEl.childNodes[preChildCount];
                                if (targetNode) {
                                    try {
                                        const newRange = document.createRange();
                                        if (targetNode.nodeType === Node.TEXT_NODE) {
                                            newRange.setStart(targetNode, 0);
                                        } else {
                                            newRange.setStartBefore(targetNode);
                                        }
                                        newRange.collapse(true);
                                        sel.removeAllRanges();
                                        sel.addRange(newRange);
                                    } catch (err) {
                                        this.focusNode(targetEl, false);
                                    }
                                } else {
                                    this.focusNode(targetEl, true);
                                }

                                this.updateCurrentFormat();
                                this.syncAllPages();
                                return;
                            }
                        }
                    }
                }
            }

            // 5. DELETE: Peleburan paragraf ke depan (pull next paragraph forward on Delete at end)
            if (e.key === 'Delete') {
                const anchor = sel.anchorNode;
                const block = anchor?.nodeType === 1
                    ? anchor.closest('p, h1, h2, h3, h4, li, blockquote')
                    : anchor?.parentElement?.closest('p, h1, h2, h3, h4, li, blockquote');

                if (block && sel.isCollapsed) {
                    const range = sel.getRangeAt(0);
                    const postRange = range.cloneRange();
                    postRange.selectNodeContents(block);
                    postRange.setStart(range.endContainer, range.endOffset);
                    const isAtEnd = postRange.toString().length === 0;

                    if (isAtEnd) {
                        const next = block.nextElementSibling;
                        if (next && !next.classList.contains('word-sheet-footer') && !next.classList.contains('word-sheet-header')) {
                            if (next.classList.contains('word-page-break-marker') || next.tagName === 'HR') {
                                e.preventDefault();
                                next.remove();
                                this.syncAllPages();
                                return;
                            }

                            if (!['TABLE', 'FIGURE'].includes(next.tagName)) {
                                e.preventDefault();
                                let nextElToMerge = next;
                                if (['UL', 'OL'].includes(next.tagName) && next.firstElementChild) {
                                    const firstLi = next.firstElementChild;
                                    nextElToMerge = firstLi;
                                }

                                const preChildCount = block.childNodes.length;
                                if (block.innerHTML === '<br>') {
                                    block.innerHTML = '';
                                }

                                const nextChildren = Array.from(nextElToMerge.childNodes);
                                for (const child of nextChildren) {
                                    if (child.nodeName === 'BR' && nextChildren.length > 1) continue;
                                    block.appendChild(child);
                                }

                                if (!block.innerHTML.trim()) {
                                    block.innerHTML = '<br>';
                                }

                                if (nextElToMerge !== next) {
                                    nextElToMerge.remove();
                                    if (next.children.length === 0) next.remove();
                                } else {
                                    next.remove();
                                }

                                const targetNode = block.childNodes[preChildCount];
                                if (targetNode) {
                                    try {
                                        const newRange = document.createRange();
                                        if (targetNode.nodeType === Node.TEXT_NODE) {
                                            newRange.setStart(targetNode, 0);
                                        } else {
                                            newRange.setStartBefore(targetNode);
                                        }
                                        newRange.collapse(true);
                                        sel.removeAllRanges();
                                        sel.addRange(newRange);
                                    } catch (err) {
                                        this.focusNode(block, true);
                                    }
                                } else {
                                    this.focusNode(block, true);
                                }

                                this.updateCurrentFormat();
                                this.syncAllPages();
                                return;
                            }
                        }
                    }
                }
            }

            // 6. TABLE ARROW NAVIGATION: Navigasi Panah Atas & Bawah antar baris tabel (Word & LibreOffice)
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                const anchor = sel.anchorNode;
                const cell = anchor?.nodeType === 1 ? anchor.closest('td, th') : anchor?.parentElement?.closest('td, th');
                if (cell && sel.isCollapsed) {
                    const row = cell.closest('tr');
                    const table = row?.closest('table');
                    if (row && table) {
                        const cellIndex = Array.from(row.cells).indexOf(cell);
                        const rowIndex = Array.from(table.rows).indexOf(row);

                        if (e.key === 'ArrowDown' && rowIndex < table.rows.length - 1) {
                            const range = sel.getRangeAt(0);
                            const postRange = range.cloneRange();
                            postRange.selectNodeContents(cell);
                            postRange.setStart(range.endContainer, range.endOffset);
                            if (!postRange.toString().includes('\n')) {
                                const targetRow = table.rows[rowIndex + 1];
                                const targetCell = targetRow.cells[cellIndex] || targetRow.cells[targetRow.cells.length - 1];
                                if (targetCell) {
                                    e.preventDefault();
                                    this.focusNode(targetCell, false);
                                    return;
                                }
                            }
                        } else if (e.key === 'ArrowUp' && rowIndex > 0) {
                            const range = sel.getRangeAt(0);
                            const preRange = range.cloneRange();
                            preRange.selectNodeContents(cell);
                            preRange.setEnd(range.startContainer, range.startOffset);
                            if (!preRange.toString().includes('\n')) {
                                const targetRow = table.rows[rowIndex - 1];
                                const targetCell = targetRow.cells[cellIndex] || targetRow.cells[targetRow.cells.length - 1];
                                if (targetCell) {
                                    e.preventDefault();
                                    this.focusNode(targetCell, false);
                                    return;
                                }
                            }
                        }
                    }
                }
            }
        },

        handleContentInput(e) {
            clearTimeout(this._inputTimer);
            this.savingState = 'saving';
            this._inputTimer = setTimeout(() => this.syncAllPages(), 500);

            const sheet = e.target ? e.target.closest('.word-sheet-content') : null;
            if (sheet && (!sheet.firstElementChild || sheet.innerHTML.trim() === '')) {
                this.ensureRootParagraph(sheet);
            }

            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;

            // Smart Typography Auto-Replacement (Word & LibreOffice Writer)
            if (sel.isCollapsed) {
                const node = sel.anchorNode;
                if (node && node.nodeType === Node.TEXT_NODE) {
                    const textVal = node.textContent;
                    const offset = sel.anchorOffset;
                    const beforeText = textVal.slice(0, offset);

                    const replacements = [
                        { pattern: /---\s$/, replace: '— ' },
                        { pattern: /--\s$/, replace: '— ' },
                        { pattern: /\.\.\.\s$/, replace: '… ' },
                        { pattern: /\([cC]\)\s$/, replace: '© ' },
                        { pattern: /\([rR]\)\s$/, replace: '® ' },
                        { pattern: /\([tT][mM]\)\s$/, replace: '™ ' },
                        { pattern: /1\/2\s$/, replace: '½ ' },
                        { pattern: /1\/4\s$/, replace: '¼ ' },
                        { pattern: /3\/4\s$/, replace: '¾ ' },
                        { pattern: /\+-\s$/, replace: '± ' },
                        { pattern: /!=\s$/, replace: '≠ ' },
                        { pattern: /<=\s$/, replace: '≤ ' },
                        { pattern: />=\s$/, replace: '≥ ' },
                        { pattern: /->\s$/, replace: '→ ' },
                        { pattern: /<-\s$/, replace: '← ' },
                        { pattern: /<=>\s$/, replace: '⇔ ' },
                        { pattern: /<<\s$/, replace: '« ' },
                        { pattern: />>\s$/, replace: '» ' },
                        { pattern: /\b1st\s$/, replace: '1ˢᵗ ' },
                        { pattern: /\b2nd\s$/, replace: '2ⁿᵈ ' },
                        { pattern: /\b3rd\s$/, replace: '3ʳᵈ ' },
                        { pattern: /\b(\d+)th\s$/, replace: '$1ᵗʰ ' },
                        { pattern: /\bke-(\d+)\s$/, replace: 'ke-$1 ' },
                        { pattern: /(\d+)-(\d+)\s$/, replace: '$1–$2 ' },
                        { pattern: /(\d+)\s*[xX]\s*(\d+)\s$/, replace: '$1 × $2 ' },
                        { pattern: /(^|\s)"([^\s"]+)"\s$/, replace: '$1“$2” ' },
                        { pattern: /(^|\s)'([^\s']+)'\s$/, replace: '$1‘$2’ ' },
                        { pattern: /([a-zA-Z])'([a-zA-Z])\s$/, replace: '$1’$2 ' },
                    ];

                    for (const r of replacements) {
                        if (r.pattern.test(beforeText)) {
                            const newBeforeText = beforeText.replace(r.pattern, r.replace);
                            const afterText = textVal.slice(offset);
                            node.textContent = newBeforeText + afterText;
                            const newOffset = newBeforeText.length;
                            const newRange = document.createRange();
                            newRange.setStart(node, newOffset);
                            newRange.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(newRange);
                            break;
                        }
                    }
                }
            }

            // AutoFormat Microsoft Word & LibreOffice Writer (*, -, 1., >, #, ##, ###)
            const anchor = sel.anchorNode;
            const p = anchor?.nodeType === 1 ? anchor.closest('p.word-paragraph-text') : anchor?.parentElement?.closest('p.word-paragraph-text');
            if (!p) {
                this.updateCurrentFormat();
                return;
            }

            const text = p.textContent || '';
            if (/^(\*|-)\s/.test(text)) {
                const clean = text.replace(/^(\*|-)\s/, '');
                const ul = document.createElement('ul');
                ul.className = 'word-bullet-list';
                const li = document.createElement('li');
                li.innerHTML = clean ? clean : '<br>';
                ul.appendChild(li);
                p.replaceWith(ul);
                this.focusNode(li, true);
                this.updateCurrentFormat();
                this.syncAllPages();
                return;
            }

            if (/^(1[\.\)]|[a-zA-Z][\.\)])\s/.test(text)) {
                const clean = text.replace(/^(1[\.\)]|[a-zA-Z][\.\)])\s/, '');
                const ol = document.createElement('ol');
                ol.className = 'word-numbered-list';
                const li = document.createElement('li');
                li.innerHTML = clean ? clean : '<br>';
                ol.appendChild(li);
                p.replaceWith(ol);
                this.focusNode(li, true);
                this.updateCurrentFormat();
                this.syncAllPages();
                return;
            }

            if (/^>\s/.test(text)) {
                const clean = text.replace(/^>\s/, '');
                const bq = document.createElement('blockquote');
                bq.className = 'word-quote-block';
                const quoteP = document.createElement('p');
                quoteP.className = 'word-quote-text';
                quoteP.innerHTML = clean ? clean : '<br>';
                bq.appendChild(quoteP);
                p.replaceWith(bq);
                this.focusNode(quoteP, true);
                this.updateCurrentFormat();
                this.syncAllPages();
                return;
            }

            if (/^###\s/.test(text)) {
                const clean = text.replace(/^###\s/, '');
                const h4 = document.createElement('h4');
                h4.className = 'word-heading-3';
                h4.innerHTML = clean ? clean : '<br>';
                p.replaceWith(h4);
                this.focusNode(h4, true);
                this.updateCurrentFormat();
                this.syncAllPages();
                return;
            }

            if (/^##\s/.test(text)) {
                const clean = text.replace(/^##\s/, '');
                const h3 = document.createElement('h3');
                h3.className = 'word-heading-2';
                h3.innerHTML = clean ? clean : '<br>';
                p.replaceWith(h3);
                this.focusNode(h3, true);
                this.updateCurrentFormat();
                this.syncAllPages();
                return;
            }

            if (/^#\s/.test(text)) {
                const clean = text.replace(/^#\s/, '');
                const h2 = document.createElement('h2');
                h2.className = 'word-heading-1';
                h2.innerHTML = clean ? clean : '<br>';
                p.replaceWith(h2);
                this.focusNode(h2, true);
                this.updateCurrentFormat();
                this.syncAllPages();
                return;
            }

            this.updateCurrentFormat();
        },

        init() {
            this.updateCounts();
            this.ensureRootParagraph();

            window.addEventListener('keydown', (e) => {
                const isCtrl = e.ctrlKey || e.metaKey;
                const wire = this.getWire();

                // Pintasan Keyboard Microsoft Word & LibreOffice Writer
                if (isCtrl && !e.altKey) {
                    const key = e.key.toLowerCase();
                    if (key === 'k') {
                        e.preventDefault();
                        if (wire) wire.openDialog('palette');
                    } else if (key === 's') {
                        e.preventDefault();
                        this.saveDocument();
                    } else if (key === 'z') {
                        e.preventDefault();
                        this.undo();
                    } else if (key === 'y') {
                        e.preventDefault();
                        this.redo();
                        }
                    } else if (key === 'f') {
                        e.preventDefault();
                        if (wire) wire.openDialog('find');
                    } else if (key === 'b') {
                        e.preventDefault();
                        this.format('bold');
                    } else if (key === 'i') {
                        e.preventDefault();
                        this.format('italic');
                    } else if (key === 'u') {
                        e.preventDefault();
                        this.format('underline');
                    } else if (key === 'e') {
                        e.preventDefault();
                        this.applyAlignment('center');
                    } else if (key === 'l' && !e.shiftKey) {
                        e.preventDefault();
                        this.applyAlignment('left');
                    } else if (key === 'r') {
                        e.preventDefault();
                        this.applyAlignment('right');
                    } else if (key === 'j') {
                        e.preventDefault();
                        this.applyAlignment('justify');
                    } else if (e.key === '1') {
                        e.preventDefault();
                        this.applyLineSpacing('1.0');
                    } else if (e.key === '2') {
                        e.preventDefault();
                        this.applyLineSpacing('2.0');
                    } else if (e.key === '5') {
                        e.preventDefault();
                        this.applyLineSpacing('1.5');
                    } else if (e.key === '[') {
                        e.preventDefault();
                        this.adjustFontSize(-1);
                    } else if (e.key === ']') {
                        e.preventDefault();
                        this.adjustFontSize(1);
                    } else if (e.shiftKey && (e.key === '<' || e.key === ',')) {
                        e.preventDefault();
                        this.adjustFontSize(-1);
                    } else if (e.shiftKey && (e.key === '>' || e.key === '.')) {
                        e.preventDefault();
                        this.adjustFontSize(1);
                    } else if (e.shiftKey && key === 'l') {
                        e.preventDefault();
                        this.format('insertUnorderedList');
                    } else if (e.shiftKey && key === 'c') {
                        // Word Format Painter Copy
                        e.preventDefault();
                        this.copyCurrentFormat();
                    } else if (e.shiftKey && key === 'v') {
                        // Word Format Painter Paste
                        e.preventDefault();
                        this.pasteCurrentFormat();
                    } else if (key === 'h') {
                        // Word Find & Replace
                        e.preventDefault();
                        if (wire) wire.openDialog('find');
                    } else if (key === 'm' || e.code === 'Space') {
                        // Word/LibreOffice Clear Direct Formatting
                        e.preventDefault();
                        this.clearCurrentFormat();
                    }
                }

                // Format Heading & Normal Style Word: Ctrl+Alt+1, 2, 3, 0
                if (isCtrl && e.altKey) {
                    if (e.key === '1') {
                        e.preventDefault();
                        this.applyBlockStyle('heading1');
                    } else if (e.key === '2') {
                        e.preventDefault();
                        this.applyBlockStyle('heading2');
                    } else if (e.key === '3') {
                        e.preventDefault();
                        this.applyBlockStyle('heading3');
                    } else if (e.key === '0') {
                        e.preventDefault();
                        this.applyBlockStyle('normal');
                    }
                }

                if (e.altKey && (e.key === '=' || e.key === '+')) {
                    e.preventDefault();
                    if (wire) wire.openDialog('equation');
                }
            });

            document.addEventListener('selectionchange', () => {
                clearTimeout(this._selTimer);
                this._selTimer = setTimeout(() => {
                    this.checkSelection();
                    this.updateCurrentFormat();
                }, 100);
            });

            setInterval(() => {
                const wire = this.getWire();
                if (wire) wire.tickSession();
            }, 5000);
        }
    };
};
