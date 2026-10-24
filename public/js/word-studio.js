/**
 * Naskah Studio — Microsoft Word & LibreOffice Writer Canvas Engine
 * Menangani pengetikan alami, pintasan keyboard, format instan tanpa kehilangan fokus,
 * tipografi cerdas (Smart Typography), dan navigasi tabel Word.
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
        _syncTimer: null,
        _inputTimer: null,
        _selTimer: null,

        getWire() {
            return this.$wire || config.wire || (window.Livewire ? window.Livewire.find(this.$el?.closest('[wire\\:id]')?.getAttribute('wire:id')) : null);
        },

        syncAllPages(immediate = false) {
            clearTimeout(this._syncTimer);
            const doSync = () => {
                const pageEls = document.querySelectorAll('.word-sheet-content');
                const pageHtmls = [];
                pageEls.forEach(el => pageHtmls.push(el.innerHTML));
                if (pageHtmls.length > 0) {
                    const wire = this.getWire();
                    if (wire) {
                        wire.syncManuscriptPages(pageHtmls);
                    }
                }
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
        },

        format(cmd, val = null) {
            document.execCommand(cmd, false, val);
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

            // 1. TAB & SHIFT+TAB: Navigasi tabel dan indentasi tanpa kehilangan fokus
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

                // Pada daftar butir: Tab menjorok ke dalam, Shift+Tab keluar
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

            // 2. ENTER: Alur alami judul ke paragraf normal & keluar dari butir daftar kosong
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
                        this.syncAllPages();
                        return;
                    }
                }
            }

            // 3. BACKSPACE: Menghapus blok kosong dan memindahkan kursor ke blok sebelumnya dengan halus
            if (e.key === 'Backspace') {
                const anchor = sel.anchorNode;
                const block = anchor?.nodeType === 1
                    ? anchor.closest('p, h1, h2, h3, h4, li, blockquote')
                    : anchor?.parentElement?.closest('p, h1, h2, h3, h4, li, blockquote');

                if (block) {
                    const text = block.textContent;
                    if (!text || text.trim() === '' || block.innerHTML === '<br>') {
                        const prev = block.previousElementSibling;
                        if (prev && !prev.classList.contains('word-sheet-header')) {
                            e.preventDefault();
                            block.remove();
                            this.focusNode(prev, true);
                            this.syncAllPages();
                            return;
                        }
                    }
                }
            }
        },

        handleContentInput(e) {
            clearTimeout(this._inputTimer);
            this._inputTimer = setTimeout(() => this.syncAllPages(), 500);

            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;

            // Smart Typography Auto-Replacement (Word & LibreOffice Writer: -- -> —, ... -> …, (c) -> ©)
            if (sel.isCollapsed) {
                const node = sel.anchorNode;
                if (node && node.nodeType === Node.TEXT_NODE) {
                    const textVal = node.textContent;
                    const offset = sel.anchorOffset;
                    const beforeText = textVal.slice(0, offset);

                    const replacements = [
                        { pattern: /--\s$/, replace: '— ' },
                        { pattern: /\.\.\.\s$/, replace: '… ' },
                        { pattern: /\([cC]\)\s$/, replace: '© ' },
                        { pattern: /\([rR]\)\s$/, replace: '® ' },
                        { pattern: /\([tT][mM]\)\s$/, replace: '™ ' },
                        { pattern: /->\s$/, replace: '→ ' },
                        { pattern: /<-\s$/, replace: '← ' },
                        { pattern: /<=>\s$/, replace: '⇔ ' },
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
            if (!p) return;

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
                this.syncAllPages();
                return;
            }
        },

        init() {
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
                        if (wire) wire.undo();
                    } else if (key === 'y') {
                        e.preventDefault();
                        if (wire) wire.redo();
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
                        this.format('justifyCenter');
                    } else if (key === 'l' && !e.shiftKey) {
                        e.preventDefault();
                        this.format('justifyLeft');
                    } else if (key === 'r') {
                        e.preventDefault();
                        this.format('justifyRight');
                    } else if (key === 'j') {
                        e.preventDefault();
                        this.format('justifyFull');
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
                        if (wire) {
                            wire.copyFormat(this.activeBlock);
                            wire.notify('Format Disalin', 'Gaya format disalin (Format Painter).', 'info');
                        }
                    } else if (e.shiftKey && key === 'v') {
                        // Word Format Painter Paste
                        e.preventDefault();
                        if (wire) wire.pasteFormat(this.activeBlock);
                    } else if (key === 'h') {
                        // Word Find & Replace
                        e.preventDefault();
                        if (wire) wire.openDialog('find');
                    } else if (key === 'm' || e.code === 'Space') {
                        // Word/LibreOffice Clear Direct Formatting
                        e.preventDefault();
                        document.execCommand('removeFormat', false, null);
                        if (wire) wire.clearFormatting();
                    }
                }

                // Format Heading & Normal Style Word: Ctrl+Alt+1, 2, 3, 0
                if (isCtrl && e.altKey) {
                    if (e.key === '1') {
                        e.preventDefault();
                        this.format('formatBlock', '<h2>');
                    } else if (e.key === '2') {
                        e.preventDefault();
                        this.format('formatBlock', '<h3>');
                    } else if (e.key === '3') {
                        e.preventDefault();
                        this.format('formatBlock', '<h4>');
                    } else if (e.key === '0') {
                        e.preventDefault();
                        this.format('formatBlock', '<p>');
                    }
                }

                if (e.altKey && (e.key === '=' || e.key === '+')) {
                    e.preventDefault();
                    if (wire) wire.openDialog('equation');
                }
            });

            document.addEventListener('selectionchange', () => {
                clearTimeout(this._selTimer);
                this._selTimer = setTimeout(() => this.checkSelection(), 120);
            });

            setInterval(() => {
                const wire = this.getWire();
                if (wire) wire.tickSession();
            }, 5000);
        }
    };
};
