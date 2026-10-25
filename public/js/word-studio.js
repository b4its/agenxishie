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
            const cmd = align === 'center' ? 'justifyCenter' : (align === 'right' ? 'justifyRight' : (align === 'justify' ? 'justifyFull' : 'justifyLeft'));
            document.execCommand(cmd, false, null);
            this.currentFormat.align = align;
            this.syncAllPages();
        },

        applyIndent(delta) {
            if (delta > 0) {
                document.execCommand('indent', false, null);
            } else {
                document.execCommand('outdent', false, null);
            }
            this.syncAllPages();
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
            node.focus();
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
                    const preText = preRange.toString();

                    const postRange = range.cloneRange();
                    postRange.selectNodeContents(heading);
                    postRange.setStart(range.endContainer, range.endOffset);
                    const postText = postRange.toString();

                    const newP = document.createElement('p');
                    newP.className = 'word-paragraph-text';
                    newP.innerHTML = postText ? postText : '<br>';

                    if (postText) {
                        heading.textContent = preText;
                    }

                    heading.insertAdjacentElement('afterend', newP);
                    this.focusNode(newP, false);
                    this.updateCurrentFormat();
                    this.syncAllPages();
                    return;
                }

                // Bila di dalam butir daftar kosong: Enter mengakhiri daftar dan kembali ke paragraf normal
                const li = anchor?.nodeType === 1 ? anchor.closest('li') : anchor?.parentElement?.closest('li');
                if (li && (!li.textContent.trim() || li.textContent === '')) {
                    e.preventDefault();
                    const list = li.closest('ul, ol');
                    if (list) {
                        const newP = document.createElement('p');
                        newP.className = 'word-paragraph-text';
                        newP.innerHTML = '<br>';
                        li.remove();
                        if (list.children.length === 0) {
                            list.replaceWith(newP);
                        } else {
                            list.insertAdjacentElement('afterend', newP);
                        }
                        this.focusNode(newP, false);
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

                // Bila di dalam Paragraf biasa: Pecah paragraf pada posisi kursor menjadi blok baru (Microsoft Word & LibreOffice Writer)
                const p = anchor?.nodeType === 1 ? anchor.closest('p.word-paragraph-text, p') : anchor?.parentElement?.closest('p.word-paragraph-text, p');
                if (p && !p.closest('.word-quote-block') && !cell) {
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
                                this.focusNode(prev, true);
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

                        // Case C: Di awal butir daftar (li) -> Keluar dari daftar
                        if (block.tagName === 'LI') {
                            e.preventDefault();
                            const list = block.closest('ul, ol');
                            if (list) {
                                const newP = document.createElement('p');
                                newP.className = 'word-paragraph-text';
                                newP.innerHTML = block.innerHTML || '<br>';
                                block.remove();
                                if (list.children.length === 0) {
                                    list.replaceWith(newP);
                                } else {
                                    list.insertAdjacentElement('afterend', newP);
                                }
                                this.focusNode(newP, false);
                                this.updateCurrentFormat();
                                this.syncAllPages();
                                return;
                            }
                        }

                        // Case D: Di awal paragraf non-kosong -> LEBURKAN (Merge) dengan paragraf sebelumnya (Word & LibreOffice)
                        if (prev && !prev.classList.contains('word-sheet-header') && !['TABLE', 'FIGURE'].includes(prev.tagName)) {
                            e.preventDefault();
                            const blockHtml = block.innerHTML === '<br>' ? '' : block.innerHTML;
                            const prevLength = (prev.innerText || prev.textContent || '').length;

                            if (prev.innerHTML === '<br>') {
                                prev.innerHTML = blockHtml || '<br>';
                            } else if (blockHtml) {
                                prev.innerHTML += blockHtml;
                            }

                            block.remove();

                            // Letakkan kursor tepat di titik sambungan merger
                            try {
                                const newRange = document.createRange();
                                newRange.selectNodeContents(prev);
                                newRange.collapse(false);
                                sel.removeAllRanges();
                                sel.addRange(newRange);
                            } catch (err) {
                                this.focusNode(prev, true);
                            }

                            this.updateCurrentFormat();
                            this.syncAllPages();
                            return;
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
                        if (next && !next.classList.contains('word-sheet-footer') && !next.classList.contains('word-sheet-header') && !['TABLE', 'FIGURE'].includes(next.tagName)) {
                            e.preventDefault();
                            const nextHtml = next.innerHTML === '<br>' ? '' : next.innerHTML;
                            if (block.innerHTML === '<br>') {
                                block.innerHTML = nextHtml || '<br>';
                            } else if (nextHtml) {
                                block.innerHTML += nextHtml;
                            }
                            next.remove();
                            this.updateCurrentFormat();
                            this.syncAllPages();
                            return;
                        }
                    }
                }
            }
        },

        handleContentInput(e) {
            clearTimeout(this._inputTimer);
            this.savingState = 'saving';
            this._inputTimer = setTimeout(() => this.syncAllPages(), 500);

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
                        { pattern: /(\d+)-(\d+)\s$/, replace: '$1–$2 ' },
                        { pattern: /(\d+)\s*[xX]\s*(\d+)\s$/, replace: '$1 × $2 ' },
                        { pattern: /(?:^|\s)"([^\s"]+)"\s$/, replace: ' “$1” ' },
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

            if (/^1\.\s/.test(text)) {
                const clean = text.replace(/^1\.\s/, '');
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
                        const pageEls = document.querySelectorAll('.word-sheet-content');
                        const pageHtmls = [];
                        pageEls.forEach(el => pageHtmls.push(el.innerHTML));
                        if (wire) wire.save(pageHtmls);
                    } else if (key === 'z') {
                        e.preventDefault();
                        if (document.queryCommandEnabled && document.queryCommandEnabled('undo')) {
                            document.execCommand('undo');
                            this.updateCurrentFormat();
                            this.syncAllPages();
                        } else if (wire) {
                            wire.undo();
                        }
                    } else if (key === 'y') {
                        e.preventDefault();
                        if (document.queryCommandEnabled && document.queryCommandEnabled('redo')) {
                            document.execCommand('redo');
                            this.updateCurrentFormat();
                            this.syncAllPages();
                        } else if (wire) {
                            wire.redo();
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
                        if (wire) wire.setLineSpacing(1.0);
                    } else if (e.key === '2') {
                        e.preventDefault();
                        if (wire) wire.setLineSpacing(2.0);
                    } else if (e.key === '5') {
                        e.preventDefault();
                        if (wire) wire.setLineSpacing(1.5);
                    } else if (e.key === '[') {
                        e.preventDefault();
                        if (wire) wire.adjustFontSize(-1);
                    } else if (e.key === ']') {
                        e.preventDefault();
                        if (wire) wire.adjustFontSize(1);
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
                        document.execCommand('removeFormat', false, null);
                        if (wire) wire.clearFormatting();
                        this.updateCurrentFormat();
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
