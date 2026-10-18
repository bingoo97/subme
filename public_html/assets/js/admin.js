document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var hasBootstrap = typeof window.bootstrap !== 'undefined';
    if (!hasBootstrap) {
        console.warn('Bootstrap not loaded');
    }

    var doc = document;

    function q(selector, root) {
        return (root || doc).querySelector(selector);
    }

    function qa(selector, root) {
        return Array.prototype.slice.call((root || doc).querySelectorAll(selector));
    }

    function closest(element, selector) {
        return element && typeof element.closest === 'function' ? element.closest(selector) : null;
    }

    function setHidden(element, hidden) {
        if (!element) {
            return;
        }
        if (hidden) {
            element.setAttribute('hidden', 'hidden');
        } else {
            element.removeAttribute('hidden');
        }
    }

    function debounce(fn, wait) {
        var timer = 0;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = window.setTimeout(function () {
                fn.apply(null, args);
            }, wait);
        };
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function jsonFetch(url, options) {
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return { ok: false, message: 'Invalid JSON response.' };
            }).then(function (payload) {
                payload.__http_ok = response.ok;
                payload.__status = response.status;
                return payload;
            });
        });
    }

    function encodeBase64Utf8(value) {
        var input = String(value || '');
        try {
            return window.btoa(unescape(encodeURIComponent(input)));
        } catch (error) {
            return '';
        }
    }

    function buildUrlEncodedPayload(values) {
        var params = new URLSearchParams();
        Object.keys(values || {}).forEach(function (key) {
            params.append(key, String(values[key] == null ? '' : values[key]));
        });
        return params.toString();
    }

    function buildUrlWithQuery(url, params) {
        var base = String(url || '').trim();
        var query = toQuery(params || {});

        if (!base) {
            return query ? ('?' + query) : '';
        }
        if (!query) {
            return base;
        }

        return base + (base.indexOf('?') === -1 ? '?' : '&') + query;
    }

    function fallbackCopyText(text) {
        var helper = document.createElement('textarea');
        helper.value = String(text || '');
        helper.setAttribute('readonly', 'readonly');
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        helper.style.pointerEvents = 'none';
        document.body.appendChild(helper);
        helper.focus();
        helper.select();
        try {
            document.execCommand('copy');
        } catch (error) {
            // noop
        }
        document.body.removeChild(helper);
    }

    function copyTextWithFallback(text) {
        var value = String(text || '');
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(value).catch(function () {
                fallbackCopyText(value);
            });
        }

        fallbackCopyText(value);
        return Promise.resolve();
    }

    function initRichTextEditors() {
        var allowedTags = {
            P: true,
            BR: true,
            HR: true,
            STRONG: true,
            B: true,
            EM: true,
            I: true,
            U: true,
            H1: true,
            H2: true,
            H3: true,
            H4: true,
            H5: true,
            H6: true,
            UL: true,
            OL: true,
            LI: true,
            BLOCKQUOTE: true,
            A: true,
            IMG: true,
            VIDEO: true,
            DIV: true,
            SPAN: true,
            TABLE: true,
            THEAD: true,
            TBODY: true,
            TR: true,
            TD: true,
            TH: true
        };
        var alwaysAllowedClassNames = {
            red: true,
            'border-red': true
        };
        var allowedStyleProperties = {
            'text-align': true,
            color: true,
            background: true,
            'background-color': true,
            'font-size': true,
            'font-weight': true,
            'font-style': true,
            'font-family': true,
            'line-height': true,
            'letter-spacing': true,
            'text-decoration': true,
            'text-transform': true,
            'white-space': true,
            display: true,
            width: true,
            'min-width': true,
            'max-width': true,
            height: true,
            'min-height': true,
            'max-height': true,
            margin: true,
            'margin-top': true,
            'margin-right': true,
            'margin-bottom': true,
            'margin-left': true,
            padding: true,
            'padding-top': true,
            'padding-right': true,
            'padding-bottom': true,
            'padding-left': true,
            border: true,
            'border-top': true,
            'border-right': true,
            'border-bottom': true,
            'border-left': true,
            'border-color': true,
            'border-width': true,
            'border-style': true,
            'border-radius': true,
            'box-shadow': true,
            opacity: true,
            overflow: true,
            'overflow-x': true,
            'overflow-y': true,
            float: true,
            clear: true
        };

        function isSafeUrl(value, allowDataImages) {
            var url = String(value || '').trim();
            if (!url) {
                return false;
            }

            var lower = url.toLowerCase();
            if (allowDataImages && lower.indexOf('data:image/') === 0) {
                return true;
            }

            if (
                !/^[a-z][a-z0-9+.-]*:/i.test(url)
                && lower.indexOf('//') !== 0
                && !/\s/.test(url)
                && /^[./A-Za-z0-9_~%+\-?&=#@(),!$*;'[\]]+$/.test(url)
            ) {
                return true;
            }

            return lower.indexOf('http://') === 0
                || lower.indexOf('https://') === 0
                || lower.indexOf('mailto:') === 0
                || lower.indexOf('tel:') === 0
                || lower.indexOf('/') === 0
                || lower.indexOf('#') === 0;
        }

        function sanitizeStyle(value) {
            var safeDeclarations = [];

            String(value || '')
                .split(';')
                .forEach(function (declaration) {
                    var parts = String(declaration || '').split(':');
                    var property = String(parts.shift() || '').trim().toLowerCase();
                    var propertyValue = String(parts.join(':') || '').trim();
                    var normalizedValue = propertyValue.replace(/\s+/g, ' ');

                    if (!property || !normalizedValue || !allowedStyleProperties[property]) {
                        return;
                    }

                    if (/(expression\s*\(|javascript\s*:|vbscript\s*:|data\s*:|url\s*\(|@import|behavior\s*:)/i.test(normalizedValue)) {
                        return;
                    }

                    if (!/^[#(),.%+\-\/:\w\s"'!]+$/.test(normalizedValue)) {
                        return;
                    }

                    safeDeclarations.push(property + ':' + normalizedValue);
                });

            return safeDeclarations.join(';') + (safeDeclarations.length ? ';' : '');
        }

        function sanitizeClassNames(value) {
            return String(value || '')
                .split(/\s+/)
                .map(function (className) {
                    return String(className || '').trim();
                })
                .filter(function (className) {
                    return !!alwaysAllowedClassNames[className] || /^[A-Za-z0-9_-]+$/.test(className);
                })
                .join(' ');
        }

        function styleHasBold(value) {
            return /font-weight\s*:\s*(bold|[7-9]00)/i.test(String(value || ''));
        }

        function styleHasItalic(value) {
            return /font-style\s*:\s*italic/i.test(String(value || ''));
        }

        function styleHasUnderline(value) {
            return /text-decoration(?:-line)?\s*:\s*[^;]*underline/i.test(String(value || ''));
        }

        function wrapNodeChildren(node, tagName) {
            var wrapper;
            if (!node || !tagName) {
                return null;
            }

            wrapper = doc.createElement(tagName);
            while (node.firstChild) {
                wrapper.appendChild(node.firstChild);
            }
            node.appendChild(wrapper);
            return wrapper;
        }

        function unwrapNode(node) {
            var parent = node && node.parentNode ? node.parentNode : null;
            if (!node || !parent) {
                return;
            }

            while (node.firstChild) {
                parent.insertBefore(node.firstChild, node);
            }
            parent.removeChild(node);
        }

        function sanitizeHtml(html) {
            var template = doc.createElement('template');
            template.innerHTML = String(html || '');

            function walk(node) {
                var children = Array.prototype.slice.call(node.childNodes || []);
                children.forEach(function (child) {
                    if (child.nodeType === 1) {
                        var tag = child.tagName ? child.tagName.toUpperCase() : '';
                        var rawStyle = child.getAttribute('style') || '';
                        var preserveBold = styleHasBold(rawStyle);
                        var preserveItalic = styleHasItalic(rawStyle);
                        var preserveUnderline = styleHasUnderline(rawStyle);
                        if (!allowedTags[tag]) {
                            if (/^(SCRIPT|STYLE|IFRAME|OBJECT|EMBED|FORM|INPUT|BUTTON|TEXTAREA|SELECT|OPTION)$/i.test(tag)) {
                                child.remove();
                                return;
                            }

                            while (child.firstChild) {
                                node.insertBefore(child.firstChild, child);
                            }
                            child.remove();
                            return;
                        }

                        Array.prototype.slice.call(child.attributes || []).forEach(function (attribute) {
                            var attrName = String(attribute.name || '').toLowerCase();
                            var attrValue = String(attribute.value || '');
                            if (attrName.indexOf('on') === 0) {
                                child.removeAttribute(attribute.name);
                                return;
                            }

                            if (tag === 'A' && attrName === 'href') {
                                if (!isSafeUrl(attrValue, false)) {
                                    child.removeAttribute(attribute.name);
                                }
                                return;
                            }

                            if (tag === 'IMG' && attrName === 'src') {
                                if (!isSafeUrl(attrValue, true)) {
                                    child.remove();
                                }
                                return;
                            }

                            if (tag === 'VIDEO' && (attrName === 'src' || attrName === 'poster')) {
                                if (!isSafeUrl(attrValue, false)) {
                                    child.removeAttribute(attribute.name);
                                }
                                return;
                            }

                            if (attrName === 'style') {
                                var safeStyle = sanitizeStyle(attrValue);
                                if (safeStyle) {
                                    child.setAttribute('style', safeStyle);
                                } else {
                                    child.removeAttribute('style');
                                }
                                return;
                            }

                            if (attrName === 'class') {
                                var safeClasses = sanitizeClassNames(attrValue);
                                if (safeClasses) {
                                    child.setAttribute('class', safeClasses);
                                } else {
                                    child.removeAttribute('class');
                                }
                                return;
                            }

                            if (tag === 'A' && (attrName === 'target' || attrName === 'rel' || attrName === 'title')) {
                                return;
                            }

                            if (tag === 'IMG' && (attrName === 'alt' || attrName === 'title' || attrName === 'width' || attrName === 'height')) {
                                return;
                            }

                            if (tag === 'VIDEO' && (
                                attrName === 'controls'
                                || attrName === 'preload'
                                || attrName === 'playsinline'
                                || attrName === 'muted'
                                || attrName === 'loop'
                                || attrName === 'autoplay'
                                || attrName === 'width'
                                || attrName === 'height'
                                || attrName === 'title'
                            )) {
                                return;
                            }

                            child.removeAttribute(attribute.name);
                        });

                        if (preserveBold) {
                            wrapNodeChildren(child, 'strong');
                        }
                        if (preserveItalic) {
                            wrapNodeChildren(child, 'em');
                        }
                        if (preserveUnderline) {
                            wrapNodeChildren(child, 'u');
                        }

                        if (tag === 'A' && child.getAttribute('href')) {
                            child.setAttribute('target', '_blank');
                            child.setAttribute('rel', 'noopener noreferrer');
                        }

                        walk(child);

                        if (tag === 'SPAN' && child.attributes.length === 0) {
                            unwrapNode(child);
                        }
                        return;
                    }

                    if (child.nodeType === 8) {
                        child.remove();
                    }
                });
            }

            walk(template.content);

            var sanitized = template.innerHTML
                .replace(/<(p|div|h1|h2|h3|h4|h5|h6|blockquote)>\s*<\/\1>/gi, '')
                .replace(/<p><br><\/p>/gi, '')
                .trim();

            var preview = doc.createElement('div');
            preview.innerHTML = sanitized;
            var plain = (preview.textContent || preview.innerText || '').replace(/\s+/g, ' ').trim();
            if (!plain && !preview.querySelector('img')) {
                return '';
            }

            return sanitized;
        }

        function renderToolbarButton(config) {
            var button = doc.createElement('button');
            button.type = 'button';
            button.className = 'admin-rich-editor__tool';
            button.setAttribute('data-rich-editor-action', config.action || '');
            button.setAttribute('title', config.label);
            button.setAttribute('aria-label', config.label);
            button.innerHTML = config.html;
            return button;
        }

        function renderToolbarColorInput() {
            var input = doc.createElement('input');
            input.type = 'color';
            input.className = 'admin-rich-editor__color';
            input.value = '#162033';
            input.setAttribute('data-rich-editor-control', 'color');
            input.setAttribute('title', 'Text color');
            input.setAttribute('aria-label', 'Text color');
            return input;
        }

        function renderToolbarWeightSelect() {
            var select = doc.createElement('select');
            var options = [
                { value: '', label: 'Weight' },
                { value: '400', label: 'Regular' },
                { value: '500', label: 'Medium' },
                { value: '600', label: 'Semibold' },
                { value: '700', label: 'Bold' },
                { value: '800', label: 'Extra bold' }
            ];

            select.className = 'admin-rich-editor__select';
            select.setAttribute('data-rich-editor-control', 'font-weight');
            select.setAttribute('title', 'Font weight');
            select.setAttribute('aria-label', 'Font weight');

            options.forEach(function (optionConfig) {
                var option = doc.createElement('option');
                option.value = optionConfig.value;
                option.textContent = optionConfig.label;
                select.appendChild(option);
            });

            return select;
        }

        function renderToolbarFontSizeSelect() {
            var select = doc.createElement('select');
            var placeholder = doc.createElement('option');
            var size;

            select.className = 'admin-rich-editor__select admin-rich-editor__select--font-size';
            select.setAttribute('data-rich-editor-control', 'font-size');
            select.setAttribute('title', 'Font size');
            select.setAttribute('aria-label', 'Font size');

            placeholder.value = '';
            placeholder.textContent = 'Size';
            select.appendChild(placeholder);

            for (size = 8; size <= 32; size += 1) {
                var option = doc.createElement('option');
                option.value = size + 'px';
                option.textContent = size + 'px';
                select.appendChild(option);
            }

            return select;
        }

        function editorCsrfToken() {
            var input = q('input[name="_csrf"]');
            return input ? String(input.value || '') : '';
        }

        function normalizeImageDimension(value) {
            var raw = String(value || '').trim();
            if (!raw) {
                return '';
            }

            if (/^\d{1,5}(\.\d{1,2})?$/.test(raw)) {
                return raw + 'px';
            }

            if (/^\d{1,5}(\.\d{1,2})?(px|%|rem|em|vw|vh)$/i.test(raw)) {
                return raw;
            }

            if (raw.toLowerCase() === 'auto') {
                return 'auto';
            }

            return '';
        }

        function buildEditorImageHtml(url, altText, widthValue, heightValue, mode) {
            var safeMode = String(mode || 'responsive');
            var width = normalizeImageDimension(widthValue);
            var height = normalizeImageDimension(heightValue);
            var styles = ['display:block'];

            if (safeMode === 'full') {
                styles.push('width:100%', 'max-width:100%', 'height:auto');
            } else if (safeMode === 'custom') {
                if (width) {
                    styles.push('width:' + width, 'max-width:100%');
                } else {
                    styles.push('max-width:100%', 'width:auto');
                }
                styles.push('height:' + (height || 'auto'));
            } else {
                styles.push('max-width:100%', 'height:auto', 'width:auto');
            }

            return '<p><img src="' + escapeHtml(url) + '" alt="' + escapeHtml(altText || '') + '" style="' + escapeHtml(styles.join(';') + ';') + '"></p>';
        }

        function createEditorModal(className, title) {
            var overlay = doc.createElement('div');
            overlay.className = className + ' admin-editor-modal';
            overlay.setAttribute('hidden', 'hidden');
            overlay.innerHTML = [
                '<div class="admin-editor-modal__backdrop" data-editor-modal-close></div>',
                '<section class="admin-editor-modal__panel" role="dialog" aria-modal="true">',
                '<div class="admin-editor-modal__header">',
                '<strong>' + escapeHtml(title) + '</strong>',
                '<button type="button" class="btn btn-outline-dark btn-sm" data-editor-modal-close aria-label="Close"><i class="bi bi-x-lg"></i></button>',
                '</div>',
                '<div class="admin-editor-modal__body"></div>',
                '</section>'
            ].join('');
            doc.body.appendChild(overlay);
            overlay.addEventListener('click', function (event) {
                if (closest(event.target, '[data-editor-modal-close]')) {
                    overlay.setAttribute('hidden', 'hidden');
                }
            });
            return overlay;
        }

        var imageSettingsModal = null;
        var imageSettingsTarget = null;
        var imageSettingsEditor = null;

        function openImageSettingsModal(editorApi, imageNode) {
            var body;
            var src;
            var alt;
            var styleValue;
            var widthMatch;
            var heightMatch;

            if (!editorApi || !imageNode) {
                return;
            }

            imageSettingsEditor = editorApi;
            imageSettingsTarget = imageNode;

            if (!imageSettingsModal) {
                imageSettingsModal = createEditorModal('admin-editor-image-modal', 'Ustawienia obrazka');
                body = q('.admin-editor-modal__body', imageSettingsModal);
                body.innerHTML = [
                    '<form class="admin-editor-image-form" data-editor-image-form>',
                    '<label><span>Adres URL</span><input type="text" class="form-control" data-editor-image-src placeholder="https:// albo /uploads/..."></label>',
                    '<label><span>Opis ALT</span><input type="text" class="form-control" data-editor-image-alt placeholder="Opis obrazka"></label>',
                    '<label><span>Rozmiar</span><select class="form-select" data-editor-image-mode><option value="responsive">Responsywnie</option><option value="full">Pełna szerokość</option><option value="custom">Własny rozmiar</option></select></label>',
                    '<div class="admin-editor-image-form__grid">',
                    '<label><span>Szerokość</span><input type="text" class="form-control" data-editor-image-width placeholder="np. 420px albo 50%"></label>',
                    '<label><span>Wysokość</span><input type="text" class="form-control" data-editor-image-height placeholder="auto"></label>',
                    '</div>',
                    '<div class="admin-editor-image-form__preview"><img alt="" data-editor-image-preview></div>',
                    '<div class="admin-editor-image-form__actions">',
                    '<button type="button" class="btn btn-outline-danger" data-editor-image-remove>Usuń obrazek</button>',
                    '<button type="submit" class="btn btn-success">Zapisz obrazek</button>',
                    '</div>',
                    '</form>'
                ].join('');

                body.addEventListener('input', function () {
                    var preview = q('[data-editor-image-preview]', body);
                    var url = q('[data-editor-image-src]', body);
                    if (preview && url) {
                        preview.src = String(url.value || '');
                    }
                });

                body.addEventListener('click', function (event) {
                    var removeButton = closest(event.target, '[data-editor-image-remove]');
                    if (!removeButton || !imageSettingsTarget || !imageSettingsEditor) {
                        return;
                    }
                    event.preventDefault();
                    imageSettingsTarget.remove();
                    imageSettingsEditor.sync();
                    imageSettingsModal.setAttribute('hidden', 'hidden');
                });

                body.addEventListener('submit', function (event) {
                    var urlInput = q('[data-editor-image-src]', body);
                    var altInput = q('[data-editor-image-alt]', body);
                    var modeInput = q('[data-editor-image-mode]', body);
                    var widthInput = q('[data-editor-image-width]', body);
                    var heightInput = q('[data-editor-image-height]', body);
                    var html;
                    var template;
                    var nextImage;

                    event.preventDefault();
                    if (!imageSettingsTarget || !imageSettingsEditor || !urlInput) {
                        return;
                    }
                    if (!isSafeUrl(urlInput.value, true)) {
                        window.alert('Ten adres obrazka nie jest dozwolony.');
                        return;
                    }

                    html = buildEditorImageHtml(
                        urlInput.value,
                        altInput ? altInput.value : '',
                        widthInput ? widthInput.value : '',
                        heightInput ? heightInput.value : '',
                        modeInput ? modeInput.value : 'responsive'
                    );
                    template = doc.createElement('template');
                    template.innerHTML = html;
                    nextImage = template.content.querySelector('img');
                    if (!nextImage) {
                        return;
                    }

                    imageSettingsTarget.setAttribute('src', nextImage.getAttribute('src') || '');
                    imageSettingsTarget.setAttribute('alt', nextImage.getAttribute('alt') || '');
                    imageSettingsTarget.setAttribute('style', nextImage.getAttribute('style') || '');
                    imageSettingsTarget.removeAttribute('width');
                    imageSettingsTarget.removeAttribute('height');
                    imageSettingsEditor.sync();
                    imageSettingsModal.setAttribute('hidden', 'hidden');
                });
            }

            body = q('.admin-editor-modal__body', imageSettingsModal);
            src = imageNode.getAttribute('src') || '';
            alt = imageNode.getAttribute('alt') || '';
            styleValue = imageNode.getAttribute('style') || '';
            widthMatch = styleValue.match(/(?:^|;)\s*width\s*:\s*([^;]+)/i);
            heightMatch = styleValue.match(/(?:^|;)\s*height\s*:\s*([^;]+)/i);

            q('[data-editor-image-src]', body).value = src;
            q('[data-editor-image-alt]', body).value = alt;
            q('[data-editor-image-width]', body).value = widthMatch ? widthMatch[1].trim() : (imageNode.getAttribute('width') || '');
            q('[data-editor-image-height]', body).value = heightMatch ? heightMatch[1].trim() : (imageNode.getAttribute('height') || '');
            q('[data-editor-image-mode]', body).value = /width\s*:\s*100%/i.test(styleValue) ? 'full' : (widthMatch || heightMatch ? 'custom' : 'responsive');
            q('[data-editor-image-preview]', body).src = src;
            imageSettingsModal.removeAttribute('hidden');
            q('[data-editor-image-src]', body).focus();
        }

        var editorGalleryModal = null;
        var editorGalleryActiveEditor = null;
        var editorGalleryItems = [];

        function renderEditorGalleryItems() {
            var body = editorGalleryModal ? q('.admin-editor-modal__body', editorGalleryModal) : null;
            var grid = body ? q('[data-editor-gallery-grid]', body) : null;
            if (!grid) {
                return;
            }

            if (!editorGalleryItems.length) {
                grid.innerHTML = '<div class="admin-editor-gallery__empty">Brak obrazów w galerii.</div>';
                return;
            }

            grid.innerHTML = editorGalleryItems.map(function (item) {
                var url = String(item.url || '');
                return [
                    '<article class="admin-editor-gallery__item" data-editor-gallery-item data-url="' + escapeHtml(url) + '">',
                    '<button type="button" class="admin-editor-gallery__thumb" data-editor-gallery-insert><img src="' + escapeHtml(url) + '" alt=""></button>',
                    '<div class="admin-editor-gallery__meta">',
                    '<span title="' + escapeHtml(item.name || '') + '">' + escapeHtml(item.name || 'image') + '</span>',
                    '<button type="button" class="btn btn-outline-danger btn-sm" data-editor-gallery-delete>Usuń</button>',
                    '</div>',
                    '</article>'
                ].join('');
            }).join('');
        }

        function loadEditorGallery() {
            if (!editorGalleryModal) {
                return Promise.resolve();
            }

            var body = q('.admin-editor-modal__body', editorGalleryModal);
            var status = q('[data-editor-gallery-status]', body);
            if (status) {
                status.textContent = 'Ładowanie...';
            }

            return jsonFetch('/admin/editor_gallery.php?action=list', { credentials: 'same-origin' }).then(function (payload) {
                editorGalleryItems = payload && payload.ok && Array.isArray(payload.items) ? payload.items : [];
                renderEditorGalleryItems();
                if (status) {
                    status.textContent = payload && payload.ok ? '' : 'Nie udało się załadować galerii.';
                }
            }).catch(function () {
                if (status) {
                    status.textContent = 'Nie udało się załadować galerii.';
                }
            });
        }

        function openEditorGallery(editorApi) {
            var body;
            editorGalleryActiveEditor = editorApi || null;
            if (editorGalleryActiveEditor && typeof editorGalleryActiveEditor.saveSelection === 'function') {
                editorGalleryActiveEditor.saveSelection();
            }

            if (!editorGalleryModal) {
                editorGalleryModal = createEditorModal('admin-editor-gallery-modal', 'Galeria edytora');
                body = q('.admin-editor-modal__body', editorGalleryModal);
                body.innerHTML = [
                    '<div class="admin-editor-gallery">',
                    '<div class="admin-editor-gallery__upload">',
                    '<input type="file" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" data-editor-gallery-file>',
                    '<button type="button" class="btn btn-dark" data-editor-gallery-upload>Dodaj zdjęcie</button>',
                    '</div>',
                    '<div class="admin-editor-gallery__status" data-editor-gallery-status></div>',
                    '<div class="admin-editor-gallery__grid" data-editor-gallery-grid></div>',
                    '</div>'
                ].join('');

                body.addEventListener('click', function (event) {
                    var item = closest(event.target, '[data-editor-gallery-item]');
                    var uploadButton = closest(event.target, '[data-editor-gallery-upload]');
                    var deleteButton = closest(event.target, '[data-editor-gallery-delete]');
                    var insertButton = closest(event.target, '[data-editor-gallery-insert]');
                    var fileInput = q('[data-editor-gallery-file]', body);
                    var status = q('[data-editor-gallery-status]', body);
                    var formData;
                    var url;

                    if (uploadButton) {
                        event.preventDefault();
                        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                            if (status) {
                                status.textContent = 'Wybierz plik obrazu.';
                            }
                            return;
                        }
                        formData = new FormData();
                        formData.append('action', 'upload');
                        formData.append('_csrf', editorCsrfToken());
                        formData.append('image', fileInput.files[0]);
                        if (status) {
                            status.textContent = 'Wgrywanie...';
                        }
                        fetch('/admin/editor_gallery.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        }).then(function (response) {
                            return response.json();
                        }).then(function (payload) {
                            editorGalleryItems = payload && payload.ok && Array.isArray(payload.items) ? payload.items : editorGalleryItems;
                            fileInput.value = '';
                            renderEditorGalleryItems();
                            if (status) {
                                status.textContent = payload && payload.ok ? 'Zdjęcie dodane do galerii.' : (payload.message || 'Nie udało się dodać zdjęcia.');
                            }
                        }).catch(function () {
                            if (status) {
                                status.textContent = 'Nie udało się dodać zdjęcia.';
                            }
                        });
                        return;
                    }

                    if (deleteButton && item) {
                        event.preventDefault();
                        url = item.getAttribute('data-url') || '';
                        if (!url || !window.confirm('Usunąć ten obraz z galerii?')) {
                            return;
                        }
                        formData = new FormData();
                        formData.append('action', 'delete');
                        formData.append('_csrf', editorCsrfToken());
                        formData.append('url', url);
                        fetch('/admin/editor_gallery.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        }).then(function (response) {
                            return response.json();
                        }).then(function (payload) {
                            editorGalleryItems = payload && Array.isArray(payload.items) ? payload.items : editorGalleryItems;
                            renderEditorGalleryItems();
                            if (status) {
                                status.textContent = payload && payload.ok ? 'Obraz usunięty.' : (payload.message || 'Nie udało się usunąć obrazu.');
                            }
                        }).catch(function () {
                            if (status) {
                                status.textContent = 'Nie udało się usunąć obrazu.';
                            }
                        });
                        return;
                    }

                    if (insertButton && item && editorGalleryActiveEditor) {
                        event.preventDefault();
                        url = item.getAttribute('data-url') || '';
                        if (!url) {
                            return;
                        }
                        editorGalleryActiveEditor.insertImage(url, '');
                        editorGalleryModal.setAttribute('hidden', 'hidden');
                    }
                });
            }

            editorGalleryModal.removeAttribute('hidden');
            loadEditorGallery();
        }

        qa('textarea[data-admin-rich-editor]').forEach(function (textarea) {
            if (!textarea || textarea.getAttribute('data-admin-rich-editor-ready') === '1') {
                return;
            }

            textarea.setAttribute('data-admin-rich-editor-ready', '1');

            var allowImages = textarea.getAttribute('data-admin-rich-editor-images') !== '0';
            var allowImageUrls = textarea.getAttribute('data-admin-rich-editor-image-urls') === '1';
            var allowVideos = textarea.getAttribute('data-admin-rich-editor-videos') === '1';
            var wrapper = doc.createElement('div');
            wrapper.className = 'admin-rich-editor';

            var toolbar = doc.createElement('div');
            toolbar.className = 'admin-rich-editor__toolbar';
            var colorInput = renderToolbarColorInput();
            var weightSelect = renderToolbarWeightSelect();
            var fontSizeSelect = renderToolbarFontSizeSelect();

            var tools = [
                { action: 'paragraph', label: 'Paragraph', html: '<strong>P</strong>' },
                { action: 'h2', label: 'Heading 2', html: '<strong>H2</strong>' },
                { action: 'h3', label: 'Heading 3', html: '<strong>H3</strong>' },
                { action: 'separator', label: '', html: '' },
                { action: 'bold', label: 'Bold', html: '<i class="bi bi-type-bold"></i>' },
                { action: 'italic', label: 'Italic', html: '<i class="bi bi-type-italic"></i>' },
                { action: 'underline', label: 'Underline', html: '<i class="bi bi-type-underline"></i>' },
                { action: 'separator', label: '', html: '' },
                { action: 'textControls', label: '', html: '' },
                { action: 'separator', label: '', html: '' },
                { action: 'unorderedList', label: 'Bullet list', html: '<i class="bi bi-list-ul"></i>' },
                { action: 'orderedList', label: 'Numbered list', html: '<i class="bi bi-list-ol"></i>' },
                { action: 'quote', label: 'Quote', html: '<strong>&ldquo;</strong>' },
                { action: 'link', label: 'Insert link', html: '<i class="bi bi-link-45deg"></i>' },
                { action: 'separator', label: '', html: '' },
                { action: 'alignLeft', label: 'Align left', html: '<strong>L</strong>' },
                { action: 'alignCenter', label: 'Align center', html: '<strong>C</strong>' },
                { action: 'alignRight', label: 'Align right', html: '<strong>R</strong>' },
                { action: 'clear', label: 'Clear formatting', html: '<i class="bi bi-eraser"></i>' },
                { action: 'redBox', label: 'Insert red warning box', html: '<i class="bi bi-exclamation-triangle-fill"></i>' }
            ];

            if (allowImages) {
                tools.push({ action: 'image', label: 'Insert image from disk', html: '<i class="bi bi-image"></i>' });
            }

            if (allowImageUrls) {
                tools.push({ action: 'imageUrl', label: 'Insert image from URL', html: '<i class="bi bi-card-image"></i>' });
            }

            if (allowVideos) {
                tools.push({ action: 'videoUrl', label: 'Insert video from URL', html: '<i class="bi bi-camera-video"></i>' });
            }

            tools.push({ action: 'source', label: 'Toggle HTML mode', html: '<i class="bi bi-code-slash"></i>' });

            tools.forEach(function (tool) {
                if (tool.action === 'separator') {
                    var separator = doc.createElement('span');
                    separator.className = 'admin-rich-editor__toolbar-separator';
                    separator.setAttribute('aria-hidden', 'true');
                    toolbar.appendChild(separator);
                    return;
                }
                if (tool.action === 'textControls') {
                    toolbar.appendChild(colorInput);
                    toolbar.appendChild(weightSelect);
                    toolbar.appendChild(fontSizeSelect);
                    return;
                }
                toolbar.appendChild(renderToolbarButton(tool));
            });

            var surface = doc.createElement('div');
            surface.className = 'admin-rich-editor__surface';
            surface.contentEditable = 'true';
            surface.setAttribute('data-placeholder', 'Write content here...');

            var source = doc.createElement('textarea');
            source.className = 'admin-rich-editor__source form-control';
            source.setAttribute('rows', '20');
            source.setAttribute('hidden', 'hidden');
            source.style.minHeight = '500px';

            var fileInput = doc.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = 'image/*';
            fileInput.hidden = true;

            var galleryActions = doc.createElement('div');
            galleryActions.className = 'admin-rich-editor__gallery-actions';
            galleryActions.innerHTML = '<button type="button" class="btn btn-success btn-md" data-rich-editor-gallery><i class="bi bi-images" aria-hidden="true"></i><span>Galeria obrazów</span></button>';

            wrapper.appendChild(toolbar);
            wrapper.appendChild(surface);
            wrapper.appendChild(source);
            wrapper.appendChild(fileInput);
            wrapper.appendChild(galleryActions);
            textarea.insertAdjacentElement('afterend', wrapper);
            textarea.hidden = true;
            var helpText = wrapper.nextElementSibling;
            if (helpText && helpText.classList && helpText.classList.contains('form-text')) {
                helpText.classList.add('admin-rich-editor__gallery-hint');
                if (!helpText.nextElementSibling || !helpText.nextElementSibling.classList || !helpText.nextElementSibling.classList.contains('admin-rich-editor__hint-divider')) {
                    var hintDivider = doc.createElement('hr');
                    hintDivider.className = 'admin-rich-editor__hint-divider';
                    helpText.insertAdjacentElement('afterend', hintDivider);
                }
            }
            var savedRange = null;
            var editorApi = null;

            function dispatchTextareaEvent(name) {
                var event;
                if (!name) {
                    return;
                }

                if (typeof window.Event === 'function') {
                    event = new window.Event(name, { bubbles: true });
                } else {
                    event = doc.createEvent('Event');
                    event.initEvent(name, true, true);
                }
                textarea.dispatchEvent(event);
            }

            function isSourceMode() {
                return !source.hasAttribute('hidden');
            }

            function syncTextareaFromSurface(emitInput) {
                textarea.value = sanitizeHtml(surface.innerHTML);
                if (emitInput) {
                    dispatchTextareaEvent('input');
                }
            }

            function syncSourceFromSurface() {
                syncTextareaFromSurface(false);
                source.value = textarea.value;
            }

            function syncSurfaceFromSource() {
                surface.innerHTML = sanitizeHtml(source.value);
                syncTextareaFromSurface(false);
            }

            function ensureSourceEditorHeight() {
                var targetHeight = Math.max(500, surface.offsetHeight || 0, source.scrollHeight || 0);
                source.style.minHeight = '500px';
                source.style.height = targetHeight + 'px';
            }

            function updateToggleButton() {
                qa('[data-rich-editor-action="source"]', toolbar).forEach(function (button) {
                    button.classList.toggle('is-active', isSourceMode());
                });
                qa('[data-rich-editor-control]', toolbar).forEach(function (control) {
                    control.disabled = isSourceMode();
                });
            }

            function getSelectionRange() {
                var selection = window.getSelection ? window.getSelection() : null;
                var range = null;
                var container = null;

                if (!selection || selection.rangeCount <= 0) {
                    return null;
                }

                range = selection.getRangeAt(0);
                container = range.commonAncestorContainer;

                if (container && container.nodeType === 3) {
                    container = container.parentNode;
                }

                if (!container || !surface.contains(container)) {
                    return null;
                }

                return range;
            }

            function saveSelectionRange() {
                var range = getSelectionRange();
                if (range) {
                    savedRange = range.cloneRange();
                }
            }

            function isSavedRangeUsable() {
                var container;
                if (!savedRange) {
                    return false;
                }

                container = savedRange.commonAncestorContainer;
                if (container && container.nodeType === 3) {
                    container = container.parentNode;
                }

                return !!container && surface.contains(container);
            }

            function applyInlineStyle(property, value) {
                var range = getSelectionRange();
                var fragment;
                var wrapperNode;

                if (isSourceMode() || !property || !value || !range || range.collapsed) {
                    return false;
                }

                fragment = range.extractContents();
                if (!fragment || !fragment.childNodes || fragment.childNodes.length === 0) {
                    return false;
                }

                wrapperNode = doc.createElement('span');
                wrapperNode.setAttribute('style', property + ':' + String(value) + ';');
                wrapperNode.appendChild(fragment);
                range.insertNode(wrapperNode);

                surface.innerHTML = sanitizeHtml(surface.innerHTML);
                syncTextareaFromSurface(true);
                return true;
            }

            function runCommand(command, value) {
                if (isSourceMode()) {
                    return;
                }

                surface.focus();
                try {
                    doc.execCommand('styleWithCSS', false, command === 'foreColor');
                } catch (error) {
                    // noop
                }
                try {
                    doc.execCommand('useCSS', false, false);
                } catch (error) {
                    // noop
                }
                if (command === 'formatBlock') {
                    doc.execCommand(command, false, value);
                } else if (command === 'insertHTML') {
                    doc.execCommand(command, false, value);
                } else {
                    doc.execCommand(command, false, value || null);
                }
                syncTextareaFromSurface(true);
            }

            function toggleSourceMode() {
                if (isSourceMode()) {
                    syncSurfaceFromSource();
                    source.setAttribute('hidden', 'hidden');
                    surface.removeAttribute('hidden');
                    surface.focus();
                } else {
                    syncSourceFromSurface();
                    surface.setAttribute('hidden', 'hidden');
                    source.removeAttribute('hidden');
                    ensureSourceEditorHeight();
                    source.focus();
                }
                updateToggleButton();
            }

            function promptForLink() {
                var url = window.prompt('Paste the link URL:', 'https://');
                if (!url) {
                    return;
                }

                if (!isSafeUrl(url, false)) {
                    window.alert('This link address is not allowed.');
                    return;
                }

                runCommand('createLink', url);
            }

            function promptForVideoUrl() {
                var url = window.prompt('Paste the direct video URL:', 'https://');
                var videoHtml = '';

                if (!url) {
                    return;
                }

                if (!isSafeUrl(url, false)) {
                    window.alert('This video address is not allowed.');
                    return;
                }

                videoHtml = '<p><video controls preload="metadata" playsinline src="' + escapeHtml(url) + '"></video></p>';
                if (isSourceMode()) {
                    source.value += (source.value ? '\n' : '') + videoHtml;
                    syncSurfaceFromSource();
                    return;
                }

                runCommand('insertHTML', videoHtml);
            }

            function insertHtmlAtSavedRange(html) {
                var selection;
                var wrapperNode;

                if (isSourceMode()) {
                    source.value += (source.value ? '\n' : '') + String(html || '');
                    syncSurfaceFromSource();
                    return;
                }

                surface.focus();
                if (isSavedRangeUsable()) {
                    selection = window.getSelection ? window.getSelection() : null;
                    if (selection) {
                        selection.removeAllRanges();
                        selection.addRange(savedRange);
                    }
                    runCommand('insertHTML', html);
                    saveSelectionRange();
                    return;
                }

                wrapperNode = doc.createElement('div');
                wrapperNode.innerHTML = String(html || '');
                while (wrapperNode.firstChild) {
                    surface.appendChild(wrapperNode.firstChild);
                }
                syncTextareaFromSurface(true);
                saveSelectionRange();
            }

            function buildResponsiveImageHtml(url, altText, widthValue, heightValue) {
                return buildEditorImageHtml(url, altText, widthValue, heightValue, String(widthValue || heightValue || '').trim() ? 'custom' : 'responsive');
            }

            function promptForImageUrl() {
                var url = window.prompt('Paste the direct image URL:', 'https://');
                var widthValue = '';
                var heightValue = '';
                var imageHtml = '';

                if (!url) {
                    return;
                }

                if (!isSafeUrl(url, false)) {
                    window.alert('This image address is not allowed.');
                    return;
                }

                widthValue = window.prompt('Optional width (e.g. 320px, 50%, 100%). Leave empty to keep natural size:', '') || '';
                heightValue = window.prompt('Optional height (e.g. 180px). Leave empty to keep automatic proportion:', '') || '';

                imageHtml = buildResponsiveImageHtml(url, '', widthValue, heightValue);
                if (isSourceMode()) {
                    source.value += (source.value ? '\n' : '') + imageHtml;
                    syncSurfaceFromSource();
                    return;
                }

                runCommand('insertHTML', imageHtml);
            }

            function insertImageFromFile(file) {
                if (!file) {
                    return;
                }

                var reader = new window.FileReader();
                reader.onload = function (event) {
                    var result = event && event.target ? String(event.target.result || '') : '';
                    if (!result) {
                        return;
                    }

                    var imageHtml = buildResponsiveImageHtml(result, file.name || 'image', '', '');
                    insertHtmlAtSavedRange(imageHtml);
                };
                reader.readAsDataURL(file);
            }

            function getSelectedHtml() {
                var selection = window.getSelection ? window.getSelection() : null;
                var range;
                var container;

                if (!selection || selection.rangeCount <= 0 || selection.isCollapsed) {
                    return '';
                }

                range = selection.getRangeAt(0);
                container = doc.createElement('div');
                container.appendChild(range.cloneContents());
                return container.innerHTML;
            }

            function insertRedWarningBox() {
                var selectedHtml = getSelectedHtml();
                var boxHtml = '<div class="border-red red">' + (selectedHtml || 'Important content') + '</div>';

                if (isSourceMode()) {
                    source.value += (source.value ? '\n' : '') + boxHtml;
                    syncSurfaceFromSource();
                    return;
                }

                runCommand('insertHTML', boxHtml);
            }

            surface.innerHTML = sanitizeHtml(textarea.value);
            syncTextareaFromSurface(false);
            updateToggleButton();

            editorApi = {
                saveSelection: saveSelectionRange,
                sync: function () {
                    if (isSourceMode()) {
                        syncSurfaceFromSource();
                    } else {
                        syncTextareaFromSurface(true);
                    }
                },
                insertImage: function (url, altText) {
                    if (!isSafeUrl(url, true)) {
                        window.alert('Ten adres obrazka nie jest dozwolony.');
                        return;
                    }
                    insertHtmlAtSavedRange(buildEditorImageHtml(url, altText || '', '', '', 'responsive'));
                }
            };

            toolbar.addEventListener('click', function (event) {
                var button = closest(event.target, '[data-rich-editor-action]');
                if (!button) {
                    return;
                }

                var action = button.getAttribute('data-rich-editor-action') || '';
                if (!action) {
                    return;
                }

                event.preventDefault();

                if (action === 'paragraph') {
                    runCommand('formatBlock', '<p>');
                    return;
                }
                if (action === 'h2') {
                    runCommand('formatBlock', '<h2>');
                    return;
                }
                if (action === 'h3') {
                    runCommand('formatBlock', '<h3>');
                    return;
                }
                if (action === 'bold') {
                    runCommand('bold');
                    return;
                }
                if (action === 'italic') {
                    runCommand('italic');
                    return;
                }
                if (action === 'underline') {
                    runCommand('underline');
                    return;
                }
                if (action === 'unorderedList') {
                    runCommand('insertUnorderedList');
                    return;
                }
                if (action === 'orderedList') {
                    runCommand('insertOrderedList');
                    return;
                }
                if (action === 'quote') {
                    runCommand('formatBlock', '<blockquote>');
                    return;
                }
                if (action === 'link') {
                    promptForLink();
                    return;
                }
                if (action === 'alignLeft') {
                    runCommand('justifyLeft');
                    return;
                }
                if (action === 'alignCenter') {
                    runCommand('justifyCenter');
                    return;
                }
                if (action === 'alignRight') {
                    runCommand('justifyRight');
                    return;
                }
                if (action === 'clear') {
                    runCommand('removeFormat');
                    return;
                }
                if (action === 'redBox') {
                    insertRedWarningBox();
                    return;
                }
                if (action === 'image') {
                    fileInput.click();
                    return;
                }
                if (action === 'imageUrl') {
                    promptForImageUrl();
                    return;
                }
                if (action === 'videoUrl') {
                    promptForVideoUrl();
                    return;
                }
                if (action === 'source') {
                    toggleSourceMode();
                }
            });

            galleryActions.addEventListener('mousedown', function () {
                saveSelectionRange();
            });

            galleryActions.addEventListener('click', function (event) {
                var button = closest(event.target, '[data-rich-editor-gallery]');
                if (!button) {
                    return;
                }

                event.preventDefault();
                saveSelectionRange();
                openEditorGallery(editorApi);
            });

            colorInput.addEventListener('input', function () {
                if (isSourceMode()) {
                    return;
                }
                runCommand('foreColor', colorInput.value || '#162033');
            });

            weightSelect.addEventListener('change', function () {
                var weight = String(weightSelect.value || '').trim();
                if (!weight) {
                    return;
                }
                applyInlineStyle('font-weight', weight);
                weightSelect.value = '';
            });

            fontSizeSelect.addEventListener('change', function () {
                var fontSize = String(fontSizeSelect.value || '').trim();
                if (!fontSize) {
                    return;
                }
                applyInlineStyle('font-size', fontSize);
                fontSizeSelect.value = '';
            });

            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (file) {
                    insertImageFromFile(file);
                }
                fileInput.value = '';
            });

            surface.addEventListener('input', function () {
                syncTextareaFromSurface(true);
                saveSelectionRange();
            });
            surface.addEventListener('blur', function () {
                syncTextareaFromSurface(true);
                dispatchTextareaEvent('change');
            });
            surface.addEventListener('keyup', saveSelectionRange);
            surface.addEventListener('mouseup', saveSelectionRange);
            surface.addEventListener('focus', saveSelectionRange);
            surface.addEventListener('click', function (event) {
                var imageNode = closest(event.target, 'img');
                if (!imageNode || !surface.contains(imageNode)) {
                    saveSelectionRange();
                    return;
                }

                event.preventDefault();
                openImageSettingsModal(editorApi, imageNode);
            });
            surface.addEventListener('paste', function () {
                window.setTimeout(function () {
                    syncTextareaFromSurface(true);
                    saveSelectionRange();
                }, 0);
            });

            source.addEventListener('input', function () {
                textarea.value = source.value;
                ensureSourceEditorHeight();
                dispatchTextareaEvent('input');
            });

            var form = closest(textarea, 'form');
            if (form) {
                form.addEventListener('submit', function () {
                    if (isSourceMode()) {
                        syncSurfaceFromSource();
                    } else {
                        syncTextareaFromSurface();
                    }
                });
            }
        });
    }

    function initAdminPersonalNotesAutosave() {
        qa('[data-admin-personal-notes-form]').forEach(function (form) {
            var textarea = q('textarea[name="personal_notes_html"]', form);
            var csrfInput = q('input[name="_csrf"]', form);
            var statusNode = q('[data-admin-personal-notes-status]', form);
            var saveButton = q('[data-admin-personal-notes-save]', form);
            var saveUrl = form.getAttribute('data-save-url') || window.location.href;
            var autosaveIntervalMs = parseInt(form.getAttribute('data-autosave-interval') || '45000', 10);
            var lastSavedValue = textarea ? String(textarea.value || '') : '';
            var isSaving = false;
            var heartbeatTimer = 0;
            var rateLimitUntil = 0;

            if (!textarea || !csrfInput || !statusNode) {
                return;
            }

            if (!(autosaveIntervalMs > 0)) {
                autosaveIntervalMs = 45000;
            }

            function setStatus(message, tone) {
                statusNode.textContent = String(message || '');
                statusNode.classList.remove('text-muted', 'text-success', 'text-danger');
                if (tone === 'success') {
                    statusNode.classList.add('text-success');
                } else if (tone === 'error') {
                    statusNode.classList.add('text-danger');
                } else {
                    statusNode.classList.add('text-muted');
                }
            }

            function savedAtLabel(rawValue) {
                var value = String(rawValue || '').trim();
                if (!value) {
                    return form.getAttribute('data-save-success') || 'Saved.';
                }

                return (form.getAttribute('data-save-success') || 'Saved.') + ' ' + value;
            }

            function hasUnsavedChanges() {
                return String(textarea.value || '') !== lastSavedValue;
            }

            function setSaveButtonState(disabled) {
                if (!saveButton) {
                    return;
                }
                saveButton.disabled = !!disabled;
            }

            function flushWithBeacon() {
                var currentValue;
                var payload;

                if (!navigator.sendBeacon || isSaving || !hasUnsavedChanges()) {
                    return;
                }

                currentValue = String(textarea.value || '');
                payload = buildUrlEncodedPayload({
                    admin_save_personal_notes_ajax: '1',
                    _csrf: csrfInput.value,
                    personal_notes_html_b64: encodeBase64Utf8(currentValue)
                });

                try {
                    navigator.sendBeacon(saveUrl, new Blob([payload], { type: 'application/x-www-form-urlencoded; charset=UTF-8' }));
                    lastSavedValue = currentValue;
                } catch (error) {
                    // noop
                }
            }

            function runSave(force) {
                var currentValue = String(textarea.value || '');
                var requestBody;
                var now = Date.now();

                if (isSaving) {
                    return;
                }

                if (rateLimitUntil > now) {
                    setStatus(form.getAttribute('data-save-rate-limit') || 'Too many save attempts. Wait a moment and use Save.', 'error');
                    return;
                }

                if (currentValue === lastSavedValue) {
                    setStatus(form.getAttribute('data-save-idle') || 'Changes save automatically.', 'idle');
                    return;
                }

                isSaving = true;
                setStatus(form.getAttribute('data-save-saving') || 'Saving notes...', 'idle');
                setSaveButtonState(true);

                requestBody = buildUrlEncodedPayload({
                    admin_save_personal_notes_ajax: '1',
                    _csrf: csrfInput.value,
                    personal_notes_html_b64: encodeBase64Utf8(currentValue)
                });

                jsonFetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: requestBody,
                    credentials: 'same-origin'
                }).then(function (payload) {
                    if (!payload || !payload.ok) {
                        if (payload && payload.__status === 429) {
                            rateLimitUntil = Date.now() + 30000;
                            setStatus(form.getAttribute('data-save-rate-limit') || 'Too many save attempts. Wait a moment and use Save.', 'error');
                            return;
                        }

                        setStatus((payload && payload.message) ? payload.message : (form.getAttribute('data-save-error') || 'Unable to save administrator notes.'), 'error');
                        return;
                    }

                    lastSavedValue = currentValue;
                    rateLimitUntil = 0;
                    setStatus(savedAtLabel(payload.updated_at || ''), 'success');
                }).catch(function () {
                    setStatus(form.getAttribute('data-save-error') || 'Unable to save administrator notes.', 'error');
                }).finally(function () {
                    isSaving = false;
                    setSaveButtonState(false);
                    if (!hasUnsavedChanges()) {
                        setStatus(form.getAttribute('data-save-idle') || 'Changes save automatically.', 'idle');
                    }
                });
            }

            textarea.addEventListener('input', function () {
                setStatus(form.getAttribute('data-save-pending') || 'You have unsaved changes...', 'idle');
            });

            textarea.addEventListener('change', function () {
                setStatus(form.getAttribute('data-save-pending') || 'You have unsaved changes...', 'idle');
            });

            if (saveButton) {
                saveButton.addEventListener('click', function () {
                    runSave(true);
                });
            }

            heartbeatTimer = window.setInterval(function () {
                if (!isSaving && hasUnsavedChanges()) {
                    runSave(false);
                }
            }, autosaveIntervalMs);

            window.addEventListener('visibilitychange', function () {
                if (doc.visibilityState === 'hidden') {
                    flushWithBeacon();
                }
            });

            window.addEventListener('pagehide', function () {
                flushWithBeacon();
            });
        });
    }

    function toQuery(params) {
        var search = new URLSearchParams();
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== null && params[key] !== undefined) {
                search.append(key, params[key]);
            }
        });
        return search.toString();
    }

    function unlockSensitiveRoute(routeLabel) {
        var body = doc.body;
        if (!body) {
            return Promise.resolve(false);
        }

        if (body.getAttribute('data-admin-sensitive-unlocked') === '1') {
            return Promise.resolve(true);
        }

        var promptText = body.getAttribute('data-admin-sensitive-prompt') || 'Enter the password to open this section:';
        var errorText = body.getAttribute('data-admin-sensitive-error') || 'Incorrect password.';
        var csrfToken = body.getAttribute('data-admin-sensitive-csrf') || '';
        var password = window.prompt(promptText + (routeLabel ? '\n\n' + routeLabel : ''), '');

        if (password === null) {
            return Promise.resolve(false);
        }

        var formData = new window.FormData();
        formData.append('admin_sensitive_unlock_ajax', '1');
        formData.append('_csrf', csrfToken);
        formData.append('sensitive_password', password);

        return jsonFetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(function (payload) {
            if (!payload || !payload.ok) {
                window.alert((payload && payload.message) ? payload.message : errorText);
                return false;
            }

            body.setAttribute('data-admin-sensitive-unlocked', '1');
            return true;
        }).catch(function () {
            window.alert(errorText);
            return false;
        });
    }

    function initSensitiveRouteProtection() {
        var body = doc.body;
        if (!body) {
            return;
        }

        var gateMode = body.getAttribute('data-admin-sensitive-gate') === '1';
        var cancelText = body.getAttribute('data-admin-sensitive-cancel') || 'Access to this section was cancelled.';

        if (gateMode) {
            unlockSensitiveRoute(body.getAttribute('data-admin-sensitive-label') || '').then(function (ok) {
                if (ok) {
                    window.location.reload();
                    return;
                }

                window.alert(cancelText);
                window.location.href = '/admin/';
            });
            return;
        }

        qa('[data-admin-sensitive-link]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (body.getAttribute('data-admin-sensitive-unlocked') === '1') {
                    return;
                }

                event.preventDefault();
                unlockSensitiveRoute(link.getAttribute('data-admin-sensitive-label') || '').then(function (ok) {
                    if (ok) {
                        window.location.href = link.href;
                    }
                });
            });
        });
    }

    function renderPresenceDot(presence) {
        var className = presence && presence.class_name ? presence.class_name : 'admin-chat-presence admin-chat-presence--offline';
        var label = presence && presence.label ? presence.label : 'Offline';
        return '<span class="' + escapeHtml(className) + '" title="' + escapeHtml(label) + '" aria-label="' + escapeHtml(label) + '"></span>';
    }

    function initSidebarAndDropdowns() {
        doc.addEventListener('click', function (event) {
            var sidebarToggle = closest(event.target, '[data-admin-sidebar-toggle], [data-admin-menu-toggle]');
            if (sidebarToggle) {
                event.preventDefault();
                var sidebarTarget = sidebarToggle.getAttribute('data-admin-sidebar-toggle')
                    || sidebarToggle.getAttribute('data-admin-menu-toggle')
                    || '#adminSidebar';
                var sidebarNode = q(sidebarTarget);
                if (sidebarNode) {
                    sidebarNode.classList.toggle('is-open');
                }
                return;
            }

            var dropdownToggle = closest(event.target, '[data-admin-dropdown]');
            if (dropdownToggle) {
                event.preventDefault();
                event.stopPropagation();
                var dropdownTarget = dropdownToggle.getAttribute('data-admin-dropdown');
                if (dropdownTarget) {
                    var dropdownNode = q(dropdownTarget);
                    if (dropdownNode) {
                        dropdownNode.classList.toggle('show');
                    }
                }
                return;
            }

            if (!closest(event.target, '[data-admin-dropdown]')) {
                qa('[data-admin-dropdown]').forEach(function (toggle) {
                    var selector = toggle.getAttribute('data-admin-dropdown');
                    var node = selector ? q(selector) : null;
                    if (node) {
                        node.classList.remove('show');
                    }
                });
            }

            var confirmTrigger = closest(event.target, '[data-admin-confirm]');
            if (confirmTrigger) {
                var message = confirmTrigger.getAttribute('data-admin-confirm') || '';
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }
        });
    }

    function initDangerForms() {
        qa('[data-admin-danger-form]').forEach(function (form) {
            var input = q('[data-admin-danger-input]', form);
            var submit = q('[data-admin-danger-submit]', form);
            if (!input || !submit) {
                return;
            }

            var sync = function () {
                submit.disabled = input.value.trim() !== (input.getAttribute('data-confirm-text') || '').trim();
            };

            input.addEventListener('input', sync);
            sync();

            var modal = closest(form, '.modal');
            if (modal) {
                modal.addEventListener('hidden.bs.modal', function () {
                    input.value = '';
                    sync();
                });
            }
        });
    }

    function initSettingsBackupGenerator() {
        var form = q('[data-admin-database-backup-form]');
        var progressWrap = form ? q('[data-admin-database-backup-progress]', form) : null;
        var progressBar = form ? q('[data-admin-database-backup-progress-bar]', form) : null;
        var statusNode = form ? q('[data-admin-database-backup-status]', form) : null;
        var submitButton = form ? q('[data-admin-database-backup-submit]', form) : null;
        var latestBody = q('[data-admin-database-backup-latest-body]');
        var running = false;
        var progressTimer = 0;
        var progressValue = 0;
        var originalButtonText = submitButton ? submitButton.textContent : '';
        var runningLabel = String(form ? (form.getAttribute('data-running-label') || '') : '').trim() || 'Creating SQL backup...';
        var successLabel = String(form ? (form.getAttribute('data-success-label') || '') : '').trim() || 'SQL backup created successfully.';
        var errorLabel = String(form ? (form.getAttribute('data-error-label') || '') : '').trim() || 'Unable to create the SQL backup.';

        if (!form || !progressWrap || !progressBar || !statusNode || !submitButton || !latestBody) {
            return;
        }

        function setProgressState(percent, message, tone) {
            var safePercent = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
            progressBar.style.width = safePercent + '%';
            progressBar.textContent = safePercent + '%';
            progressBar.setAttribute('aria-valuenow', String(safePercent));
            progressBar.classList.remove('bg-danger', 'bg-success');
            progressBar.classList.add(tone === 'success' ? 'bg-success' : 'bg-danger');
            if (typeof message === 'string' && message.trim()) {
                statusNode.textContent = message.trim();
            }
        }

        function renderLatestBackupRow(backup) {
            var downloadLabel = String(latestBody.getAttribute('data-download-label') || 'Download');
            var emptyLabel = String(latestBody.getAttribute('data-empty-label') || 'No SQL backup has been created yet.');

            if (!backup || !backup.download_url) {
                latestBody.innerHTML = '<tr><td colspan="4" class="text-muted">' + escapeHtml(emptyLabel) + '</td></tr>';
                return;
            }

            latestBody.innerHTML = ''
                + '<tr>'
                + '<td>' + escapeHtml(backup.name || 'latest-database-backup.sql') + '</td>'
                + '<td>' + escapeHtml(backup.generated_at_label || '') + '</td>'
                + '<td>' + escapeHtml(backup.size_label || '0 B') + '</td>'
                + '<td class="text-end"><a class="btn btn-success btn-sm" href="' + escapeHtml(backup.download_url || '#') + '">' + escapeHtml(downloadLabel) + '</a></td>'
                + '</tr>';
        }

        form.addEventListener('submit', function (event) {
            var formData;

            event.preventDefault();
            if (running) {
                return;
            }

            running = true;
            progressValue = 4;
            submitButton.disabled = true;
            submitButton.textContent = originalButtonText || submitButton.textContent;
            setHidden(progressWrap, false);
            setProgressState(progressValue, runningLabel, 'danger');

            window.clearInterval(progressTimer);
            progressTimer = window.setInterval(function () {
                if (progressValue >= 92) {
                    return;
                }

                if (progressValue < 38) {
                    progressValue += 7;
                } else if (progressValue < 74) {
                    progressValue += 4;
                } else {
                    progressValue += 2;
                }

                if (progressValue > 92) {
                    progressValue = 92;
                }

                setProgressState(progressValue, runningLabel, 'danger');
            }, 220);

            formData = new FormData(form);
            formData.append('admin_download_database_backup', '1');

            fetch(form.getAttribute('action') || window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { ok: false, message: 'Invalid JSON response.' };
                    });
                })
                .then(function (payload) {
                    window.clearInterval(progressTimer);
                    running = false;
                    submitButton.disabled = false;

                    if (payload && payload.ok) {
                        setProgressState(100, String(payload.message || successLabel), 'success');
                        renderLatestBackupRow(payload.backup || null);
                        return;
                    }

                    setProgressState(Math.max(progressValue, 12), String((payload && payload.message) || errorLabel), 'danger');
                })
                .catch(function () {
                    window.clearInterval(progressTimer);
                    running = false;
                    submitButton.disabled = false;
                    setProgressState(Math.max(progressValue, 12), errorLabel, 'danger');
                });
        });
    }

    function initAdminPanelSubscriptionReminderModal() {
        var modalNode = q('[data-admin-panel-subscription-modal]');
        var storageKey;
        var throttleMinutes;
        var now;
        var lastShown;
        var shouldShow = true;
        var modal;

        if (!modalNode || !hasBootstrap || !window.bootstrap.Modal) {
            return;
        }

        storageKey = modalNode.getAttribute('data-storage-key') || 'admin-panel-subscription-reminder';
        throttleMinutes = parseInt(modalNode.getAttribute('data-throttle-minutes') || '60', 10) || 60;
        now = Date.now();

        try {
            lastShown = parseInt(window.localStorage ? (window.localStorage.getItem(storageKey) || '0') : '0', 10) || 0;
            shouldShow = !lastShown || (now - lastShown) >= (throttleMinutes * 60 * 1000);
        } catch (error) {
            shouldShow = true;
        }

        if (!shouldShow) {
            return;
        }

        try {
            if (window.localStorage) {
                window.localStorage.setItem(storageKey, String(now));
            }
        } catch (error) {
            // localStorage may be unavailable in privacy-restricted contexts.
        }

        modal = window.bootstrap.Modal.getOrCreateInstance(modalNode);
        window.setTimeout(function () {
            modal.show();
        }, 350);
    }

    function initProviderUrlReplacement() {
        qa('[data-provider-url-replacement-scope]').forEach(function (scope) {
            var toggle = q('[data-provider-url-replacement-toggle]', scope);
            var section = q('[data-provider-url-replacement-section]', scope);
            var deliveryLinksSelect = q('[data-provider-delivery-links-select]', scope);
            if (!toggle || !section) {
                return;
            }

            var sync = function () {
                var deliveryLinksEnabled = !deliveryLinksSelect || String(deliveryLinksSelect.value || '1') === '1';
                toggle.disabled = !deliveryLinksEnabled;
                if (!deliveryLinksEnabled) {
                    toggle.checked = false;
                }

                var visible = deliveryLinksEnabled && !!toggle.checked;
                setHidden(section, !visible);
                qa('input, textarea, select', section).forEach(function (field) {
                    field.disabled = !visible;
                });
            };

            toggle.addEventListener('change', sync);
            if (deliveryLinksSelect) {
                deliveryLinksSelect.addEventListener('change', sync);
            }
            sync();
        });
    }

    function initProviderLogoManagers() {
        function normalizeLogoUrl(value) {
            var url = String(value || '').trim();
            if (!url) {
                return '';
            }

            if (/^https?:\/\//i.test(url) || url.charAt(0) === '/') {
                return url;
            }

            return '/' + url.replace(/^\/+/, '');
        }

        qa('[data-product-provider-logo-manager]').forEach(function (manager) {
            var form = closest(manager, 'form');
            var csrfInput = form ? q('input[name="_csrf"]', form) : null;
            var providerIdInput = form ? q('input[name="provider_id"]', form) : null;
            var fileInput = q('[data-product-provider-logo-file]', manager);
            var uploadTrigger = q('[data-product-provider-logo-upload-trigger]', manager);
            var removeTrigger = q('[data-product-provider-logo-remove]', manager);
            var dropzone = q('[data-product-provider-logo-dropzone]', manager);
            var urlInput = q('[data-product-provider-logo-url]', manager);
            var statusNode = q('[data-product-provider-logo-status]', manager);
            var preview = form ? q('[data-product-provider-logo-preview]', form) : null;
            var previewImage = preview ? q('[data-product-provider-logo-preview-image]', preview) : null;
            var previewEmpty = preview ? q('[data-product-provider-logo-preview-empty]', preview) : null;
            var busy = false;
            var defaultStatus = statusNode ? String(statusNode.textContent || '') : '';

            if (!form || !csrfInput || !fileInput || !uploadTrigger || !removeTrigger || !dropzone || !urlInput || !statusNode || !preview || !previewImage || !previewEmpty) {
                return;
            }

            function currentUrl() {
                return String(urlInput.value || '').trim();
            }

            function currentProviderId() {
                if (!providerIdInput) {
                    return 0;
                }
                return parseInt(String(providerIdInput.value || '0'), 10) || 0;
            }

            function setBusy(nextBusy) {
                busy = !!nextBusy;
                manager.classList.toggle('is-busy', busy);
                uploadTrigger.disabled = busy;
                removeTrigger.disabled = busy || currentUrl() === '';
                urlInput.disabled = busy;
                dropzone.disabled = busy;
            }

            function setStatus(message, tone) {
                statusNode.textContent = String(message || '');
                statusNode.classList.toggle('is-error', tone === 'error');
                statusNode.classList.toggle('is-success', tone === 'success');
            }

            function renderPreview(rawUrl) {
                var normalizedUrl = normalizeLogoUrl(rawUrl);
                var emptyText = preview.getAttribute('data-empty-text') || 'Provider logo is not set yet.';
                var errorText = preview.getAttribute('data-error-text') || 'Preview could not be loaded from this address.';

                if (!normalizedUrl) {
                    previewImage.setAttribute('hidden', 'hidden');
                    previewImage.removeAttribute('src');
                    previewEmpty.textContent = emptyText;
                    previewEmpty.removeAttribute('hidden');
                    removeTrigger.disabled = busy;
                    return;
                }

                previewImage.onload = function () {
                    previewImage.removeAttribute('hidden');
                    previewEmpty.setAttribute('hidden', 'hidden');
                };
                previewImage.onerror = function () {
                    previewImage.setAttribute('hidden', 'hidden');
                    previewEmpty.textContent = errorText;
                    previewEmpty.removeAttribute('hidden');
                };
                previewImage.src = normalizedUrl;
                previewImage.removeAttribute('hidden');
                previewEmpty.setAttribute('hidden', 'hidden');
                removeTrigger.disabled = busy ? true : false;
            }

            function uploadFile(file) {
                var formData;

                if (!file || busy) {
                    return;
                }

                formData = new window.FormData();
                formData.append('admin_product_provider_logo_ajax', '1');
                formData.append('logo_action', 'upload');
                formData.append('current_logo_url', currentUrl());
                formData.append('provider_id', String(currentProviderId()));
                formData.append('_csrf', String(csrfInput.value || ''));
                formData.append('provider_logo_file', file);

                setBusy(true);
                setStatus(manager.getAttribute('data-uploading-label') || 'Uploading provider logo...', '');

                jsonFetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (payload) {
                    if (!payload || !payload.ok) {
                        setStatus((payload && payload.message) ? payload.message : (manager.getAttribute('data-upload-error') || 'Unable to upload the provider logo right now.'), 'error');
                        return;
                    }

                    urlInput.value = String(payload.url || '');
                    renderPreview(urlInput.value);
                    setStatus(payload.message || manager.getAttribute('data-upload-success') || 'Provider logo uploaded successfully.', 'success');
                }).catch(function () {
                    setStatus(manager.getAttribute('data-upload-error') || 'Unable to upload the provider logo right now.', 'error');
                }).finally(function () {
                    setBusy(false);
                    fileInput.value = '';
                });
            }

            function clearLogo() {
                var existingUrl = currentUrl();
                var formData;

                if (busy || existingUrl === '') {
                    return;
                }

                if (existingUrl.indexOf('/uploads/providers/') !== 0) {
                    urlInput.value = '';
                    renderPreview('');
                    setStatus(manager.getAttribute('data-remove-success') || 'Provider logo removed.', 'success');
                    return;
                }

                formData = new window.FormData();
                formData.append('admin_product_provider_logo_ajax', '1');
                formData.append('logo_action', 'remove');
                formData.append('current_logo_url', existingUrl);
                formData.append('provider_id', String(currentProviderId()));
                formData.append('_csrf', String(csrfInput.value || ''));

                setBusy(true);
                setStatus(manager.getAttribute('data-removing-label') || 'Removing provider logo...', '');

                jsonFetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (payload) {
                    if (!payload || !payload.ok) {
                        setStatus((payload && payload.message) ? payload.message : (manager.getAttribute('data-remove-error') || 'Unable to remove the provider logo right now.'), 'error');
                        return;
                    }

                    urlInput.value = '';
                    renderPreview('');
                    setStatus(payload.message || manager.getAttribute('data-remove-success') || 'Provider logo removed.', 'success');
                }).catch(function () {
                    setStatus(manager.getAttribute('data-remove-error') || 'Unable to remove the provider logo right now.', 'error');
                }).finally(function () {
                    setBusy(false);
                });
            }

            uploadTrigger.addEventListener('click', function () {
                if (!busy) {
                    fileInput.click();
                }
            });

            dropzone.addEventListener('click', function () {
                if (!busy) {
                    fileInput.click();
                }
            });

            dropzone.addEventListener('dragover', function (event) {
                event.preventDefault();
                if (!busy) {
                    dropzone.classList.add('is-dragover');
                }
            });

            dropzone.addEventListener('dragleave', function () {
                dropzone.classList.remove('is-dragover');
            });

            dropzone.addEventListener('drop', function (event) {
                var file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
                uploadFile(file);
            });

            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                uploadFile(file);
            });

            removeTrigger.addEventListener('click', function () {
                clearLogo();
            });

            urlInput.addEventListener('input', function () {
                renderPreview(urlInput.value);
                setStatus(defaultStatus, '');
            });

            renderPreview(currentUrl());
            setStatus(defaultStatus, '');
            setBusy(false);
        });
    }

    function initProductTypeForms() {
        qa('[data-product-form-scope]').forEach(function (scope) {
            var typeField = q('[data-product-type-select]', scope);
            var durationField = q('[data-product-duration-select]', scope);
            var trialField = q('[data-product-trial-toggle]', scope);
            if (!typeField) {
                return;
            }

            function normalizeProductType(value) {
                value = String(value || 'subscription').toLowerCase();
                if (value === 'credits' || value === 'product') {
                    return value;
                }
                return 'subscription';
            }

            var syncTrialConstraint = function () {
                var productType = normalizeProductType(typeField.value || 'subscription');
                var durationHours = parseInt(durationField && durationField.value ? durationField.value : '0', 10) || 0;
                var trialRequired = productType === 'subscription' && durationHours > 0 && durationHours <= 24;

                if (!trialField) {
                    return;
                }

                if (trialRequired) {
                    trialField.checked = true;
                    trialField.disabled = true;
                } else {
                    trialField.disabled = false;
                }
            };

            var sync = function () {
                var productType = normalizeProductType(typeField.value || 'subscription');

                qa('[data-product-type-section]', scope).forEach(function (section) {
                    var sectionTypes = String(section.getAttribute('data-product-type-section') || '')
                        .toLowerCase()
                        .split(',')
                        .map(function (item) { return item.trim(); })
                        .filter(Boolean);
                    var visible = sectionTypes.indexOf(productType) !== -1;

                    setHidden(section, !visible);

                    qa('input, textarea, select', section).forEach(function (field) {
                        field.disabled = !visible;

                        if (field.hasAttribute('data-required-when-visible')) {
                            field.required = visible;
                        }
                    });
                });

                syncTrialConstraint();
            };

            typeField.addEventListener('change', sync);
            if (durationField) {
                durationField.addEventListener('change', syncTrialConstraint);
            }
            sync();
        });
    }

    function initOrderStatusForms() {
        function applyPill(node, statusValue, statusType, labels) {
            if (!node) {
                return;
            }

            var normalized = String(statusValue || '').toLowerCase();
            var icon = 'bi bi-dot';
            var tone = 'is-neutral';
            var label = normalized;

            if (statusType === 'payment') {
                if (normalized === 'paid') {
                    icon = 'bi bi-check-lg';
                    tone = 'is-success';
                    label = labels.paid;
                } else if (normalized === 'unpaid') {
                    icon = 'bi bi-x-lg';
                    tone = 'is-danger';
                    label = labels.unpaid;
                } else if (normalized === 'pending' || normalized === 'pending_payment' || normalized === 'awaiting_review') {
                    icon = 'bi bi-arrow-repeat';
                    tone = 'is-pending';
                    label = labels.pending;
                } else {
                    icon = 'bi bi-x-lg';
                    tone = 'is-danger';
                    label = labels.other;
                }
            } else {
                if (normalized === 'delivered' || normalized === 'fulfilled' || normalized === 'completed' || normalized === 'shipped' || normalized === 'sent') {
                    icon = 'bi bi-check-lg';
                    tone = 'is-success';
                    label = labels.sent;
                } else if (normalized === 'pending' || normalized === 'processing' || normalized === 'queued' || normalized === 'in_progress') {
                    icon = 'bi bi-arrow-repeat';
                    tone = 'is-pending';
                    label = labels.pending;
                } else {
                    icon = 'bi bi-x-lg';
                    tone = 'is-danger';
                    label = labels.other;
                }
            }

            node.classList.remove('is-neutral', 'is-success', 'is-danger', 'is-pending');
            node.classList.add(tone);

            var iconNode = q('i', node);
            if (iconNode) {
                iconNode.className = icon + (tone === 'is-pending' ? ' admin-order-modal__status-icon--spin' : '');
            }

            var labelNode = q('span[data-admin-order-' + statusType + '-label]', node) || q('span:last-child', node);
            if (labelNode) {
                labelNode.textContent = label;
            }
        }

        qa('[data-admin-order-status-form]').forEach(function (form) {
            var statusField = q('[data-admin-order-main-status]', form);
            var paymentField = q('[data-admin-order-payment-status]', form);
            var originalPaymentField = q('[data-admin-order-payment-status-original]', form);
            var fulfillmentField = q('[data-admin-order-fulfillment-status]', form);
            var paymentPill = q('[data-admin-order-payment-pill]', form);
            var fulfillmentPill = q('[data-admin-order-fulfillment-pill]', form);
            var activationAlert = q('[data-admin-order-activation-alert]', form);
            var statusDirectionHint = q('[data-admin-order-status-hint]', form);
            var expiredAlert = q('[data-admin-order-expired-alert]', form);
            var activeAlert = q('[data-admin-order-active-alert]', form);

            if (!statusField || !paymentField || !fulfillmentField) {
                return;
            }

            var originalPayment = String((originalPaymentField ? originalPaymentField.value : paymentField.value) || '').toLowerCase();
            var originalFulfillment = String(fulfillmentField.value || '').toLowerCase();

            var paymentLabels = {
                paid: form.getAttribute('data-label-payment-paid') || 'Paid',
                unpaid: form.getAttribute('data-label-payment-unpaid') || 'Unpaid',
                pending: form.getAttribute('data-label-payment-pending') || 'Pending',
                other: form.getAttribute('data-label-payment-other') || 'Unpaid'
            };
            var fulfillmentLabels = {
                sent: form.getAttribute('data-label-shipping-sent') || 'Sent',
                pending: form.getAttribute('data-label-shipping-pending') || 'Pending',
                other: form.getAttribute('data-label-shipping-other') || 'Cancelled'
            };

            var applyStatusFieldTone = function (statusValue) {
                var tone = 'is-neutral';
                if (statusValue === 'active') {
                    tone = 'is-success';
                } else if (['expired', 'cancelled', 'failed', 'inactive'].indexOf(statusValue) !== -1) {
                    tone = 'is-danger';
                } else if (['pending_payment', 'awaiting_review', 'pending', 'unpaid', 'pending_activation'].indexOf(statusValue) !== -1) {
                    tone = 'is-pending';
                }

                statusField.classList.remove('is-neutral', 'is-success', 'is-danger', 'is-pending', 'is-action-required');
                statusField.classList.add(tone);
                if (tone === 'is-pending' && String(paymentField.value || '').toLowerCase() === 'paid') {
                    statusField.classList.add('is-action-required');
                }
                if (statusDirectionHint) {
                    setHidden(statusDirectionHint, String(paymentField.value || '').toLowerCase() !== 'paid'
                        || ['pending_payment', 'awaiting_review', 'pending', 'pending_activation'].indexOf(statusValue) === -1);
                }
            };

            var sync = function () {
                var statusValue = String(statusField.value || '').toLowerCase();

                if (statusValue === 'active') {
                    paymentField.value = 'paid';
                    fulfillmentField.value = 'delivered';
                } else if (statusValue === 'pending_payment') {
                    if (String(paymentField.value || '').toLowerCase() === 'paid' || originalPayment === 'paid') {
                        paymentField.value = 'paid';
                    } else {
                        paymentField.value = 'unpaid';
                    }
                    fulfillmentField.value = 'pending';
                } else {
                    paymentField.value = originalPayment;
                    fulfillmentField.value = originalFulfillment;
                }

                applyStatusFieldTone(statusValue);
                applyPill(paymentPill, paymentField.value, 'payment', paymentLabels);
                applyPill(fulfillmentPill, fulfillmentField.value, 'fulfillment', fulfillmentLabels);
                if (activationAlert) {
                    setHidden(activationAlert, statusValue === 'active' || statusValue === 'expired');
                }
                if (expiredAlert) {
                    setHidden(expiredAlert, statusValue !== 'expired');
                }
                if (activeAlert) {
                    setHidden(activeAlert, statusValue !== 'active');
                }
            };

            statusField.addEventListener('change', sync);
            paymentField.addEventListener('change', function () {
                applyPill(paymentPill, paymentField.value, 'payment', paymentLabels);
                applyStatusFieldTone(String(statusField.value || '').toLowerCase());
            });
            sync();
        });
    }

    function initOrderExtendPreviews() {
        function pad(value) {
            return value < 10 ? '0' + value : String(value);
        }

        function parseSqlDateTime(value) {
            var match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
            if (!match) {
                return null;
            }

            var date = new Date(
                parseInt(match[1], 10),
                parseInt(match[2], 10) - 1,
                parseInt(match[3], 10),
                parseInt(match[4] || '0', 10),
                parseInt(match[5] || '0', 10),
                parseInt(match[6] || '0', 10)
            );

            return Number.isNaN(date.getTime()) ? null : date;
        }

        function formatDateTime(date) {
            if (!date || Number.isNaN(date.getTime())) {
                return '';
            }

            return pad(date.getDate()) + '.' + pad(date.getMonth() + 1) + '.' + date.getFullYear() + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        function formatDateTimeInput(date) {
            if (!date || Number.isNaN(date.getTime())) {
                return '';
            }

            return date.getFullYear()
                + '-'
                + pad(date.getMonth() + 1)
                + '-'
                + pad(date.getDate())
                + 'T'
                + pad(date.getHours())
                + ':'
                + pad(date.getMinutes());
        }

        function calendarMonthsFromDuration(durationHours) {
            durationHours = Math.max(0, parseInt(durationHours || '0', 10) || 0);
            if (durationHours <= 24) {
                return 0;
            }
            if (durationHours % 8760 === 0) {
                return (durationHours / 8760) * 12;
            }
            if (durationHours % 720 === 0) {
                return durationHours / 720;
            }

            return 0;
        }

        function addCalendarMonths(baseDate, months) {
            var baseDay = baseDate.getDate();
            var targetMonth = new Date(
                baseDate.getFullYear(),
                baseDate.getMonth() + months,
                1,
                baseDate.getHours(),
                baseDate.getMinutes(),
                baseDate.getSeconds()
            );
            var lastDay = new Date(targetMonth.getFullYear(), targetMonth.getMonth() + 1, 0).getDate();
            targetMonth.setDate(Math.min(baseDay, lastDay));

            return targetMonth;
        }

        function addDuration(baseDate, durationHours) {
            var months = calendarMonthsFromDuration(durationHours);
            if (months > 0) {
                return addCalendarMonths(baseDate, months);
            }

            return new Date(baseDate.getTime() + ((parseInt(durationHours || '0', 10) || 0) * 3600000));
        }

        qa('[data-admin-order-extend-form]').forEach(function (form) {
            var productField = q('[data-admin-order-extend-product]', form);
            var previewField = q('[data-admin-order-extend-preview]', form);
            var selectionSummary = q('[data-admin-order-extend-selection]', form);

            if (!productField || !previewField) {
                return;
            }

            var sync = function () {
                var selected = productField.options[productField.selectedIndex];
                if (selectionSummary) selectionSummary.textContent = selected ? selected.textContent.trim() : '';
                var durationHours = selected ? parseInt(selected.getAttribute('data-duration-hours') || '0', 10) || 0 : 0;
                var currentExpiry = parseSqlDateTime(form.getAttribute('data-current-expires-at') || '');
                var now = new Date();
                var baseDate = currentExpiry && currentExpiry.getTime() > now.getTime() ? currentExpiry : now;

                if (durationHours <= 0) {
                    previewField.value = '';
                    return;
                }

                previewField.value = formatDateTimeInput(addDuration(baseDate, durationHours));
            };

            productField.addEventListener('change', sync);
            sync();
        });
    }

    function initSearch() {
        var input = q('[data-admin-search-input]');
        var reset = q('[data-admin-search-reset]');
        var results = q('[data-admin-search-results]');
        var defaultContent = q('[data-admin-default-content]');

        if (!input || !results || !defaultContent) {
            return;
        }

        var searchUrl = input.getAttribute('data-search-url') || '/admin/search.php';
        var loadingText = input.getAttribute('data-loading-text') || 'Loading results...';
        var errorTitle = input.getAttribute('data-error-title') || 'Search error';
        var errorText = input.getAttribute('data-error-text') || 'Unable to load search results right now.';
        var csrfToken = input.getAttribute('data-csrf-token') || '';
        var requestIndex = 0;
        var controller = null;
        var timeout = null;
        var searchTimer = null;
        var minimumHint = input.getAttribute('data-minimum-hint') || 'Enter at least 2 characters or a complete ID.';

        function cancelRequest() {
            if (controller) { controller.abort(); controller = null; }
            if (timeout) { window.clearTimeout(timeout); timeout = null; }
            results.removeAttribute('aria-busy');
        }

        function resetSearch() {
            if (searchTimer) window.clearTimeout(searchTimer);
            searchTimer = null;
            requestIndex += 1;
            cancelRequest();
            input.value = '';
            setHidden(results, true);
            results.innerHTML = '';
            defaultContent.removeAttribute('hidden');
            if (reset) {
                setHidden(reset, true);
            }
        }

        function runSearch() {
            if (searchTimer) window.clearTimeout(searchTimer);
            searchTimer = null;
            var query = input.value.trim();
            if (reset) {
                setHidden(reset, query === '');
            }

            if (query === '') {
                resetSearch();
                return;
            }

            requestIndex += 1;
            cancelRequest();
            var activeRequest = requestIndex;
            defaultContent.setAttribute('hidden', 'hidden');
            setHidden(results, false);
            if (!/^\d+$/.test(query) && Array.from(query.replace(/^@/, '')).length < 2) {
                results.innerHTML = '<div class="admin-panel-card admin-search-card"><div class="admin-search-feedback" role="status">' + escapeHtml(minimumHint) + '</div></div>';
                return;
            }
            results.setAttribute('aria-busy', 'true');
            results.innerHTML = '<div class="admin-panel-card admin-search-card"><div class="admin-search-feedback" role="status"><span class="spinner-border" aria-hidden="true"></span>' + escapeHtml(loadingText) + '</div></div>';
            controller = window.AbortController ? new window.AbortController() : null;
            var activeController = controller;
            timeout = controller ? window.setTimeout(function () { activeController.abort(); }, 10000) : null;

            jsonFetch(searchUrl + '?' + toQuery({ q: query }), { signal: controller ? controller.signal : undefined }).then(function (payload) {
                if (activeRequest !== requestIndex || input.value.trim() !== query) {
                    return;
                }

                if (!payload || !payload.ok || payload.__http_ok === false) {
                    results.innerHTML = '<div class="admin-panel-card admin-search-card"><div class="admin-search-feedback" role="status"><strong>' + escapeHtml(errorTitle) + '</strong><span>' + escapeHtml(errorText) + '</span></div></div>';
                    return;
                }

                results.innerHTML = payload.html || '';
                setHidden(results, !payload.html);
                if (!payload.html) {
                    defaultContent.removeAttribute('hidden');
                }
            }).catch(function () {
                if (activeRequest !== requestIndex || input.value.trim() !== query) {
                    return;
                }
                results.innerHTML = '<div class="admin-panel-card admin-search-card"><div class="admin-search-feedback" role="status"><strong>' + escapeHtml(errorTitle) + '</strong><span>' + escapeHtml(errorText) + '</span></div></div>';
            }).then(function () {
                if (activeRequest === requestIndex) {
                    if (timeout) window.clearTimeout(timeout);
                    timeout = null; controller = null;
                    results.removeAttribute('aria-busy');
                }
            });
        }

        function debouncedSearch() {
            if (searchTimer) window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(runSearch, 250);
        }
        input.addEventListener('input', function () {
            requestIndex += 1; cancelRequest();
            if (input.value.trim() === '') resetSearch();
            else debouncedSearch();
        });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { event.preventDefault(); resetSearch(); }
            if (event.key === 'Enter') { event.preventDefault(); runSearch(); }
        });
        if (reset) {
            reset.addEventListener('click', function () {
                resetSearch();
                input.focus();
            });
        }

        results.addEventListener('click', function (event) {
            var createButton = closest(event.target, '[data-admin-search-create-user]');
            if (!createButton) {
                return;
            }

            var email = String(createButton.getAttribute('data-email') || '').trim();
            if (!email) {
                return;
            }

            createButton.disabled = true;

            jsonFetch(searchUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: toQuery({
                    action: 'create_customer',
                    email: email,
                    _csrf: csrfToken
                })
            }).then(function (payload) {
                if (!payload || !payload.ok) {
                    createButton.disabled = false;
                    return;
                }

                if (payload.customer_id) {
                    window.location.href = '/admin/?page=users&customer_id=' + encodeURIComponent(String(payload.customer_id));
                    return;
                }

                createButton.disabled = false;
                runSearch();
            }).catch(function () {
                createButton.disabled = false;
            });
        });
    }

    function initPaymentsCustomerSearch() {
        var toggle = q('[data-admin-payment-customer-toggle]');
        var wrap = q('[data-admin-payment-customer-search]');
        var input = q('[data-admin-payment-customer-input]');
        var results = q('[data-admin-payment-customer-results]');
        var quickCreateWrap = q('[data-admin-payment-quick-create]');
        var selectedUser = q('[data-admin-payment-selected-user]');
        var quickAlert = q('[data-admin-payment-quick-alert]');
        var assetSelect = q('[data-admin-payment-quick-asset]');
        var productSelect = q('[data-admin-payment-quick-product]');
        var submitButton = q('[data-admin-payment-quick-submit]');
        var requestIndex = 0;
        var selectedCustomerId = 0;
        var cooldownTimer = 0;
        var cooldownRemaining = 0;

        if (!toggle || !wrap || !input || !results || !quickCreateWrap || !selectedUser || !quickAlert || !assetSelect || !productSelect || !submitButton) {
            return;
        }

        var searchUrl = input.getAttribute('data-search-url') || '/admin/search.php';
        var loadingText = input.getAttribute('data-loading-text') || 'Loading results...';
        var csrfToken = input.getAttribute('data-csrf-token') || '';
        var createSuccessText = quickCreateWrap.getAttribute('data-create-success') || 'Payment request created successfully.';
        var createErrorText = quickCreateWrap.getAttribute('data-create-error') || 'Unable to create payment request.';
        var createPendingErrorText = quickCreateWrap.getAttribute('data-create-pending-error') || 'User already has a pending crypto payment request.';
        var noAssetsText = quickCreateWrap.getAttribute('data-create-no-assets') || 'No active cryptocurrencies are available for this user.';
        var openDetailsText = quickCreateWrap.getAttribute('data-open-details-text') || 'Open payment details';
        var openPaymentsText = quickCreateWrap.getAttribute('data-open-payments-text') || 'Open payments';

        function showQuickAlert(message, isError) {
            quickAlert.textContent = message || '';
            quickAlert.classList.toggle('alert-danger', !!isError);
            quickAlert.classList.toggle('alert-success', !isError && !!message);
            setHidden(quickAlert, !message);
        }

        function resetResults() {
            results.innerHTML = '';
            setHidden(results, true);
        }

        function resetQuickCreate() {
            selectedCustomerId = 0;
            selectedUser.innerHTML = '';
            assetSelect.innerHTML = '';
            productSelect.innerHTML = '';
            submitButton.disabled = true;
            setHidden(quickCreateWrap, true);
            showQuickAlert('', false);
            if (cooldownTimer) {
                window.clearInterval(cooldownTimer);
                cooldownTimer = 0;
            }
            cooldownRemaining = 0;
            submitButton.removeAttribute('data-default-label');
        }

        function setSubmitCooldown(seconds) {
            if (cooldownTimer) {
                window.clearInterval(cooldownTimer);
            }
            cooldownRemaining = Math.max(0, parseInt(seconds || 0, 10) || 0);
            if (!submitButton.getAttribute('data-default-label')) {
                submitButton.setAttribute('data-default-label', submitButton.textContent.trim());
            }
            submitButton.disabled = true;
            submitButton.querySelector('span').textContent = 'Cooldown ' + cooldownRemaining + 's';
            cooldownTimer = window.setInterval(function () {
                cooldownRemaining -= 1;
                if (cooldownRemaining <= 0) {
                    window.clearInterval(cooldownTimer);
                    cooldownTimer = 0;
                    submitButton.querySelector('span').textContent = submitButton.getAttribute('data-default-label') || 'Create crypto payment request';
                    submitButton.disabled = !selectedCustomerId || !assetSelect.value || !productSelect.value;
                    return;
                }
                submitButton.querySelector('span').textContent = 'Cooldown ' + cooldownRemaining + 's';
            }, 1000);
        }

        function renderSelectedUser(customer) {
            var handle = String(customer.public_handle || '').trim();
            var email = String(customer.email || '').trim();
            var userUrl = '/admin/?page=payments&customer_id=' + encodeURIComponent(String(customer.id || '0'));
            selectedUser.innerHTML = '' +
                '<div class="admin-payments-note__selected-user-card">' +
                    '<div class="admin-payments-note__selected-user-text">' +
                        (handle ? '<strong>@' + escapeHtml(handle) + '</strong>' : '') +
                        '<span>' + escapeHtml(email || '—') + '</span>' +
                    '</div>' +
                    '<a class="btn btn-outline-dark btn-sm" href="' + escapeHtml(userUrl) + '">' + escapeHtml(openPaymentsText) + '</a>' +
                '</div>';
        }

        function populateQuickSelect(select, items, mapper) {
            select.innerHTML = '';
            (items || []).forEach(function (item, index) {
                var option = document.createElement('option');
                var normalized = mapper(item, index);
                option.value = normalized.value;
                option.textContent = normalized.label;
                select.appendChild(option);
            });
        }

        function loadQuickCreateData(customerId, customerMeta) {
            selectedCustomerId = parseInt(customerId || 0, 10) || 0;
            if (!selectedCustomerId) {
                return;
            }

            renderSelectedUser(customerMeta || {});
            setHidden(quickCreateWrap, false);
            submitButton.disabled = true;
            assetSelect.innerHTML = '';
            productSelect.innerHTML = '';
            showQuickAlert(loadingText, false);

            jsonFetch(searchUrl + '?' + toQuery({
                action: 'payment_customer_crypto_data',
                customer_id: selectedCustomerId
            })).then(function (payload) {
                var items;
                if (!payload || !payload.ok) {
                    showQuickAlert((payload && payload.message) || 'Unable to load payment options.', true);
                    submitButton.disabled = true;
                    return;
                }

                items = Array.isArray(payload.items) ? payload.items : [];
                populateQuickSelect(assetSelect, items, function (item) {
                    var code = String(item.code || '').trim();
                    var name = String(item.name || code).trim();
                    return {
                        value: String(item.id || ''),
                        label: code ? (code + ' - ' + name) : name
                    };
                });
                populateQuickSelect(productSelect, payload.product_options || [], function (item) {
                    return {
                        value: String(item.product_id || ''),
                        label: String(item.label || '')
                    };
                });

                if (!items.length) {
                    showQuickAlert((payload.empty_state_html ? '' : (payload.message || noAssetsText)), true);
                    if (payload.empty_state_html) {
                        quickAlert.innerHTML = payload.empty_state_html;
                        setHidden(quickAlert, false);
                    }
                    submitButton.disabled = true;
                    return;
                }

                if (!(payload.product_options || []).length) {
                    showQuickAlert('No active products are available.', true);
                    submitButton.disabled = true;
                    return;
                }

                if (payload.has_pending_payment) {
                    showQuickAlert(createPendingErrorText, true);
                    submitButton.disabled = true;
                    return;
                }

                showQuickAlert('', false);
                submitButton.disabled = !assetSelect.value || !productSelect.value;
            }).catch(function () {
                showQuickAlert('Unable to load payment options.', true);
                submitButton.disabled = true;
            });
        }

        function runSearch() {
            var query = input.value.trim();

            if (query === '') {
                resetResults();
                return;
            }

            requestIndex += 1;
            var activeRequest = requestIndex;
            setHidden(results, false);
            results.innerHTML = '<div class="admin-panel-card"><div class="admin-search-feedback">' + escapeHtml(loadingText) + '</div></div>';

            jsonFetch(searchUrl + '?' + toQuery({
                action: 'payment_customer_search',
                q: query
            })).then(function (payload) {
                if (activeRequest !== requestIndex) {
                    return;
                }

                if (!payload || !payload.ok) {
                    results.innerHTML = '';
                    setHidden(results, true);
                    return;
                }

                results.innerHTML = payload.html || '';
                setHidden(results, !payload.html);
            }).catch(function () {
                if (activeRequest !== requestIndex) {
                    return;
                }
                results.innerHTML = '';
                setHidden(results, true);
            });
        }

        toggle.addEventListener('click', function () {
            var isHidden = wrap.hasAttribute('hidden');
            setHidden(wrap, !isHidden);
            toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            if (isHidden) {
                window.setTimeout(function () {
                    input.focus();
                }, 30);
            } else {
                input.value = '';
                resetResults();
                resetQuickCreate();
            }
        });

        input.addEventListener('input', debounce(runSearch, 250));

        results.addEventListener('click', function (event) {
            var button = closest(event.target, '[data-admin-payment-customer-select]');
            if (!button) {
                return;
            }
            loadQuickCreateData(button.getAttribute('data-customer-id'), {
                id: button.getAttribute('data-customer-id') || '',
                email: button.getAttribute('data-customer-email') || '',
                public_handle: button.getAttribute('data-customer-handle') || ''
            });
        });

        [assetSelect, productSelect].forEach(function (field) {
            field.addEventListener('change', function () {
                if (cooldownRemaining > 0) {
                    submitButton.disabled = true;
                    return;
                }
                submitButton.disabled = !selectedCustomerId || !assetSelect.value || !productSelect.value;
            });
        });

        submitButton.addEventListener('click', function () {
            if (!selectedCustomerId || !assetSelect.value || !productSelect.value || cooldownRemaining > 0) {
                return;
            }

            submitButton.disabled = true;
            showQuickAlert('', false);

            jsonFetch(searchUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: toQuery({
                    action: 'create_quick_crypto_payment_request',
                    customer_id: selectedCustomerId,
                    asset_id: assetSelect.value,
                    product_id: productSelect.value,
                    _csrf: csrfToken
                })
            }).then(function (payload) {
                if (!payload || !payload.ok) {
                    if (payload && payload.message === 'pending_crypto_payment') {
                        showQuickAlert(createPendingErrorText, true);
                    } else {
                        showQuickAlert((payload && payload.message) || createErrorText, true);
                    }
                    if (payload && payload.cooldown_remaining) {
                        setSubmitCooldown(payload.cooldown_remaining);
                    } else {
                        submitButton.disabled = !selectedCustomerId || !assetSelect.value || !productSelect.value;
                    }
                    return;
                }

                showQuickAlert(createSuccessText, false);
                if (payload.payment_url) {
                    qa('.admin-payments-note__selected-link', selectedUser).forEach(function (node) {
                        node.remove();
                    });
                    selectedUser.insertAdjacentHTML('beforeend', '<a class="admin-inline-link admin-payments-note__selected-link" href="' + escapeHtml(payload.payment_url) + '">' + escapeHtml(openDetailsText) + '</a>');
                }
                setSubmitCooldown(payload.cooldown_seconds || 5);
                window.setTimeout(function () {
                    var nextUrl = new URL(window.location.href);
                    nextUrl.searchParams.set('page', 'payments');
                    nextUrl.searchParams.set('customer_id', String(selectedCustomerId));
                    window.location.href = nextUrl.toString();
                }, 900);
            }).catch(function () {
                showQuickAlert(createErrorText, true);
                submitButton.disabled = !selectedCustomerId || !assetSelect.value || !productSelect.value;
            });
        });
    }

    function initWalletCustomerPickers() {
        qa('[data-admin-wallet-customer-picker]').forEach(function (picker) {
            var form = closest(picker, 'form');
            var searchInput = q('[data-admin-wallet-customer-search]', picker);
            var results = q('[data-admin-wallet-customer-results]', picker);
            var customerIdInput = q('[data-admin-wallet-customer-id]', picker);
            var searchUrl = picker.getAttribute('data-search-url') || '';
            var submitName = picker.getAttribute('data-submit-name') || '';
            var requestIndex = 0;

            if (!form || !searchInput || !results || !customerIdInput || searchUrl === '' || submitName === '') {
                return;
            }

            function renderEmpty(message) {
                results.innerHTML = '<div class="admin-wallet-customer-picker__empty">' + escapeHtml(message) + '</div>';
                setHidden(results, false);
            }

            function renderResults(customers) {
                if (!customers.length) {
                    renderEmpty(picker.getAttribute('data-search-empty') || 'No users found.');
                    return;
                }

                results.innerHTML = customers.map(function (customer) {
                    var disabled = !!customer.disabled;
                    var hint = String(customer.hint || '').trim();
                    var handle = String(customer.public_handle || '').trim();
                    var primary = handle !== '' ? ('@' + handle) : String(customer.email || '').trim();
                    var meta = [customer.email || '', customer.status || ''].filter(Boolean).join(' • ');
                    return '' +
                        '<button type="button" class="admin-wallet-customer-picker__result' + (disabled ? ' is-disabled' : '') + '"' +
                            ' data-admin-wallet-customer-result' +
                            ' data-customer-id="' + escapeHtml(customer.id) + '"' +
                            (disabled ? ' disabled' : '') + '>' +
                            '<strong>' + escapeHtml(primary) + '</strong>' +
                            '<span>' + escapeHtml(hint !== '' ? hint : meta) + '</span>' +
                        '</button>';
                }).join('');
                setHidden(results, false);
            }

            function submitAssignment(customerId) {
                customerIdInput.value = String(customerId || 0);
                if (!customerId) {
                    return;
                }

                var submitter = document.createElement('button');
                submitter.type = 'submit';
                submitter.name = submitName;
                submitter.value = '1';
                submitter.formNoValidate = true;
                submitter.hidden = true;
                form.appendChild(submitter);

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter);
                } else {
                    submitter.click();
                }
            }

            searchInput.addEventListener('input', debounce(function () {
                var query = searchInput.value.trim();
                customerIdInput.value = '0';

                if (query.length < 2) {
                    results.innerHTML = '';
                    setHidden(results, true);
                    return;
                }

                requestIndex += 1;
                var activeRequest = requestIndex;
                renderEmpty('Loading...');

                jsonFetch(buildUrlWithQuery(searchUrl, { q: query }), {
                    credentials: 'same-origin'
                }).then(function (payload) {
                    if (activeRequest !== requestIndex) {
                        return;
                    }

                    if (!payload.ok) {
                        renderEmpty(picker.getAttribute('data-search-error') || 'Unable to search users.');
                        return;
                    }

                    renderResults(Array.isArray(payload.customers) ? payload.customers : []);
                }).catch(function () {
                    if (activeRequest !== requestIndex) {
                        return;
                    }
                    renderEmpty(picker.getAttribute('data-search-error') || 'Unable to search users.');
                });
            }, 250));

            results.addEventListener('click', function (event) {
                var resultButton = closest(event.target, '[data-admin-wallet-customer-result]');
                if (!resultButton || resultButton.disabled) {
                    return;
                }

                event.preventDefault();
                submitAssignment(parseInt(resultButton.getAttribute('data-customer-id') || '0', 10) || 0);
            });
        });
    }

	    function initWalletNetworkForms() {
	        qa('[data-admin-wallet-network-form]').forEach(function (form) {
	            var walletForm = closest(form, 'form') || form;
	            var assetSelect = q('[data-admin-wallet-asset-select]', form);
	            var networkSelect = q('[data-admin-wallet-network-select]', form);
	            var fixedInput = q('[data-admin-wallet-network-fixed]', form);
	            var addressInput = q('input[name="address"]', form);
	            var walletIdInput = q('input[name="wallet_id"]', walletForm);
	            var saveButton = q('button[name="admin_save_crypto_wallet"]', walletForm);
	            var ownerInput = q('input[name="owner_full_name"]', form);
	            var providerSelect = q('select[name="wallet_provider"]', form);
	            var memoInput = q('input[name="memo_tag"]', form);
	            var notesInput = q('textarea[name="notes"]', form);
	            var existingAddressAlert = q('[data-admin-wallet-existing-address-alert]', form);
	            var usdtCompanionRow = q('[data-admin-wallet-usdt-companion-row]', form);
	            var usdtCompanionSwitch = q('[data-admin-wallet-usdt-companion-switch]', form);
	            var lookupUrl = String(form.getAttribute('data-admin-wallet-lookup-url') || '').trim();
	            var existingAddressDefaultText = String(form.getAttribute('data-admin-wallet-exists-default') || '').trim();
	            var lookupErrorText = String(form.getAttribute('data-admin-wallet-check-error') || '').trim();
	            var lookupSequence = 0;
	            var addressLookupBlocked = false;

	            if (!assetSelect || !networkSelect || !fixedInput) {
	                return;
	            }

            function parseNetworkOptions(raw) {
                if (!raw) {
                    return {};
                }
                try {
                    var parsed = JSON.parse(raw);
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (error) {
                    return {};
                }
            }

	            function renderNetworkOptions(optionsMap, selectedValue) {
	                return Object.keys(optionsMap).map(function (networkCode) {
	                    var selected = String(networkCode) === String(selectedValue || '') ? ' selected' : '';
	                    return '<option value="' + escapeHtml(networkCode) + '"' + selected + '>' + escapeHtml(String(optionsMap[networkCode] || networkCode)) + '</option>';
	                }).join('');
	            }

	            function setProviderValue(value) {
	                var existingOption = null;
	                value = String(value || '').trim();
	                if (!providerSelect || !value) {
	                    return;
	                }
	                Array.prototype.slice.call(providerSelect.options || []).some(function (option) {
	                    if (String(option.value || '') === value) {
	                        existingOption = option;
	                        return true;
	                    }
	                    return false;
	                });
	                if (!existingOption) {
	                    existingOption = doc.createElement('option');
	                    existingOption.value = value;
	                    existingOption.textContent = value;
	                    providerSelect.appendChild(existingOption);
	                }
	                providerSelect.value = value;
	            }

	            function evmCompanionTargetForAsset(assetCode, networkCode) {
	                assetCode = String(assetCode || '').toUpperCase();
	                networkCode = String(networkCode || '').toLowerCase();
	                if (['ethereum', 'bnb', 'polygon'].indexOf(networkCode) === -1) {
	                    return null;
	                }
	                if (['ETH', 'BNB', 'POL', 'MATIC'].indexOf(assetCode) !== -1) {
	                    return {
	                        assetCode: 'USDT',
	                        networkCode: networkCode
	                    };
	                }
	                if (assetCode === 'USDT') {
	                    return {
	                        assetCode: 'ETH',
	                        networkCode: 'ethereum'
	                    };
	                }
	                return null;
	            }

	            function syncUsdtCompanionSwitch() {
	                var selectedOption = assetSelect.options[assetSelect.selectedIndex];
	                var assetCode = selectedOption ? String(selectedOption.getAttribute('data-asset-code') || '').toUpperCase() : '';
	                var companionTarget = evmCompanionTargetForAsset(assetCode, networkSelect.value);
	                var targetEnabled = companionTarget && usdtCompanionRow
	                    ? usdtCompanionRow.getAttribute('data-admin-wallet-evm-companion-' + String(companionTarget.assetCode || '').toLowerCase()) === '1'
	                    : false;
	                var shouldShow = !!(usdtCompanionRow && companionTarget && targetEnabled);

	                if (!usdtCompanionRow) {
	                    return;
	                }
	                usdtCompanionRow.setAttribute('data-usdt-companion-asset', companionTarget ? companionTarget.assetCode : '');
	                usdtCompanionRow.setAttribute('data-usdt-companion-network', companionTarget ? companionTarget.networkCode : '');
	                setHidden(usdtCompanionRow, !shouldShow);
	                if (!shouldShow && usdtCompanionSwitch) {
	                    usdtCompanionSwitch.checked = false;
	                }
	            }

	            function setWalletAddressBlocked(blocked) {
	                addressLookupBlocked = !!blocked;
	                if (saveButton) {
	                    saveButton.disabled = addressLookupBlocked;
	                    saveButton.classList.toggle('disabled', addressLookupBlocked);
	                }
	            }

	            function renderExistingAddressAlert(payload) {
	                var wallets = Array.isArray(payload.wallets) ? payload.wallets : [];
	                var message = String(payload.message || '').trim();
	                var walletText = wallets.map(function (wallet) {
	                    var parts = [];
	                    if (wallet.asset_code) {
	                        parts.push(String(wallet.asset_code));
	                    }
	                    if (wallet.network_label) {
	                        parts.push(String(wallet.network_label));
	                    }
	                    if (wallet.label) {
	                        parts.push(String(wallet.label));
	                    }
	                    return parts.join(' / ');
	                }).filter(Boolean).join(', ');

	                if (!existingAddressAlert || !payload.found) {
	                    setHidden(existingAddressAlert, true);
	                    return;
	                }

	                existingAddressAlert.classList.remove('alert-info', 'alert-warning', 'alert-danger');
	                existingAddressAlert.classList.add(payload.can_reuse ? 'alert-info' : 'alert-danger');
	                existingAddressAlert.innerHTML = '<strong>' + escapeHtml(message || existingAddressDefaultText) + '</strong>'
	                    + (walletText ? '<div class="small mt-1">' + escapeHtml(walletText) + '</div>' : '');
	                setHidden(existingAddressAlert, false);
	            }

	            function applyExistingAddressPayload(payload) {
	                if (payload && payload.ok === false) {
	                    if (existingAddressAlert) {
	                        existingAddressAlert.classList.remove('alert-info', 'alert-warning');
	                        existingAddressAlert.classList.add('alert-danger');
	                        existingAddressAlert.textContent = String(payload.message || lookupErrorText || '').trim();
	                        setHidden(existingAddressAlert, false);
	                    }
	                    setWalletAddressBlocked(true);
	                    return;
	                }

	                if (!payload || !payload.found) {
	                    renderExistingAddressAlert({ found: false });
	                    setWalletAddressBlocked(false);
	                    return;
	                }

	                if (payload.can_reuse) {
	                    if (ownerInput && payload.owner_full_name) {
	                        ownerInput.value = String(payload.owner_full_name || '');
	                    }
	                    if (notesInput && payload.notes) {
	                        notesInput.value = String(payload.notes || '');
	                    }
	                    if (memoInput && payload.memo_tag && !String(memoInput.value || '').trim()) {
	                        memoInput.value = String(payload.memo_tag || '');
	                    }
	                    setProviderValue(payload.wallet_provider || '');
	                }

	                renderExistingAddressAlert(payload);
	                setWalletAddressBlocked(!payload.can_reuse);
	            }

	            function scheduleExistingAddressLookup() {
	                var address = addressInput ? String(addressInput.value || '').trim() : '';
	                if (lookupUrl && address) {
	                    setWalletAddressBlocked(true);
	                }
	                lookupExistingAddress();
	            }

	            var lookupExistingAddress = debounce(function () {
	                var address = addressInput ? String(addressInput.value || '').trim() : '';
	                var requestId;
	                var params;

	                if (!lookupUrl || !address) {
	                    renderExistingAddressAlert({ found: false });
	                    setWalletAddressBlocked(false);
	                    return;
	                }

	                requestId = ++lookupSequence;
	                setWalletAddressBlocked(true);
	                params = new URLSearchParams();
	                params.set('address', address);
	                params.set('crypto_asset_id', String(assetSelect.value || '0'));
	                params.set('network_code', String(networkSelect.value || ''));
	                params.set('wallet_id', walletIdInput ? String(walletIdInput.value || '0') : '0');

	                fetch(lookupUrl + '&' + params.toString(), {
	                    method: 'GET',
	                    credentials: 'same-origin',
	                    headers: { 'Accept': 'application/json' }
	                }).then(function (response) {
	                    return response.json().catch(function () {
	                        return { ok: false, message: lookupErrorText };
	                    });
	                }).then(function (payload) {
	                    if (requestId !== lookupSequence) {
	                        return;
	                    }
	                    applyExistingAddressPayload(payload || {});
	                }).catch(function () {
	                    if (existingAddressAlert) {
	                        existingAddressAlert.classList.remove('alert-info', 'alert-warning');
	                        existingAddressAlert.classList.add('alert-danger');
	                        existingAddressAlert.textContent = lookupErrorText;
	                        setHidden(existingAddressAlert, false);
	                    }
	                    setWalletAddressBlocked(true);
	                });
	            }, 350);

	            if (walletForm && saveButton) {
	                walletForm.addEventListener('submit', function (event) {
	                    var submitter = event.submitter || doc.activeElement;
	                    var isWalletSaveSubmit = !submitter || submitter === saveButton || String(submitter.name || '') === 'admin_save_crypto_wallet';
	                    if (addressLookupBlocked && isWalletSaveSubmit) {
	                        event.preventDefault();
	                        event.stopPropagation();
	                    }
	                });
	            }

	            function syncNetworkFromAsset() {
	                var selectedOption = assetSelect.options[assetSelect.selectedIndex];
	                var networkOptions = parseNetworkOptions(selectedOption ? selectedOption.getAttribute('data-network-options') : '');
                var defaultNetwork = selectedOption ? String(selectedOption.getAttribute('data-default-network') || '') : '';
                var allowsChoice = selectedOption ? selectedOption.getAttribute('data-allows-network-choice') === '1' : false;
                var currentNetwork = String(networkSelect.value || '');
                var resolvedNetwork = currentNetwork && Object.prototype.hasOwnProperty.call(networkOptions, currentNetwork)
                    ? currentNetwork
                    : defaultNetwork;

                if (!resolvedNetwork) {
                    resolvedNetwork = Object.keys(networkOptions)[0] || '';
                }

                networkSelect.innerHTML = renderNetworkOptions(networkOptions, resolvedNetwork);
                networkSelect.value = resolvedNetwork;

	                if (allowsChoice) {
	                    networkSelect.classList.remove('d-none');
	                    fixedInput.classList.add('d-none');
	                    fixedInput.value = '';
	                } else {
	                    networkSelect.classList.add('d-none');
	                    fixedInput.classList.remove('d-none');
	                    fixedInput.value = resolvedNetwork ? String(networkOptions[resolvedNetwork] || resolvedNetwork) : '';
	                }
	                syncUsdtCompanionSwitch();
	                scheduleExistingAddressLookup();
	            }

	            assetSelect.addEventListener('change', syncNetworkFromAsset);
	            networkSelect.addEventListener('change', function () {
	                syncUsdtCompanionSwitch();
	                scheduleExistingAddressLookup();
	            });
	            if (addressInput) {
	                addressInput.addEventListener('input', scheduleExistingAddressLookup);
	                addressInput.addEventListener('blur', scheduleExistingAddressLookup);
	            }
	            syncNetworkFromAsset();
	        });
	    }

    function initOrderCreateForms() {
        qa('[data-admin-order-create]').forEach(function (form) {
            var searchInput = q('[data-admin-order-customer-search]', form);
            var results = q('[data-admin-order-customer-results]', form);
            var customerIdInput = q('[data-admin-order-customer-id]', form);
            var selectedWrap = q('[data-admin-order-customer-selected]', form);
            var selectedLink = q('[data-admin-order-customer-link]', form);
            var selectedEmail = q('[data-admin-order-customer-email]', form);
            var providerSelect = q('[data-admin-order-provider]', form);
            var productSelect = q('[data-admin-order-product]', form);
            var searchUrl = form.getAttribute('data-search-url') || '';
            var createUrl = form.getAttribute('data-create-url') || '';
            var csrfInput = q('input[name="_csrf"]', form);
            var requestIndex = 0;
            var createIndex = 0;
            var selectedCustomerEmail = String(searchInput && searchInput.value ? searchInput.value : '').trim();
            var customerVisibleProviderIds = [];
            var productOptions = [];

            if (!searchInput || !results || !customerIdInput || !selectedWrap || !selectedLink || !selectedEmail || searchUrl === '') {
                return;
            }

            if (providerSelect && productSelect) {
                try {
                    productOptions = JSON.parse(form.getAttribute('data-products') || '[]');
                } catch (error) {
                    productOptions = [];
                }
            }

            function parseVisibleProviderIds(raw) {
                if (Array.isArray(raw)) {
                    return raw.map(function (value) {
                        return parseInt(value, 10) || 0;
                    }).filter(function (value) {
                        return value > 0;
                    });
                }

                if (typeof raw !== 'string' || raw.trim() === '') {
                    return [];
                }

                try {
                    return parseVisibleProviderIds(JSON.parse(raw));
                } catch (error) {
                    return raw.split(',').map(function (value) {
                        return parseInt(String(value || '').trim(), 10) || 0;
                    }).filter(function (value) {
                        return value > 0;
                    });
                }
            }

            function hasSelectedCustomer() {
                return (parseInt(customerIdInput.value || '0', 10) || 0) > 0;
            }

            function isProviderAllowed(providerId) {
                providerId = parseInt(providerId || '0', 10) || 0;
                if (providerId <= 0) {
                    return false;
                }
                if (!hasSelectedCustomer() || !customerVisibleProviderIds.length) {
                    return true;
                }
                return customerVisibleProviderIds.indexOf(providerId) !== -1;
            }

            function isValidEmail(value) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
            }

            function normalizeHandle(value) {
                var normalized = String(value || '').trim().toLowerCase();
                if (normalized.charAt(0) === '@') {
                    normalized = normalized.slice(1);
                }
                return normalized;
            }

            function resetSelectedState() {
                customerIdInput.value = '0';
                selectedCustomerEmail = '';
                customerVisibleProviderIds = [];
                selectedWrap.classList.remove('is-selected');
                selectedLink.classList.add('is-disabled');
                selectedLink.setAttribute('href', '#');
                selectedLink.setAttribute('aria-disabled', 'true');
                selectedEmail.textContent = selectedWrap.getAttribute('data-empty-label') || 'No customer assigned';
                syncProviderOptions();
            }

            function setSelectedState(customer) {
                var email = String(customer && customer.email ? customer.email : '').trim();
                var customerId = parseInt(customer && customer.id ? customer.id : 0, 10) || 0;

                customerIdInput.value = String(customerId);
                selectedCustomerEmail = email;
                searchInput.value = email;
                selectedWrap.classList.toggle('is-selected', customerId > 0);
                selectedLink.classList.toggle('is-disabled', customerId <= 0);
                selectedLink.setAttribute('href', customerId > 0 ? '/admin/?page=users&customer_id=' + customerId : '#');
                if (customerId > 0) {
                    selectedLink.removeAttribute('aria-disabled');
                } else {
                    selectedLink.setAttribute('aria-disabled', 'true');
                }
                selectedEmail.textContent = email || (selectedWrap.getAttribute('data-empty-label') || 'No customer assigned');
                results.innerHTML = '';
                setHidden(results, true);
                customerVisibleProviderIds = parseVisibleProviderIds(customer && customer.visible_provider_ids ? customer.visible_provider_ids : []);
                syncProviderOptions();
            }

            function renderEmpty(message) {
                results.innerHTML = '<div class="admin-order-picker__empty">' + escapeHtml(message) + '</div>';
                setHidden(results, false);
            }

            function syncProviderOptions() {
                var currentProviderId;
                var currentAllowed = false;

                if (!providerSelect) {
                    return;
                }

                currentProviderId = parseInt(providerSelect.value || '0', 10) || 0;

                qa('option', providerSelect).forEach(function (option, index) {
                    var optionProviderId = parseInt(option.value || '0', 10) || 0;
                    var allowed = index === 0 || isProviderAllowed(optionProviderId);

                    option.disabled = !allowed && index !== 0;
                    option.hidden = !allowed && index !== 0;

                    if (optionProviderId === currentProviderId && allowed) {
                        currentAllowed = true;
                    }
                });

                if (currentProviderId > 0 && !currentAllowed) {
                    providerSelect.value = '';
                }

                if (productSelect) {
                    productSelect.setAttribute('data-selected-product-id', '');
                }

                syncProductOptions();
            }

            function syncProductOptions() {
                var providerId;
                var previousProductId;
                var placeholder;
                var filtered;
                var optionsHtml;

                if (!providerSelect || !productSelect) {
                    return;
                }

                providerId = parseInt(providerSelect.value || '0', 10) || 0;
                previousProductId = String(productSelect.value || productSelect.getAttribute('data-selected-product-id') || '').trim();
                placeholder = providerId > 0
                    ? (productSelect.getAttribute('data-placeholder-default') || 'Choose subscription time')
                    : (productSelect.getAttribute('data-placeholder-locked') || 'Choose package first');

                if (providerId <= 0) {
                    productSelect.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>';
                    productSelect.value = '';
                    productSelect.disabled = true;
                    return;
                }

                filtered = productOptions.filter(function (item) {
                    var itemProviderId = parseInt(item && item.provider_id ? item.provider_id : 0, 10) || 0;
                    return itemProviderId === providerId && isProviderAllowed(itemProviderId);
                });

                optionsHtml = '<option value="">' + escapeHtml(placeholder) + '</option>' + filtered.map(function (item) {
                    var optionId = String(item && item.id ? item.id : '').trim();
                    return '<option value="' + escapeHtml(optionId) + '">' + escapeHtml(item && item.label ? item.label : '') + '</option>';
                }).join('');

                productSelect.innerHTML = optionsHtml;
                productSelect.disabled = filtered.length === 0;

                if (filtered.some(function (item) { return String(item && item.id ? item.id : '') === previousProductId; })) {
                    productSelect.value = previousProductId;
                } else {
                    productSelect.value = '';
                }

                productSelect.setAttribute('data-selected-product-id', String(productSelect.value || ''));
            }

            function renderResults(customers, query) {
                var normalizedEmail = String(query || '').trim().toLowerCase();
                var canCreate = createUrl !== '' && isValidEmail(normalizedEmail);
                var hasExactEmail = false;
                var itemsHtml = '';

                (customers || []).forEach(function (customer) {
                    var email = String(customer.email || '').trim();
                    var handle = String(customer.public_handle || '').trim();
                    var parts = [];

                    if (email.toLowerCase() === normalizedEmail) {
                        hasExactEmail = true;
                    }
                    if (handle !== '') {
                        parts.push('@' + handle);
                    }
                    if (customer.status) {
                        parts.push(String(customer.status));
                    }
                    if (customer.id) {
                        parts.push('ID ' + String(customer.id));
                    }

                    itemsHtml += '' +
                        '<button type="button" class="admin-order-picker__result" data-admin-order-customer-result data-customer-id="' + escapeHtml(customer.id) + '" data-visible-provider-ids="' + escapeHtml(JSON.stringify(customer.visible_provider_ids || [])) + '">' +
                            '<strong>' + escapeHtml(email) + '</strong>' +
                            '<span>' + escapeHtml(parts.join(' • ')) + '</span>' +
                        '</button>';
                });

                if (canCreate && !hasExactEmail) {
                    itemsHtml += '' +
                        '<button type="button" class="admin-order-picker__result admin-order-picker__result--create" data-admin-order-create-customer data-customer-email="' + escapeHtml(query) + '">' +
                            '<strong>' + escapeHtml(form.getAttribute('data-create-label') || 'Add user') + '</strong>' +
                            '<span>' + escapeHtml(form.getAttribute('data-create-help') || 'This email is not in the service yet. Create the user and select them for this order.') + '</span>' +
                        '</button>';
                }

                if (itemsHtml === '') {
                    renderEmpty(form.getAttribute('data-search-empty') || 'No users found.');
                    return;
                }

                results.innerHTML = itemsHtml;
                setHidden(results, false);
            }

            function searchCustomers(query) {
                requestIndex += 1;
                var activeRequest = requestIndex;

                renderEmpty('Loading...');
                jsonFetch(buildUrlWithQuery(searchUrl, { q: query }), {
                    credentials: 'same-origin'
                }).then(function (payload) {
                    if (activeRequest !== requestIndex) {
                        return;
                    }
                    if (!payload.ok) {
                        renderEmpty(form.getAttribute('data-search-error') || 'Unable to search users.');
                        return;
                    }
                    renderResults(Array.isArray(payload.customers) ? payload.customers : [], query);
                }).catch(function () {
                    if (activeRequest !== requestIndex) {
                        return;
                    }
                    renderEmpty(form.getAttribute('data-search-error') || 'Unable to search users.');
                });
            }

            function createCustomer(email, button) {
                var csrfToken = csrfInput ? String(csrfInput.value || '') : '';
                var formData;
                createIndex += 1;
                var activeCreate = createIndex;

                if (!createUrl || email === '' || !isValidEmail(email) || csrfToken === '') {
                    return;
                }

                if (button) {
                    button.disabled = true;
                    q('span', button).textContent = form.getAttribute('data-create-loading') || 'Creating user...';
                }

                formData = new window.FormData();
                formData.append('_csrf', csrfToken);
                formData.append('email', email);

                jsonFetch(createUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (payload) {
                    var customer = payload && payload.customer ? payload.customer : null;

                    if (activeCreate !== createIndex) {
                        return;
                    }
                    if (!payload || !payload.ok || !customer) {
                        renderEmpty((payload && payload.message) ? payload.message : (form.getAttribute('data-create-error') || 'Unable to create the user.'));
                        return;
                    }

                    setSelectedState(customer);
                }).catch(function () {
                    if (activeCreate !== createIndex) {
                        return;
                    }
                    renderEmpty(form.getAttribute('data-create-error') || 'Unable to create the user.');
                });
            }

            searchInput.addEventListener('input', debounce(function () {
                var query = String(searchInput.value || '').trim();
                var selectedEmailLower = selectedCustomerEmail.toLowerCase();

                if (selectedEmailLower !== '' && query.toLowerCase() !== selectedEmailLower) {
                    resetSelectedState();
                }

                if (query === '' || (!isValidEmail(query) && !/^@?[a-z0-9._-]{2,}$/i.test(query) && !ctypeDigit(query))) {
                    results.innerHTML = '';
                    setHidden(results, true);
                    return;
                }

                searchCustomers(query);
            }, 200));

            results.addEventListener('click', function (event) {
                var customerButton = closest(event.target, '[data-admin-order-customer-result]');
                var createButton = closest(event.target, '[data-admin-order-create-customer]');

                if (customerButton) {
                    event.preventDefault();
                    setSelectedState({
                        id: customerButton.getAttribute('data-customer-id') || '0',
                        email: q('strong', customerButton) ? q('strong', customerButton).textContent : '',
                        visible_provider_ids: customerButton.getAttribute('data-visible-provider-ids') || '[]'
                    });
                    return;
                }

                if (createButton) {
                    event.preventDefault();
                    createCustomer(String(createButton.getAttribute('data-customer-email') || '').trim(), createButton);
                }
            });

            searchInput.addEventListener('focus', function () {
                var query = String(searchInput.value || '').trim();
                if (query !== '' && !results.hasAttribute('hidden')) {
                    setHidden(results, false);
                }
            });

            doc.addEventListener('click', function (event) {
                if (!closest(event.target, '[data-admin-order-create]')) {
                    setHidden(results, true);
                }
            });

            if (providerSelect && productSelect) {
                providerSelect.addEventListener('change', function () {
                    productSelect.setAttribute('data-selected-product-id', '');
                    syncProductOptions();
                });

                productSelect.addEventListener('change', function () {
                    productSelect.setAttribute('data-selected-product-id', String(productSelect.value || ''));
                });

                customerVisibleProviderIds = parseVisibleProviderIds(form.getAttribute('data-visible-provider-ids') || '[]');
                syncProviderOptions();
            }
        });

        function ctypeDigit(value) {
            return /^[0-9]+$/.test(String(value || '').trim());
        }
    }

    function initUserQuickOrderForms() {
        qa('[data-admin-user-quick-order-form]').forEach(function (form) {
            var providerSelect = q('[data-admin-user-quick-order-provider]', form);
            var productSelect = q('[data-admin-user-quick-order-product]', form);
            var productOptions = [];

            if (!providerSelect || !productSelect) {
                return;
            }

            try {
                productOptions = JSON.parse(form.getAttribute('data-products') || '[]');
            } catch (error) {
                productOptions = [];
            }

            function syncProducts() {
                var providerId = parseInt(providerSelect.value || '0', 10) || 0;
                var previousProductId = String(productSelect.value || productSelect.getAttribute('data-selected-product-id') || '').trim();
                var placeholder = providerId > 0
                    ? (productSelect.getAttribute('data-placeholder-default') || 'Choose subscription time')
                    : (productSelect.getAttribute('data-placeholder-locked') || 'Choose package first');
                var filtered;
                var optionsHtml;
                var hasPrevious = false;

                if (providerId <= 0) {
                    productSelect.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>';
                    productSelect.value = '';
                    productSelect.disabled = true;
                    return;
                }

                filtered = productOptions.filter(function (item) {
                    return (parseInt(item && item.provider_id ? item.provider_id : 0, 10) || 0) === providerId;
                });

                optionsHtml = '<option value="">' + escapeHtml(placeholder) + '</option>' + filtered.map(function (item) {
                    var itemId = String(parseInt(item && item.id ? item.id : 0, 10) || 0);
                    if (itemId === previousProductId) {
                        hasPrevious = true;
                    }
                    return '<option value="' + escapeHtml(itemId) + '">' + escapeHtml(item && item.label ? item.label : '') + '</option>';
                }).join('');

                productSelect.innerHTML = optionsHtml;
                productSelect.disabled = filtered.length === 0;
                productSelect.value = hasPrevious ? previousProductId : '';
            }

            providerSelect.addEventListener('change', syncProducts);
            syncProducts();
        });
    }

    function initUserDetailCopy() {
        var feedbackTimers = new WeakMap();

        document.addEventListener('click', function (event) {
            var button = closest(event.target, '[data-admin-copy-value]');
            var feedback;
            var feedbackText;
            var previousTimer;

            if (!button || button.disabled) {
                return;
            }

            feedback = q('[data-admin-copy-feedback]', button);
            feedbackText = String(button.getAttribute('data-admin-copy-feedback-text') || 'Copied').trim();
            copyTextWithFallback(button.getAttribute('data-admin-copy-value') || '').then(function () {
                if (!feedback) {
                    return;
                }
                feedback.textContent = feedbackText || 'Copied';
                setHidden(feedback, false);
                previousTimer = feedbackTimers.get(button) || 0;
                window.clearTimeout(previousTimer);
                feedbackTimers.set(button, window.setTimeout(function () {
                    setHidden(feedback, true);
                }, 1200));
            });
        });
    }

	    function initOrdersGuidanceToggle() {
	        var toggle = q('[data-admin-orders-guidance-toggle]');
	        var targets = qa('.admin-orders-workflow-card, .admin-orders-help-alert, .alert-info.admin-orders-help-alert');
	        var storageKey = 'subme-admin-orders-guidance-visible';
        var hiddenClass = 'is-admin-orders-guidance-hidden';

        if (!toggle || !targets.length) {
            return;
        }

        function readStoredState() {
            try {
                return window.localStorage ? window.localStorage.getItem(storageKey) : null;
            } catch (error) {
                return null;
            }
        }

        function writeStoredState(visible) {
            try {
                if (window.localStorage) {
                    window.localStorage.setItem(storageKey, visible ? '1' : '0');
                }
            } catch (error) {
                // Local view preference only; ignore storage errors.
            }
        }

        function setGuidanceVisible(visible, persist) {
            targets.forEach(function (target) {
                setHidden(target, !visible);
            });
            if (doc.body) {
                doc.body.classList.toggle(hiddenClass, !visible);
            }
            toggle.checked = visible;
            toggle.setAttribute('aria-checked', visible ? 'true' : 'false');
            if (persist) {
                writeStoredState(visible);
            }
        }

        setGuidanceVisible(readStoredState() === '1', false);

	        toggle.addEventListener('change', function () {
	            setGuidanceVisible(toggle.checked, true);
	        });
	    }

    function initOrdersActionMenus() {
        var active = null;
        function closeMenu(restoreFocus) {
            if (!active) return;
            var previous = active;
            active = null;
            previous.menu.hidden = true;
            previous.trigger.setAttribute('aria-expanded', 'false');
            previous.placeholder.replaceWith(previous.menu);
            if (restoreFocus) previous.trigger.focus();
        }
        function positionMenu() {
            if (!active) return;
            var rect = active.trigger.getBoundingClientRect();
            var viewport = window.visualViewport;
            var left = viewport ? viewport.offsetLeft : 0;
            var top = viewport ? viewport.offsetTop : 0;
            var width = viewport ? viewport.width : doc.documentElement.clientWidth;
            var height = viewport ? viewport.height : window.innerHeight;
            active.menu.style.maxHeight = Math.max(100, height - 24) + 'px';
            var size = active.menu.getBoundingClientRect();
            var y = rect.bottom + 6;
            if (y + size.height > top + height - 12) y = rect.top - size.height - 6;
            active.menu.style.left = Math.max(left + 12, Math.min(rect.right - size.width, left + width - size.width - 12)) + 'px';
            active.menu.style.top = Math.max(top + 12, y) + 'px';
        }
        function toggleMenu(trigger) {
            if (active && active.trigger === trigger) { closeMenu(false); return; }
            closeMenu(false);
            var menu = doc.getElementById(trigger.getAttribute('aria-controls'));
            if (!menu) return;
            var placeholder = doc.createComment('order actions');
            menu.replaceWith(placeholder);
            doc.body.appendChild(menu);
            active = {menu: menu, trigger: trigger, placeholder: placeholder};
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            positionMenu();
            var firstItem = q('a[href],button:not(:disabled),input:not(:disabled)', menu);
            if (firstItem) firstItem.focus();
        }
        doc.addEventListener('click', function (event) {
            var trigger = closest(event.target, '[data-admin-orders-menu-toggle]');
            if (trigger) { toggleMenu(trigger); return; }
            if (!active || active.trigger.contains(event.target)) return;
            if (!active.menu.contains(event.target)) { closeMenu(false); return; }
            if (closest(event.target, 'a, button')) closeMenu(false);
        });
        doc.addEventListener('keydown', function (event) {
            if (!active) return;
            if (event.key === 'Escape') { event.preventDefault(); closeMenu(true); return; }
            var items = qa('a[href],button:not(:disabled),input:not(:disabled)', active.menu);
            if (!items.length) return;
            var index = items.indexOf(doc.activeElement);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                items[(index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
            }
        });
        doc.addEventListener('focusin', function (event) {
            if (active && !active.menu.contains(event.target) && !active.trigger.contains(event.target)) closeMenu(false);
        });
        window.addEventListener('resize', function () { closeMenu(false); });
        doc.addEventListener('scroll', function (event) {
            if (!active || active.menu.contains(event.target)) return;
            var rect = active.trigger.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > window.innerHeight) closeMenu(false);
            else positionMenu();
        }, true);
    }

    function initCompletedOrdersExpirySort() {
        qa('[data-admin-completed-orders-body]').forEach(function (body) {
            var direction = String(body.getAttribute('data-sort-direction') || '').toLowerCase();
            if (direction !== 'asc' && direction !== 'desc') {
                return;
            }
            var rows = qa('[data-admin-completed-order-row]', body);

            rows.sort(function (left, right) {
                var leftExpiry = String(left.getAttribute('data-order-expires-at') || '');
                var rightExpiry = String(right.getAttribute('data-order-expires-at') || '');

                if (leftExpiry === rightExpiry) {
                    return 0;
                }
                if (leftExpiry === '') {
                    return 1;
                }
                if (rightExpiry === '') {
                    return -1;
                }

                return direction === 'asc'
                    ? leftExpiry.localeCompare(rightExpiry)
                    : rightExpiry.localeCompare(leftExpiry);
            });

            rows.forEach(function (row) {
                body.appendChild(row);
            });
        });
    }

    function initPaymentReceivedFields() {
        var statusSelect = q('#payment_status');
        var receivedFields = qa('[data-admin-payment-received-field]');

        if (!statusSelect || !receivedFields.length) {
            return;
        }

        function syncReceivedFields() {
            var status = String(statusSelect.value || '').toLowerCase();
            var shouldHide = status === 'pending' || status === 'pending_payment';

            receivedFields.forEach(function (field) {
                setHidden(field, shouldHide);
            });
        }

        syncReceivedFields();
        statusSelect.addEventListener('change', syncReceivedFields);
    }

    function initPaymentBlockchainCheck() {
        var button = q('[data-admin-payment-blockchain-check]');
        var editorForm = q('.admin-editor-form');
        var resultNode = q('[data-admin-payment-blockchain-check-result]');
        var statusSelect = q('#payment_status');
        var receivedCryptoInput = q('#payment_received_crypto_amount');
        var receivedFiatInput = q('#payment_received_fiat_amount');

        if (!button || !editorForm || !resultNode) {
            return;
        }

        function renderResult(type, title, message, explorerUrl) {
            var className = type === 'success' ? 'alert-success' : (type === 'danger' ? 'alert-danger' : 'alert-warning');
            var html = '';

            resultNode.className = 'alert ' + className + ' admin-payment-blockchain-check-alert';
            html += title ? '<strong>' + escapeHtml(title) + '</strong>' : '';
            html += message ? '<span>' + escapeHtml(message) + '</span>' : '';
            if (explorerUrl) {
                html += '<a href="' + escapeHtml(explorerUrl) + '" target="_blank" rel="noopener noreferrer" class="admin-payment-blockchain-check-alert__link">'
                    + escapeHtml(button.getAttribute('data-explorer-label') || 'Explorer')
                    + '</a>';
            }
            resultNode.innerHTML = html;
            setHidden(resultNode, false);
        }

        button.addEventListener('click', function (event) {
            var labelNode = q('span', button);
            var originalLabel = labelNode ? labelNode.textContent : '';
            var loadingLabel = button.getAttribute('data-loading-label') || 'Checking...';
            var formData = new URLSearchParams(new FormData(editorForm));

            event.preventDefault();
            formData.set('admin_crypto_payment_blockchain_check_ajax', '1');

            button.disabled = true;
            if (labelNode) {
                labelNode.textContent = loadingLabel;
            }
            renderResult('warning', '', loadingLabel, '');

            fetch(editorForm.getAttribute('action') || window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                credentials: 'same-origin',
                body: formData.toString()
            }).then(function (response) {
                return response.json().catch(function () {
                    return { ok: false, message: 'Invalid JSON response.' };
                }).then(function (payload) {
                    payload.__http_ok = response.ok;
                    return payload;
                });
            }).then(function (payload) {
                if (!payload || !payload.ok) {
                    renderResult(
                        'warning',
                        payload && payload.match_title ? payload.match_title : '',
                        payload && payload.message ? payload.message : 'Unable to check blockchain right now.',
                        payload && payload.explorer_url ? payload.explorer_url : ''
                    );
                    return;
                }

                if (statusSelect && payload.status) {
                    statusSelect.value = String(payload.status);
                    statusSelect.classList.remove(
                        'admin-payment-status-select--danger',
                        'admin-payment-status-select--warning',
                        'admin-payment-status-select--info',
                        'admin-payment-status-select--success'
                    );
                    statusSelect.classList.add('admin-payment-status-select--success');
                    statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (receivedCryptoInput && payload.received_crypto_amount) {
                    receivedCryptoInput.value = String(payload.received_crypto_amount);
                }
                if (receivedFiatInput && payload.received_fiat_amount) {
                    receivedFiatInput.value = String(payload.received_fiat_amount);
                }

                renderResult(
                    'success',
                    payload.title || '',
                    payload.message || '',
                    payload.tx_url || ''
                );
            }).catch(function () {
                renderResult('danger', '', 'Unable to check blockchain right now.', '');
            }).finally(function () {
                button.disabled = false;
                if (labelNode) {
                    labelNode.textContent = originalLabel;
                }
            });
        });
    }

    function initTopbarTools() {
        var activeFlyout = null;

        function ensureToggleBadge(toggle) {
            var badge;
            if (!toggle) {
                return null;
            }

            badge = q('.admin-chat-inbox__badge', toggle);
            if (badge) {
                return badge;
            }

            badge = document.createElement('span');
            badge.className = 'admin-chat-inbox__badge';
            toggle.appendChild(badge);
            return badge;
        }

        function updateToggleBadge(toggle, count) {
            var badge;
            var safeCount = Math.max(0, parseInt(count, 10) || 0);
            if (!toggle) {
                return;
            }

            badge = q('.admin-chat-inbox__badge', toggle);
            if (safeCount <= 0) {
                if (badge) {
                    badge.remove();
                }
                return;
            }

            badge = badge || ensureToggleBadge(toggle);
            if (!badge) {
                return;
            }

            badge.textContent = String(safeCount);
        }

        function ensureOrdersCountBadge(section) {
            var head = section ? q('.admin-topbar-notifications__section-head', section) : null;
            var badge = section ? q('[data-admin-topbar-orders-count]', section) : null;
            if (badge || !head) {
                return badge;
            }

            badge = document.createElement('span');
            badge.className = 'admin-status-pill admin-status-pill--warning';
            badge.setAttribute('data-admin-topbar-orders-count', '1');
            head.appendChild(badge);
            return badge;
        }

        function updateOrdersCount(section, delta) {
            var badge = ensureOrdersCountBadge(section);
            var count = badge ? parseInt(badge.textContent || '0', 10) || 0 : 0;
            count = Math.max(0, count + delta);
            if (!badge) {
                return;
            }
            if (count <= 0) {
                badge.remove();
                return;
            }
            badge.textContent = String(count);
        }

        function renderTopbarOrderItem(order) {
            if (typeof order.task_html === 'string') {
                var taskWrapper = document.createElement('div');
                taskWrapper.innerHTML = order.task_html;
                var taskItem = q('[data-admin-topbar-order-item]', taskWrapper);
                if (taskItem) return taskItem;
            }
            var article = document.createElement('article');
            article.className = 'admin-topbar-notifications__item admin-topbar-notifications__item--compact admin-topbar-notifications__item--order';
            var inlineText = activeFlyout ? (activeFlyout.getAttribute('data-topbar-order-created-text') || 'Payment confirmed. Waiting for fulfillment.') : 'Payment confirmed. Waiting for fulfillment.';
            var openOrderText = activeFlyout ? (activeFlyout.getAttribute('data-topbar-open-order-inline-text') || 'Order') : 'Order';
            var openPaymentText = activeFlyout ? (activeFlyout.getAttribute('data-topbar-open-payment-inline-text') || 'Payment') : 'Payment';
            var customerLabel = String(order.customer_email || '').trim();
            var customerHandle = String(order.customer_handle || '').trim().replace(/^@+/, '');
            var customerUrl = '/admin/?page=users&customer_id=' + encodeURIComponent(String(order.customer_id || 0));
            var orderUrl = String(order.order_url || '/admin/?page=orders');
            var paymentLink = String(order.payment_url || '').trim();
            var statusIcon = String(order.status_icon || 'bi bi-arrow-repeat').trim() || 'bi bi-arrow-repeat';
            var statusClass = String(order.status_class || 'admin-order-status-icon--awaiting-activation').trim() || 'admin-order-status-icon--awaiting-activation';
            var walletAddressCompact = String(order.wallet_address_compact || '').trim();
            var walletExplorerUrl = String(order.wallet_explorer_url || '').trim();
            var walletLabel = String(order.wallet_label || '').trim();
            article.innerHTML =
                '<div class="admin-topbar-notifications__order-visual" aria-hidden="true">'
                + '<i class="' + escapeHtml(statusIcon) + ' admin-order-status-icon ' + escapeHtml(statusClass) + '"></i></div>'
                + '<div class="admin-topbar-notifications__item-main">'
                + '<div class="admin-topbar-notifications__order-topline"><div class="admin-topbar-notifications__order-identity">'
                + (customerHandle ? '<a class="admin-topbar-notifications__order-handle" href="' + escapeHtml(customerUrl) + '">@' + escapeHtml(customerHandle) + '</a>' : '')
                + (customerLabel ? '<a class="admin-topbar-notifications__order-email" href="' + escapeHtml(customerUrl) + '">' + escapeHtml(customerLabel) + '</a>' : '')
                + '</div><a class="admin-topbar-notifications__order-reference" href="' + escapeHtml(orderUrl) + '">#' + escapeHtml(String(order.id || '')) + '</a></div>'
                + '<p class="admin-topbar-notifications__order-summary">' + escapeHtml(inlineText) + '</p>'
                + '<div class="admin-topbar-notifications__wallet-details">'
                + (walletAddressCompact !== '' && walletExplorerUrl !== ''
                    ? '<div class="admin-topbar-notifications__wallet-row"><a class="admin-topbar-notifications__wallet-link" href="' + escapeHtml(walletExplorerUrl) + '" target="_blank" rel="noopener noreferrer"><code>' + escapeHtml(walletAddressCompact) + '</code></a></div>' : '')
                + (walletLabel !== '' ? '<div class="admin-topbar-notifications__wallet-meta"><span class="admin-status-pill admin-status-pill--assigned">' + escapeHtml(walletLabel) + '</span></div>' : '')
                + '</div><div class="admin-topbar-notifications__order-links">'
                + '<a class="admin-topbar-notifications__order-action" href="' + escapeHtml(orderUrl) + '"><i class="bi bi-receipt" aria-hidden="true"></i>' + escapeHtml(openOrderText) + '<i class="bi bi-arrow-right" aria-hidden="true"></i></a>'
                + (paymentLink ? '<a class="admin-topbar-notifications__order-action admin-topbar-notifications__order-action--secondary" href="' + escapeHtml(paymentLink) + '"><i class="bi bi-wallet2" aria-hidden="true"></i>' + escapeHtml(openPaymentText) + '</a>' : '')
                + '</div></div>';
            return article;
        }

        function moveAcceptedPaymentToOrders(flyout, button, payload) {
            var order = payload && payload.order ? payload.order : null;
            var paymentItem = closest(button, '[data-admin-topbar-payment-item]');
            var paymentsSection = q('[data-admin-topbar-payments-section]', flyout);
            var ordersSection = q('[data-admin-topbar-orders-section]', flyout);
            var paymentsList = q('[data-admin-topbar-payments-list]', paymentsSection);
            var ordersList = q('[data-admin-topbar-orders-list]', ordersSection);

            if (paymentItem) {
                paymentItem.remove();
            }

            if (paymentsList && !q('[data-admin-topbar-payment-item]', paymentsList)) {
                paymentsList.remove();
                if (paymentsSection) {
                    setHidden(paymentsSection, true);
                }
            }

            if (order && ordersSection) {
                setHidden(ordersSection, false);
                if (!ordersList) {
                    ordersList = document.createElement('div');
                    ordersList.className = 'admin-topbar-notifications__list';
                    ordersList.setAttribute('data-admin-topbar-orders-list', '1');
                    ordersSection.appendChild(ordersList);
                }
                var existingTask = q('[data-admin-topbar-order-item="' + (parseInt(order.id, 10) || 0) + '"]', ordersList);
                if (existingTask) existingTask.remove();
                ordersList.insertBefore(renderTopbarOrderItem(order), ordersList.firstChild || null);
                if (!existingTask) updateOrdersCount(ordersSection, 1);
            }
        }

        function syncTopbarOrderTasks(flyout, payload) {
            if (!flyout || typeof payload.orders_html !== 'string' || !payload.orders_revision) return;
            if (flyout.getAttribute('data-orders-revision') === payload.orders_revision) return;
            var section = q('[data-admin-topbar-orders-section]', flyout);
            if (!section) return;
            var wrapper = document.createElement('div');
            wrapper.innerHTML = payload.orders_html;
            var replacement = q('[data-admin-topbar-orders-section]', wrapper);
            if (!replacement) return;
            var activeLink = section.contains(document.activeElement) && document.activeElement.tagName === 'A'
                ? document.activeElement.getAttribute('href') : '';
            section.replaceWith(replacement);
            flyout.setAttribute('data-orders-revision', payload.orders_revision);
            if (activeLink) {
                var focusLink = qa('a', replacement).filter(function (link) { return link.getAttribute('href') === activeLink; })[0];
                if (focusLink) focusLink.focus({ preventScroll: true });
            }
        }

        function resolveOrdersRedirectUrl(payload) {
            var redirectUrl = payload && payload.redirect_url ? String(payload.redirect_url).trim() : '';
            var order = payload && payload.order ? payload.order : null;
            var customerId = order ? parseInt(order.customer_id || '0', 10) || 0 : 0;

            if (redirectUrl !== '') {
                return redirectUrl;
            }
            if (order && order.order_url) {
                return String(order.order_url);
            }
            if (customerId > 0) {
                return '/admin/?page=orders&customer_id=' + encodeURIComponent(String(customerId));
            }
            return '/admin/?page=orders';
        }

        var cryptoAcceptModalController = (function () {
            var modalNode = q('[data-admin-crypto-accept-modal]');
            var modalInstance = modalNode && hasBootstrap && window.bootstrap && typeof window.bootstrap.Modal !== 'undefined'
                ? window.bootstrap.Modal.getOrCreateInstance(modalNode)
                : null;
            var handleLink = modalNode ? q('[data-admin-crypto-accept-handle-link]', modalNode) : null;
            var handleText = modalNode ? q('[data-admin-crypto-accept-handle-text]', modalNode) : null;
            var walletLabel = modalNode ? q('[data-admin-crypto-accept-wallet-label]', modalNode) : null;
            var emailNode = modalNode ? q('[data-admin-crypto-accept-email]', modalNode) : null;
            var cryptoLabel = modalNode ? q('[data-admin-crypto-accept-crypto-label]', modalNode) : null;
            var statusNode = modalNode ? q('[data-admin-crypto-accept-status]', modalNode) : null;
            var topupBadge = modalNode ? q('[data-admin-crypto-accept-topup-badge]', modalNode) : null;
            var fiatLabel = modalNode ? q('[data-admin-crypto-accept-fiat-label]', modalNode) : null;
            var timeWrap = modalNode ? q('[data-admin-crypto-accept-time-wrap]', modalNode) : null;
            var timeNode = modalNode ? q('[data-admin-crypto-accept-time]', modalNode) : null;
            var explorerLink = modalNode ? q('[data-admin-crypto-accept-address-link]', modalNode) : null;
            var explorerText = modalNode ? q('[data-admin-crypto-accept-address-text]', modalNode) : null;
            var explorerAddressNodes = modalNode ? qa('[data-admin-crypto-accept-address]', modalNode) : [];
            var explorerPanel = modalNode ? q('[data-admin-crypto-accept-explorer-panel]', modalNode) : null;
            var logoNode = modalNode ? q('[data-admin-crypto-accept-logo]', modalNode) : null;
            var step1Node = modalNode ? q('[data-admin-crypto-accept-step1]', modalNode) : null;
            var step2Node = modalNode ? q('[data-admin-crypto-accept-step2]', modalNode) : null;
            var warningNode = modalNode ? q('[data-admin-crypto-accept-underpaid]', modalNode) : null;
            var cryptoInput = modalNode ? q('[data-admin-crypto-accept-crypto-input]', modalNode) : null;
            var fiatInput = modalNode ? q('[data-admin-crypto-accept-fiat-input]', modalNode) : null;
            var cryptoInputLabel = modalNode ? q('[data-admin-crypto-accept-crypto-input-label]', modalNode) : null;
            var cryptoMismatchNode = modalNode ? q('[data-admin-crypto-accept-crypto-mismatch]', modalNode) : null;
            var cryptoMismatchTextNode = modalNode ? q('[data-admin-crypto-accept-crypto-mismatch-text]', modalNode) : null;
            var cryptoExpectedPrefixNode = modalNode ? q('[data-admin-crypto-accept-crypto-expected-prefix]', modalNode) : null;
            var cryptoExpectedValueNode = modalNode ? q('[data-admin-crypto-accept-crypto-expected-value]', modalNode) : null;
            var submitButton = modalNode ? q('[data-admin-crypto-accept-submit]', modalNode) : null;
            var previewUrl = modalNode ? String(modalNode.getAttribute('data-preview-url') || '').trim() : '';
            var loadingPreviewText = modalNode ? String(modalNode.getAttribute('data-loading-preview-text') || 'Loading recent transactions...') : 'Loading recent transactions...';
            var previewErrorText = modalNode ? String(modalNode.getAttribute('data-preview-error-text') || 'Unable to load recent transactions right now.') : 'Unable to load recent transactions right now.';
            var openExplorerText = modalNode ? String(modalNode.getAttribute('data-open-explorer-text') || 'Check in explorer') : 'Check in explorer';
            var state = {
                button: null,
                form: null,
                ajax: false,
                flyout: null,
                payload: null,
                previewMeta: null,
                fiatManual: false,
                cryptoManual: false,
                submitting: false,
                previewRequestToken: 0
            };

            if (!modalNode || !cryptoInput || !fiatInput || !submitButton) {
                return null;
            }

            function parseNumeric(value) {
                var normalized = String(value == null ? '' : value).replace(',', '.').trim();
                var parsed = parseFloat(normalized);
                return isFinite(parsed) ? parsed : 0;
            }

            function formatCryptoValue(value) {
                var numeric = parseNumeric(value);
                var label;

                if (!isFinite(numeric) || numeric <= 0) {
                    return '0';
                }

                label = numeric.toFixed(8).replace(/\.?0+$/, '');
                return label === '-0' ? '0' : label;
            }

            function formatFiatValue(value) {
                var numeric = parseNumeric(value);
                if (!isFinite(numeric) || numeric <= 0) {
                    return '0.00';
                }
                return numeric.toFixed(2);
            }

            function roundCustomerCryptoFiatCredit(value) {
                var numeric = parseNumeric(value);
                if (!isFinite(numeric) || numeric <= 0) {
                    return 0;
                }
                return Math.round(numeric * 2) / 2;
            }

            function formatRoundedFiatValue(value) {
                return formatFiatValue(roundCustomerCryptoFiatCredit(value));
            }

            function formatFiatLabelValue(value, payload) {
                var amountLabel = formatFiatValue(value);
                var symbol = payload ? String(payload.currency_symbol || '').trim() : '';
                var code = payload ? String(payload.currency_code || '').trim() : '';
                if (symbol) {
                    return amountLabel + ' ' + symbol;
                }
                if (code) {
                    return amountLabel + ' ' + code;
                }
                return amountLabel;
            }

            function ensureHiddenInput(form, name, value) {
                var field = q('input[name="' + name + '"]', form);
                if (!field) {
                    field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = name;
                    form.appendChild(field);
                }
                field.value = String(value == null ? '' : value);
            }

            function clearState() {
                state.button = null;
                state.form = null;
                state.ajax = false;
                state.flyout = null;
                state.payload = null;
                state.previewMeta = null;
                state.fiatManual = false;
                state.cryptoManual = false;
                state.submitting = false;
                state.previewRequestToken += 1;
                submitButton.disabled = false;
                if (cryptoInput) {
                    cryptoInput.classList.remove('admin-crypto-accept-modal__input--mismatch');
                }
                setHidden(cryptoMismatchNode, true);
                if (explorerPanel) {
                    explorerPanel.innerHTML = '<div class="admin-crypto-accept-modal__explorer-loading">' + escapeHtml(loadingPreviewText) + '</div>';
                }
            }

            function hideModal() {
                if (modalInstance) {
                    modalInstance.hide();
                    return;
                }
                setHidden(modalNode, true);
                modalNode.classList.remove('show');
                clearState();
            }

            function showModal() {
                if (modalInstance) {
                    modalInstance.show();
                    return;
                }
                setHidden(modalNode, false);
                modalNode.classList.add('show');
            }

            function fillIdentity(payload) {
                var handle = String(payload.customer_handle || '').trim();
                var email = String(payload.customer_email || '').trim();
                var wallet = String(payload.wallet_label || '').trim();
                var profileUrl = String(payload.customer_url || '').trim();
                var displayFallback = handle || email || '#';

                setHidden(handleLink, true);
                setHidden(handleText, true);

                if (profileUrl && handle !== '') {
                    handleLink.textContent = handle;
                    handleLink.setAttribute('href', profileUrl);
                    setHidden(handleLink, false);
                } else {
                    handleText.textContent = displayFallback;
                    setHidden(handleText, false);
                }

                if (walletLabel) {
                    walletLabel.textContent = wallet;
                    setHidden(walletLabel, false);
                } else {
                    setHidden(walletLabel, true);
                }

                if (emailNode) {
                    emailNode.textContent = email;
                }
            }

            function fillWalletAddress(payload) {
                var address = String(payload.wallet_address_compact || payload.wallet_address || '').trim();
                var explorerUrl = String(payload.explorer_url || '').trim();

                explorerAddressNodes.forEach(function (node) {
                    node.textContent = address;
                });

                if (explorerUrl !== '') {
                    if (explorerLink) {
                        explorerLink.setAttribute('href', explorerUrl);
                        explorerLink.setAttribute('target', '_blank');
                        explorerLink.setAttribute('rel', 'noopener noreferrer');
                    }
                    setHidden(explorerLink, false);
                    setHidden(explorerText, true);
                } else {
                    setHidden(explorerLink, true);
                    setHidden(explorerText, address === '');
                }
            }

            function renderPreviewLoading() {
                if (!explorerPanel) {
                    return;
                }
                explorerPanel.innerHTML = '<div class="admin-crypto-accept-modal__explorer-loading">' + escapeHtml(loadingPreviewText) + '</div>';
            }

            function renderPreviewError(message, explorerUrl) {
                var html = ''
                    + '<div class="admin-crypto-preview admin-crypto-preview--fallback">'
                    + '<div class="admin-crypto-preview__empty">'
                    + '<div class="admin-crypto-preview__empty-title">' + escapeHtml(message || previewErrorText) + '</div>'
                    + (explorerUrl
                        ? '<a href="' + escapeHtml(explorerUrl) + '" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark btn-sm admin-crypto-preview__fallback-link">' + escapeHtml(openExplorerText) + '</a>'
                        : '')
                    + '</div>'
                    + '</div>';

                if (explorerPanel) {
                    explorerPanel.innerHTML = html;
                }
            }

            function loadPreview() {
                var activeForm = state.form;
                var currentPayload = state.payload || {};
                var requestToken;
                var submitData;

                if (!explorerPanel) {
                    return;
                }

                if (!activeForm || !previewUrl) {
                    renderPreviewError(previewErrorText, String(currentPayload.explorer_url || '').trim());
                    return;
                }

                requestToken = state.previewRequestToken + 1;
                state.previewRequestToken = requestToken;
                renderPreviewLoading();

                submitData = new URLSearchParams(new FormData(activeForm));
                submitData.set('admin_crypto_address_preview_ajax', '1');

                fetch(previewUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    credentials: 'same-origin',
                    body: submitData.toString()
                }).then(function (response) {
                    return response.json().catch(function () {
                        return { ok: false, message: previewErrorText };
                    });
                }).then(function (responsePayload) {
                    if (requestToken !== state.previewRequestToken) {
                        return;
                    }
                    if (!responsePayload || !responsePayload.ok || !String(responsePayload.html || '').trim()) {
                        renderPreviewError(
                            responsePayload && responsePayload.message ? responsePayload.message : previewErrorText,
                            String((responsePayload && responsePayload.explorer_url) || currentPayload.explorer_url || '').trim()
                        );
                        return;
                    }
                    state.previewMeta = responsePayload.preview || null;
                    explorerPanel.innerHTML = String(responsePayload.html || '');
                    if (!state.cryptoManual && state.previewMeta && parseNumeric(state.previewMeta.candidate_crypto_amount) > 0) {
                        var candidateCrypto = parseNumeric(state.previewMeta.candidate_crypto_amount);
                        var hasSavedReceivedCrypto = String(currentPayload.received_crypto_amount || '').trim() !== '';
                        if (!hasSavedReceivedCrypto) {
                            cryptoInput.value = formatCryptoValue(candidateCrypto);
                        }
                    }
                    updateComputedFields();
                }).catch(function () {
                    if (requestToken !== state.previewRequestToken) {
                        return;
                    }
                    renderPreviewError(previewErrorText, String(currentPayload.explorer_url || '').trim());
                });
            }

            function updateComputedFields() {
                var payload = state.payload || {};
                var rate = parseNumeric(payload.exchange_rate);
                var assetCode = String(payload.asset_code || payload.asset_name || 'Crypto').trim();
                var currentFiat;
                var requestedFiat;
                var requestedCrypto;
                var previewMeta = state.previewMeta || {};
                var candidateCrypto;
                var candidateFiat;
                var candidateMismatch;
                var expectedLabel;
                var detectedFiatLabel;
                var mismatchBaseText;
                var expectedBaseText;
                var requestedCryptoLabel;
                var requestedFiatLabel;

                if (!state.fiatManual && rate > 0) {
                    fiatInput.value = String(cryptoInput.value).trim() === '' ? '' : formatRoundedFiatValue(parseNumeric(cryptoInput.value) * rate);
                }

                currentFiat = parseNumeric(fiatInput.value);
                requestedFiat = parseNumeric(payload.requested_fiat_amount);
                requestedCrypto = parseNumeric(payload.requested_crypto_amount);
                candidateCrypto = parseNumeric(previewMeta.candidate_crypto_amount);
                candidateFiat = parseNumeric(previewMeta.candidate_fiat_amount);
                candidateMismatch = requestedCrypto > 0 && candidateCrypto > 0 && Math.abs(candidateCrypto - requestedCrypto) > 0.000000005;
                requestedCryptoLabel = String(payload.requested_crypto_label || '').trim();
                requestedFiatLabel = String(payload.requested_fiat_label || '').trim();
                if (requestedCryptoLabel === '') {
                    requestedCryptoLabel = formatCryptoValue(requestedCrypto) + (assetCode ? ' ' + assetCode : '');
                }
                if (requestedFiatLabel === '') {
                    requestedFiatLabel = formatFiatLabelValue(requestedFiat, payload);
                }

                cryptoLabel.textContent = requestedCryptoLabel;
                fiatLabel.textContent = requestedFiatLabel;

                if (timeNode) {
                    timeNode.textContent = String(payload.requested_at_label || '').trim();
                }
                setHidden(timeWrap, String(payload.requested_at_label || '').trim() === '');

                if (cryptoMismatchTextNode) {
                    mismatchBaseText = modalNode.getAttribute('data-crypto-mismatch-text') || 'The detected crypto amount is different than requested.';
                    if (candidateFiat <= 0 && candidateCrypto > 0 && rate > 0) {
                        candidateFiat = candidateCrypto * rate;
                    }
                    detectedFiatLabel = candidateFiat > 0 ? formatFiatLabelValue(roundCustomerCryptoFiatCredit(candidateFiat), payload) : '';
                    cryptoMismatchTextNode.textContent = candidateMismatch && detectedFiatLabel !== ''
                        ? (mismatchBaseText + ' = ' + detectedFiatLabel)
                        : mismatchBaseText;
                }
                if (cryptoExpectedPrefixNode) {
                    expectedBaseText = modalNode.getAttribute('data-crypto-expected-prefix') || 'It should be';
                    cryptoExpectedPrefixNode.textContent = expectedBaseText;
                }
                if (cryptoExpectedValueNode) {
                    expectedLabel = formatCryptoValue(requestedCrypto) + (assetCode ? ' ' + assetCode : '');
                    if (requestedFiat > 0) {
                        expectedLabel += ' = ' + formatFiatLabelValue(requestedFiat, payload);
                    }
                    cryptoExpectedValueNode.textContent = expectedLabel;
                }
                if (cryptoInput) {
                    cryptoInput.classList.toggle('admin-crypto-accept-modal__input--mismatch', candidateMismatch);
                }
                setHidden(cryptoMismatchNode, !candidateMismatch);

                setHidden(
                    warningNode,
                    !((parseInt(payload.order_id, 10) || 0) > 0 && currentFiat > 0 && requestedFiat > 0 && (currentFiat + 0.004) < requestedFiat)
                );
            }

            function populate(payload) {
                var assetCode = String(payload.asset_code || payload.asset_name || 'Crypto').trim();
                var cryptoLabelTemplate = modalNode.getAttribute('data-received-crypto-label') || 'Received {asset}';
                var statusLabel = String(payload.status_label || '').trim();
                var topupText = modalNode.getAttribute('data-topup-badge') || '';
                var receivedCrypto = String(payload.received_crypto_amount || '').trim();
                var receivedFiat = String(payload.received_fiat_amount || '').trim();
                var logoUrl = String(payload.asset_logo_url || '').trim();

                state.payload = payload;
                state.fiatManual = receivedFiat !== '';
                state.cryptoManual = false;

                if (cryptoInputLabel) {
                    cryptoInputLabel.textContent = cryptoLabelTemplate.replace('{asset}', assetCode || 'Crypto');
                }
                if (statusNode) {
                    statusNode.textContent = statusLabel;
                }
                if (topupBadge) {
                    topupBadge.textContent = topupText;
                    setHidden(topupBadge, !payload.is_topup);
                }

                if (logoNode && logoUrl !== '') {
                    logoNode.setAttribute('src', logoUrl);
                    logoNode.setAttribute('alt', assetCode || 'Crypto');
                    setHidden(logoNode, false);
                } else if (logoNode) {
                    logoNode.removeAttribute('src');
                    setHidden(logoNode, true);
                }

                fillIdentity(payload);
                fillWalletAddress(payload);
                loadPreview();

                if (step1Node) {
                    step1Node.textContent = modalNode.getAttribute('data-step1') || step1Node.textContent;
                }
                if (step2Node) {
                    step2Node.textContent = modalNode.getAttribute('data-step2') || step2Node.textContent;
                }

                cryptoInput.value = receivedCrypto;
                fiatInput.value = receivedFiat !== '' ? formatFiatValue(receivedFiat) : '';
                updateComputedFields();
            }

            function parseButtonPayload(button) {
                var raw = button ? String(button.getAttribute('data-admin-crypto-accept-payload') || '').trim() : '';
                if (!raw) {
                    return null;
                }
                try {
                    return JSON.parse(raw);
                } catch (error) {
                    return null;
                }
            }

            function submit() {
                var cryptoValue = formatCryptoValue(cryptoInput.value);
                var fiatValue = formatRoundedFiatValue(fiatInput.value);
                var invalidCryptoText = modalNode.getAttribute('data-invalid-crypto-text') || 'Enter the amount of crypto you actually received.';
                var invalidFiatText = modalNode.getAttribute('data-invalid-fiat-text') || 'Enter the FIAT amount you want to credit.';
                var actionUrl;
                var submitData;
                var activeButton;
                var activeFlyout;
                var activeForm;

                if (!state.form || !state.button || state.submitting) {
                    return;
                }
                if (parseNumeric(cryptoValue) <= 0) {
                    window.alert(invalidCryptoText);
                    cryptoInput.focus();
                    return;
                }
                if (parseNumeric(fiatValue) <= 0) {
                    window.alert(invalidFiatText);
                    fiatInput.focus();
                    return;
                }

                state.submitting = true;
                submitButton.disabled = true;

                if (state.ajax) {
                    activeButton = state.button;
                    activeFlyout = state.flyout;
                    actionUrl = state.form.getAttribute('action') || window.location.href;
                    submitData = new URLSearchParams(new FormData(state.form));
                    submitData.set('admin_payment_quick_action_ajax', '1');
                    submitData.set('received_crypto_amount', cryptoValue);
                    submitData.set('received_fiat_amount', fiatValue);
                    activeButton.disabled = true;

                    fetch(actionUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        credentials: 'same-origin',
                        body: submitData.toString()
                    }).then(function (response) {
                        return response.json().catch(function () {
                            return { ok: false, message: 'Invalid JSON response.' };
                        });
                    }).then(function (payload) {
                        state.submitting = false;
                        submitButton.disabled = false;
                        activeButton.disabled = false;
                        if (!payload || !payload.ok) {
                            window.alert((payload && payload.message) ? payload.message : 'Unable to accept payment.');
                            return;
                        }
                        hideModal();
                        if (activeFlyout) {
                            moveAcceptedPaymentToOrders(activeFlyout, activeButton, payload);
                        }
                        window.location.assign(resolveOrdersRedirectUrl(payload));
                    }).catch(function () {
                        state.submitting = false;
                        submitButton.disabled = false;
                        activeButton.disabled = false;
                        window.alert('Unable to accept payment.');
                    });
                    return;
                }

                activeForm = state.form;
                activeButton = state.button;
                ensureHiddenInput(activeForm, 'received_crypto_amount', cryptoValue);
                ensureHiddenInput(activeForm, 'received_fiat_amount', fiatValue);
                hideModal();

                if (typeof activeForm.requestSubmit === 'function') {
                    activeForm.requestSubmit(activeButton);
                    return;
                }

                if (activeButton.name) {
                    ensureHiddenInput(activeForm, activeButton.name, activeButton.value);
                }
                activeForm.submit();
            }

            cryptoInput.addEventListener('input', function () {
                state.cryptoManual = true;
                updateComputedFields();
            });

            fiatInput.addEventListener('input', function () {
                state.fiatManual = true;
                updateComputedFields();
            });

            submitButton.addEventListener('click', function (event) {
                event.preventDefault();
                submit();
            });

            if (modalNode.addEventListener) {
                modalNode.addEventListener('hidden.bs.modal', function () {
                    clearState();
                });
            }

            return {
                open: function (button, form, options) {
                    var payload = parseButtonPayload(button);
                    if (!payload) {
                        return false;
                    }

                    state.button = button;
                    state.form = form;
                    state.ajax = !!(options && options.ajax);
                    state.flyout = options && options.flyout ? options.flyout : null;
                    state.submitting = false;
                    submitButton.disabled = false;
                    populate(payload);
                    showModal();
                    if (window.matchMedia('(min-width: 992px)').matches) {
                        window.setTimeout(function () {
                            if (state.payload) {
                                cryptoInput.focus();
                                cryptoInput.select();
                            }
                        }, 40);
                    }
                    return true;
                }
            };
        })();

        function closeFlyout(flyout) {
            if (!flyout) {
                return;
            }
            var root = closest(flyout, '[data-admin-topbar-flyout-root]');
            var toggle = root ? q('[data-admin-topbar-flyout-toggle]', root) : null;
            setHidden(flyout, true);
            if (toggle) {
                toggle.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
            if (activeFlyout === flyout) {
                activeFlyout = null;
            }
        }

        function openFlyout(flyout) {
            var root = closest(flyout, '[data-admin-topbar-flyout-root]');
            var toggle = root ? q('[data-admin-topbar-flyout-toggle]', root) : null;
            if (activeFlyout && activeFlyout !== flyout) {
                closeFlyout(activeFlyout);
            }
            setHidden(flyout, false);
            if (toggle) {
                toggle.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
            activeFlyout = flyout;
        }

        qa('[data-admin-topbar-flyout-root]').forEach(function (root) {
            var toggle = q('[data-admin-topbar-flyout-toggle]', root);
            var flyout = q('[data-admin-topbar-flyout]', root);
            var stateUrl = root.getAttribute('data-admin-topbar-state-url') || '';
            var fallbackStateUrls = stateUrl ? [
                stateUrl,
                'topbar_state.php',
                '/admin/topbar_state.php'
            ].filter(function (value, index, array) {
                return value && array.indexOf(value) === index;
            }) : [];
            var stateUrlIndex = 0;
            var pollTimer = 0;
            var pollInFlight = false;
            if (!toggle || !flyout) {
                return;
            }

            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (flyout.hasAttribute('hidden')) {
                    openFlyout(flyout);
                    pollTopbarState(true);
                } else {
                    closeFlyout(flyout);
                }
            });

            qa('[data-admin-topbar-flyout-close]', flyout).forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    closeFlyout(flyout);
                });
            });

            qa('[data-admin-topbar-expand]', flyout).forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    var targetName = button.getAttribute('data-admin-topbar-expand') || '';
                    var target = targetName ? q('[data-admin-topbar-expand-target="' + targetName + '"]', flyout) : null;
                    var expanded = button.getAttribute('aria-expanded') === 'true';
                    if (!target) {
                        return;
                    }

                    setHidden(target, expanded);
                    button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                });
            });

            qa('[data-admin-topbar-payment-submit]', flyout).forEach(function (button) {
                button.addEventListener('click', function (event) {
                    var form = closest(button, 'form');
                    var confirmMessage = button.getAttribute('data-admin-confirm') || '';
                    var hiddenButtonField = null;
                    if (!form) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    if (button.hasAttribute('data-admin-crypto-accept-trigger') && cryptoAcceptModalController) {
                        if (cryptoAcceptModalController.open(button, form, {
                            ajax: button.hasAttribute('data-admin-topbar-payment-accept-ajax'),
                            flyout: flyout
                        })) {
                            return;
                        }
                    }

                    if (confirmMessage && !window.confirm(confirmMessage)) {
                        return;
                    }

                    if (button.hasAttribute('data-admin-topbar-payment-accept-ajax')) {
                        var actionUrl = form.getAttribute('action') || window.location.href;
                        var submitData = new URLSearchParams(new FormData(form));
                        submitData.set('admin_payment_quick_action_ajax', '1');
                        button.disabled = true;

                        fetch(actionUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            credentials: 'same-origin',
                            body: submitData.toString()
                        }).then(function (response) {
                            return response.json().catch(function () {
                                return { ok: false, message: 'Invalid JSON response.' };
                            });
                        }).then(function (payload) {
                            button.disabled = false;
                            if (!payload || !payload.ok) {
                                window.alert((payload && payload.message) ? payload.message : (flyout.getAttribute('data-topbar-accept-error-text') || 'Unable to accept payment.'));
                                return;
                            }
                            moveAcceptedPaymentToOrders(flyout, button, payload);
                            window.location.assign(resolveOrdersRedirectUrl(payload));
                        }).catch(function () {
                            button.disabled = false;
                            window.alert(flyout.getAttribute('data-topbar-accept-error-text') || 'Unable to accept payment.');
                        });
                        return;
                    }

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(button);
                        return;
                    }

                    if (button.name) {
                        hiddenButtonField = document.createElement('input');
                        hiddenButtonField.type = 'hidden';
                        hiddenButtonField.name = button.name;
                        hiddenButtonField.value = button.value;
                        form.appendChild(hiddenButtonField);
                    }

                    form.submit();
                });
            });

            function pollTopbarState(force) {
                var activeStateUrl = fallbackStateUrls[stateUrlIndex] || stateUrl;
                if (!stateUrl || pollInFlight) {
                    return;
                }
                if (!force && doc.hidden) {
                    return;
                }

                pollInFlight = true;
                var controller = window.AbortController ? new window.AbortController() : null;
                var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 20000) : null;
                jsonFetch(activeStateUrl, { credentials: 'same-origin', signal: controller ? controller.signal : undefined }).then(function (payload) {
                    if (payload && payload.__status === 404 && stateUrlIndex < fallbackStateUrls.length - 1) {
                        stateUrlIndex += 1;
                        return;
                    }
                    if (!payload || !payload.ok) {
                        return;
                    }
                    updateToggleBadge(toggle, payload.badge_count);
                    syncTopbarOrderTasks(flyout, payload);
                }).catch(function () {
                    return;
                }).finally(function () {
                    if (timeout !== null) window.clearTimeout(timeout);
                    pollInFlight = false;
                });
            }

            if (stateUrl) {
                pollTopbarState(true);
                pollTimer = window.setInterval(function () {
                    pollTopbarState(false);
                }, 30000);

                if (pollTimer) {
                    doc.addEventListener('visibilitychange', function () {
                        if (!doc.hidden) {
                            pollTopbarState(true);
                        }
                    });
                }
            }
        });

        qa('button[data-admin-crypto-accept-trigger]').forEach(function (button) {
            if (closest(button, '[data-admin-topbar-flyout]')) {
                return;
            }

            button.addEventListener('click', function (event) {
                var form = closest(button, 'form');
                if (!form || !cryptoAcceptModalController) {
                    return;
                }

                if (!cryptoAcceptModalController.open(button, form, {
                    ajax: button.hasAttribute('data-admin-topbar-payment-accept-ajax'),
                    flyout: null
                })) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
            });
        });

        qa('[data-admin-converter-root]').forEach(function (root) {
            var openButtons = qa('[data-admin-converter-open]');
            var modal = q('[data-admin-converter-modal]', root);
            var fiatInput = q('[data-admin-converter-fiat-input]', root);
            var sourceSelect = q('[data-admin-converter-source-select]', root);
            var assetSelect = q('[data-admin-converter-asset-select]', root);
            var result = q('[data-admin-converter-crypto-result]', root);
            var copyFeedback = q('[data-admin-converter-copy-feedback]', root);
            var rateHint = q('[data-admin-converter-rate-hint]', root);
            var fiatSubline = q('[data-admin-converter-fiat-subline]', root);
            var sourceLogo = q('[data-admin-converter-source-logo]', root);
            var assetLogo = q('[data-admin-converter-asset-logo]', root);
            var updatedLine = q('[data-admin-converter-updated-line]', root);
            var refreshButton = q('[data-admin-converter-refresh]', root);
            var assets = [];
            var assetsRaw = root.getAttribute('data-assets') || '[]';
            var currencyCode = root.getAttribute('data-currency-code') || 'USD';
            var currencySymbol = root.getAttribute('data-currency-symbol') || '';
            var currencyIcon = root.getAttribute('data-currency-icon') || currencySymbol || currencyCode.slice(0, 1);
            var csrfNode = q('input[name="_csrf"]');
            var csrfToken = csrfNode ? String(csrfNode.value || '') : '';
            var refreshBusy = false;
            var ratesCheckedAt = 0;
            var copyFeedbackTimer = 0;
            var copyValue = '';
            var chatPanel = q('[data-admin-chat-panel]');
            var chatToggle = q('[data-admin-chat-toggle]');
            var restoreAdminChatAfterClose = false;

            if (!openButtons.length || !modal || !fiatInput || !sourceSelect || !assetSelect || !result || !copyFeedback || !rateHint || !fiatSubline || !sourceLogo || !assetLogo || !updatedLine || !refreshButton) {
                return;
            }

            try {
                assets = JSON.parse(assetsRaw);
            } catch (error) {
                assets = [];
            }

            function closeConverter() {
                setHidden(modal, true);
                hideCopyFeedback();
                if (restoreAdminChatAfterClose && chatPanel && chatPanel.hasAttribute('hidden')) {
                    setHidden(chatPanel, false);
                    if (chatToggle) {
                        chatToggle.classList.add('is-open');
                        chatToggle.setAttribute('aria-expanded', 'true');
                    }
                }
                restoreAdminChatAfterClose = false;
            }

            function openConverter(trigger) {
                if (activeFlyout) {
                    closeFlyout(activeFlyout);
                }
                restoreAdminChatAfterClose = !!(trigger && closest(trigger, '[data-admin-chat-panel], .admin-chat-inbox__conversation-header') && chatPanel && !chatPanel.hasAttribute('hidden'));
                setHidden(modal, false);
                updateConverter();
                if (!refreshBusy && (Date.now() - ratesCheckedAt > 60000)
                    && (assets.length < 2 || assets.some(function (item) { return !!item.is_stale; }))) {
                    ratesCheckedAt = Date.now();
                    refreshButton.click();
                }
            }

            function itemSelectValue(item) {
                if (!item) {
                    return '';
                }
                if (item.key) {
                    return String(item.key);
                }
                if (item.type === 'fiat') {
                    return 'fiat:' + String(item.code || '');
                }
                return 'crypto:' + String(item.id || '');
            }

            function renderAssetLogo(item) {
                if (item && item.type === 'fiat') {
                    return '<span>' + escapeHtml(String(item.symbol || item.code || 'F')) + '</span>';
                }

                var text = item && item.code ? String(item.code).slice(0, 1) : 'C';
                if (item && item.logo_url) {
                    return '<img src="' + escapeHtml(String(item.logo_url)) + '" alt="' + escapeHtml(text) + '">';
                }
                return '<span>' + escapeHtml(text) + '</span>';
            }

            function renderSourceLogo(item) {
                if (item && item.type === 'fiat') {
                    sourceLogo.classList.add('admin-topbar-converter__asset-icon--fiat');
                    sourceLogo.innerHTML = renderAssetLogo(item);
                    return;
                }

                sourceLogo.classList.remove('admin-topbar-converter__asset-icon--fiat');
                sourceLogo.innerHTML = renderAssetLogo(item);
            }

            function renderTargetLogo(item) {
                assetLogo.classList.toggle('admin-topbar-converter__asset-icon--fiat', !!(item && item.type === 'fiat'));
                assetLogo.innerHTML = renderAssetLogo(item);
            }

            function renderUpdatedLine(item) {
                if (!item || !item.code) {
                    updatedLine.textContent = root.getAttribute('data-empty-text') || 'Choose a crypto asset to calculate the amount.';
                    setHidden(refreshButton, true);
                    return;
                }

                updatedLine.textContent = String(item.code) + ' rate updated: ' + String(item.rate_updated_label || '-');
                setHidden(refreshButton, !item.is_stale);
            }

            function formatCryptoDisplay(value, assetCode) {
                var numeric = parseFloat(value || '0');
                var label = '';
                var code = String(assetCode || '').toUpperCase();

                if (!isFinite(numeric) || numeric <= 0) {
                    return '0';
                }

                if (code === 'USDT') {
                    return numeric.toFixed(2).replace(/\.?0+$/, '');
                }

                label = numeric.toFixed(6).replace(/\.?0+$/, '');
                if (label === '0') {
                    label = numeric.toFixed(8).replace(/\.?0+$/, '');
                }
                return label;
            }

            function formatFiatDisplay(value, fiatItem) {
                var numeric = parseFloat(value || '0');
                var formatted = '';
                var displayCode = fiatItem && fiatItem.code ? String(fiatItem.code) : currencyCode;
                var displaySymbol = fiatItem && fiatItem.symbol ? String(fiatItem.symbol) : currencySymbol;

                if (!isFinite(numeric) || numeric < 0) {
                    numeric = 0;
                }

                formatted = numeric.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                if (displaySymbol && displaySymbol !== displayCode) {
                    return String(displaySymbol) + formatted;
                }

                return formatted + ' ' + displayCode;
            }

            function formatItemDisplay(value, item) {
                if (item && item.type === 'fiat') {
                    var numeric = parseFloat(value || '0');
                    if (!isFinite(numeric) || numeric <= 0) {
                        return '0.00';
                    }
                    return numeric.toFixed(2);
                }

                return formatCryptoDisplay(value, item ? item.code : '');
            }

            function findAssetById(assetId) {
                var selectedAsset = null;
                var requestedValue = String(assetId || '');

                assets.forEach(function (item) {
                    var itemValue = itemSelectValue(item);
                    var legacyCryptoMatch = item.type !== 'fiat' && String(item.id) === requestedValue;
                    if (itemValue === requestedValue || legacyCryptoMatch) {
                        selectedAsset = item;
                    }
                });

                return selectedAsset;
            }

            function hideCopyFeedback() {
                window.clearTimeout(copyFeedbackTimer);
                setHidden(copyFeedback, true);
            }

            function showCopyFeedback() {
                setHidden(copyFeedback, false);
                window.clearTimeout(copyFeedbackTimer);
                copyFeedbackTimer = window.setTimeout(function () {
                    setHidden(copyFeedback, true);
                }, 1400);
            }

            function replaceAssets(items) {
                var previousSourceValue = String(sourceSelect.value || '');
                var previousValue = String(assetSelect.value || '');
                var fiatItems = [];
                var cryptoItems = [];
                var optionHtml = '';
                var defaultTarget = '';
                assets = Array.isArray(items) ? items : [];

                assets.forEach(function (item) {
                    if (item && item.type === 'fiat') {
                        fiatItems.push(item);
                    } else {
                        cryptoItems.push(item);
                    }
                });

                optionHtml = fiatItems.concat(cryptoItems).map(function (item) {
                    return '<option value="' + escapeHtml(itemSelectValue(item)) + '">' + escapeHtml(item.code || item.name || 'Asset') + '</option>';
                }).join('');

                sourceSelect.innerHTML = optionHtml;
                assetSelect.innerHTML = optionHtml;

                if (previousSourceValue === 'fiat') {
                    previousSourceValue = 'fiat:' + currencyCode;
                }
                if (previousSourceValue) {
                    var previousSourceItem = findAssetById(previousSourceValue);
                    sourceSelect.value = previousSourceItem ? itemSelectValue(previousSourceItem) : '';
                }
                if (!sourceSelect.value) {
                    sourceSelect.value = 'fiat:' + currencyCode;
                }
                if (previousValue) {
                    var previousTargetItem = findAssetById(previousValue);
                    assetSelect.value = previousTargetItem ? itemSelectValue(previousTargetItem) : '';
                } else {
                    assetSelect.value = '';
                }
                if (!assetSelect.value) {
                    if (cryptoItems.length) {
                        defaultTarget = itemSelectValue(cryptoItems[0]);
                    } else if (fiatItems.length) {
                        defaultTarget = itemSelectValue(fiatItems[0]);
                    }
                    assetSelect.value = defaultTarget;
                }
            }

            function currentSourceAsset() {
                return findAssetById(String(sourceSelect.value || 'fiat:' + currencyCode));
            }

            function updateConverter() {
                var amount = parseFloat(fiatInput.value || '0');
                var sourceAsset = currentSourceAsset();
                var sourceRate = sourceAsset ? parseFloat(sourceAsset.rate || '0') : 0;
                var sourceFiatValue = isFinite(amount) && amount > 0 && sourceRate > 0 ? amount * sourceRate : 0;
                var selectedAsset = findAssetById(assetSelect.value);
                renderSourceLogo(sourceAsset);
                fiatSubline.textContent = formatFiatDisplay(sourceFiatValue);

                if (!selectedAsset) {
                    result.textContent = '0';
                    copyValue = '';
                    hideCopyFeedback();
                    rateHint.textContent = root.getAttribute('data-empty-text') || 'Choose a crypto asset to calculate the amount.';
                    renderTargetLogo(null);
                    renderUpdatedLine(null);
                    return;
                }

                renderTargetLogo(selectedAsset);
                renderUpdatedLine(selectedAsset);

                if (!isFinite(amount) || amount <= 0 || sourceRate <= 0 || !selectedAsset.rate || parseFloat(selectedAsset.rate) <= 0) {
                    result.textContent = '0';
                    copyValue = '';
                    hideCopyFeedback();
                    rateHint.textContent = formatFiatDisplay(0);
                    return;
                }

                copyValue = formatItemDisplay(sourceFiatValue / parseFloat(selectedAsset.rate), selectedAsset);
                result.textContent = copyValue;
                hideCopyFeedback();
                rateHint.textContent = formatFiatDisplay(sourceFiatValue);
            }

            openButtons.forEach(function (openButton) {
                openButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    openConverter(openButton);
                });
            });

            qa('[data-admin-converter-close]', modal).forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    closeConverter();
                });
            });

            fiatInput.addEventListener('input', updateConverter);
            sourceSelect.addEventListener('change', updateConverter);
            assetSelect.addEventListener('change', updateConverter);
            result.addEventListener('click', function () {
                if (!copyValue) {
                    return;
                }

                copyTextWithFallback(copyValue).then(function () {
                    showCopyFeedback();
                });
            });
            result.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    result.click();
                }
            });
            refreshButton.addEventListener('click', function (event) {
                event.preventDefault();
                if (refreshBusy || refreshButton.hasAttribute('hidden')) {
                    return;
                }

                refreshBusy = true;
                refreshButton.disabled = true;
                refreshButton.innerHTML = '<i class="bi bi-arrow-repeat" aria-hidden="true"></i>';

                jsonFetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        admin_refresh_converter_rates_ajax: '1',
                        currency_code: currencyCode,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    refreshBusy = false;
                    refreshButton.disabled = false;
                    refreshButton.innerHTML = '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i>';
                    if (!payload || !payload.ok) {
                        return;
                    }
                    replaceAssets(payload.items || []);
                    updateConverter();
                }).catch(function () {
                    refreshBusy = false;
                    refreshButton.disabled = false;
                    refreshButton.innerHTML = '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i>';
                });
            });
            replaceAssets(assets);
            updateConverter();

            doc.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hasAttribute('hidden')) {
                    closeConverter();
                }
            });
        });

        doc.addEventListener('click', function (event) {
            if (activeFlyout && !closest(event.target, '[data-admin-topbar-flyout-root]')) {
                closeFlyout(activeFlyout);
            }

            qa('[data-admin-converter-modal]').forEach(function (modal) {
                if (!modal.hasAttribute('hidden') && closest(event.target, '[data-admin-topbar-converter__backdrop]')) {
                    setHidden(modal, true);
                }
            });
        });
    }

    function initChat() {
        var root = q('[data-admin-chat-inbox]');
        if (!root) {
            return;
        }

        var toggle = q('[data-admin-chat-toggle]', root);
        var panel = q('[data-admin-chat-panel]', root);
        var listView = q('[data-admin-chat-list-view]', root);
        var conversationView = q('[data-admin-chat-conversation-view]', root);
        var searchInput = q('[data-admin-chat-search-input]', root);
        var searchResults = q('[data-admin-chat-search-results]', root);
        var body = q('[data-admin-chat-conversation-body]', root);
        var title = q('[data-admin-chat-conversation-title]', root);
        var conversationHandle = q('[data-admin-chat-conversation-handle]', root);
        var conversationAvatar = q('[data-admin-chat-conversation-avatar]', root);
        var status = q('[data-admin-chat-conversation-status]', root);
        var conversationBadge = q('[data-admin-chat-conversation-badge]', root);
        var conversationRetention = q('[data-admin-chat-conversation-retention]', root);
        var conversationMeta = q('[data-admin-chat-conversation-meta]', root);
        var conversationMembersCount = q('[data-admin-chat-members-count]', root);
        var conversationMembersToggle = q('[data-admin-chat-members-toggle]', root);
        var conversationMembersPopover = q('[data-admin-chat-members-popover]', root);
        var conversationMembersList = q('[data-admin-chat-members-list]', root);
        var emailNotificationsToggle = q('[data-admin-chat-email-notifications-toggle]', root);
        var retentionInlineSelect = q('[data-admin-chat-retention-select-inline]', root);
        var retentionInlineLabel = retentionInlineSelect ? closest(retentionInlineSelect, 'label') : null;
        var groupRetentionSelect = retentionInlineSelect;
        var composerAlert = q('[data-admin-chat-alert]', root);
        var composerInput = q('[data-admin-chat-input]', root);
        var composerPreview = q('[data-admin-chat-link-preview]', root);
        var composerReplyPreview = q('[data-admin-chat-reply-preview]', root);
        var composerReplySender = q('[data-admin-chat-reply-sender]', root);
        var composerReplyText = q('[data-admin-chat-reply-text]', root);
        var uploadInput = q('[data-admin-chat-file]', root);
        var voiceButton = q('[data-admin-chat-voice-button]', root);
        var voiceStatus = q('[data-admin-chat-voice-status]', root);
        var voiceTimerLabel = q('[data-admin-chat-voice-timer]', root);
        var ordersLinkButton = q('[data-admin-chat-orders-link]', root);
        var headerMoreButton = q('[data-admin-chat-header-more]', root);
        var headerActions = q('[data-admin-chat-header-actions]', root);
        var converterHeaderButton = q('.admin-chat-inbox__conversation-header [data-admin-converter-open]', root);
        var quickRepliesHeaderButton = q('[data-admin-chat-quick-open]', root);
        var cryptoOpenButton = q('[data-admin-chat-crypto-open]', root);
        var cryptoLoader = q('[data-admin-chat-crypto-loader]', root);
        var cryptoTooltip = q('[data-admin-chat-crypto-tooltip]', root);
        var bankOpenButton = q('[data-admin-chat-bank-open]', root);
        var csrfToken = root.getAttribute('data-csrf-token') || '';
        var chatStateUrl = root.getAttribute('data-admin-chat-state-url') || '/admin/chat.php?action=inbox_state';
        var chatInboxFetchInFlight = false;
        var retentionHintTemplate = root.getAttribute('data-chat-group-retention-hint-template') || 'Auto-delete after {label}';
        var activeConversationId = 0;
        var activeCustomerId = 0;
        var activeConversationType = 'live_chat';
        var activeConversationMessageLimit = 10;
        var activeConversationLoadedCount = 0;
        var activeConversationTotalMessages = 0;
        var activeConversationOldestMessageId = 0;
        var activeConversationHasMoreMessages = false;
        var loadingOlderMessages = false;
        var conversationFetchInFlight = false;
        var conversationRequestToken = 0;
        var conversationPollTimer = 0;
        var userBrowsingHistory = false;
        var lastKnownConversationScrollTop = 0;
        var lastRenderedConversationHtml = '';
        var olderMessagesTimer = 0;
        var olderMessagesIndicatorTimer = 0;
        var chatUrl = '/admin/chat.php';
        var quickModal = q('[data-admin-chat-quick-modal]', root);
        var quickList = q('[data-admin-chat-quick-list]', root);
        var groupModal = q('[data-admin-chat-group-modal]', root);
        var groupAlert = q('[data-admin-chat-group-alert]', root);
        var groupNameInput = q('[data-admin-chat-group-name]', root);
        var groupEmailInput = q('[data-admin-chat-group-email]', root);
        var groupSearchResults = q('[data-admin-chat-group-search-results]', root);
        var groupMembers = q('[data-admin-chat-group-members]', root);
        var groupReadonlyInput = q('[data-admin-chat-group-readonly]', root);
        var groupRetentionCreateInput = q('[data-admin-chat-group-retention-create]', root);
        var groupSetupStep = q('[data-admin-chat-group-setup-step]', root);
        var groupInviteStep = q('[data-admin-chat-group-invite-step]', root);
        var groupCreateLead = q('[data-admin-chat-group-create-lead]', root);
        var groupInviteLead = q('[data-admin-chat-group-invite-lead]', root);
        var groupContextField = q('[data-admin-chat-group-context]', root);
        var groupContextTitle = q('[data-admin-chat-group-context-title]', root);
        var groupContextAvatar = q('[data-admin-chat-group-context-avatar]', root);
        var groupAvatarInput = q('[data-admin-chat-group-avatar-file]', root);
        var groupAvatarPreview = q('[data-admin-chat-group-avatar-preview]', root);
        var groupAvatarOpenButton = q('[data-admin-chat-group-avatar-open]', root);
        var groupAvatarClearButton = q('[data-admin-chat-group-avatar-clear]', root);
        var groupOpenCreatedButton = q('[data-admin-chat-group-open-created]', root);
        var groupModalTitle = groupModal ? q('.admin-chat-inbox__quick-header strong', groupModal) : null;
        var groupModalIntro = groupModal ? q('.admin-chat-inbox__quick-header p', groupModal) : null;
        var groupSubmitButton = q('[data-admin-chat-group-submit]', root);
        var groupNameLabel = groupNameInput ? closest(groupNameInput, 'label') : null;
        var groupReadonlyLabel = groupReadonlyInput ? closest(groupReadonlyInput, 'label') : null;
        var groupRetentionCreateLabel = groupRetentionCreateInput ? closest(groupRetentionCreateInput, 'label') : null;
        var groupInviteOpenButton = q('[data-admin-chat-group-invite-open]', root);
        var groupSettingsOpenButton = q('[data-admin-chat-group-settings-open]', root);
        var groupSettingsModal = q('[data-admin-chat-group-settings-modal]', root);
        var groupSettingsAlert = q('[data-admin-chat-group-settings-alert]', root);
        var createCustomerModal = q('[data-admin-chat-create-customer-modal]', root);
        var createCustomerAlert = q('[data-admin-chat-create-customer-alert]', root);
        var createCustomerEmailInput = q('[data-admin-chat-create-customer-email]', root);
        var createCustomerPasswordInput = q('[data-admin-chat-create-customer-password]', root);
        var createCustomerLocaleInput = q('[data-admin-chat-create-customer-locale]', root);
        var createCustomerStatusInput = q('[data-admin-chat-create-customer-status]', root);
        var createCustomerTypeInput = q('[data-admin-chat-create-customer-type]', root);
        var createCustomerSendEmailInput = q('[data-admin-chat-create-customer-send-email]', root);
        var createCustomerProviderInputs = qa('[data-admin-chat-create-customer-provider]', root);
        var readonlyToggle = q('[data-admin-chat-readonly-toggle]', root);
        var leaveGroupButton = q('[data-admin-chat-leave-group]', root);
        var groupInvitesWrap = q('[data-admin-chat-group-invites]', root);
        var groupEmails = [];
        var groupEmailRequests = {};
        var groupMode = 'create';
        var groupModalWasOpenBeforeCreate = false;
        var groupTargetConversationId = 0;
        var groupTargetConversationTitle = '';
        var groupTargetConversationAvatarUrl = '';
        var groupAvatarFile = null;
        var groupAvatarPreviewUrl = '';
        var groupModalDefaultTitle = groupModalTitle ? groupModalTitle.textContent : 'Create group';
        var groupModalDefaultIntro = groupModalIntro ? groupModalIntro.textContent : 'Set the group name, logo and auto-delete first. Then add members and send invitations that expire automatically after 24 hours if they are not accepted.';
        var groupModalDefaultSubmit = groupSubmitButton ? groupSubmitButton.textContent : 'Create group';
        var linkPreviewUrl = '';
        var linkPreviewData = null;
        var linkPreviewDismissedUrl = '';
        var linkPreviewLoading = false;
        var replyToMessageId = 0;
        var linkPreviewRequestId = 0;
        var linkPreviewTimer = 0;
        var messageActionTouchTimer = 0;
        var voiceRecording = false;
        var voiceRecorder = null;
        var voiceStream = null;
        var voiceChunks = [];
        var voiceRecordStartedAt = 0;
        var voiceRecordTimer = 0;
        var voicePendingPointer = false;
        var voiceShouldStopAfterStart = false;
        var voiceStartInFlight = false;
        var voiceUploadInFlight = false;
        var voicePointerMode = false;
        var voiceAudioContext = null;
        var paymentModals = {
            crypto: buildPaymentModalState('crypto'),
            bank: buildPaymentModalState('bank')
        };

        function buildPaymentModalState(type) {
            var modalWrap = q(type === 'crypto' ? '[data-admin-chat-crypto-modal]' : '[data-admin-chat-bank-modal]', root);
            if (!modalWrap) {
                return null;
            }

            return {
                type: type,
                wrap: modalWrap,
                modal: q('[data-admin-chat-payment-modal]', modalWrap),
                info: q('[data-admin-chat-payment-info]', modalWrap),
                preview: q('[data-admin-chat-payment-preview]', modalWrap),
                sendButton: q('[data-admin-chat-payment-send]', modalWrap),
                amountSelect: q('[data-admin-chat-payment-amount]', modalWrap),
                assetWrap: q('[data-admin-chat-asset-wrap]', modalWrap),
                assetSelect: q('[data-admin-chat-payment-asset]', modalWrap),
                productSelect: q('[data-admin-chat-payment-product]', modalWrap),
                bankWrap: q('[data-admin-chat-payment-bank-wrap]', modalWrap),
                bankSelect: q('[data-admin-chat-payment-bank-account]', modalWrap),
                payload: null,
                previewPayload: null
            };
        }

        function showComposerAlert(message, isError) {
            if (!composerAlert) {
                return;
            }
            composerAlert.textContent = message || '';
            composerAlert.classList.toggle('alert-danger', !!isError);
            composerAlert.classList.toggle('alert-success', !isError && !!message);
            setHidden(composerAlert, !message);
        }

        function voiceFeatureEnabled() {
            return String(root.getAttribute('data-chat-voice-enabled') || '0') === '1';
        }

        function voiceConversationSupported() {
            if (activeConversationType === 'live_chat') {
                return activeConversationId > 0;
            }

            if (activeConversationType === 'group_chat') {
                return activeConversationId > 0;
            }

            return false;
        }

        function voiceMaxDurationSeconds() {
            return Math.max(10, parseInt(root.getAttribute('data-chat-voice-max-duration-seconds') || '30', 10) || 30);
        }

        function isDesktopVoiceToggleMode() {
            return !!(window.matchMedia && window.matchMedia('(pointer:fine)').matches);
        }

        function isVoiceBusy() {
            return !!(voiceRecording || voiceStartInFlight || voiceUploadInFlight);
        }

        function voiceBusyMessage() {
            return root.getAttribute('data-chat-voice-busy') || 'Finish the current voice message first.';
        }

        function voiceMinDurationSeconds() {
            return Math.max(2, parseInt(root.getAttribute('data-chat-voice-min-duration-seconds') || '2', 10) || 2);
        }

        function playVoiceCue(kind) {
            var ContextCtor = window.AudioContext || window.webkitAudioContext;
            var context;
            var oscillator;
            var gain;
            var startAt;

            if (!ContextCtor) {
                return;
            }

            try {
                if (!voiceAudioContext) {
                    voiceAudioContext = new ContextCtor();
                }
                context = voiceAudioContext;
                if (context.state === 'suspended' && typeof context.resume === 'function') {
                    context.resume().catch(function () {});
                }
                oscillator = context.createOscillator();
                gain = context.createGain();
                startAt = context.currentTime + 0.01;
                oscillator.type = 'sine';
                if (kind === 'stop') {
                    oscillator.frequency.setValueAtTime(988, startAt);
                    oscillator.frequency.exponentialRampToValueAtTime(740, startAt + 0.1);
                } else {
                    oscillator.frequency.setValueAtTime(740, startAt);
                    oscillator.frequency.exponentialRampToValueAtTime(988, startAt + 0.08);
                }
                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(0.095, startAt + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, startAt + 0.14);
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start(startAt);
                oscillator.stop(startAt + 0.13);
            } catch (error) {
                return;
            }
        }

        function stopVoiceStreamTracks() {
            if (!voiceStream) {
                return;
            }
            try {
                voiceStream.getTracks().forEach(function (track) {
                    track.stop();
                });
            } catch (error) {
                // noop
            }
            voiceStream = null;
        }

        function updateVoiceComposerState() {
            var featureEnabled;
            var mediaSupported;
            var voiceConversationActive;
            var canRecord;
            var label;

            if (!voiceButton) {
                return;
            }

            featureEnabled = voiceFeatureEnabled();
            mediaSupported = !!window.MediaRecorder && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            voiceConversationActive = voiceConversationSupported();
            canRecord = featureEnabled && mediaSupported && voiceConversationActive && !voiceUploadInFlight;

            if (!featureEnabled) {
                label = root.getAttribute('data-chat-voice-disabled-tooltip') || 'Voice message recording is currently disabled.';
            } else if (!voiceConversationActive) {
                label = root.getAttribute('data-chat-voice-live-chat-only') || 'Voice messages are available in Support Chat, groups and 1:1 conversations, but not in Global Chat.';
            } else if (!mediaSupported) {
                label = root.getAttribute('data-chat-voice-unsupported') || 'This browser does not support voice messages.';
            } else {
                label = voiceRecording
                    ? (root.getAttribute('data-chat-voice-stop') || 'Click to stop recording')
                    : (root.getAttribute('data-chat-voice-start') || 'Click to start recording');
            }

            voiceButton.classList.toggle('is-disabled', !canRecord && !voiceRecording);
            voiceButton.classList.toggle('is-recording', !!voiceRecording);
            voiceButton.setAttribute('aria-disabled', !canRecord && !voiceRecording ? 'true' : 'false');
            voiceButton.setAttribute('title', label);
            voiceButton.setAttribute('aria-label', label);
            updateVoiceButtonIcon();
        }

        function updateVoiceButtonIcon() {
            var icon;

            if (!voiceButton) {
                return;
            }

            icon = voiceButton.querySelector('i');
            if (!icon) {
                return;
            }

            icon.classList.remove('bi-mic-fill', 'bi-stop-fill', 'bi-stop-circle-fill');
            if (voiceRecording) {
                icon.classList.add('bi-stop-circle-fill');
                return;
            }

            icon.classList.add('bi-mic-fill');
        }

        function resetVoiceRecordingState() {
            window.clearInterval(voiceRecordTimer);
            voiceRecordTimer = 0;
            stopVoiceStreamTracks();
            voiceRecorder = null;
            voiceRecording = false;
            voiceStartInFlight = false;
            voicePendingPointer = false;
            voiceShouldStopAfterStart = false;
            voicePointerMode = false;
            voiceRecordStartedAt = 0;
            voiceChunks = [];
            if (voiceStatus) {
                setHidden(voiceStatus, true);
            }
            if (voiceTimerLabel) {
                voiceTimerLabel.textContent = '0:00';
            }
            updateVoiceComposerState();
        }

        function updateVoiceTimer() {
            var elapsedSeconds = 0;
            var maxDurationSeconds = voiceMaxDurationSeconds();

            if (voiceRecordStartedAt > 0) {
                elapsedSeconds = Math.max(0, Math.floor((Date.now() - voiceRecordStartedAt) / 1000));
            }

            if (voiceTimerLabel) {
                voiceTimerLabel.textContent = Math.floor(elapsedSeconds / 60) + ':' + String(elapsedSeconds % 60).padStart(2, '0');
            }

            if (elapsedSeconds >= maxDurationSeconds && voiceRecorder && voiceRecorder.state === 'recording') {
                stopVoiceRecording(true);
            }
        }

        function uploadVoiceMessage(blob, fileName, durationSeconds) {
            var formData;

            if (!blob || !activeConversationId || voiceUploadInFlight) {
                return;
            }

            voiceUploadInFlight = true;
            updateVoiceComposerState();
            formData = new FormData();
            formData.append('voice_file', blob, fileName || 'voice-message.webm');
            formData.append('reply_to_message_id', String(replyToMessageId || 0));
            formData.append('audio_duration_seconds', String(durationSeconds || 0));

            postConversation('voice_upload', formData, true).then(function (payload) {
                if (!payload.ok) {
                    voiceUploadInFlight = false;
                    updateVoiceComposerState();
                    showComposerAlert(payload.message || root.getAttribute('data-chat-voice-upload-failed') || 'Unable to upload the voice message.', true);
                    return;
                }
                voiceUploadInFlight = false;
                updateVoiceComposerState();
                clearReplyTarget();
                showComposerAlert('', false);
                renderConversation(payload, { scrollToBottom: true });
            }).catch(function () {
                voiceUploadInFlight = false;
                updateVoiceComposerState();
                showComposerAlert(root.getAttribute('data-chat-voice-upload-failed') || 'Unable to upload the voice message.', true);
            }).finally(function () {
                voiceUploadInFlight = false;
                updateVoiceComposerState();
            });
        }

        function stopVoiceRecording(keepPendingPointer) {
            if (!keepPendingPointer) {
                voicePendingPointer = false;
            }
            if (voiceStartInFlight && !voiceRecording) {
                voiceShouldStopAfterStart = true;
                return false;
            }
            if (!voiceRecorder || voiceRecorder.state !== 'recording') {
                return false;
            }
            playVoiceCue('stop');
            voiceRecorder.stop();
            return false;
        }

        function startVoiceRecording() {
            var preferredMimeTypes;
            var recorderOptions = {};
            var mimeType = '';

            if (!voiceFeatureEnabled()) {
                showComposerAlert(root.getAttribute('data-chat-voice-disabled-tooltip') || 'Voice message recording is currently disabled.', true);
                return false;
            }

            if (isVoiceBusy()) {
                showComposerAlert(voiceBusyMessage(), true);
                return false;
            }

            if (!voiceConversationSupported()) {
                showComposerAlert(root.getAttribute('data-chat-voice-live-chat-only') || 'Voice messages are available in Support Chat, groups and 1:1 conversations, but not in Global Chat.', true);
                return false;
            }

            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function' || typeof window.MediaRecorder === 'undefined') {
                showComposerAlert(root.getAttribute('data-chat-voice-unsupported') || 'This browser does not support voice messages.', true);
                return false;
            }

            voiceStartInFlight = true;
            voicePendingPointer = true;
            voiceShouldStopAfterStart = false;
            updateVoiceComposerState();

            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                var recorder;
                var stopHandled = false;
                preferredMimeTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg'];

                preferredMimeTypes.some(function (candidate) {
                    if (window.MediaRecorder.isTypeSupported && window.MediaRecorder.isTypeSupported(candidate)) {
                        mimeType = candidate;
                        return true;
                    }
                    return false;
                });
                if (mimeType) {
                    recorderOptions.mimeType = mimeType;
                }

                recorder = new window.MediaRecorder(stream, recorderOptions);
                voiceStream = stream;
                voiceRecorder = recorder;
                voiceChunks = [];
                voiceRecording = true;
                voiceStartInFlight = false;
                voiceRecordStartedAt = Date.now();
                if (voiceStatus) {
                    setHidden(voiceStatus, false);
                }
                updateVoiceTimer();
                playVoiceCue('start');
                window.clearInterval(voiceRecordTimer);
                voiceRecordTimer = window.setInterval(updateVoiceTimer, 250);
                updateVoiceComposerState();
                showComposerAlert('', false);

                recorder.addEventListener('dataavailable', function (event) {
                    if (event.data && event.data.size > 0) {
                        voiceChunks.push(event.data);
                    }
                });
                recorder.addEventListener('stop', function () {
                    var durationMs = Math.max(0, Date.now() - voiceRecordStartedAt);
                    var minDurationSeconds = voiceMinDurationSeconds();
                    var durationSeconds = Math.max(1, Math.round(durationMs / 1000));
                    var blob;
                    var fileName;
                    stopHandled = true;

                    if (!voiceChunks.length) {
                        resetVoiceRecordingState();
                        return;
                    }
                    if (durationMs < (minDurationSeconds * 1000)) {
                        showComposerAlert(root.getAttribute('data-chat-voice-too-short') || 'Hold the recording for at least 2 seconds.', true);
                        resetVoiceRecordingState();
                        return;
                    }

                    blob = new Blob(voiceChunks, { type: recorder.mimeType || 'audio/webm' });
                    fileName = 'voice-message.' + ((blob.type || '').indexOf('ogg') >= 0 ? 'ogg' : ((blob.type || '').indexOf('mp4') >= 0 ? 'm4a' : 'webm'));
                    uploadVoiceMessage(blob, fileName, durationSeconds);
                    resetVoiceRecordingState();
                });
                recorder.addEventListener('error', function () {
                    if (stopHandled || voiceChunks.length > 0 || (voiceRecorder && voiceRecorder.state === 'inactive')) {
                        return;
                    }
                    showComposerAlert(root.getAttribute('data-chat-voice-record-failed') || 'Unable to record the voice message.', true);
                    resetVoiceRecordingState();
                });
                recorder.start();
                if (voiceShouldStopAfterStart) {
                    stopVoiceRecording(false);
                }
            }).catch(function () {
                voiceStartInFlight = false;
                voicePendingPointer = false;
                updateVoiceComposerState();
                showComposerAlert(root.getAttribute('data-chat-voice-permission-denied') || 'Microphone access was denied.', true);
            });

            return false;
        }

        function clearReplyTarget() {
            replyToMessageId = 0;
            if (composerReplySender) {
                composerReplySender.textContent = '';
            }
            if (composerReplyText) {
                composerReplyText.textContent = '';
            }
            if (composerReplyPreview) {
                composerReplyPreview.setAttribute('hidden', 'hidden');
                composerReplyPreview.setAttribute('data-reply-to-message-id', '0');
            }
        }

        function setReplyTarget(messageId, sender, text) {
            var safeMessageId = parseInt(messageId || 0, 10) || 0;
            if (!safeMessageId) {
                clearReplyTarget();
                return;
            }

            replyToMessageId = safeMessageId;
            if (composerReplySender) {
                composerReplySender.textContent = String(sender || '').trim();
            }
            if (composerReplyText) {
                composerReplyText.textContent = String(text || '').trim();
            }
            if (composerReplyPreview) {
                composerReplyPreview.setAttribute('data-reply-to-message-id', String(safeMessageId));
                composerReplyPreview.removeAttribute('hidden');
            }
        }

        function closeMessageActions() {
            qa('[data-admin-chat-message].is-actions-open', body || root).forEach(function (node) {
                node.classList.remove('is-actions-open');
            });
        }

        function captureConversationMessageIds() {
            var ids = {};

            qa('[data-admin-chat-message][data-message-id]', body || root).forEach(function (node) {
                var safeId = String(node.getAttribute('data-message-id') || '').trim();
                if (safeId) {
                    ids[safeId] = true;
                }
            });

            return ids;
        }

        function captureConversationAudioPlayers() {
            var players = {};

            qa('[data-admin-chat-message][data-message-id]', body || root).forEach(function (node) {
                var safeId = String(node.getAttribute('data-message-id') || '').trim();
                var audio = node.querySelector('[data-chat-audio-player]');
                if (!safeId || !audio || !audio.currentSrc) {
                    return;
                }
                players[safeId] = {
                    node: audio,
                    src: audio.currentSrc,
                    currentTime: audio.currentTime || 0,
                    wasPlaying: !audio.paused && !audio.ended
                };
            });

            return players;
        }

        function restoreConversationAudioPlayers(players) {
            if (!players || !body) {
                return;
            }

            Object.keys(players).forEach(function (messageId) {
                var state = players[messageId];
                var messageNode = body.querySelector('[data-admin-chat-message][data-message-id="' + messageId + '"]');
                var replacement;
                if (!state || !state.node || !messageNode) {
                    return;
                }
                replacement = messageNode.querySelector('[data-chat-audio-player]');
                if (!replacement) {
                    return;
                }
                if (replacement.currentSrc && state.src && replacement.currentSrc !== state.src) {
                    return;
                }
                try {
                    state.node.currentTime = state.currentTime || 0;
                } catch (error) {
                    // ignore currentTime restore failures
                }
                replacement.parentNode.replaceChild(state.node, replacement);
                if (state.wasPlaying && typeof state.node.play === 'function') {
                    var playResult = state.node.play();
                    if (playResult && typeof playResult.catch === 'function') {
                        playResult.catch(function () {
                            return undefined;
                        });
                    }
                }
            });
        }

        function prepareConversationAudioPlayers() {
            if (!body) {
                return;
            }

            qa('[data-chat-audio-player]', body).forEach(function (audio) {
                if (!audio || audio.getAttribute('data-audio-prepared') === '1') {
                    return;
                }
                audio.setAttribute('data-audio-prepared', '1');
                audio.preload = 'metadata';
                if (audio.currentSrc) {
                    return;
                }
                if (typeof audio.load === 'function') {
                    try {
                        audio.load();
                    } catch (error) {
                        // ignore audio preload failures
                    }
                }
            });
        }

        function animateConversationMessageEntry(nodes) {
            var items = Array.isArray(nodes) ? nodes.filter(Boolean) : [];

            if (!items.length) {
                return;
            }

            items.forEach(function (node) {
                node.classList.add('is-entering');
            });

            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    items.forEach(function (node) {
                        node.classList.add('is-entering-active');
                    });
                });
            });

            window.setTimeout(function () {
                items.forEach(function (node) {
                    node.classList.remove('is-entering', 'is-entering-active');
                });
            }, 340);
        }

        function animateNewConversationMessages(previousIds, previousConversationId, options) {
            var items = [];
            var previousKeys = previousIds ? Object.keys(previousIds) : [];
            options = options || {};

            if (!previousConversationId || previousConversationId !== activeConversationId || !previousKeys.length || !!options.preservePrependOffset || !body) {
                return;
            }

            qa('[data-admin-chat-message][data-message-id]', body).forEach(function (node) {
                var safeId = String(node.getAttribute('data-message-id') || '').trim();
                var timeAnchor;

                if (!safeId || previousIds[safeId]) {
                    return;
                }

                timeAnchor = node.previousElementSibling;
                if (timeAnchor && timeAnchor.classList && timeAnchor.classList.contains('admin-chat-conversation__time-anchor')) {
                    items.push(timeAnchor);
                }
                items.push(node);
            });

            animateConversationMessageEntry(items);
        }

        function toggleMessageActions(messageId, forceOpen) {
            var safeMessageId = parseInt(messageId || 0, 10) || 0;
            var itemNode;
            var shouldOpen;

            if (!safeMessageId || !body) {
                closeMessageActions();
                return;
            }

            itemNode = q('[data-admin-chat-message][data-message-id="' + String(safeMessageId) + '"]', body);
            if (!itemNode) {
                return;
            }

            shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !itemNode.classList.contains('is-actions-open');
            closeMessageActions();
            if (shouldOpen) {
                itemNode.classList.add('is-actions-open');
            }
        }

        function flashConversationMessage(messageNode) {
            if (!messageNode) {
                return;
            }

            messageNode.classList.add('is-focused');
            window.setTimeout(function () {
                messageNode.classList.remove('is-focused');
            }, 1800);
        }

        function scrollConversationToMessage(messageId, attempt) {
            var safeMessageId = parseInt(messageId || 0, 10) || 0;
            var tries = parseInt(attempt || 0, 10) || 0;
            var messageNode;
            var nextLimit;

            if (!safeMessageId || !body) {
                return;
            }

            messageNode = q('[data-admin-chat-message][data-message-id="' + String(safeMessageId) + '"]', body);
            if (messageNode) {
                body.scrollTo({
                    top: Math.max(0, messageNode.offsetTop - 40),
                    behavior: 'smooth'
                });
                flashConversationMessage(messageNode);
                return;
            }

            if (!activeConversationHasMoreMessages || !activeConversationId || tries >= 6 || conversationFetchInFlight) {
                return;
            }

            nextLimit = Math.max(activeConversationMessageLimit + 15, 10);
            if (activeConversationTotalMessages > 0) {
                nextLimit = Math.min(nextLimit, activeConversationTotalMessages);
            }

            fetchConversation(activeConversationId, {
                messageLimit: nextLimit,
                preservePrependOffset: true,
                scrollToBottom: false,
                silent: true
            });

            window.setTimeout(function () {
                scrollConversationToMessage(safeMessageId, tries + 1);
            }, 180);
        }

        function renderConversationMembers(items) {
            if (!conversationMembersList) {
                return;
            }
            if (!Array.isArray(items) || !items.length) {
                conversationMembersList.innerHTML = '';
                return;
            }

            conversationMembersList.innerHTML = items.map(function (item) {
                var label = escapeHtml(item.display_label || item.email || '');
                var titleText = escapeHtml(item.email || item.display_label || '');
                if (item.profile_url) {
                    return '<a class="admin-chat-inbox__conversation-member" href="' + escapeHtml(item.profile_url) + '" title="' + titleText + '">' + label + '</a>';
                }
                return '<span class="admin-chat-inbox__conversation-member admin-chat-inbox__conversation-member--static" title="' + titleText + '">' + label + '</span>';
            }).join('');
        }

        function closeMembersPopover() {
            if (conversationMembersPopover) {
                setHidden(conversationMembersPopover, true);
            }
            if (conversationMembersToggle) {
                conversationMembersToggle.setAttribute('aria-expanded', 'false');
            }
        }

        function showGroupAlert(message, isError) {
            if (!groupAlert) {
                return;
            }
            groupAlert.textContent = message || '';
            groupAlert.classList.toggle('alert-danger', !!isError);
            groupAlert.classList.toggle('alert-success', !isError && !!message);
            setHidden(groupAlert, !message);
        }

        function setGroupSearchResultsHtml(html, forceVisible) {
            if (!groupSearchResults) {
                return;
            }
            groupSearchResults.innerHTML = html || '';
            setHidden(groupSearchResults, forceVisible ? false : !html);
        }

        function hideGroupSearchResults() {
            setGroupSearchResultsHtml('', false);
        }

        function showCreateCustomerAlert(message, isError) {
            if (!createCustomerAlert) {
                return;
            }
            createCustomerAlert.textContent = message || '';
            createCustomerAlert.classList.toggle('alert-danger', !!isError);
            createCustomerAlert.classList.toggle('alert-success', !isError && !!message);
            setHidden(createCustomerAlert, !message);
        }

        function adminChatStorageAvailable() {
            try {
                return !!window.localStorage;
            } catch (error) {
                return false;
            }
        }

        function adminChatPanelStateKey() {
            return 'admin-chat:panel-open';
        }

        function adminChatConversationStateKey() {
            return 'admin-chat:active-conversation';
        }

        function isAdminChatDocked() {
            return !!(window.matchMedia && window.matchMedia('(min-width: 1280px)').matches);
        }

        function refreshAdminChatViewportHeight() {
            var viewportOffsetTop = 0;
            var viewportHeight = window.visualViewport && window.visualViewport.height
                ? window.visualViewport.height
                : window.innerHeight;
            if (window.visualViewport && typeof window.visualViewport.offsetTop === 'number') {
                viewportOffsetTop = window.visualViewport.offsetTop;
            }

            document.documentElement.style.setProperty('--admin-chat-viewport-height', Math.max(1, Math.round(viewportHeight)) + 'px');
            document.documentElement.style.setProperty('--admin-chat-viewport-offset-top', Math.max(0, Math.round(viewportOffsetTop)) + 'px');
            syncAdminChatBodyLock();
        }

        var adminChatBodyLockedScrollY = 0;

        function shouldLockAdminChatBody() {
            return !!(panel && !panel.hasAttribute('hidden') && window.matchMedia && window.matchMedia('(max-width: 768px)').matches);
        }

        function syncAdminChatBodyLock() {
            var bodyNode = document.body;
            var shouldLock;

            if (!bodyNode) {
                return;
            }

            bodyNode.classList.toggle('admin-chat-docked-open', !!(panel && !panel.hasAttribute('hidden')));
            shouldLock = shouldLockAdminChatBody();
            if (shouldLock) {
                if (!bodyNode.classList.contains('admin-chat-panel-open')) {
                    adminChatBodyLockedScrollY = window.pageYOffset || window.scrollY || 0;
                    bodyNode.style.top = (-adminChatBodyLockedScrollY) + 'px';
                }
                bodyNode.classList.add('admin-chat-panel-open');
                return;
            }

            if (!bodyNode.classList.contains('admin-chat-panel-open')) {
                bodyNode.style.top = '';
                return;
            }

            bodyNode.classList.remove('admin-chat-panel-open');
            bodyNode.style.top = '';
            window.scrollTo(0, adminChatBodyLockedScrollY || 0);
        }

        function scheduleAdminChatViewportRefresh() {
            refreshAdminChatViewportHeight();
            window.setTimeout(refreshAdminChatViewportHeight, 60);
            window.setTimeout(refreshAdminChatViewportHeight, 180);
            window.setTimeout(refreshAdminChatViewportHeight, 320);
        }

        function renderAvatarMarkup(item, extraClass) {
            var classes = ['admin-chat-inbox__avatar'];
            var avatarTheme = item && item.avatar_theme ? item.avatar_theme : 'theme-1';
            var avatarUrl = item && item.avatar_url ? String(item.avatar_url) : '';
            var avatarText = item && item.avatar_text ? String(item.avatar_text) : 'U';

            classes.push(avatarTheme);
            if (extraClass) {
                classes.push(extraClass);
            }

            if (avatarUrl) {
                return '<span class="' + escapeHtml(classes.join(' ')) + '"><img src="' + escapeHtml(avatarUrl) + '" alt="' + escapeHtml(avatarText || 'Avatar') + '"></span>';
            }

            return '<span class="' + escapeHtml(classes.join(' ')) + '">' + escapeHtml(avatarText || 'U') + '</span>';
        }

        function ensureChatToggleBadge() {
            var badge;
            if (!toggle) {
                return null;
            }

            badge = q('.admin-chat-inbox__badge', toggle);
            if (badge) {
                return badge;
            }

            badge = document.createElement('span');
            badge.className = 'admin-chat-inbox__badge';
            toggle.appendChild(badge);
            return badge;
        }

        function updateChatToggleBadge(count) {
            var badge;
            var safeCount = Math.max(0, parseInt(count, 10) || 0);
            if (!toggle) {
                return;
            }

            badge = q('.admin-chat-inbox__badge', toggle);
            if (safeCount <= 0) {
                if (badge) {
                    badge.remove();
                }
                return;
            }

            badge = badge || ensureChatToggleBadge();
            if (!badge) {
                return;
            }

            badge.textContent = String(safeCount);
        }

        function updateChatInboxItems(items) {
            var itemMap = {};
            var orderedIds = [];
            var newLabel = root.getAttribute('data-chat-new-label') || 'Nowe';
            var listNode = q('.admin-chat-inbox__list', root);

            (Array.isArray(items) ? items : []).forEach(function (item) {
                var conversationKey = String(parseInt(item.conversation_id || 0, 10) || 0);
                itemMap[conversationKey] = item;
                orderedIds.push(conversationKey);
            });

            qa('[data-admin-chat-item]', root).forEach(function (itemNode) {
                var conversationId = String(parseInt(itemNode.getAttribute('data-conversation-id') || '0', 10) || 0);
                var payload = itemMap[conversationId] || null;
                var unreadCount = payload ? Math.max(0, parseInt(payload.unread_count || 0, 10) || 0) : 0;
                var titleLine = q('.admin-chat-inbox__item-title-line', itemNode);
                var unreadNode = q('.messenger-inbox__type-badge--new', itemNode);
                var legacyUnreadNode = q('.admin-chat-inbox__unread', itemNode);
                var presenceNode = q('[data-admin-chat-presence-dot]', itemNode);

                itemNode.classList.toggle('is-unread', unreadCount > 0);

                if (legacyUnreadNode) {
                    legacyUnreadNode.remove();
                }

                if (presenceNode && payload && payload.presence) {
                    presenceNode.innerHTML = renderPresenceDot(payload.presence);
                }

                if (unreadCount > 0) {
                    if (!unreadNode && titleLine) {
                        unreadNode = document.createElement('span');
                        unreadNode.className = 'messenger-inbox__type-badge messenger-inbox__type-badge--new';
                        titleLine.appendChild(unreadNode);
                    }
                    if (unreadNode) {
                        unreadNode.textContent = newLabel + ': ' + String(unreadCount);
                    }
                } else if (unreadNode) {
                    unreadNode.remove();
                }
            });

            if (listNode && orderedIds.length) {
                orderedIds.forEach(function (conversationId) {
                    var itemNode = q('[data-admin-chat-item][data-conversation-id="' + conversationId + '"]', listNode);
                    if (itemNode) {
                        listNode.appendChild(itemNode);
                    }
                });
            }
        }

        function pollChatInboxState(force) {
            if (!chatStateUrl || chatInboxFetchInFlight || doc.hidden && !force) {
                return;
            }

            chatInboxFetchInFlight = true;
            var controller = window.AbortController ? new window.AbortController() : null;
            var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 20000) : null;
            jsonFetch(chatStateUrl, { credentials: 'same-origin', signal: controller ? controller.signal : undefined }).then(function (payload) {
                if (!payload || !payload.ok) {
                    return;
                }

                updateChatToggleBadge(payload.badge_count);
                updateChatInboxItems(payload.items);
            }).catch(function () {
                return;
            }).finally(function () {
                if (timeout !== null) window.clearTimeout(timeout);
                chatInboxFetchInFlight = false;
            });
        }

        function saveAdminChatPanelState(isOpen) {
            if (!adminChatStorageAvailable()) {
                return;
            }

            try {
                window.localStorage.setItem(adminChatPanelStateKey(), isOpen ? '1' : '0');
            } catch (error) {
                return;
            }
        }

        function restoreAdminChatPanelState() {
            var defaultOpen = isAdminChatDocked();
            if (!adminChatStorageAvailable()) {
                return defaultOpen;
            }

            try {
                var stored = window.localStorage.getItem(adminChatPanelStateKey());
                if (stored === null) {
                    // Both admin entry URLs share the preference; retain a previous explicit choice.
                    var legacyKeys = [
                        'admin-chat:panel-open:' + String(window.location.pathname || '/admin/'),
                        'admin-chat:panel-open:/admin/',
                        'admin-chat:panel-open:/admin/index.php'
                    ];
                    for (var index = 0; index < legacyKeys.length; index += 1) {
                        stored = window.localStorage.getItem(legacyKeys[index]);
                        if (stored === '0' || stored === '1') {
                            window.localStorage.setItem(adminChatPanelStateKey(), stored);
                            break;
                        }
                    }
                }
                return stored === '0' ? false : (stored === '1' ? true : defaultOpen);
            } catch (error) {
                return defaultOpen;
            }
        }

        function saveAdminChatConversationState(conversationId) {
            if (!adminChatStorageAvailable()) {
                return;
            }

            try {
                if ((parseInt(conversationId, 10) || 0) > 0) {
                    window.localStorage.setItem(adminChatConversationStateKey(), String(parseInt(conversationId, 10) || 0));
                } else {
                    window.localStorage.removeItem(adminChatConversationStateKey());
                }
            } catch (error) {
                return;
            }
        }

        function restoreAdminChatConversationState() {
            if (!adminChatStorageAvailable()) {
                return 0;
            }

            try {
                return parseInt(window.localStorage.getItem(adminChatConversationStateKey())
                    || window.localStorage.getItem('admin-chat:active-conversation:' + String(window.location.pathname || '/admin/')) || '0', 10) || 0;
            } catch (error) {
                return 0;
            }
        }

        function startConversationWithCustomer(customerId, options) {
            var normalizedCustomerId = parseInt(customerId || 0, 10) || 0;
            var requestOptions = options || {};

            if (normalizedCustomerId <= 0) {
                return;
            }

            openPanel(false);
            if (!requestOptions.preserveAlert) {
                showComposerAlert('', false);
            }

            jsonFetch(chatUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: toQuery({
                    action: 'start_conversation',
                    participant_type: 'customer',
                    customer_id: normalizedCustomerId,
                    _csrf: csrfToken
                })
            }).then(function (payload) {
                if (!payload.ok) {
                    showComposerAlert(root.getAttribute('data-chat-start-error') || 'Unable to start conversation.', true);
                    return;
                }
                renderConversation(payload, {
                    scrollToBottom: !Object.prototype.hasOwnProperty.call(requestOptions, 'scrollToBottom') || !!requestOptions.scrollToBottom,
                    animateConversation: !Object.prototype.hasOwnProperty.call(requestOptions, 'animateConversation') || !!requestOptions.animateConversation
                });
            }).catch(function () {
                showComposerAlert(root.getAttribute('data-chat-start-error') || 'Unable to start conversation.', true);
            });
        }

        function getConversationScrollMetrics() {
            if (!body) {
                return null;
            }

            return {
                element: body,
                scrollTop: body.scrollTop,
                scrollHeight: body.scrollHeight,
                clientHeight: body.clientHeight,
                distanceFromBottom: Math.max(0, body.scrollHeight - body.clientHeight - body.scrollTop)
            };
        }

        function restoreConversationScrollPosition(scrollTop) {
            var metrics = getConversationScrollMetrics();
            var safeScrollTop = Math.max(0, parseInt(scrollTop || 0, 10) || 0);
            if (!metrics || !metrics.element) {
                return;
            }

            metrics.element.scrollTop = Math.min(safeScrollTop, Math.max(0, metrics.element.scrollHeight - metrics.element.clientHeight));
            lastKnownConversationScrollTop = metrics.element.scrollTop;
            userBrowsingHistory = Math.max(0, metrics.element.scrollHeight - metrics.element.clientHeight - metrics.element.scrollTop) > 24;
        }

        function bindConversationScrollHandler() {
            if (!body) {
                return;
            }

            body.removeEventListener('scroll', handleConversationScroll);
            body.addEventListener('scroll', handleConversationScroll, { passive: true });
        }

        function handleConversationScroll() {
            var metrics = getConversationScrollMetrics();
            if (!metrics) {
                return;
            }

            lastKnownConversationScrollTop = metrics.scrollTop;
            userBrowsingHistory = metrics.distanceFromBottom > 24;
            if (metrics.scrollTop > 72 && !loadingOlderMessages) {
                window.clearTimeout(olderMessagesTimer);
                window.clearTimeout(olderMessagesIndicatorTimer);
                if (body) {
                    body.classList.remove('is-loading-older');
                }
            }
            maybeLoadOlderMessages();
        }

        function maybeLoadOlderMessages() {
            var metrics = getConversationScrollMetrics();
            var nextLimit;

            if (!activeConversationId || activeConversationType === '' || !body || conversationView && conversationView.hasAttribute('hidden')) {
                return;
            }

            if (!activeConversationHasMoreMessages || loadingOlderMessages || conversationFetchInFlight || isVoiceBusy()) {
                return;
            }

            if (!metrics || metrics.scrollTop > 48) {
                return;
            }

            window.clearTimeout(olderMessagesTimer);
            window.clearTimeout(olderMessagesIndicatorTimer);
            nextLimit = Math.max(activeConversationMessageLimit + 5, 10);
            olderMessagesIndicatorTimer = window.setTimeout(function () {
                if (body && !loadingOlderMessages) {
                    body.classList.add('is-loading-older');
                }
            }, 220);
            olderMessagesTimer = window.setTimeout(function () {
                loadingOlderMessages = true;
                fetchConversation(activeConversationId, {
                    force: true,
                    messageLimit: nextLimit,
                    preservePrependOffset: true,
                    scrollToBottom: false,
                    preserveScroll: true,
                    silent: true
                });
            }, 760);
        }

        function playConversationEntryAnimation() {
            if (!conversationView) {
                return;
            }

            conversationView.classList.remove('is-entering');
            if (conversationView.offsetWidth) {
                conversationView.offsetWidth;
            }
            conversationView.classList.add('is-entering');
            window.setTimeout(function () {
                if (conversationView) {
                    conversationView.classList.remove('is-entering');
                }
            }, 260);
        }

        function pollActiveConversation(force) {
            if (!activeConversationId || !panel || panel.hasAttribute('hidden')) {
                return;
            }

            if (conversationView && conversationView.hasAttribute('hidden')) {
                return;
            }

            if (!force && document.hidden) {
                return;
            }

            if (loadingOlderMessages || conversationFetchInFlight || isVoiceBusy()) {
                return;
            }

            fetchConversation(activeConversationId, {
                silent: true,
                preserveScroll: true,
                messageLimit: activeConversationMessageLimit
            });
        }

        function syncPaymentHeaderActions(payload) {
            var isDirectConversation = activeConversationType === 'live_chat' && activeConversationId > 0 && activeCustomerId > 0;
            var hasPendingCryptoPayment = !!(payload && payload.pending_crypto_payment);

            if (cryptoOpenButton) {
                setHidden(cryptoOpenButton, !isDirectConversation);
                cryptoOpenButton.disabled = false;
                cryptoOpenButton.removeAttribute('data-admin-chat-crypto-disabled');
            }
            if (cryptoLoader) {
                setHidden(cryptoLoader, true);
            }
            if (cryptoTooltip) {
                setHidden(cryptoTooltip, true);
            }
            if (cryptoOpenButton && isDirectConversation && hasPendingCryptoPayment) {
                cryptoOpenButton.disabled = true;
                cryptoOpenButton.setAttribute('data-admin-chat-crypto-disabled', '1');
                if (cryptoTooltip) {
                    cryptoTooltip.textContent = root.getAttribute('data-chat-payment-pending-tooltip') || 'Crypto Payment - Pending...';
                    setHidden(cryptoTooltip, false);
                }
            }

            if (bankOpenButton) {
                setHidden(bankOpenButton, !isDirectConversation);
            }
        }

        function closePaymentModal(type) {
            var modalState = paymentModals[type];
            if (!modalState || !modalState.wrap) {
                return;
            }

            setHidden(modalState.wrap, true);
            modalState.payload = null;
            modalState.previewPayload = null;
            modalState.requestVersion = (modalState.requestVersion || 0) + 1;
            if (modalState.info) {
                modalState.info.textContent = '';
                setHidden(modalState.info, true);
            }
            if (modalState.preview) {
                modalState.preview.innerHTML = '';
                setHidden(modalState.preview, true);
            }
            if (modalState.sendButton) {
                modalState.sendButton.disabled = false;
            }
        }

        function closeAllPaymentModals() {
            closePaymentModal('crypto');
            closePaymentModal('bank');
        }

        function renderPaymentStateHtml(message, isError) {
            return '<div class="admin-chat-payment-modal__state' + (isError ? ' is-error' : '') + '">' + escapeHtml(message) + '</div>';
        }

        function setPaymentInfo(modalState, message) {
            if (!modalState || !modalState.info) {
                return;
            }

            modalState.info.textContent = message || '';
            setHidden(modalState.info, !message);
        }

        function setPaymentPreview(modalState, html) {
            if (!modalState || !modalState.preview) {
                return;
            }

            modalState.preview.innerHTML = html || '';
            setHidden(modalState.preview, !html);
        }

        function ensureSelectHasValue(select, desiredValue, fallbackValue) {
            var values;
            var normalizedDesired;
            var normalizedFallback;

            if (!select) {
                return;
            }

            normalizedDesired = String(desiredValue || '');
            normalizedFallback = String(fallbackValue || '');
            values = qa('option', select).map(function (option) {
                return String(option.value || '');
            });

            if (normalizedDesired && values.indexOf(normalizedDesired) !== -1) {
                select.value = normalizedDesired;
                return;
            }

            if (normalizedFallback && values.indexOf(normalizedFallback) !== -1) {
                select.value = normalizedFallback;
                return;
            }

            if (values.length) {
                select.value = values[0];
            }
        }

        function formatAmountLabel(amount, currency) {
            var trimmedAmount = String(amount || '').trim();
            var symbol = currency && currency.symbol ? String(currency.symbol) : '';
            var code = currency && currency.code ? String(currency.code) : '';
            if (!trimmedAmount) {
                return code ? code : '';
            }
            return (symbol && symbol !== code ? symbol : '') + trimmedAmount + (code ? ' ' + code : '');
        }

        function populateAmountOptions(select, options, currency, preferredValue) {
            var amountOptions = Array.isArray(options) ? options : [];
            if (!select) {
                return;
            }

            select.innerHTML = amountOptions.map(function (amount) {
                var amountValue = String(amount || '');
                return '<option value="' + escapeHtml(amountValue) + '">' + escapeHtml(formatAmountLabel(amountValue, currency)) + '</option>';
            }).join('');
            ensureSelectHasValue(select, preferredValue, amountOptions.length ? amountOptions[0] : '');
        }

        function populateCryptoProductOptions(modalState, presets) {
            var defaultLabel;
            var items = Array.isArray(presets) ? presets : [];
            if (!modalState || !modalState.productSelect) {
                return;
            }

            defaultLabel = modalState.productSelect.getAttribute('data-default-label') || 'Custom amount';
            modalState.productSelect.innerHTML = '<option value="">' + escapeHtml(defaultLabel) + '</option>' + items.map(function (item) {
                return '<option value="' + escapeHtml(item.product_id) + '" data-price="' + escapeHtml(item.amount) + '">' + escapeHtml(item.label || '') + '</option>';
            }).join('');
            modalState.productSelect.value = '';
        }

        function syncCryptoProductAmount(modalState) {
            var selectedOption;
            var presetAmount;

            if (!modalState || !modalState.productSelect || !modalState.amountSelect) {
                return;
            }

            selectedOption = modalState.productSelect.options[modalState.productSelect.selectedIndex] || null;
            presetAmount = selectedOption ? String(selectedOption.getAttribute('data-price') || '').trim() : '';
            if (!presetAmount) {
                return;
            }

            ensureSelectHasValue(modalState.amountSelect, presetAmount, modalState.amountSelect.value);
        }

        function populateCryptoAssets(modalState, items) {
            var rows = Array.isArray(items) ? items : [];
            if (!modalState || !modalState.assetSelect) {
                return;
            }

            modalState.assetSelect.innerHTML = rows.map(function (item) {
                var label = (item.name || item.code || 'Crypto') + ' (' + (item.code || '') + ')';
                if (item.rate_label) {
                    label += ' - ' + item.rate_label;
                }
                return '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(label) + '</option>';
            }).join('');
            ensureSelectHasValue(modalState.assetSelect, rows.length ? String(rows[0].id) : '', rows.length ? String(rows[0].id) : '');
        }

        function populateBankAccounts(modalState, assignedAccounts, availableAccounts) {
            var options = [];
            if (!modalState || !modalState.bankSelect) {
                return;
            }

            (Array.isArray(assignedAccounts) ? assignedAccounts : []).forEach(function (item) {
                options.push({
                    value: String(item.bank_account_id || ''),
                    label: (item.label || item.bank_name || 'Bank account') + ' · ' + (item.account_holder_name || '') + (item.iban ? ' · ' + item.iban : ''),
                    isAssigned: true
                });
            });
            (Array.isArray(availableAccounts) ? availableAccounts : []).forEach(function (item) {
                options.push({
                    value: String(item.bank_account_id || ''),
                    label: (item.label || item.bank_name || 'Bank account') + ' · ' + (item.account_holder_name || '') + (item.iban ? ' · ' + item.iban : ''),
                    isAssigned: false
                });
            });

            modalState.bankSelect.innerHTML = options.map(function (item) {
                var prefix = item.isAssigned ? 'Assigned · ' : '';
                return '<option value="' + escapeHtml(item.value) + '">' + escapeHtml(prefix + item.label) + '</option>';
            }).join('');
            ensureSelectHasValue(modalState.bankSelect, options.length ? options[0].value : '', options.length ? options[0].value : '');
            if (modalState.bankWrap) {
                setHidden(modalState.bankWrap, options.length <= 1);
            }
        }

        function refreshPaymentPreview(type) {
            var modalState = paymentModals[type];
            var params;

            if (!modalState || !modalState.payload || !activeConversationId) {
                return;
            }

            var requestVersion = (modalState.requestVersion || 0) + 1;
            modalState.requestVersion = requestVersion;
            modalState.previewPayload = null;
            var requestConversationId = activeConversationId;
            function isCurrentPreview() {
                return modalState.requestVersion === requestVersion && activeConversationId === requestConversationId;
            }
            params = {
                action: 'payment_preview',
                conversation_id: activeConversationId,
                type: type,
                amount: modalState.amountSelect ? modalState.amountSelect.value : ''
            };

            if (type === 'crypto') {
                params.asset_id = modalState.assetSelect ? modalState.assetSelect.value : '';
                if (!params.asset_id || !params.amount) {
                    setPaymentPreview(modalState, renderPaymentStateHtml(root.getAttribute('data-chat-payment-preview-empty') || 'Choose a payment option to see the details.', false));
                    if (modalState.sendButton) {
                        modalState.sendButton.disabled = true;
                    }
                    return;
                }
            } else {
                params.bank_account_id = modalState.bankSelect ? modalState.bankSelect.value : '';
                if (!params.amount) {
                    setPaymentPreview(modalState, renderPaymentStateHtml(root.getAttribute('data-chat-payment-preview-empty') || 'Choose a payment option to see the details.', false));
                    if (modalState.sendButton) {
                        modalState.sendButton.disabled = true;
                    }
                    return;
                }
            }

            setPaymentPreview(modalState, renderPaymentStateHtml(root.getAttribute('data-chat-payment-loading') || 'Loading payment options...', false));
            if (modalState.sendButton) {
                modalState.sendButton.disabled = true;
            }

            jsonFetch(chatUrl + '?' + toQuery(params)).then(function (payload) {
                if (!isCurrentPreview()) return;
                if (!payload.ok) {
                    modalState.previewPayload = null;
                    setPaymentPreview(modalState, payload.preview_html || renderPaymentStateHtml(root.getAttribute('data-chat-payment-preview-error') || 'Unable to prepare payment preview.', true));
                    if (modalState.sendButton) {
                        modalState.sendButton.disabled = true;
                    }
                    return;
                }

                modalState.previewPayload = payload;
                setPaymentPreview(modalState, payload.preview_html || '');
                if (modalState.sendButton) {
                    modalState.sendButton.disabled = !!modalState.sendInFlight;
                }
            }).catch(function () {
                if (!isCurrentPreview()) return;
                modalState.previewPayload = null;
                setPaymentPreview(modalState, renderPaymentStateHtml(root.getAttribute('data-chat-payment-preview-error') || 'Unable to prepare payment preview.', true));
                if (modalState.sendButton) {
                    modalState.sendButton.disabled = true;
                }
            });
        }

        function hydratePaymentModal(type, payload) {
            var modalState = paymentModals[type];
            var infoMessage = '';

            if (!modalState) {
                return;
            }

            modalState.payload = payload;
            modalState.previewPayload = null;

            if (type === 'crypto') {
                populateCryptoProductOptions(modalState, payload.product_presets || []);
                populateCryptoAssets(modalState, payload.items || []);
                populateAmountOptions(modalState.amountSelect, payload.amount_options || [], payload.currency || null, '');

                if (payload.rate_notice && payload.rate_notice.refreshed && payload.rate_notice.label) {
                    infoMessage = (root.getAttribute('data-chat-payment-rates-updated') || 'Crypto rates were updated: {datetime}.').replace('{datetime}', payload.rate_notice.label);
                }
                setPaymentInfo(modalState, infoMessage);

                if (!Array.isArray(payload.items) || !payload.items.length) {
                    setPaymentPreview(modalState, payload.empty_state_html || renderPaymentStateHtml(root.getAttribute('data-chat-payment-preview-empty') || 'Choose a payment option to see the details.', false));
                    if (modalState.sendButton) {
                        modalState.sendButton.disabled = true;
                    }
                    return;
                }

                syncCryptoProductAmount(modalState);
                refreshPaymentPreview(type);
                return;
            }

            populateBankAccounts(modalState, payload.assigned_accounts || [], payload.available_accounts || []);
            populateAmountOptions(modalState.amountSelect, payload.amount_options || [], payload.currency || null, '');
            setPaymentInfo(modalState, '');

            if ((!payload.assigned_accounts || !payload.assigned_accounts.length) && (!payload.available_accounts || !payload.available_accounts.length)) {
                setPaymentPreview(modalState, renderPaymentStateHtml(root.getAttribute('data-chat-payment-preview-error') || 'Unable to prepare payment preview.', true));
                if (modalState.sendButton) {
                    modalState.sendButton.disabled = true;
                }
                return;
            }

            refreshPaymentPreview(type);
        }

        function openPaymentModal(type) {
            var modalState = paymentModals[type];
            if (!modalState || !activeConversationId) {
                return;
            }

            var requestConversationId = activeConversationId;
            modalState.requestVersion = (modalState.requestVersion || 0) + 1;
            var requestVersion = modalState.requestVersion;
            function isCurrentModal() {
                return activeConversationId === requestConversationId && modalState.requestVersion === requestVersion;
            }
            closeQuickModal();
            closePaymentModal(type === 'crypto' ? 'bank' : 'crypto');
            setHidden(modalState.wrap, false);
            setPaymentInfo(modalState, '');
            setPaymentPreview(modalState, renderPaymentStateHtml(root.getAttribute('data-chat-payment-loading') || 'Loading payment options...', false));
            if (modalState.sendButton) {
                modalState.sendButton.disabled = true;
            }

            jsonFetch(chatUrl + '?' + toQuery({
                action: 'payment_modal',
                conversation_id: activeConversationId,
                type: type
            })).then(function (payload) {
                if (!isCurrentModal()) return;
                if (!payload.ok) {
                    setPaymentPreview(modalState, renderPaymentStateHtml(payload.message || root.getAttribute('data-chat-payment-preview-error') || 'Unable to prepare payment preview.', true));
                    return;
                }

                hydratePaymentModal(type, payload);
            }).catch(function () {
                if (!isCurrentModal()) return;
                setPaymentPreview(modalState, renderPaymentStateHtml(root.getAttribute('data-chat-payment-preview-error') || 'Unable to prepare payment preview.', true));
            });
        }

        function submitPaymentRequest(type) {
            var modalState = paymentModals[type];
            var postBody;
            var errorMessage;

            if (!modalState || !activeConversationId || !modalState.sendButton || modalState.sendInFlight) {
                return;
            }

            if (!modalState.previewPayload || !modalState.previewPayload.ok) {
                refreshPaymentPreview(type);
                return;
            }

            modalState.sendInFlight = true;
            var requestConversationId = activeConversationId;
            modalState.sendButton.disabled = true;
            errorMessage = root.getAttribute('data-chat-payment-send-error') || 'Unable to create payment request.';
            postBody = {
                conversation_id: activeConversationId,
                message_limit: activeConversationMessageLimit,
                _csrf: csrfToken
            };

            if (type === 'crypto') {
                postBody.action = 'create_crypto_payment_request';
                postBody.asset_id = modalState.assetSelect ? modalState.assetSelect.value : '';
                postBody.amount = modalState.amountSelect ? modalState.amountSelect.value : '';
                postBody.product_id = modalState.productSelect ? modalState.productSelect.value : '';
            } else {
                postBody.action = 'create_bank_payment_request';
                postBody.amount = modalState.amountSelect ? modalState.amountSelect.value : '';
                postBody.bank_account_id = modalState.bankSelect ? modalState.bankSelect.value : '';
            }

            jsonFetch(chatUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery(postBody)
                }).then(function (payload) {
                if (activeConversationId !== requestConversationId) return;
                var message = payload.message || errorMessage;
                modalState.sendButton.disabled = false;
                if (!payload.ok) {
                    if (message === 'pending_crypto_payment') {
                        message = root.getAttribute('data-chat-payment-pending-error') || message;
                    }
                    setPaymentPreview(modalState, renderPaymentStateHtml(message, true));
                    showComposerAlert(message, true);
                    return;
                    }

                    closePaymentModal(type);
                    showComposerAlert('', false);
                    renderConversation(payload, { scrollToBottom: true, preserveTransientUi: true });
                }).catch(function () {
                if (activeConversationId !== requestConversationId) return;
                modalState.sendButton.disabled = false;
                setPaymentPreview(modalState, renderPaymentStateHtml(errorMessage, true));
                showComposerAlert(errorMessage, true);
            }).then(function () {
                modalState.sendInFlight = false;
            });
        }

        function groupInviteValues() {
            return groupEmails.map(function (entry) {
                if (entry && typeof entry === 'object') {
                    return String(entry.value || '').toLowerCase();
                }

                return String(entry || '').toLowerCase();
            });
        }

        function setGroupTargetConversation(conversationId, title, avatarUrl) {
            groupTargetConversationId = parseInt(conversationId || 0, 10) || 0;
            groupTargetConversationTitle = String(title || '').trim();
            groupTargetConversationAvatarUrl = String(avatarUrl || '').trim();
        }

        function groupContextAvatarHtml() {
            var avatarUrl = String(groupTargetConversationAvatarUrl || groupAvatarPreviewUrl || '').trim();
            var title = String(groupTargetConversationTitle || (groupNameInput ? groupNameInput.value : '') || 'Group').trim();
            var initial = title ? title.charAt(0).toUpperCase() : 'G';

            if (avatarUrl) {
                return '<img src="' + escapeHtml(avatarUrl) + '" alt="">';
            }

            return '<span>' + escapeHtml(initial) + '</span>';
        }

        function renderGroupContextSummary() {
            var title = String(groupTargetConversationTitle || (groupNameInput ? groupNameInput.value : '') || '').trim();

            if (groupContextTitle) {
                groupContextTitle.textContent = title || 'Group';
            }

            if (groupContextAvatar) {
                groupContextAvatar.innerHTML = groupContextAvatarHtml();
            }
        }

        function clearGroupAvatarPreviewUrl() {
            if (groupAvatarPreviewUrl && String(groupAvatarPreviewUrl).indexOf('blob:') === 0 && window.URL && typeof window.URL.revokeObjectURL === 'function') {
                window.URL.revokeObjectURL(groupAvatarPreviewUrl);
            }
            groupAvatarPreviewUrl = '';
        }

        function renderGroupAvatarPreview(previewUrl) {
            var safeUrl = String(previewUrl || '').trim();

            if (!groupAvatarPreview) {
                return;
            }

            if (safeUrl) {
                groupAvatarPreview.classList.add('has-image');
                groupAvatarPreview.innerHTML = '<img src="' + escapeHtml(safeUrl) + '" alt="">';
                if (groupAvatarClearButton) {
                    groupAvatarClearButton.style.display = '';
                }
                return;
            }

            groupAvatarPreview.classList.remove('has-image');
            groupAvatarPreview.innerHTML = '<span><i class="bi bi-camera" aria-hidden="true"></i></span>';
            if (groupAvatarClearButton) {
                groupAvatarClearButton.style.display = 'none';
            }
            renderGroupContextSummary();
        }

        function resetGroupAvatar() {
            groupAvatarFile = null;
            clearGroupAvatarPreviewUrl();
            if (groupAvatarInput) {
                groupAvatarInput.value = '';
            }
            renderGroupAvatarPreview('');
        }

        function handleGroupAvatarSelection(fileInput) {
            var files = fileInput && fileInput.files ? fileInput.files : [];
            var file = files && files.length ? files[0] : null;
            var objectUrl = '';

            if (!file) {
                resetGroupAvatar();
                return;
            }

            groupAvatarFile = file;
            clearGroupAvatarPreviewUrl();
            if (window.URL && typeof window.URL.createObjectURL === 'function') {
                objectUrl = window.URL.createObjectURL(file);
                groupAvatarPreviewUrl = objectUrl;
            }

            renderGroupAvatarPreview(objectUrl);
            renderGroupContextSummary();
        }

        function renderGroupMembers() {
            if (!groupMembers) {
                return;
            }
            if (!groupEmails.length) {
                groupMembers.innerHTML = '';
                return;
            }

            groupMembers.innerHTML = groupEmails.map(function (entry, index) {
                var label = entry && typeof entry === 'object'
                    ? String(entry.label || entry.value || '')
                    : String(entry || '');

                return '' +
                    '<button type="button" class="admin-chat-group-modal__member" data-admin-chat-group-remove data-index="' + index + '">' +
                        '<span>' + escapeHtml(label) + '</span>' +
                        '<i class="bi bi-x"></i>' +
                    '</button>';
            }).join('');
        }

        function syncGroupModalMode() {
            var isInviteMode = groupMode === 'invite';

            if (groupModalTitle) {
                groupModalTitle.textContent = isInviteMode
                    ? (root.getAttribute('data-chat-group-invite-members-title') || 'Add members')
                    : groupModalDefaultTitle;
            }

            if (groupModalIntro) {
                groupModalIntro.textContent = isInviteMode
                    ? (root.getAttribute('data-chat-group-invite-members-intro') || 'Add reseller or admin email, or start typing a handle with @, to invite more people to this group.')
                    : groupModalDefaultIntro;
            }

            if (groupSubmitButton) {
                groupSubmitButton.textContent = isInviteMode
                    ? (root.getAttribute('data-chat-group-invite-members-submit') || 'Send invitations')
                    : groupModalDefaultSubmit;
            }

            if (groupSetupStep) {
                setHidden(groupSetupStep, isInviteMode);
            }
            if (groupInviteStep) {
                setHidden(groupInviteStep, !isInviteMode);
            }
            if (groupContextField) {
                setHidden(groupContextField, !isInviteMode);
            }
            if (groupOpenCreatedButton) {
                setHidden(groupOpenCreatedButton, !(isInviteMode && groupTargetConversationId > 0));
            }

            renderGroupContextSummary();
        }

        function normalizeInviteIdentifier(value) {
            var normalized = String(value || '').trim().toLowerCase();

            if (normalized.charAt(0) === '@') {
                normalized = '@' + normalized.slice(1).replace(/[^a-z0-9._-]+/g, '');
            }

            return normalized;
        }

        function isValidInviteIdentifier(value) {
            if (!value) {
                return false;
            }

            if (value.charAt(0) === '@') {
                return /^@[a-z0-9._-]{2,}$/i.test(value);
            }

            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }

        function resetGroupModal() {
            groupEmails = [];
            groupEmailRequests = {};
            groupMode = 'create';
            setGroupTargetConversation(0, '', '');
            if (groupNameInput) {
                groupNameInput.value = '';
            }
            if (groupEmailInput) {
                groupEmailInput.value = '';
            }
            if (groupReadonlyInput) {
                groupReadonlyInput.checked = false;
            }
            if (groupRetentionCreateInput) {
                groupRetentionCreateInput.value = '24h';
            }
            resetGroupAvatar();
            syncGroupModalMode();
            hideGroupSearchResults();
            renderGroupMembers();
            showGroupAlert('', false);
        }

        function showGroupSettingsAlert(message, isError) {
            if (!groupSettingsAlert) {
                return;
            }
            if (!message) {
                setHidden(groupSettingsAlert, true);
                groupSettingsAlert.textContent = '';
                groupSettingsAlert.classList.remove('is-error');
                return;
            }
            groupSettingsAlert.textContent = message;
            groupSettingsAlert.classList.toggle('is-error', !!isError);
            setHidden(groupSettingsAlert, false);
        }

        function resetCreateCustomerModal() {
            if (createCustomerEmailInput) {
                createCustomerEmailInput.value = '';
            }
            if (createCustomerPasswordInput) {
                createCustomerPasswordInput.value = '';
            }
            if (createCustomerLocaleInput) {
                createCustomerLocaleInput.value = 'pl';
            }
            if (createCustomerStatusInput) {
                createCustomerStatusInput.value = 'active';
            }
            if (createCustomerTypeInput) {
                createCustomerTypeInput.value = 'client';
            }
            if (createCustomerSendEmailInput) {
                createCustomerSendEmailInput.checked = true;
            }
            createCustomerProviderInputs.forEach(function (input) {
                input.checked = true;
            });
            showCreateCustomerAlert('', false);
        }

        function openGroupModal() {
            var activeAvatarImage;

            if (!groupModal) {
                return;
            }
            closeCreateCustomerModal();
            if (groupMode === 'invite') {
                activeAvatarImage = conversationAvatar ? q('img', conversationAvatar) : null;
                setGroupTargetConversation(
                    activeConversationId,
                    title ? title.textContent : '',
                    activeAvatarImage ? (activeAvatarImage.getAttribute('src') || '') : ''
                );
            }
            syncGroupModalMode();
            setHidden(groupModal, false);
            window.setTimeout(function () {
                if (groupMode === 'invite' && groupEmailInput) {
                    groupEmailInput.focus();
                } else if (groupNameInput) {
                    groupNameInput.focus();
                }
            }, 20);
        }

        function closeGroupModal() {
            if (!groupModal) {
                return;
            }
            setHidden(groupModal, true);
            resetGroupModal();
        }

        function openGroupSettingsModal() {
            if (!groupSettingsModal || (activeConversationType !== 'group_chat' && activeConversationType !== 'global_group')) {
                return;
            }
            showGroupSettingsAlert('', false);
            setHidden(groupSettingsModal, false);
            window.setTimeout(function () {
                if (groupRetentionSelect) {
                    groupRetentionSelect.focus();
                }
            }, 20);
        }

        function closeGroupSettingsModal() {
            if (!groupSettingsModal) {
                return;
            }
            setHidden(groupSettingsModal, true);
            showGroupSettingsAlert('', false);
        }

        function openCreateCustomerModal() {
            if (!createCustomerModal) {
                return;
            }
            groupModalWasOpenBeforeCreate = !!groupModal && !groupModal.hasAttribute('hidden');
            if (groupModalWasOpenBeforeCreate && groupModal) {
                setHidden(groupModal, true);
            }
            setHidden(createCustomerModal, false);
            window.setTimeout(function () {
                if (createCustomerEmailInput) {
                    createCustomerEmailInput.focus();
                }
            }, 20);
        }

        function closeCreateCustomerModal() {
            if (!createCustomerModal) {
                return;
            }
            setHidden(createCustomerModal, true);
            resetCreateCustomerModal();
            if (groupModalWasOpenBeforeCreate && groupModal) {
                setHidden(groupModal, false);
            }
            groupModalWasOpenBeforeCreate = false;
        }

        function submitCreateCustomer() {
            var submitButton = q('[data-admin-chat-create-customer-submit]', root);
            var formData = new window.FormData();

            if (!createCustomerModal || !submitButton) {
                return;
            }

            submitButton.disabled = true;
            formData.append('action', 'quick_create_customer');
            formData.append('_csrf', csrfToken);
            formData.append('email', createCustomerEmailInput ? createCustomerEmailInput.value.trim() : '');
            formData.append('password', createCustomerPasswordInput ? createCustomerPasswordInput.value : '');
            formData.append('locale_code', createCustomerLocaleInput ? createCustomerLocaleInput.value : 'pl');
            formData.append('status', createCustomerStatusInput ? createCustomerStatusInput.value : 'active');
            formData.append('customer_type', createCustomerTypeInput ? createCustomerTypeInput.value : 'client');
            formData.append('send_password_email', createCustomerSendEmailInput && createCustomerSendEmailInput.checked ? '1' : '0');
            formData.append('provider_visibility_form_present', createCustomerProviderInputs.length ? '1' : '0');
            createCustomerProviderInputs.forEach(function (input) {
                if (input.checked) {
                    formData.append('visible_provider_ids[]', String(input.value || ''));
                }
            });

            jsonFetch(chatUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(function (payload) {
                var successMessage = root.getAttribute('data-chat-create-user-success') || 'User created successfully.';

                submitButton.disabled = false;
                if (!payload || !payload.ok) {
                    showCreateCustomerAlert((payload && payload.message) ? payload.message : (root.getAttribute('data-chat-create-user-error') || 'Unable to create the user.'), true);
                    return;
                }

                if (createCustomerSendEmailInput && createCustomerSendEmailInput.checked) {
                    if (payload.email_notification && payload.email_notification.sent) {
                        successMessage += ' ' + (root.getAttribute('data-chat-create-user-email-sent') || 'Password email has been sent.');
                    } else if (payload.email_notification && (payload.email_notification.failed || payload.email_notification.status === 'failed')) {
                        successMessage += ' ' + ((root.getAttribute('data-chat-create-user-email-failed') || 'Password email could not be queued.')
                            + (payload.email_notification.last_error ? (' ' + String(payload.email_notification.last_error)) : ''));
                    } else {
                        successMessage += ' '
                            + ((payload.email_notification && (payload.email_notification.ok || payload.email_notification.queued))
                                ? (root.getAttribute('data-chat-create-user-email-queued') || 'Password email has been queued.')
                                : (root.getAttribute('data-chat-create-user-email-failed') || 'Password email could not be queued.'));
                    }
                }

                closeCreateCustomerModal();
                if (groupModal && !groupModal.hasAttribute('hidden') && groupEmailInput && payload.email) {
                    groupEmailInput.value = String(payload.email);
                    groupEmailInput.focus();
                    showGroupAlert(root.getAttribute('data-chat-create-user-group-hint') || 'User created. Click Add to include them in the group.', false);
                } else if (payload.conversation_id) {
                    fetchConversation(payload.conversation_id);
                    openPanel(false);
                } else if (searchInput && payload.email) {
                    searchInput.value = String(payload.email);
                    searchInput.dispatchEvent(new window.Event('input', { bubbles: true }));
                    searchInput.focus();
                } else if (searchInput) {
                    searchInput.focus();
                }

                showComposerAlert(successMessage, false);
            }).catch(function () {
                submitButton.disabled = false;
                showCreateCustomerAlert(root.getAttribute('data-chat-create-user-error') || 'Unable to create the user.', true);
            });
        }

        function updateGroupInviteCards(conversationId, removeOnly) {
            var removed = false;
            qa('[data-admin-chat-group-invite-card]', root).forEach(function (card) {
                var actionButton = q('[data-conversation-id]', card);
                var cardConversationId = actionButton ? (parseInt(actionButton.getAttribute('data-conversation-id') || '0', 10) || 0) : 0;
                if (!conversationId || cardConversationId === conversationId) {
                    card.remove();
                    removed = true;
                }
            });

            if (removed && groupInvitesWrap && !q('[data-admin-chat-group-invite-card]', groupInvitesWrap)) {
                groupInvitesWrap.remove();
            }

            if (!removeOnly && groupInvitesWrap && !q('[data-admin-chat-group-invite-card]', groupInvitesWrap)) {
                groupInvitesWrap = null;
            }
        }

        function addGroupEmail(explicitIdentifier) {
            var email = normalizeInviteIdentifier(typeof explicitIdentifier === 'string' ? explicitIdentifier : (groupEmailInput ? groupEmailInput.value : ''));
            var invalidInviteText = root.getAttribute('data-chat-group-invalid-invite') || 'Enter a valid email address or handle starting with @.';
            var duplicateInviteText = root.getAttribute('data-chat-group-duplicate-invite') || 'This invitation is already added.';
            var checkingUserText = root.getAttribute('data-chat-group-checking-user') || 'Checking user...';
            var userNotFoundText = root.getAttribute('data-chat-group-user-not-found') || 'No reseller or admin account was found for this email.';
            var invitePreparedText = root.getAttribute('data-chat-group-invite-prepared') || 'Invitation prepared. It will expire after 24 hours if not accepted.';
            var existingInviteValues = groupInviteValues();
            if (!email) {
                return;
            }
            if (!isValidInviteIdentifier(email)) {
                showGroupAlert(invalidInviteText, true);
                hideGroupSearchResults();
                return;
            }
            if (existingInviteValues.indexOf(email) !== -1) {
                showGroupAlert(duplicateInviteText, true);
                hideGroupSearchResults();
                return;
            }

            if (groupEmailRequests[email]) {
                showGroupAlert(checkingUserText, false);
                return;
            }

            showGroupAlert(checkingUserText, false);
            groupEmailRequests[email] = true;
            var clearPendingGroupEmail = function () {
                delete groupEmailRequests[email];
            };
            jsonFetch(chatUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: toQuery({
                    action: 'validate_group_email',
                    email: email,
                    conversation_id: groupMode === 'invite' ? activeConversationId : 0,
                    _csrf: csrfToken
                })
            }).then(function (payload) {
                if (!payload.ok) {
                    clearPendingGroupEmail();
                    showGroupAlert(payload.message || userNotFoundText, true);
                    return;
                }

                if (groupInviteValues().indexOf(String(payload.email || email).toLowerCase()) !== -1) {
                    clearPendingGroupEmail();
                    showGroupAlert(duplicateInviteText, true);
                    return;
                }

                groupEmails.push({
                    value: String(payload.email || email).toLowerCase(),
                    label: String(payload.display_name || payload.email || email)
                });
                if (groupEmailInput) {
                    groupEmailInput.value = '';
                    groupEmailInput.focus();
                }
                hideGroupSearchResults();
                renderGroupMembers();
                showGroupAlert(invitePreparedText, false);
                clearPendingGroupEmail();
            }).catch(function () {
                showGroupAlert(userNotFoundText, true);
                clearPendingGroupEmail();
            });
        }

        function normalizePreviewUrl(value) {
            var normalized = String(value || '').trim();

            while (normalized && /[.,!?);:\]]$/.test(normalized)) {
                normalized = normalized.slice(0, -1);
            }

            if (!normalized) {
                return '';
            }

            if (/^www\./i.test(normalized)) {
                normalized = 'https://' + normalized;
            }

            if (!/^https?:\/\//i.test(normalized) && /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?:\/.*)?$/i.test(normalized)) {
                normalized = 'https://' + normalized;
            }

            if (!/^https?:\/\//i.test(normalized) || /\s/.test(normalized)) {
                return '';
            }

            return normalized;
        }

        function extractFirstUrl(value) {
            var match = String(value || '').match(/(?:^|[^\w@])((?:https?:\/\/|www\.)[^\s<]+|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?:\/[^\s<]*)?)/i);
            if (!match || !match[1]) {
                return '';
            }

            return normalizePreviewUrl(match[1]);
        }

        function buildPreviewCardHtml(preview) {
            if (!preview || !preview.url) {
                return '';
            }

            return '' +
                '<a href="' + escapeHtml(preview.url) + '" target="_blank" rel="noopener noreferrer" class="chat-link-preview chat-link-preview--composer">' +
                    (preview.image_url ? '<span class="chat-link-preview__media"><img src="' + escapeHtml(preview.image_url) + '" alt="" loading="lazy"></span>' : '') +
                    '<span class="chat-link-preview__content">' +
                        (preview.site_name ? '<span class="chat-link-preview__site">' + escapeHtml(preview.site_name) + '</span>' : '') +
                        (preview.title ? '<span class="chat-link-preview__title">' + escapeHtml(preview.title) + '</span>' : '') +
                        (preview.description ? '<span class="chat-link-preview__description">' + escapeHtml(preview.description) + '</span>' : '') +
                        (preview.display_url ? '<span class="chat-link-preview__url">' + escapeHtml(preview.display_url) + '</span>' : '') +
                    '</span>' +
                '</a>';
        }

        function renderLinkPreview() {
            if (!composerPreview) {
                return;
            }

            if (linkPreviewLoading && linkPreviewUrl) {
                composerPreview.innerHTML = '<div class="chat-link-preview-composer"><div class="chat-link-preview-composer__state">' + escapeHtml(root.getAttribute('data-chat-link-preview-loading') || 'Loading link preview...') + '</div></div>';
                setHidden(composerPreview, false);
                return;
            }

            if (!linkPreviewData || !linkPreviewUrl || linkPreviewDismissedUrl === linkPreviewUrl) {
                composerPreview.innerHTML = '';
                setHidden(composerPreview, true);
                return;
            }

            composerPreview.innerHTML = '' +
                '<div class="chat-link-preview-composer">' +
                    '<button type="button" class="chat-link-preview-composer__remove" data-admin-chat-link-preview-remove>' + escapeHtml(root.getAttribute('data-chat-link-preview-remove') || 'Send without preview') + '</button>' +
                    buildPreviewCardHtml(linkPreviewData) +
                '</div>';
            setHidden(composerPreview, false);
        }

        function resetLinkPreview() {
            window.clearTimeout(linkPreviewTimer);
            linkPreviewTimer = 0;
            linkPreviewUrl = '';
            linkPreviewData = null;
            linkPreviewDismissedUrl = '';
            linkPreviewLoading = false;
            renderLinkPreview();
        }

        function dismissLinkPreview() {
            if (!linkPreviewUrl) {
                return;
            }

            linkPreviewDismissedUrl = linkPreviewUrl;
            linkPreviewLoading = false;
            renderLinkPreview();
        }

        function queueLinkPreview() {
            var message = composerInput ? composerInput.value.trim() : '';
            var detectedUrl = extractFirstUrl(message);
            var requestId;

            window.clearTimeout(linkPreviewTimer);
            linkPreviewTimer = 0;

            if (!activeConversationId || !message || !detectedUrl) {
                linkPreviewUrl = '';
                linkPreviewData = null;
                linkPreviewLoading = false;
                if (!message) {
                    linkPreviewDismissedUrl = '';
                }
                renderLinkPreview();
                return;
            }

            if (linkPreviewDismissedUrl === detectedUrl) {
                linkPreviewUrl = detectedUrl;
                linkPreviewData = null;
                linkPreviewLoading = false;
                renderLinkPreview();
                return;
            }

            if (linkPreviewUrl === detectedUrl && linkPreviewData) {
                linkPreviewLoading = false;
                renderLinkPreview();
                return;
            }

            linkPreviewUrl = detectedUrl;
            linkPreviewData = null;
            linkPreviewLoading = true;
            renderLinkPreview();
            requestId = ++linkPreviewRequestId;

            linkPreviewTimer = window.setTimeout(function () {
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'link_preview',
                        conversation_id: activeConversationId,
                        url: detectedUrl
                    })
                }).then(function (payload) {
                    var currentUrl = extractFirstUrl(composerInput ? composerInput.value.trim() : '');
                    if (requestId !== linkPreviewRequestId || currentUrl !== detectedUrl || linkPreviewDismissedUrl === detectedUrl) {
                        return;
                    }

                    linkPreviewLoading = false;
                    linkPreviewData = payload && payload.ok && payload.preview ? payload.preview : null;
                    renderLinkPreview();
                }).catch(function () {
                    if (requestId !== linkPreviewRequestId) {
                        return;
                    }

                    linkPreviewLoading = false;
                    linkPreviewData = null;
                    renderLinkPreview();
                });
            }, 260);
        }

        function closeConversationHeaderActions() {
            setHidden(headerActions, true);
            if (headerMoreButton) headerMoreButton.setAttribute('aria-expanded', 'false');
        }

        if (headerMoreButton && headerActions) {
            headerMoreButton.addEventListener('click', function () {
                var willOpen = headerActions.hasAttribute('hidden');
                setHidden(headerActions, !willOpen);
                headerMoreButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
            headerActions.addEventListener('click', function (event) {
                if (closest(event.target, '.admin-chat-inbox__header-action')) {
                    closeConversationHeaderActions();
                }
            });
            doc.addEventListener('click', function (event) {
                if (!closest(event.target, '[data-admin-chat-header-more], [data-admin-chat-header-actions]')) {
                    closeConversationHeaderActions();
                }
            });
            doc.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !headerActions.hasAttribute('hidden')) {
                    closeConversationHeaderActions();
                    headerMoreButton.focus();
                    event.preventDefault();
                }
            });
        }

        function openPanel(focusSearch) {
            refreshAdminChatViewportHeight();
            if (panel) {
                setHidden(panel, false);
            }
            if (toggle) {
                toggle.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
            syncAdminChatBodyLock();
            saveAdminChatPanelState(true);
            if (focusSearch && searchInput) {
                window.setTimeout(function () {
                    searchInput.focus();
                }, 30);
            }
        }

        function closePanel() {
            if (isVoiceBusy()) {
                showComposerAlert(voiceBusyMessage(), true);
                return;
            }
            if (panel) {
                setHidden(panel, true);
            }
            if (toggle) {
                toggle.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
            closeConversationHeaderActions();
            saveAdminChatPanelState(false);
            syncAdminChatBodyLock();
            closeQuickModal();
            closeAllPaymentModals();
        }

        function showList() {
            if (isVoiceBusy()) {
                showComposerAlert(voiceBusyMessage(), true);
                return;
            }
            clearReplyTarget();
            closeMessageActions();
            if (listView) {
                setHidden(listView, false);
            }
            if (conversationView) {
                setHidden(conversationView, true);
            }
            activeConversationId = 0;
            activeCustomerId = 0;
            activeConversationType = 'live_chat';
            activeConversationLoadedCount = 0;
            activeConversationTotalMessages = 0;
            activeConversationOldestMessageId = 0;
            activeConversationHasMoreMessages = false;
            loadingOlderMessages = false;
            userBrowsingHistory = false;
            lastKnownConversationScrollTop = 0;
            lastRenderedConversationHtml = '';
            saveAdminChatConversationState(0);
            if (composerInput) {
                composerInput.value = '';
            }
            if (conversationHandle) {
                conversationHandle.textContent = '';
                setHidden(conversationHandle, true);
            }
            if (readonlyToggle) {
                setHidden(readonlyToggle, true);
            }
            if (leaveGroupButton) {
                setHidden(leaveGroupButton, true);
            }
            if (groupInviteOpenButton) {
                setHidden(groupInviteOpenButton, true);
            }
            if (ordersLinkButton) {
                ordersLinkButton.setAttribute('href', '/admin/?page=orders');
                setHidden(ordersLinkButton, true);
            }
            if (converterHeaderButton) {
                setHidden(converterHeaderButton, true);
            }
            if (quickRepliesHeaderButton) {
                setHidden(quickRepliesHeaderButton, true);
            }
            if (groupSettingsOpenButton) {
                setHidden(groupSettingsOpenButton, true);
            }
            if (conversationMeta) {
                setHidden(conversationMeta, true);
            }
            if (conversationMembersCount) {
                conversationMembersCount.textContent = '';
            }
            renderConversationMembers([]);
            if (emailNotificationsToggle) {
                emailNotificationsToggle.checked = true;
            }
            if (retentionInlineSelect) {
                retentionInlineSelect.value = '24h';
            }
            if (retentionInlineLabel) {
                setHidden(retentionInlineLabel, true);
            }
            closeMembersPopover();
            resetLinkPreview();
            closeQuickModal();
            closeGroupSettingsModal();
            closeAllPaymentModals();
            syncPaymentHeaderActions(null);
            if (body) {
                body.classList.remove('is-loading-older');
            }
            updateVoiceComposerState();
        }

        function showConversation() {
            if (listView) {
                setHidden(listView, true);
            }
            if (conversationView) {
                setHidden(conversationView, false);
            }
        }

        function setConversationPositioningState(isPositioning) {
            if (!conversationView) {
                return;
            }

            conversationView.classList.toggle('is-positioning', !!isPositioning);
        }

        function scrollConversationToBottom() {
            if (!body) {
                return;
            }
            body.scrollTop = body.scrollHeight;
            lastKnownConversationScrollTop = body.scrollTop;
            userBrowsingHistory = false;
        }

        function scheduleConversationScrollToBottom() {
            scrollConversationToBottom();
            window.setTimeout(scrollConversationToBottom, 40);
            window.setTimeout(scrollConversationToBottom, 180);
            window.setTimeout(scrollConversationToBottom, 360);
        }

        function renderConversation(payload, options) {
            var previousMetrics = getConversationScrollMetrics();
            var previousConversationId = activeConversationId;
            var previousMessageIds = captureConversationMessageIds();
            var activeAudioPlayers = captureConversationAudioPlayers();
            var previousScrollHeight = previousMetrics ? previousMetrics.scrollHeight : 0;
            var previousScrollTop = previousMetrics ? previousMetrics.scrollTop : 0;
            var keepBottom = false;
            var preservePrependOffset = false;
            var shouldReplaceBody = false;
            var preserveTransientUi = false;
            options = options || {};
            preserveTransientUi = !!options.preserveTransientUi;

            activeConversationId = parseInt(payload.conversation_id || 0, 10) || 0;
            activeCustomerId = parseInt(payload.customer_id || 0, 10) || 0;
            activeConversationType = payload.conversation_type || 'live_chat';
            activeConversationMessageLimit = parseInt(payload.message_limit || activeConversationMessageLimit || 10, 10) || 10;
            activeConversationLoadedCount = parseInt(payload.loaded_message_count || 0, 10) || 0;
            activeConversationTotalMessages = parseInt(payload.total_message_count || 0, 10) || 0;
            activeConversationOldestMessageId = parseInt(payload.oldest_message_id || 0, 10) || 0;
            activeConversationHasMoreMessages = !!payload.has_more_messages;
            saveAdminChatConversationState(activeConversationId);

            if (previousMetrics) {
                keepBottom = !!options.scrollToBottom || (!userBrowsingHistory && previousMetrics.distanceFromBottom <= 48);
            } else {
                keepBottom = !!options.scrollToBottom;
            }

            preservePrependOffset = !!options.preservePrependOffset && !!previousMetrics;
            if (preservePrependOffset) {
                keepBottom = false;
            }

            shouldReplaceBody = !!options.force || String(payload.html || '') !== lastRenderedConversationHtml;
            if (body && shouldReplaceBody) {
                body.innerHTML = payload.html || '';
                lastRenderedConversationHtml = String(payload.html || '');
                restoreConversationAudioPlayers(activeAudioPlayers);
                prepareConversationAudioPlayers();
                closeMessageActions();
            }
            if (body) {
                body.classList.remove('is-loading-older');
            }
            if (title) {
                var primaryTitle = payload.title || title.textContent;
                if (activeConversationType === 'live_chat' && payload.customer_public_handle) {
                    primaryTitle = '@' + String(payload.customer_public_handle);
                }
                title.textContent = primaryTitle;
                title.setAttribute('href', activeConversationType === 'live_chat' && activeCustomerId > 0 ? '/admin/?page=users&customer_id=' + activeCustomerId : '#');
            }
            if (conversationHandle) {
                var handleText = '';
                if (activeConversationType === 'live_chat') {
                    handleText = String(payload.customer_email || '').trim();
                }
                conversationHandle.textContent = handleText;
                setHidden(conversationHandle, handleText === '');
            }
            if (conversationAvatar) {
                conversationAvatar.innerHTML = payload.avatar_html || renderAvatarMarkup({ avatar_text: 'S', avatar_theme: 'theme-6' }, 'admin-chat-inbox__avatar--sm');
            }
            if (conversationBadge) {
                conversationBadge.textContent = root.getAttribute('data-group-chat-badge') || 'Admin group';
                setHidden(conversationBadge, activeConversationType !== 'group_chat');
            }
            if (status) {
                status.innerHTML = renderPresenceDot(payload.presence || null);
            }
            if (readonlyToggle) {
                readonlyToggle.innerHTML = payload.is_group_read_only ? '<i class="bi bi-lock-fill" aria-hidden="true"></i>' : '<i class="bi bi-unlock" aria-hidden="true"></i>';
                readonlyToggle.setAttribute('title', payload.is_group_read_only ? 'Read only ON' : 'Read only OFF');
                readonlyToggle.setAttribute('aria-label', payload.is_group_read_only ? 'Read only ON' : 'Read only OFF');
                setHidden(readonlyToggle, activeConversationType !== 'group_chat');
            }
            if (leaveGroupButton) {
                setHidden(leaveGroupButton, activeConversationType !== 'group_chat');
            }
            if (groupInviteOpenButton) {
                setHidden(groupInviteOpenButton, !(activeConversationType === 'group_chat' && !!payload.can_manage_group));
            }
            if (ordersLinkButton) {
                var ordersTotal = parseInt(payload.orders_total || '0', 10) || 0;
                var showOrdersLink = activeConversationType === 'live_chat' && activeCustomerId > 0 && ordersTotal > 0;
                ordersLinkButton.setAttribute('href', showOrdersLink ? '/admin/?page=orders&customer_id=' + activeCustomerId : '/admin/?page=orders');
                setHidden(ordersLinkButton, !showOrdersLink);
            }
            if (converterHeaderButton) {
                setHidden(converterHeaderButton, activeConversationType !== 'global_group');
            }
            if (quickRepliesHeaderButton) {
                setHidden(quickRepliesHeaderButton, activeConversationType !== 'global_group' && activeConversationType !== 'live_chat');
            }
            if (groupSettingsOpenButton) {
                setHidden(groupSettingsOpenButton, activeConversationType !== 'group_chat' && activeConversationType !== 'global_group');
            }
            if (groupRetentionSelect) {
                groupRetentionSelect.value = payload.retention_hours ? String(payload.retention_hours) : '24h';
            }
            if (conversationMeta) {
                setHidden(conversationMeta, activeConversationType !== 'group_chat');
            }
            if (conversationRetention) {
                var retentionLabel = '';
                if (activeConversationType === 'group_chat' || activeConversationType === 'global_group') {
                    retentionLabel = String(payload.retention_label || '').trim();
                    if (retentionLabel) {
                        retentionLabel = String(retentionHintTemplate || 'Auto-delete after {label}').replace('{label}', retentionLabel);
                    }
                }
                conversationRetention.textContent = retentionLabel;
                setHidden(conversationRetention, retentionLabel === '');
            }
            if (conversationMembersCount) {
                conversationMembersCount.textContent = payload.member_count_label || '';
            }
            renderConversationMembers(Array.isArray(payload.members) ? payload.members : []);
            if (emailNotificationsToggle) {
                emailNotificationsToggle.checked = payload.email_notifications_enabled !== false;
            }
            if (retentionInlineSelect) {
                retentionInlineSelect.value = payload.retention_hours ? String(payload.retention_hours) : '24h';
                retentionInlineSelect.disabled = !(activeConversationType === 'group_chat' || activeConversationType === 'global_group');
            }
            if (retentionInlineLabel) {
                setHidden(retentionInlineLabel, !(activeConversationType === 'group_chat' || activeConversationType === 'global_group'));
            }
            updateVoiceComposerState();
            if (keepBottom && shouldReplaceBody && !preservePrependOffset && !options.preserveScroll) {
                scrollConversationToBottom();
            }
            closeMembersPopover();
            if (composerInput && !preserveTransientUi) {
                composerInput.value = '';
            }
            if (!preserveTransientUi) {
                if (previousConversationId !== activeConversationId) {
                    clearReplyTarget();
                }
                resetLinkPreview();
                closeAllPaymentModals();
            }
            syncPaymentHeaderActions(payload);
            if (previousConversationId !== activeConversationId) closeConversationHeaderActions();
            if (headerMoreButton && headerActions) {
                setHidden(headerMoreButton, !q('.admin-chat-inbox__header-action:not([hidden])', headerActions));
            }
            showConversation();
            // Rendering a late poll/send response must never reopen a dismissed panel.
            if (shouldReplaceBody) {
                bindConversationScrollHandler();
                animateNewConversationMessages(previousMessageIds, previousConversationId, options);
            }
            if (preservePrependOffset) {
                var currentMetrics = getConversationScrollMetrics();
                if (currentMetrics && currentMetrics.element) {
                    currentMetrics.element.scrollTop = Math.max(0, previousScrollTop + (currentMetrics.scrollHeight - previousScrollHeight));
                    lastKnownConversationScrollTop = currentMetrics.element.scrollTop;
                }
            } else if (keepBottom) {
                scheduleConversationScrollToBottom();
            } else if (options.preserveScroll && previousMetrics) {
                restoreConversationScrollPosition(previousScrollTop);
            } else if (!previousMetrics) {
                scheduleConversationScrollToBottom();
            }
            loadingOlderMessages = false;
            if (keepBottom && !preservePrependOffset) {
                window.setTimeout(function () {
                    setConversationPositioningState(false);
                }, 220);
            } else {
                setConversationPositioningState(false);
            }
            if (options.animateConversation) {
                playConversationEntryAnimation();
            }
        }

        function fetchConversation(conversationId, options) {
            var requestOptions = options || {};
            var requestToken;
            var query;

            if (!conversationId) {
                return;
            }

            if (isVoiceBusy()) {
                if (!requestOptions.silent) {
                    showComposerAlert(voiceBusyMessage(), true);
                }
                return;
            }

            if (conversationFetchInFlight && requestOptions.silent) {
                return;
            }

            if (!requestOptions.silent && !Object.prototype.hasOwnProperty.call(requestOptions, 'scrollToBottom')) {
                requestOptions.scrollToBottom = true;
            }

            conversationFetchInFlight = true;
            conversationRequestToken += 1;
            requestToken = conversationRequestToken;
            if (!requestOptions.silent) {
                openPanel(false);
            }
            showComposerAlert('', false);
            if (!requestOptions.silent) {
                setConversationPositioningState(!!requestOptions.scrollToBottom);
                if (body) {
                    body.innerHTML = '<div class="admin-chat-conversation__empty">' + escapeHtml(root.getAttribute('data-loading-conversation') || 'Loading conversation...') + '</div>';
                    body.classList.remove('is-loading-older');
                }
                showConversation();
            }

            query = {
                conversation_id: conversationId,
                message_limit: parseInt(requestOptions.messageLimit || activeConversationMessageLimit || 10, 10) || 10
            };

            jsonFetch(chatUrl + '?' + toQuery(query)).then(function (payload) {
                if (requestToken !== conversationRequestToken) {
                    return;
                }

                if (!payload.ok) {
                    conversationFetchInFlight = false;
                    loadingOlderMessages = false;
                    setConversationPositioningState(false);
                    if (body) {
                        body.classList.remove('is-loading-older');
                    }
                    if (requestOptions.silent) {
                        return;
                    }
                    saveAdminChatConversationState(0);
                    showList();
                    showComposerAlert(root.getAttribute('data-chat-load-error') || 'Unable to load this conversation.', true);
                    return;
                }
                conversationFetchInFlight = false;
                renderConversation(payload, {
                    preservePrependOffset: !!requestOptions.preservePrependOffset,
                    scrollToBottom: !!requestOptions.scrollToBottom,
                    preserveScroll: !!requestOptions.preserveScroll,
                    preserveTransientUi: !!requestOptions.silent,
                    animateConversation: !requestOptions.silent
                });
            }).catch(function () {
                if (requestToken !== conversationRequestToken) {
                    return;
                }
                conversationFetchInFlight = false;
                loadingOlderMessages = false;
                setConversationPositioningState(false);
                if (body) {
                    body.classList.remove('is-loading-older');
                }
                if (requestOptions.silent) {
                    return;
                }
                saveAdminChatConversationState(0);
                showList();
                showComposerAlert(root.getAttribute('data-chat-load-error') || 'Unable to load this conversation.', true);
            });
        }

        function postConversation(action, extra, useFormData) {
            if (!activeConversationId) {
                return Promise.resolve({ ok: false, message: 'Conversation ID is required.' });
            }

            var options = { method: 'POST' };
            if (useFormData) {
                var formData = extra instanceof FormData ? extra : new FormData();
                formData.append('action', action);
                formData.append('conversation_id', String(activeConversationId));
                formData.append('message_limit', String(parseInt(activeConversationMessageLimit || 10, 10) || 10));
                formData.append('_csrf', csrfToken);
                options.body = formData;
            } else {
                var payload = extra || {};
                payload.action = action;
                payload.conversation_id = activeConversationId;
                payload.message_limit = parseInt(activeConversationMessageLimit || 10, 10) || 10;
                payload._csrf = csrfToken;
                options.headers = { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' };
                options.body = toQuery(payload);
            }

            return jsonFetch(chatUrl, options);
        }

        function closeQuickModal() {
            if (quickModal) {
                setHidden(quickModal, true);
            }
            if (quickList) {
                quickList.innerHTML = '';
            }
        }

        function loadQuickReplies() {
            if (!activeConversationId || !quickModal || !quickList) {
                return;
            }

            setHidden(quickModal, false);
            quickList.innerHTML = '<div class="admin-chat-inbox__quick-state">' + escapeHtml(root.getAttribute('data-chat-quick-replies-loading') || 'Loading quick replies...') + '</div>';

            jsonFetch(chatUrl + '?' + toQuery({ action: 'quick_replies', conversation_id: activeConversationId })).then(function (payload) {
                if (!payload.ok) {
                    quickList.innerHTML = '<div class="admin-chat-inbox__quick-state is-error">' + escapeHtml(root.getAttribute('data-chat-quick-replies-error') || 'Unable to load quick replies.') + '</div>';
                    return;
                }

                var items = Array.isArray(payload.items) ? payload.items : [];
                var sendLabel = root.getAttribute('data-chat-quick-replies-send-label') || 'Send';
                var editLabel = root.getAttribute('data-chat-quick-replies-edit-label') || 'Edit';
                var saveLabel = root.getAttribute('data-chat-quick-replies-save-label') || 'Save';
                var savedLabel = root.getAttribute('data-chat-quick-replies-saved-label') || 'Saved';
                if (!items.length) {
                    quickList.innerHTML = '<div class="admin-chat-inbox__quick-state">' + escapeHtml(root.getAttribute('data-chat-quick-replies-empty') || 'No quick replies available.') + '</div>';
                    return;
                }

                quickList.innerHTML = items.map(function (item) {
                    return '' +
                        '<div class="admin-chat-inbox__quick-item" data-quick-reply-id="' + escapeHtml(item.id) + '">' +
                            '<div class="admin-chat-inbox__quick-item-main">' +
                                '<strong>' + escapeHtml(item.title) + '</strong>' +
                                '<p>' + escapeHtml(item.preview || item.message_body || '') + '</p>' +
                                '<div class="admin-chat-inbox__quick-edit-row">' +
                                    '<button type="button" class="admin-chat-inbox__quick-edit-link" data-admin-chat-quick-edit data-quick-reply-id="' + escapeHtml(item.id) + '">' + escapeHtml(editLabel) + '</button>' +
                                    '<span class="admin-chat-inbox__quick-saved" data-admin-chat-quick-saved hidden>' + escapeHtml(savedLabel) + '</span>' +
                                '</div>' +
                                '<div class="admin-chat-inbox__quick-editor" data-admin-chat-quick-editor hidden>' +
                                    '<textarea class="form-control admin-chat-inbox__quick-editor-input" rows="5" data-admin-chat-quick-input>' + escapeHtml(item.message_body || '') + '</textarea>' +
                                    '<div class="admin-chat-inbox__quick-editor-actions">' +
                                        '<button type="button" class="btn btn-dark btn-sm" data-admin-chat-quick-save data-quick-reply-id="' + escapeHtml(item.id) + '"><i class="bi bi-save" aria-hidden="true"></i><span>' + escapeHtml(saveLabel) + '</span></button>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            '<button type="button" class="btn btn-dark btn-sm admin-chat-inbox__quick-send" data-admin-chat-send-quick-reply data-quick-reply-id="' + escapeHtml(item.id) + '" title="' + escapeHtml(sendLabel) + '" aria-label="' + escapeHtml(sendLabel) + '"><i class="bi bi-send" aria-hidden="true"></i></button>' +
                        '</div>';
                }).join('');
            }).catch(function () {
                quickList.innerHTML = '<div class="admin-chat-inbox__quick-state is-error">' + escapeHtml(root.getAttribute('data-chat-quick-replies-error') || 'Unable to load quick replies.') + '</div>';
            });
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                var isOpen = !panel.hasAttribute('hidden');
                if (isOpen) {
                    closePanel();
                } else {
                    openPanel(false);
                    showList();
                }
            });
        }

        if (title) {
            title.addEventListener('click', function () {
                if (activeConversationType === 'live_chat' && activeCustomerId > 0) {
                    closePanel();
                }
            });
        }

        if (restoreAdminChatPanelState()) {
            openPanel(false);
            var restoredConversationId = restoreAdminChatConversationState();
            if (restoredConversationId > 0) {
                fetchConversation(restoredConversationId, { scrollToBottom: true });
            } else {
                showList();
            }
        }

        window.setInterval(function () {
            pollChatInboxState(false);
        }, 20000);
        conversationPollTimer = window.setInterval(function () {
            pollActiveConversation(false);
        }, 10000);
        doc.addEventListener('visibilitychange', function () {
            if (!doc.hidden) {
                pollChatInboxState(true);
                pollActiveConversation(true);
            }
        });
        pollChatInboxState(true);

        refreshAdminChatViewportHeight();
        window.addEventListener('resize', refreshAdminChatViewportHeight);
        if (window.visualViewport && typeof window.visualViewport.addEventListener === 'function') {
            window.visualViewport.addEventListener('resize', refreshAdminChatViewportHeight);
            window.visualViewport.addEventListener('scroll', refreshAdminChatViewportHeight);
        }

        qa('[data-admin-chat-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                openPanel(button.hasAttribute('data-admin-chat-focus-search'));
                showList();
            });
        });

        if (q('[data-admin-chat-group-open]', root)) {
            q('[data-admin-chat-group-open]', root).addEventListener('click', function () {
                groupMode = 'create';
                openGroupModal();
            });
        }

        if (groupInviteOpenButton) {
            groupInviteOpenButton.addEventListener('click', function () {
                groupMode = 'invite';
                openGroupModal();
            });
        }

        if (groupOpenCreatedButton) {
            groupOpenCreatedButton.addEventListener('click', function () {
                var nextConversationId = parseInt(groupTargetConversationId || 0, 10) || 0;
                if (!nextConversationId) {
                    return;
                }

                closeGroupModal();
                fetchConversation(nextConversationId, { scrollToBottom: true, animateConversation: true });
            });
        }

        if (groupSettingsOpenButton) {
            groupSettingsOpenButton.addEventListener('click', function () {
                openGroupSettingsModal();
            });
        }

        if (conversationMembersToggle) {
            conversationMembersToggle.addEventListener('click', function () {
                if (!conversationMembersPopover || conversationMeta && conversationMeta.hasAttribute('hidden')) {
                    return;
                }
                var isHidden = conversationMembersPopover.hasAttribute('hidden');
                setHidden(conversationMembersPopover, !isHidden ? true : false);
                conversationMembersToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            });
        }

        if (q('[data-admin-chat-create-customer-open]', root)) {
            q('[data-admin-chat-create-customer-open]', root).addEventListener('click', function () {
                openCreateCustomerModal();
            });
        }

        qa('[data-admin-chat-group-close]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                closeGroupModal();
            });
        });

        qa('[data-admin-chat-group-settings-close]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                closeGroupSettingsModal();
            });
        });

        qa('[data-admin-chat-create-customer-close]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                closeCreateCustomerModal();
            });
        });

        if (q('[data-admin-chat-group-add]', root)) {
            q('[data-admin-chat-group-add]', root).addEventListener('click', function () {
                addGroupEmail();
            });
        }

        if (groupAvatarOpenButton && groupAvatarInput) {
            groupAvatarOpenButton.addEventListener('click', function () {
                groupAvatarInput.click();
            });
        }

        if (groupAvatarClearButton) {
            groupAvatarClearButton.addEventListener('click', function (event) {
                event.preventDefault();
                resetGroupAvatar();
            });
        }

        if (groupAvatarInput) {
            groupAvatarInput.addEventListener('change', function () {
                handleGroupAvatarSelection(groupAvatarInput);
            });
        }

        if (groupNameInput) {
            groupNameInput.addEventListener('input', function () {
                renderGroupContextSummary();
            });
        }

        qa('[data-admin-chat-open-conversation]').forEach(function (button) {
            button.addEventListener('click', function () {
                var conversationId = parseInt(button.getAttribute('data-conversation-id') || '0', 10) || 0;
                if (conversationId > 0) {
                    fetchConversation(conversationId);
                }
            });
        });

        if (q('[data-admin-chat-close]', root)) {
            q('[data-admin-chat-close]', root).addEventListener('click', function () {
                closePanel();
            });
        }

        if (q('[data-admin-chat-back]', root)) {
            q('[data-admin-chat-back]', root).addEventListener('click', function () {
                showList();
            });
        }

        if (cryptoOpenButton) {
            cryptoOpenButton.addEventListener('click', function () {
                if (cryptoOpenButton.disabled) {
                    return;
                }
                openPaymentModal('crypto');
            });
        }

        if (bankOpenButton) {
            bankOpenButton.addEventListener('click', function () {
                openPaymentModal('bank');
            });
        }

        if (ordersLinkButton) {
            ordersLinkButton.addEventListener('click', function () {
                closePanel();
            });
        }

        qa('[data-admin-chat-crypto-close]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                closePaymentModal('crypto');
            });
        });

        qa('[data-admin-chat-bank-close]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                closePaymentModal('bank');
            });
        });

        Object.keys(paymentModals).forEach(function (key) {
            var modalState = paymentModals[key];
            if (!modalState) {
                return;
            }

            if (modalState.productSelect) {
                modalState.productSelect.addEventListener('change', function () {
                    syncCryptoProductAmount(modalState);
                    refreshPaymentPreview(key);
                });
            }
            if (modalState.assetSelect) {
                modalState.assetSelect.addEventListener('change', function () {
                    refreshPaymentPreview(key);
                });
            }
            if (modalState.bankSelect) {
                modalState.bankSelect.addEventListener('change', function () {
                    refreshPaymentPreview(key);
                });
            }
            if (modalState.amountSelect) {
                modalState.amountSelect.addEventListener('change', function () {
                    refreshPaymentPreview(key);
                });
            }
            if (modalState.sendButton) {
                modalState.sendButton.addEventListener('click', function () {
                    submitPaymentRequest(key);
                });
            }
        });

        if (searchInput && searchResults) {
            var runUserSearch = debounce(function () {
                var query = searchInput.value.trim();
                if (query.length < 2) {
                    searchResults.innerHTML = '';
                    setHidden(searchResults, true);
                    return;
                }

                jsonFetch(chatUrl + '?' + toQuery({ action: 'search_users', q: query })).then(function (payload) {
                    if (!payload.ok) {
                        searchResults.innerHTML = '<div class="admin-chat-inbox__search-empty">' + escapeHtml(root.getAttribute('data-chat-search-error') || 'Search failed.') + '</div>';
                        setHidden(searchResults, false);
                        return;
                    }

                    var items = Array.isArray(payload.items) ? payload.items : [];
                    if (!items.length) {
                        searchResults.innerHTML = '<div class="admin-chat-inbox__search-empty">' + escapeHtml(root.getAttribute('data-chat-search-empty') || 'No users found.') + '</div>';
                        setHidden(searchResults, false);
                        return;
                    }

                    searchResults.innerHTML = items.map(function (item) {
                        return '' +
                            '<button type="button" class="admin-chat-inbox__search-item" data-admin-chat-search-result data-participant-type="' + escapeHtml(item.participant_type || 'customer') + '" data-customer-id="' + escapeHtml(item.customer_id) + '" data-admin-user-id="' + escapeHtml(item.admin_user_id || 0) + '" data-conversation-id="' + escapeHtml(item.conversation_id) + '">' +
                                renderAvatarMarkup(item) +
                                '<span class="admin-chat-inbox__search-copy">' +
                                    '<strong>' + escapeHtml(item.display_name || item.email || '') + '</strong>' +
                                    '<span>' + escapeHtml(item.meta_label || item.email || '') + '</span>' +
                                '</span>' +
                            '</button>';
                    }).join('');
                    setHidden(searchResults, false);
                }).catch(function () {
                    searchResults.innerHTML = '<div class="admin-chat-inbox__search-empty">' + escapeHtml(root.getAttribute('data-chat-search-error') || 'Search failed.') + '</div>';
                    setHidden(searchResults, false);
                });
            }, 250);

            searchInput.addEventListener('input', runUserSearch);
        }

        if (groupEmailInput) {
            var runGroupInviteSearch = debounce(function () {
                var query = groupEmailInput.value.trim();
                var searchError = root.getAttribute('data-chat-search-error') || 'Search failed.';
                var searchEmpty = root.getAttribute('data-chat-search-empty') || 'No users found.';

                if (query.length < 2) {
                    hideGroupSearchResults();
                    return;
                }

                jsonFetch(chatUrl + '?' + toQuery({ action: 'search_users', q: query })).then(function (payload) {
                    var items;

                    if (!payload || !payload.ok) {
                        setGroupSearchResultsHtml('<div class="admin-chat-group-modal__search-empty">' + escapeHtml(searchError) + '</div>', true);
                        return;
                    }

                    items = (Array.isArray(payload.items) ? payload.items : []).filter(function (item) {
                        var identifier = String(item && item.email ? item.email : '').toLowerCase();
                        return identifier && groupInviteValues().indexOf(identifier) === -1;
                    });

                    if (!items.length) {
                        setGroupSearchResultsHtml('<div class="admin-chat-group-modal__search-empty">' + escapeHtml(searchEmpty) + '</div>', true);
                        return;
                    }

                    setGroupSearchResultsHtml(items.map(function (item) {
                        return '' +
                            '<button type="button" class="admin-chat-group-modal__search-item" data-admin-chat-group-search-item data-invite-email="' + escapeHtml(item.email || '') + '" data-invite-label="' + escapeHtml(item.display_name || item.email || '') + '">' +
                                renderAvatarMarkup(item) +
                                '<span class="admin-chat-group-modal__search-copy">' +
                                    '<strong>' + escapeHtml(item.display_name || item.email || '') + '</strong>' +
                                    '<span>' + escapeHtml(item.meta_label || item.email || '') + '</span>' +
                                '</span>' +
                            '</button>';
                    }).join(''), true);
                }).catch(function () {
                    setGroupSearchResultsHtml('<div class="admin-chat-group-modal__search-empty">' + escapeHtml(searchError) + '</div>', true);
                });
            }, 220);

            groupEmailInput.addEventListener('input', runGroupInviteSearch);
            groupEmailInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addGroupEmail();
                }
            });
            groupEmailInput.addEventListener('blur', function () {
                window.setTimeout(function () {
                    hideGroupSearchResults();
                }, 160);
            });
        }

        if (createCustomerEmailInput) {
            createCustomerEmailInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitCreateCustomer();
                }
            });
        }

        if (createCustomerPasswordInput) {
            createCustomerPasswordInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitCreateCustomer();
                }
            });
        }

        if (groupSubmitButton) {
            groupSubmitButton.addEventListener('click', function () {
                var requestAction = groupMode === 'invite' ? 'invite_group_members' : 'create_group';
                var inviteValues = groupInviteValues();
                var formData = new window.FormData();

                formData.append('action', requestAction);
                formData.append('conversation_id', String(groupMode === 'invite' ? (groupTargetConversationId || activeConversationId || 0) : 0));
                formData.append('group_name', groupNameInput ? groupNameInput.value.trim() : '');
                formData.append('participant_emails_json', JSON.stringify(inviteValues));
                formData.append('is_group_read_only', groupReadonlyInput && groupReadonlyInput.checked ? '1' : '0');
                formData.append('retention_value', groupRetentionCreateInput ? groupRetentionCreateInput.value : '24h');
                formData.append('_csrf', csrfToken);

                if (groupMode !== 'invite' && groupAvatarFile) {
                    formData.append('group_avatar_file', groupAvatarFile);
                }

                jsonFetch(chatUrl, {
                    method: 'POST',
                    body: formData
                }).then(function (payload) {
                    if (!payload.ok) {
                        showGroupAlert(payload.message || (groupMode === 'invite'
                            ? (root.getAttribute('data-chat-group-invite-error') || 'Unable to invite members to the group.')
                            : (root.getAttribute('data-chat-group-create-error') || 'Unable to create the group chat.')), true);
                        return;
                    }

                    renderConversation(payload, {
                        preserveScroll: groupMode === 'invite',
                        scrollToBottom: groupMode !== 'invite'
                    });

                    if (groupMode === 'invite') {
                        groupEmails = [];
                        groupEmailRequests = {};
                        if (groupEmailInput) {
                            groupEmailInput.value = '';
                            groupEmailInput.focus();
                        }
                        renderGroupMembers();
                        if (groupOpenCreatedButton) {
                            setHidden(groupOpenCreatedButton, false);
                        }
                        showGroupAlert(root.getAttribute('data-chat-group-invite-members-success') || 'Invitations were sent successfully.', false);
                        return;
                    }

                    groupMode = 'invite';
                    groupEmails = [];
                    groupEmailRequests = {};
                    renderGroupMembers();
                    if (groupEmailInput) {
                        groupEmailInput.value = '';
                    }
                    setGroupTargetConversation(
                        parseInt(payload.conversation_id || 0, 10) || 0,
                        String(payload.title || (groupNameInput ? groupNameInput.value.trim() : '') || ''),
                        String(payload.avatar_url || '')
                    );
                    syncGroupModalMode();
                    showGroupAlert(root.getAttribute('data-chat-group-create-success') || 'Group created. Now you can invite members.', false);
                    window.setTimeout(function () {
                        if (groupEmailInput) {
                            groupEmailInput.focus();
                        }
                    }, 20);
                }).catch(function () {
                    showGroupAlert(groupMode === 'invite'
                        ? (root.getAttribute('data-chat-group-invite-error') || 'Unable to invite members to the group.')
                        : (root.getAttribute('data-chat-group-create-error') || 'Unable to create the group chat.'), true);
                });
            });
        }

        if (groupRetentionSelect) {
            groupRetentionSelect.addEventListener('change', function () {
                if (!activeConversationId || (activeConversationType !== 'group_chat' && activeConversationType !== 'global_group')) {
                    return;
                }

                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'set_group_retention',
                        conversation_id: activeConversationId,
                        retention_value: groupRetentionSelect.value || '24h',
                        message_limit: activeConversationMessageLimit,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showGroupSettingsAlert(payload.message || (root.getAttribute('data-chat-group-retention-save-error') || 'Unable to update auto-delete settings.'), true);
                        return;
                    }
                    showGroupSettingsAlert(payload.message || '', false);
                    renderConversation(payload, { preserveScroll: true });
                }).catch(function () {
                    showGroupSettingsAlert(root.getAttribute('data-chat-group-retention-save-error') || 'Unable to update auto-delete settings.', true);
                });
            });
        }

        if (emailNotificationsToggle) {
            emailNotificationsToggle.addEventListener('change', function () {
                if (!activeConversationId || activeConversationType !== 'group_chat') {
                    return;
                }

                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'set_group_email_notifications',
                        conversation_id: activeConversationId,
                        enabled: emailNotificationsToggle.checked ? '1' : '0',
                        message_limit: activeConversationMessageLimit,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        emailNotificationsToggle.checked = !emailNotificationsToggle.checked;
                        showComposerAlert(payload.message || 'Unable to update email notifications.', true);
                        return;
                    }
                    showComposerAlert('', false);
                    renderConversation(payload, { preserveScroll: true });
                }).catch(function () {
                    emailNotificationsToggle.checked = !emailNotificationsToggle.checked;
                    showComposerAlert('Unable to update email notifications.', true);
                });
            });
        }

        if (retentionInlineSelect) {
            retentionInlineSelect.addEventListener('change', function () {
                if (!activeConversationId || (activeConversationType !== 'group_chat' && activeConversationType !== 'global_group')) {
                    return;
                }

                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'set_group_retention',
                        conversation_id: activeConversationId,
                        retention_value: retentionInlineSelect.value || '24h',
                        message_limit: activeConversationMessageLimit,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(payload.message || (root.getAttribute('data-chat-group-retention-save-error') || 'Unable to update auto-delete settings.'), true);
                        return;
                    }
                    showComposerAlert('', false);
                    renderConversation(payload, { preserveScroll: true });
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-group-retention-save-error') || 'Unable to update auto-delete settings.', true);
                });
            });
        }

        if (q('[data-admin-chat-create-customer-submit]', root)) {
            q('[data-admin-chat-create-customer-submit]', root).addEventListener('click', function () {
                submitCreateCustomer();
            });
        }

        if (composerInput) {
            composerInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && event.keyCode !== 229) {
                    event.preventDefault();
                    q('[data-admin-chat-send]', root).click();
                }
            });

            composerInput.addEventListener('input', function () {
                queueLinkPreview();
            });

            composerInput.addEventListener('focus', function () {
                scheduleAdminChatViewportRefresh();
            });

            composerInput.addEventListener('blur', function () {
                scheduleAdminChatViewportRefresh();
            });
        }

        qa('[data-admin-chat-reply-clear]', root).forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                clearReplyTarget();
            });
        });

        var chatSendInFlight = false;
        if (q('[data-admin-chat-send]', root)) {
            q('[data-admin-chat-send]', root).addEventListener('click', function () {
                if (chatSendInFlight) return;
                var message = composerInput ? composerInput.value.trim() : '';
                var detectedUrl = extractFirstUrl(message);
                var previewUrl = linkPreviewData && linkPreviewUrl === detectedUrl && linkPreviewDismissedUrl !== detectedUrl ? linkPreviewUrl : '';
                var previewRemoved = detectedUrl && linkPreviewDismissedUrl === detectedUrl ? 1 : 0;
                if (isVoiceBusy()) {
                    showComposerAlert(voiceBusyMessage(), true);
                    return;
                }
                if (!message) {
                    return;
                }

                var sentConversationId = activeConversationId;
                var sentInputValue = composerInput ? composerInput.value : '';
                chatSendInFlight = true;
                postConversation('send', {
                    message: message,
                    reply_to_message_id: replyToMessageId || 0,
                    link_preview_url: previewUrl,
                    link_preview_removed: previewRemoved
                }, false).then(function (payload) {
                    if (activeConversationId !== sentConversationId) return;
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-send-error') || 'Unable to send the message.', true);
                        return;
                    }
                    if (composerInput && composerInput.value === sentInputValue) {
                        composerInput.value = '';
                        clearReplyTarget();
                        resetLinkPreview();
                    }
                    showComposerAlert('', false);
                    renderConversation(payload, { scrollToBottom: true, preserveTransientUi: true });
                }).catch(function () {
                    if (activeConversationId === sentConversationId) {
                        showComposerAlert(root.getAttribute('data-chat-send-error') || 'Unable to send the message.', true);
                    }
                }).then(function () {
                    chatSendInFlight = false;
                });
            });
        }

        if (q('[data-admin-chat-upload-button]', root) && uploadInput) {
            q('[data-admin-chat-upload-button]', root).addEventListener('click', function () {
                if (isVoiceBusy()) {
                    showComposerAlert(voiceBusyMessage(), true);
                    return;
                }
                uploadInput.click();
            });

            uploadInput.addEventListener('change', function () {
                if (isVoiceBusy()) {
                    showComposerAlert(voiceBusyMessage(), true);
                    uploadInput.value = '';
                    return;
                }
                if (!uploadInput.files || !uploadInput.files[0] || !activeConversationId) {
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'upload');
                formData.append('conversation_id', String(activeConversationId));
                formData.append('reply_to_message_id', String(replyToMessageId || 0));
                formData.append('_csrf', csrfToken);
                formData.append('file', uploadInput.files[0]);

                postConversation('upload', formData, true).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-upload-error') || 'Unable to upload the image.', true);
                        return;
                    }
                    uploadInput.value = '';
                    clearReplyTarget();
                    showComposerAlert('', false);
                    renderConversation(payload, { scrollToBottom: true });
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-upload-error') || 'Unable to upload the image.', true);
                });
            });
        }

        if (voiceButton) {
            voiceButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (voiceButton.classList.contains('is-disabled') || voiceButton.getAttribute('aria-disabled') === 'true') {
                    return false;
                }
                if (voiceRecording || voiceStartInFlight) {
                    return stopVoiceRecording(false);
                }
                return startVoiceRecording();
            });
        }

        updateVoiceComposerState();

        doc.addEventListener('click', function (event) {
            var openCustomerChat = closest(event.target, '[data-admin-chat-start-customer]');
            if (openCustomerChat) {
                event.preventDefault();
                event.stopPropagation();
                var targetCustomerId = parseInt(openCustomerChat.getAttribute('data-customer-id') || '0', 10) || 0;
                if (!targetCustomerId) {
                    return;
                }

                var flyout = closest(openCustomerChat, '[data-admin-topbar-flyout]');
                if (flyout) {
                    setHidden(flyout, true);
                    var flyoutRoot = closest(flyout, '[data-admin-topbar-flyout-root]');
                    var flyoutToggle = flyoutRoot ? q('[data-admin-topbar-flyout-toggle]', flyoutRoot) : null;
                    if (flyoutToggle) {
                        flyoutToggle.classList.remove('is-open');
                        flyoutToggle.setAttribute('aria-expanded', 'false');
                    }
                }

                startConversationWithCustomer(targetCustomerId, { scrollToBottom: true, animateConversation: true });
                return;
            }

            var removeConversation = closest(event.target, '[data-admin-chat-remove]');
            if (removeConversation) {
                event.preventDefault();
                event.stopPropagation();
                var removeConversationId = parseInt(removeConversation.getAttribute('data-conversation-id') || '0', 10) || 0;
                var displayName = removeConversation.getAttribute('data-display-name') || '';
                var confirmText = (root.getAttribute('data-chat-remove-confirm') || 'Remove conversation with {name}?').replace('{name}', displayName);
                if (!removeConversationId || !window.confirm(confirmText)) {
                    return;
                }

                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({ action: 'delete_conversation', conversation_id: removeConversationId, _csrf: csrfToken })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-remove-error') || 'Unable to remove this conversation.', true);
                        return;
                    }
                    var itemNode = closest(removeConversation, '[data-admin-chat-item]');
                    if (itemNode) {
                        itemNode.remove();
                    }
                    if (activeConversationId === removeConversationId) {
                        showList();
                    }
                });
                return;
            }

            var searchResult = closest(event.target, '[data-admin-chat-search-result]');
            if (searchResult) {
                var existingConversationId = parseInt(searchResult.getAttribute('data-conversation-id') || '0', 10) || 0;
                var customerId = parseInt(searchResult.getAttribute('data-customer-id') || '0', 10) || 0;
                var adminUserId = parseInt(searchResult.getAttribute('data-admin-user-id') || '0', 10) || 0;
                var participantType = searchResult.getAttribute('data-participant-type') || 'customer';
                if (existingConversationId > 0) {
                    fetchConversation(existingConversationId);
                    return;
                }
                if (participantType === 'customer' && !customerId) {
                    return;
                }
                if (participantType === 'admin' && !adminUserId) {
                    return;
                }
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'start_conversation',
                        participant_type: participantType,
                        customer_id: customerId,
                        admin_user_id: adminUserId,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-start-error') || 'Unable to start conversation.', true);
                        return;
                    }
                    renderConversation(payload, { scrollToBottom: true, animateConversation: true });
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-start-error') || 'Unable to start conversation.', true);
                });
                return;
            }

            var groupSearchResult = closest(event.target, '[data-admin-chat-group-search-item]');
            if (groupSearchResult) {
                addGroupEmail(groupSearchResult.getAttribute('data-invite-email') || '');
                return;
            }

            var openCustomerChatTrigger = closest(event.target, '[data-admin-open-customer-chat]');
            if (openCustomerChatTrigger) {
                event.preventDefault();
                startConversationWithCustomer(openCustomerChatTrigger.getAttribute('data-customer-id') || 0, {
                    scrollToBottom: true,
                    animateConversation: true
                });
                return;
            }

            var deleteMessage = closest(event.target, '[data-admin-chat-delete-message]');
            if (deleteMessage) {
                var deleteConversationId = parseInt(deleteMessage.getAttribute('data-conversation-id') || '0', 10) || 0;
                var deleteMessageId = parseInt(deleteMessage.getAttribute('data-message-id') || '0', 10) || 0;
                var deleteMessageNode = closest(deleteMessage, '[data-admin-chat-message]');
                var deleteTimeAnchor = deleteMessageNode ? deleteMessageNode.previousElementSibling : null;
                var runDeleteRequest;
                if (!deleteConversationId || !deleteMessageId) {
                    return;
                }

                runDeleteRequest = function () {
                    deleteMessage.disabled = true;
                    jsonFetch(chatUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: toQuery({
                            action: 'delete_message',
                            conversation_id: deleteConversationId,
                            message_id: deleteMessageId,
                            message_limit: activeConversationMessageLimit,
                            _csrf: csrfToken
                        })
                    }).then(function (payload) {
                        deleteMessage.disabled = false;
                        if (!payload.ok) {
                            if (deleteMessageNode) {
                                deleteMessageNode.classList.remove('is-removing');
                            }
                            if (deleteTimeAnchor && deleteTimeAnchor.classList && deleteTimeAnchor.classList.contains('admin-chat-conversation__time-anchor')) {
                                deleteTimeAnchor.classList.remove('is-removing');
                            }
                            showComposerAlert(root.getAttribute('data-chat-delete-message-error') || 'Unable to delete this message.', true);
                            return;
                        }
                        renderConversation(payload, { preserveScroll: true });
                    }).catch(function () {
                        deleteMessage.disabled = false;
                        if (deleteMessageNode) {
                            deleteMessageNode.classList.remove('is-removing');
                        }
                        if (deleteTimeAnchor && deleteTimeAnchor.classList && deleteTimeAnchor.classList.contains('admin-chat-conversation__time-anchor')) {
                            deleteTimeAnchor.classList.remove('is-removing');
                        }
                        showComposerAlert(root.getAttribute('data-chat-delete-message-error') || 'Unable to delete this message.', true);
                    });
                };

                closeMessageActions();
                if (deleteMessageNode) {
                    deleteMessageNode.classList.add('is-removing');
                    if (deleteTimeAnchor && deleteTimeAnchor.classList && deleteTimeAnchor.classList.contains('admin-chat-conversation__time-anchor')) {
                        deleteTimeAnchor.classList.add('is-removing');
                    }
                    window.setTimeout(runDeleteRequest, 180);
                } else {
                    runDeleteRequest();
                }
                return;
            }

            var replyOpen = closest(event.target, '[data-admin-chat-reply-open]');
            if (replyOpen) {
                event.preventDefault();
                setReplyTarget(
                    parseInt(replyOpen.getAttribute('data-message-id') || '0', 10) || 0,
                    replyOpen.getAttribute('data-reply-sender') || '',
                    replyOpen.getAttribute('data-reply-text') || ''
                );
                closeMessageActions();
                if (composerInput) {
                    composerInput.focus();
                }
                return;
            }

            var reactionToggle = closest(event.target, '[data-admin-chat-reaction-toggle]');
            if (reactionToggle) {
                event.preventDefault();
                postConversation('toggle_reaction', {
                    message_id: parseInt(reactionToggle.getAttribute('data-message-id') || '0', 10) || 0,
                    reaction_code: reactionToggle.getAttribute('data-admin-chat-reaction-toggle') || ''
                }, false).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(payload.message || 'Unable to save reaction.', true);
                        return;
                    }
                    renderConversation(payload, { preserveScroll: true, preserveTransientUi: true });
                }).catch(function () {
                    showComposerAlert('Unable to save reaction.', true);
                });
                closeMessageActions();
                return;
            }

            var replyScrollTarget = closest(event.target, '[data-admin-chat-scroll-to-message]');
            if (replyScrollTarget) {
                event.preventDefault();
                scrollConversationToMessage(parseInt(replyScrollTarget.getAttribute('data-admin-chat-scroll-to-message') || '0', 10) || 0);
                return;
            }

            var editMessage = closest(event.target, '[data-admin-chat-edit-message]');
            if (editMessage) {
                event.preventDefault();
                var editItem = closest(editMessage, '[data-admin-chat-message]');
                var editorWrap = q('[data-admin-chat-editor]', editItem);
                var editorInput = q('[data-admin-chat-edit-input]', editItem);
                var savedNote = q('[data-admin-chat-edit-saved]', editItem);
                if (!editItem || !editorWrap || !editorInput) {
                    return;
                }

                qa('[data-admin-chat-message]', body).forEach(function (messageNode) {
                    if (messageNode !== editItem) {
                        var otherEditor = q('[data-admin-chat-editor]', messageNode);
                        var otherSaved = q('[data-admin-chat-edit-saved]', messageNode);
                        if (otherEditor) {
                            setHidden(otherEditor, true);
                        }
                        if (otherSaved) {
                            setHidden(otherSaved, true);
                        }
                    }
                });

                setHidden(editorWrap, false);
                if (savedNote) {
                    setHidden(savedNote, true);
                }
                editorInput.focus();
                editorInput.setSelectionRange(editorInput.value.length, editorInput.value.length);
                return;
            }

            var saveMessage = closest(event.target, '[data-admin-chat-save-message]');
            if (saveMessage) {
                event.preventDefault();
                var saveItem = closest(saveMessage, '[data-admin-chat-message]');
                var saveEditor = q('[data-admin-chat-editor]', saveItem);
                var saveInput = q('[data-admin-chat-edit-input]', saveItem);
                var saveSaved = q('[data-admin-chat-edit-saved]', saveItem);
                var saveMessageId = parseInt(saveMessage.getAttribute('data-message-id') || '0', 10) || 0;
                var saveText = saveInput ? saveInput.value.trim() : '';
                if (!saveItem || !saveEditor || !saveInput || !saveMessageId || !activeConversationId || !saveText) {
                    return;
                }

                saveMessage.disabled = true;
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'edit_message',
                        conversation_id: activeConversationId,
                        message_id: saveMessageId,
                        message: saveText,
                        message_limit: activeConversationMessageLimit,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    saveMessage.disabled = false;
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-edit-message-error') || 'Unable to save this message.', true);
                        return;
                    }
                    renderConversation(payload, { preserveScroll: true });
                    var refreshedMessage = q('[data-admin-chat-message][data-message-id="' + String(saveMessageId) + '"]', body);
                    var refreshedSaved = refreshedMessage ? q('[data-admin-chat-edit-saved]', refreshedMessage) : null;
                    if (refreshedSaved) {
                        setHidden(refreshedSaved, false);
                        window.setTimeout(function () {
                            setHidden(refreshedSaved, true);
                        }, 2200);
                    }
                    showComposerAlert('', false);
                }).catch(function () {
                    saveMessage.disabled = false;
                    showComposerAlert(root.getAttribute('data-chat-edit-message-error') || 'Unable to save this message.', true);
                });
                return;
            }

            var blockCustomer = closest(event.target, '[data-admin-chat-toggle-customer-block]');
            if (blockCustomer) {
                event.preventDefault();
                var blockConversationId = parseInt(blockCustomer.getAttribute('data-conversation-id') || '0', 10) || 0;
                var blockCustomerId = parseInt(blockCustomer.getAttribute('data-customer-id') || '0', 10) || 0;
                var isBlocked = String(blockCustomer.getAttribute('data-blocked') || '0') === '1';
                if (!blockConversationId || !blockCustomerId || blockCustomer.disabled) {
                    return;
                }

                blockCustomer.disabled = true;
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'set_customer_block_status',
                        conversation_id: blockConversationId,
                        customer_id: blockCustomerId,
                        blocked: isBlocked ? '0' : '1',
                        message_limit: activeConversationMessageLimit,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    blockCustomer.disabled = false;
                    if (!payload.ok) {
                        showComposerAlert(payload.message || (isBlocked ? 'Unable to unblock customer.' : 'Unable to block customer.'), true);
                        return;
                    }
                    renderConversation(payload, { preserveScroll: true });
                    showComposerAlert(payload.message || '', false);
                }).catch(function () {
                    blockCustomer.disabled = false;
                    showComposerAlert(isBlocked ? 'Unable to unblock customer.' : 'Unable to block customer.', true);
                });
                return;
            }

            var quickOpen = closest(event.target, '[data-admin-chat-quick-open]');
            if (quickOpen) {
                loadQuickReplies();
                return;
            }

            var quickClose = closest(event.target, '[data-admin-chat-quick-close]');
            if (quickClose) {
                closeQuickModal();
                return;
            }

            var quickSend = closest(event.target, '[data-admin-chat-send-quick-reply]');
            if (quickSend) {
                var quickReplyId = parseInt(quickSend.getAttribute('data-quick-reply-id') || '0', 10) || 0;
                if (!quickReplyId || !activeConversationId || quickSend.disabled) {
                    return;
                }

                quickSend.disabled = true;
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'send_quick_reply',
                        conversation_id: activeConversationId,
                        quick_reply_id: quickReplyId,
                        message_limit: activeConversationMessageLimit,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    quickSend.disabled = false;
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-quick-replies-send-error') || 'Unable to send quick reply.', true);
                        return;
                    }
                    closeQuickModal();
                    renderConversation(payload, { scrollToBottom: true });
                }).catch(function () {
                    quickSend.disabled = false;
                    showComposerAlert(root.getAttribute('data-chat-quick-replies-send-error') || 'Unable to send quick reply.', true);
                });
                return;
            }

            var quickEdit = closest(event.target, '[data-admin-chat-quick-edit]');
            if (quickEdit) {
                event.preventDefault();
                var quickItem = closest(quickEdit, '.admin-chat-inbox__quick-item');
                var quickEditor = q('[data-admin-chat-quick-editor]', quickItem);
                var quickInput = q('[data-admin-chat-quick-input]', quickItem);
                var quickSaved = q('[data-admin-chat-quick-saved]', quickItem);
                if (!quickItem || !quickEditor || !quickInput) {
                    return;
                }

                qa('[data-quick-reply-id]', quickList).forEach(function (replyNode) {
                    if (replyNode !== quickItem) {
                        var otherEditor = q('[data-admin-chat-quick-editor]', replyNode);
                        var otherSaved = q('[data-admin-chat-quick-saved]', replyNode);
                        if (otherEditor) {
                            setHidden(otherEditor, true);
                        }
                        if (otherSaved) {
                            setHidden(otherSaved, true);
                        }
                    }
                });

                setHidden(quickEditor, false);
                if (quickSaved) {
                    setHidden(quickSaved, true);
                }
                quickInput.focus();
                quickInput.setSelectionRange(quickInput.value.length, quickInput.value.length);
                return;
            }

            var quickSave = closest(event.target, '[data-admin-chat-quick-save]');
            if (quickSave) {
                event.preventDefault();
                var quickSaveId = parseInt(quickSave.getAttribute('data-quick-reply-id') || '0', 10) || 0;
                var quickSaveItem = closest(quickSave, '.admin-chat-inbox__quick-item');
                var quickSaveInput = q('[data-admin-chat-quick-input]', quickSaveItem);
                var quickSaveEditor = q('[data-admin-chat-quick-editor]', quickSaveItem);
                var quickSaveSaved = q('[data-admin-chat-quick-saved]', quickSaveItem);
                var quickSavePreview = q('.admin-chat-inbox__quick-item-main p', quickSaveItem);
                var quickSaveText = quickSaveInput ? quickSaveInput.value.trim() : '';
                if (!quickSaveId || !quickSaveItem || !quickSaveInput || !quickSaveEditor || !quickSaveText) {
                    return;
                }

                quickSave.disabled = true;
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'update_quick_reply_message',
                        quick_reply_id: quickSaveId,
                        message_body: quickSaveText,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    quickSave.disabled = false;
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-quick-replies-save-error') || 'Unable to save quick reply.', true);
                        return;
                    }

                    if (quickSavePreview && payload.item) {
                        quickSavePreview.textContent = payload.item.preview || payload.item.message_body || '';
                    }
                    setHidden(quickSaveEditor, true);
                    if (quickSaveSaved) {
                        setHidden(quickSaveSaved, false);
                        window.setTimeout(function () {
                            setHidden(quickSaveSaved, true);
                        }, 2200);
                    }
                    showComposerAlert('', false);
                }).catch(function () {
                    quickSave.disabled = false;
                    showComposerAlert(root.getAttribute('data-chat-quick-replies-save-error') || 'Unable to save quick reply.', true);
                });
                return;
            }

            var groupRemove = closest(event.target, '[data-admin-chat-group-remove]');
            if (groupRemove) {
                var removeIndex = parseInt(groupRemove.getAttribute('data-index') || '-1', 10);
                if (removeIndex >= 0) {
                    groupEmails.splice(removeIndex, 1);
                    renderGroupMembers();
                }
                return;
            }

            var groupInviteAction = closest(event.target, '[data-admin-chat-group-invite-action]');
            if (groupInviteAction) {
                var inviteConversationId = parseInt(groupInviteAction.getAttribute('data-conversation-id') || '0', 10) || 0;
                var decision = groupInviteAction.getAttribute('data-admin-chat-group-invite-action') || 'reject';
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'respond_group_invite',
                        conversation_id: inviteConversationId,
                        decision: decision,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-group-invite-error') || 'Unable to update the invitation.', true);
                        return;
                    }
                    updateGroupInviteCards(inviteConversationId, true);
                    if (decision === 'accept' && payload.conversation_id) {
                        fetchConversation(payload.conversation_id);
                    }
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-group-invite-error') || 'Unable to update the invitation.', true);
                });
                return;
            }

            var leaveGroup = closest(event.target, '[data-admin-chat-leave-group]');
            if (leaveGroup && activeConversationId && activeConversationType === 'group_chat') {
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'leave_group',
                        conversation_id: activeConversationId,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-group-leave-error') || 'Unable to leave the group chat.', true);
                        return;
                    }

                    qa('[data-admin-chat-item]', root).forEach(function (item) {
                        var itemConversationId = parseInt(item.getAttribute('data-conversation-id') || '0', 10) || 0;
                        if (itemConversationId === activeConversationId) {
                            item.remove();
                        }
                    });
                    showList();
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-group-leave-error') || 'Unable to leave the group chat.', true);
                });
                return;
            }

            var toggleReadonly = closest(event.target, '[data-admin-chat-readonly-toggle]');
            if (toggleReadonly && activeConversationId && activeConversationType === 'group_chat') {
                var shouldEnableReadOnly = toggleReadonly.innerHTML.indexOf('lock-fill') === -1 ? 1 : 0;
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'toggle_group_read_only',
                        conversation_id: activeConversationId,
                        is_group_read_only: shouldEnableReadOnly,
                        message_limit: activeConversationMessageLimit,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-group-readonly-error') || 'Unable to update read only mode.', true);
                        return;
                    }
                    renderConversation(payload, { preserveScroll: true });
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-group-readonly-error') || 'Unable to update read only mode.', true);
                });
                return;
            }

            var removePreview = closest(event.target, '[data-admin-chat-link-preview-remove]');
            if (removePreview) {
                event.preventDefault();
                dismissLinkPreview();
                return;
            }

            var chatItem = closest(event.target, '[data-admin-chat-item]');
            if (chatItem) {
                var itemConversationId = parseInt(chatItem.getAttribute('data-conversation-id') || '0', 10) || 0;
                if (itemConversationId > 0) {
                    fetchConversation(itemConversationId);
                }
                return;
            }

            if (!closest(event.target, '[data-admin-chat-message]')) {
                closeMessageActions();
            }

            if (conversationMembersPopover && !conversationMembersPopover.hasAttribute('hidden')) {
                if (!closest(event.target, '[data-admin-chat-members-toggle]') && !closest(event.target, '[data-admin-chat-members-popover]')) {
                    closeMembersPopover();
                }
            }

            if (panel && !panel.hasAttribute('hidden') && !isAdminChatDocked()) {
                if (
                    !closest(event.target, '[data-admin-chat-inbox]') &&
                    !closest(event.target, '[data-admin-chat-open]') &&
                    !closest(event.target, '[data-admin-chat-open-conversation]') &&
                    !closest(event.target, '[data-admin-converter-modal]')
                ) {
                    closePanel();
                }
            }
        });

        doc.addEventListener('click', function (event) {
            var bubble = closest(event.target, '[data-admin-chat-message-bubble]');
            var messageNode;
            var messageId;
            if (!bubble || closest(event.target, 'a, button, input, textarea, label')) {
                return;
            }
            messageNode = closest(bubble, '[data-admin-chat-message]');
            messageId = messageNode ? parseInt(messageNode.getAttribute('data-message-id') || '0', 10) || 0 : 0;
            if (!messageId) {
                return;
            }
            event.preventDefault();
            toggleMessageActions(messageId);
        });

        doc.addEventListener('touchstart', function (event) {
            var bubble = closest(event.target, '[data-admin-chat-message-bubble]');
            var messageNode;
            var messageId;
            if (!bubble || closest(event.target, 'a, button, input, textarea, label')) {
                return;
            }
            messageNode = closest(bubble, '[data-admin-chat-message]');
            messageId = messageNode ? parseInt(messageNode.getAttribute('data-message-id') || '0', 10) || 0 : 0;
            if (!messageId) {
                return;
            }
            window.clearTimeout(messageActionTouchTimer);
            messageActionTouchTimer = window.setTimeout(function () {
                toggleMessageActions(messageId, true);
            }, 360);
        }, { passive: true });

        doc.addEventListener('touchend', function () {
            window.clearTimeout(messageActionTouchTimer);
        }, { passive: true });

        doc.addEventListener('touchmove', function () {
            window.clearTimeout(messageActionTouchTimer);
        }, { passive: true });

        doc.addEventListener('touchcancel', function () {
            window.clearTimeout(messageActionTouchTimer);
        }, { passive: true });
    }

    function initCryptoNetworkStatus() {
        var form = q('[data-admin-crypto-status-form]');
        var results = q('[data-admin-crypto-status-results]');
        var pane = q('#admin-settings-crypto-preview-pane');
        if (!form || !results || !pane) return;
        var button = q('[name="admin_refresh_crypto_preview_status"]', form);
        var token = q('[name="_csrf"]', form);
        var busy = false;
        var checkedAt = (parseInt(form.getAttribute('data-checked-at') || '0', 10) || 0) * 1000;
        var feedback = document.createElement('span');
        feedback.className = 'small text-muted ms-2';
        feedback.setAttribute('role', 'status');
        if (button) button.insertAdjacentElement('afterend', feedback);
        function refresh(force) {
            if (busy || (!force && Date.now() - checkedAt < 300000)) return;
            busy = true;
            if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
            feedback.textContent = form.getAttribute('data-loading') || 'Checking...';
            var controller = window.AbortController ? new window.AbortController() : null;
            var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 15000) : null;
            jsonFetch(form.getAttribute('data-status-url'), {
                signal: controller ? controller.signal : undefined,
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: toQuery({ _csrf: token ? token.value : '' })
            }).then(function (payload) {
                if (!payload || !payload.ok || typeof payload.html !== 'string') throw new Error('Unable to refresh');
                results.innerHTML = payload.html;
                checkedAt = Date.now();
                feedback.textContent = '';
            }).catch(function () {
                feedback.textContent = form.getAttribute('data-error') || 'Unable to refresh.';
            }).then(function () {
                if (timeout) window.clearTimeout(timeout);
                busy = false;
                if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
            });
        }
        form.addEventListener('submit', function (event) { event.preventDefault(); refresh(true); });
        doc.addEventListener('shown.bs.tab', function (event) {
            if (event.target && event.target.id === 'admin-settings-crypto-preview-tab') refresh(false);
        });
        if (pane.classList.contains('active')) refresh(false);
    }

    initCryptoNetworkStatus();
    initSensitiveRouteProtection();
    initTopbarTools();
    initOrdersGuidanceToggle();
    initOrdersActionMenus();
    initCompletedOrdersExpirySort();
    initPaymentReceivedFields();
    initPaymentBlockchainCheck();

    if (!hasBootstrap) {
        return;
    }

    if (window.bootstrap.Tooltip) {
        var orderTooltips = [];
        qa('[data-admin-action-help], .admin-orders-table-scroll [title], .admin-orders-table-scroll .admin-orders-menu-trigger, .admin-orders-actions .admin-orders-menu-trigger, .admin-order-modal .admin-order-summary__delivery-link-indicator[title]').forEach(function (element) {
            if (element.getAttribute('data-bs-toggle') === 'tooltip' || element.classList.contains('admin-orders-menu__item')) return;
            if (!element.hasAttribute('title')) element.setAttribute('title', element.getAttribute('aria-label') || '');
            if (element.classList.contains('admin-order-summary__delivery-link-indicator')) element.tabIndex = 0;
            var tooltip = new window.bootstrap.Tooltip(element, {
                container: doc.body,
                boundary: 'viewport',
                placement: 'top',
                trigger: 'hover focus',
                customClass: closest(element, '.admin-order-modal') ? 'admin-orders-tooltip admin-orders-tooltip--order-modal' : 'admin-orders-tooltip',
                html: false,
                delay: {show: 180, hide: 0}
            });
            orderTooltips.push(tooltip);
            element.addEventListener('click', function () { tooltip.hide(); });
        });
        function hideOrderTooltips() {
            orderTooltips.forEach(function (tooltip) { tooltip.hide(); });
        }
        window.addEventListener('resize', hideOrderTooltips);
        doc.addEventListener('scroll', hideOrderTooltips, true);
        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') hideOrderTooltips();
        });
    }

    qa('[data-bs-toggle="tooltip"]').forEach(function (element) {
        if (window.bootstrap.Tooltip) {
            new window.bootstrap.Tooltip(element);
        }
    });

    qa('[data-bs-toggle="popover"]').forEach(function (element) {
        if (window.bootstrap.Popover) {
            new window.bootstrap.Popover(element);
        }
    });

    initSidebarAndDropdowns();
    initDangerForms();
    initSettingsBackupGenerator();
    initAdminPanelSubscriptionReminderModal();
    initProviderUrlReplacement();
    initProviderLogoManagers();
    initProductTypeForms();
    initOrderStatusForms();
    initOrderExtendPreviews();
    initOrderCreateForms();
    initUserQuickOrderForms();
    initUserDetailCopy();
    initWalletCustomerPickers();
    initWalletNetworkForms();
    initRichTextEditors();
    initAdminPersonalNotesAutosave();
    initSearch();
    initPaymentsCustomerSearch();
    initChat();
});

document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-admin-billing]');
    if (!root) return;

    let payload = {};
    try { payload = JSON.parse(root.dataset.billingPayload || '{}'); } catch (error) { payload = {}; }
    // Keep customer lookup and manual entry focused on one task at a time.
    const buyerLookupPanel = root.querySelector('[data-billing-buyer-lookup-panel]');
    const buyerFieldsPanel = root.querySelector('[data-billing-buyer-fields-panel]');
    [buyerLookupPanel, buyerFieldsPanel].forEach(panel => {
        panel?.addEventListener('toggle', () => {
            if (!panel.open) return;
            const other = panel === buyerLookupPanel ? buyerFieldsPanel : buyerLookupPanel;
            if (other) other.open = false;
        });
    });
    root.addEventListener('change', event => {
        if (event.target.matches('[data-billing-customer-select], [data-billing-site-user-select]')
            && Number(event.target.value) > 0 && buyerFieldsPanel) {
            buyerFieldsPanel.open = true;
            if (buyerLookupPanel) buyerLookupPanel.open = false;
        }
    });
    // Native validation must always be able to focus fields inside disclosures.
    root.addEventListener('invalid', event => {
        let panel = event.target.closest('details');
        while (panel) {
            panel.open = true;
            panel = panel.parentElement?.closest('details');
        }
    }, true);
    const profileFilter = root.querySelector('[data-billing-profile-filter]');
    profileFilter?.addEventListener('change', () => {
        const tab = profileFilter.dataset.billingTab || 'create';
        window.location.assign(`/admin/?page=billing&billing_tab=${encodeURIComponent(tab)}&billing_profile_id=${encodeURIComponent(profileFilter.value)}`);
    });
    const remoteSearch = (input, select, type, label) => {
        if (!input || !select) return;
        const lookup = input.closest('[data-billing-lookup]');
        const results = lookup?.querySelector('[data-billing-lookup-results]');
        let timer = 0;
        let requestId = 0;
        const hideResults = () => {
            if (!results) return;
            results.hidden = true;
            results.replaceChildren();
        };
        const showState = (message, className = '') => {
            if (!results) return;
            const state = document.createElement('div');
            state.className = `admin-billing-buyer-lookup__state ${className}`.trim();
            state.textContent = message;
            results.replaceChildren(state);
            results.hidden = false;
        };
        const ensureOption = (row) => {
            const value = String(row.id);
            let option = Array.from(select.options).find((candidate) => candidate.value === value);
            if (!option) {
                option = new Option(label(row), value);
                select.add(option);
            }
            option.textContent = label(row);
            option.dataset.billingRow = JSON.stringify(row);
            return option;
        };
        const showItems = (items) => {
            items.forEach(ensureOption);
            if (!results) return;
            if (items.length === 0) {
                showState(payload.searchUi?.empty || 'No results', 'is-empty');
                return;
            }
            results.replaceChildren(...items.slice(0, 8).map((row) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'admin-billing-buyer-lookup__result';
                const primary = type === 'billing_customer'
                    ? (row.display_name || row.company_name || row.email || `#${row.id}`)
                    : (`@${row.public_handle || row.email || row.id}`);
                const secondary = type === 'billing_customer'
                    ? [row.email, row.company_name].filter(Boolean).join(' · ')
                    : [`#${row.id}`, row.email].filter(Boolean).join(' · ');
                const strong = document.createElement('strong');
                const small = document.createElement('small');
                strong.textContent = primary;
                small.textContent = secondary;
                button.append(strong, small);
                button.addEventListener('click', () => {
                    ensureOption(row);
                    select.value = String(row.id);
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    input.value = label(row);
                    hideResults();
                });
                return button;
            }));
            results.hidden = false;
        };
        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            const query = input.value.trim();
            requestId += 1;
            const currentRequestId = requestId;
            if (query.length > 128 || (query.length < 2 && !(type === 'site_customer' && /^\d+$/.test(query)))) {
                hideResults();
                return;
            }
            showState(payload.searchUi?.loading || 'Loading...', 'is-loading');
            timer = window.setTimeout(async () => {
                try {
                    const profileId = profileFilter?.value || payload.selectedProfileId || 0;
                    const response = await fetch(`/admin/?page=billing&billing_search=1&type=${encodeURIComponent(type)}&q=${encodeURIComponent(query)}&profile_id=${encodeURIComponent(profileId)}`, { headers: { Accept: 'application/json' } });
                    const data = await response.json();
                    if (currentRequestId !== requestId) return;
                    if (!response.ok || !data.ok || !Array.isArray(data.items)) {
                        showState(payload.searchUi?.empty || 'No results', 'is-empty');
                        return;
                    }
                    showItems(data.items);
                } catch (error) {
                    if (currentRequestId === requestId) showState(payload.searchUi?.empty || 'No results', 'is-empty');
                }
            }, 220);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { requestId += 1; window.clearTimeout(timer); hideResults(); }
        });
        document.addEventListener('click', (event) => {
            if (lookup && !lookup.contains(event.target)) { requestId += 1; window.clearTimeout(timer); hideResults(); }
        });
    };
    const directoryForm = root.querySelector('[data-billing-customer-form]');
    if (directoryForm) {
        const directoryUserSelect = directoryForm.querySelector('[data-billing-directory-user-select]');
        const directoryCountry = directoryForm.querySelector('[data-billing-customer-country]');
        const directoryCountryName = directoryForm.querySelector('[data-billing-customer-country-name]');
        const syncDirectoryCountryName = () => {
            if (!directoryCountryName) return;
            const option = directoryCountry?.selectedOptions[0];
            directoryCountryName.value = option?.dataset.countryName || (option?.textContent || '').replace(/\s*\([A-Z]{2}\)\s*$/, '').trim();
        };
        directoryCountry?.addEventListener('change', syncDirectoryCountryName);
        remoteSearch(directoryForm.querySelector('[data-billing-directory-user-search]'), directoryUserSelect, 'site_customer', (row) => `#${row.id} · @${row.public_handle || ''} · ${row.email || ''}`);
        directoryUserSelect?.addEventListener('change', () => {
            const option = directoryUserSelect.selectedOptions[0];
            let row = null;
            try { row = JSON.parse(option?.dataset.billingRow || 'null'); } catch (error) { row = null; }
            if (!row) row = (payload.siteCustomers || []).find((item) => String(item.id) === directoryUserSelect.value);
            if (!row) return;
            const name = directoryForm.querySelector('[name="billing_customer_display_name"]');
            const email = directoryForm.querySelector('[name="billing_customer_email"]');
            const country = directoryForm.querySelector('[name="billing_customer_country_code"]');
            if (name && !name.value) name.value = row.public_handle || row.email || '';
            if (email && !email.value) email.value = row.email || '';
            if (country && !country.value) {
                country.value = row.country_code || '';
                syncDirectoryCountryName();
            }
        });
    }

    root.querySelectorAll('[data-billing-list-search]').forEach((input) => {
        const listName = input.dataset.billingListSearch || '';
        const rows = Array.from(root.querySelectorAll(`[data-billing-filter-row="${listName}"]`));
        const emptyState = root.querySelector(`[data-billing-filter-empty="${listName}"]`);
        const filterRows = () => {
            const query = input.value.trim().toLocaleLowerCase();
            let visible = 0;
            rows.forEach((row) => {
                const matches = query === '' || (row.textContent || '').toLocaleLowerCase().includes(query);
                row.hidden = !matches;
                if (matches) visible += 1;
            });
            if (emptyState) emptyState.hidden = visible > 0;
        };
        input.addEventListener('input', filterRows);
        filterRows();
    });

    const logoInput = root.querySelector('[data-billing-logo-input]');
    const logoPreview = root.querySelector('[data-billing-logo-preview]');
    logoInput?.addEventListener('change', () => {
        const file = logoInput.files?.[0];
        if (!file || !logoPreview || !/^image\/(png|jpeg|webp)$/.test(file.type)) return;

        const previousObjectUrl = logoPreview.dataset.objectUrl || '';
        if (previousObjectUrl) URL.revokeObjectURL(previousObjectUrl);

        const objectUrl = URL.createObjectURL(file);
        logoPreview.src = objectUrl;
        logoPreview.dataset.objectUrl = objectUrl;
        const wrapper = logoPreview.closest('.admin-billing-logo-preview');
        wrapper?.classList.remove('is-empty');
        const removeLabel = wrapper?.querySelector('label');
        if (removeLabel) removeLabel.hidden = false;
    });

    const companyForm = root.querySelector('[data-billing-company-form]');
    const companyCountry = companyForm?.querySelector('[data-billing-profile-country]');
    const companyActivity = companyForm?.querySelector('[data-billing-profile-activity]');
    const companyVatStatus = companyForm?.querySelector('[data-billing-profile-vat-status]');
    const profileGuidance = companyForm?.querySelector('[data-billing-profile-guidance]');
    const ossCapability = companyForm?.querySelector('[name="eu_oss_enabled"]');
    const medicalCapability = companyForm?.querySelector('[name="medical_exemption_enabled"]');
    const marginCapability = companyForm?.querySelector('[name="margin_scheme_enabled"]');
    const renderProfileGuidance = () => {
        if (!profileGuidance) return;
        const vatStatus = companyVatStatus?.value || 'manual';
        const activity = companyActivity?.value || 'general';
        const country = String(companyCountry?.value || '').toUpperCase();
        const countryRules = payload.profileCapabilityRules?.[country] || {};
        const availability = ['oss', 'medical', 'margin'].reduce((result, capability) => {
            const rule = countryRules?.[capability] || {};
            result[capability] = (rule.vat_statuses || []).includes(vatStatus)
                && (rule.business_activities || []).includes(activity);
            return result;
        }, {});
        const guidanceKey = ['healthcare', 'psychology'].includes(activity)
            ? 'healthcare'
            : (['digital_services', 'second_hand_goods'].includes(activity) ? activity : 'general');
        const availableCount = Object.values(availability).filter(Boolean).length;
        profileGuidance.textContent = vatStatus === 'manual'
            ? (payload.profileGuidance?.status_manual || '')
            : (availableCount > 0
                ? (payload.profileGuidance?.[guidanceKey] || payload.profileGuidance?.general || '')
                : (payload.profileGuidance?.no_special || payload.profileGuidance?.general || ''));
        [[ossCapability, 'oss'], [medicalCapability, 'medical'], [marginCapability, 'margin']].forEach(([control, capability]) => {
            if (!control) return;
            const available = Boolean(availability[capability]);
            control.disabled = !available;
            const wrapper = control.closest('[data-billing-profile-capability]');
            if (wrapper) wrapper.hidden = !available;
            if (!available) control.checked = false;
        });
        const emptyState = companyForm?.querySelector('[data-billing-profile-capabilities-empty]');
        if (emptyState) emptyState.hidden = availableCount > 0;
    };
    companyCountry?.addEventListener('change', () => {
        const defaults = payload.countries?.[companyCountry.value];
        if (defaults) {
            const currency = companyForm.querySelector('[data-billing-profile-currency]');
            const prefix = companyForm.querySelector('[data-billing-profile-prefix]');
            const countryName = companyForm.querySelector('[name="country_name"]');
            if (currency) currency.value = defaults.currency || 'EUR';
            if (prefix) prefix.value = defaults.number_prefix || '';
            if (countryName) countryName.value = defaults.name || companyCountry.selectedOptions[0]?.textContent?.trim() || '';
        }
        const preview = companyForm?.querySelector('[data-billing-profile-country-preview]');
        if (preview) {
            const code = String(companyCountry.value || '').toUpperCase();
            const selectedLabel = companyCountry.selectedOptions[0]?.textContent?.replace(/\s*\([A-Z]{2}\)\s*$/, '').trim() || code;
            const image = preview.querySelector('img');
            const label = preview.querySelector('span');
            if (image) image.src = `/img/flags/${/^[A-Z]{2}$/.test(code) ? code : 'noflag'}.gif`;
            if (label) label.textContent = selectedLabel;
        }
        renderProfileGuidance();
    });
    companyActivity?.addEventListener('change', renderProfileGuidance);
    companyVatStatus?.addEventListener('change', renderProfileGuidance);
    renderProfileGuidance();

    const ossRateCountry = root.querySelector('[data-billing-oss-rate-country]');
    const renderOssRateCountry = () => {
        const selectedCountry = String(ossRateCountry?.value || '').toUpperCase();
        root.querySelectorAll('[data-billing-oss-rate-row]').forEach((row) => {
            row.hidden = String(row.dataset.billingOssRateRow || '').toUpperCase() !== selectedCountry;
        });
    };
    ossRateCountry?.addEventListener('change', renderOssRateCountry);
    renderOssRateCountry();

    root.querySelectorAll('label.form-label:not([for])').forEach((label, index) => {
        const next = label.nextElementSibling;
        const control = next?.matches('input, select, textarea') ? next : next?.querySelector('input:not([type="hidden"]), select, textarea');
        if (!control || label.querySelector('input,select,textarea')) return;
        if (!control.id) control.id = `billing-field-${index}`;
        label.htmlFor = control.id;
    });
    const form = root.querySelector('[data-billing-document-form]');
    const draftStoragePrefix = `adminBillingDraft:v3:admin-${Number(payload.adminUserId || 0)}`;
    const draftStorageKey = (profileId, documentId) => `${draftStoragePrefix}:${Number(profileId || 0)}:${Number(documentId || 0) > 0 ? `doc-${Number(documentId)}` : 'new'}`;
    if (Number(payload.savedProfileId || 0) > 0) {
        try {
            localStorage.removeItem(draftStorageKey(payload.savedProfileId, 0));
            if (Number(payload.savedDocumentId || 0) > 0) localStorage.removeItem(draftStorageKey(payload.savedProfileId, payload.savedDocumentId));
        } catch (error) { /* local cache is optional */ }
    }
    if (!form) return;
    const currentDraftKey = draftStorageKey(payload.selectedProfileId, payload.documentId);
    const serializableControls = () => Array.from(form.elements).filter((control) => {
        if (!control.name || control.name === '_csrf' || control.disabled) return false;
        return !['file', 'submit', 'button'].includes(String(control.type || '').toLowerCase());
    });
    const serializeDraft = () => ({
        savedAt: Date.now(),
        controls: serializableControls().map((control) => ({
            name: control.name,
            type: String(control.type || ''),
            value: String(control.value ?? ''),
            checked: Boolean(control.checked),
        })),
    });
    const restoreDraft = () => {
        if (payload.hasServerDraft) return false;
        let draft = null;
        try { draft = JSON.parse(localStorage.getItem(currentDraftKey) || 'null'); } catch (error) { draft = null; }
        if (!draft || !Array.isArray(draft.controls) || !draft.controls.length || !Number.isFinite(draft.savedAt) || Date.now() - draft.savedAt > 7 * 86400000 || draft.savedAt > Date.now() + 60000) return false;
        const itemCount = draft.controls.filter((control) => control.name === 'item_description[]').length;
        const items = form.querySelector('[data-billing-items]');
        const firstItem = items?.querySelector('[data-billing-item]');
        while (items && firstItem && items.querySelectorAll('[data-billing-item]').length < itemCount) {
            items.appendChild(firstItem.cloneNode(true));
        }
        const controlsByName = {};
        serializableControls().forEach((control) => {
            controlsByName[control.name] ||= [];
            controlsByName[control.name].push(control);
            if (['checkbox', 'radio'].includes(String(control.type || '').toLowerCase())) control.checked = false;
        });
        const offsets = {};
        draft.controls.forEach((saved) => {
            const index = offsets[saved.name] || 0;
            offsets[saved.name] = index + 1;
            const control = controlsByName[saved.name]?.[index];
            if (!control) return;
            if (['checkbox', 'radio'].includes(String(control.type || '').toLowerCase())) control.checked = Boolean(saved.checked);
            else control.value = saved.value;
        });
        const notice = form.querySelector('[data-billing-draft-notice]');
        if (notice) notice.hidden = false;
        return true;
    };
    restoreDraft();
    let draftSaveTimer = 0;
    const saveDraftCache = () => {
        window.clearTimeout(draftSaveTimer);
        try { localStorage.setItem(currentDraftKey, JSON.stringify(serializeDraft())); } catch (error) { /* local cache is optional */ }
    };
    const queueDraftSave = () => {
        window.clearTimeout(draftSaveTimer);
        draftSaveTimer = window.setTimeout(saveDraftCache, 300);
    };
    form.addEventListener('input', queueDraftSave);
    form.addEventListener('change', queueDraftSave);
    form.addEventListener('submit', saveDraftCache);
    const languageSelect = form.querySelector('[data-billing-language]');
    const priceMode = form.querySelector('[data-billing-price-mode]');
    let calculatedTotals = { subtotal: 0, tax: 0, gross: 0 };
    let renderSourceAudit = () => {};
    let renderIssueChecklist = () => {};

    const money = (value) => {
        const currency = form.querySelector('[data-billing-currency]')?.value || 'EUR';
        return `${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}`;
    };
    const numeric = (value) => {
        const parsed = Number(String(value ?? '').replace(',', '.'));
        return Number.isFinite(parsed) ? parsed : 0;
    };
    const setBuyer = (row, overwrite = true) => {
        if (!row) return;
        const map = {
            display_name: row.display_name || row.public_handle || '',
            company_name: row.company_name || '', phone: row.phone || '',
            email: row.email || '', tax_id: row.tax_id || '', address_line1: row.address_line1 || '',
            address_line2: row.address_line2 || '', postal_code: row.postal_code || '', city: row.city || '',
            country_name: row.country_name || '', country_code: row.country_code || ''
        };
        Object.entries(map).forEach(([key, value]) => {
            const input = form.querySelector(`[data-billing-buyer="${key}"]`);
            if (input && (overwrite || !input.value)) input.value = value;
        });
        const buyerTypeControl = form.querySelector('[data-billing-buyer-type]');
        if (buyerTypeControl && (String(map.tax_id).trim() || String(map.company_name).trim())) buyerTypeControl.value = 'business';
        const preferredLanguage = String(row.locale_code || row.linked_locale_code || '').toLowerCase();
        if (languageSelect && ['pl', 'en', 'de'].includes(preferredLanguage)) {
            languageSelect.value = preferredLanguage;
            languageSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        form.querySelector('[data-billing-destination-country]')?.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const billingCustomerSelect = form.querySelector('[data-billing-customer-select]');
    const siteUserSelect = form.querySelector('[data-billing-site-user-select]');
    remoteSearch(form.querySelector('[data-billing-customer-search]'), billingCustomerSelect, 'billing_customer', (row) => `${row.display_name || ''} · ${row.email || ''}`);
    remoteSearch(form.querySelector('[data-billing-site-user-search]'), siteUserSelect, 'site_customer', (row) => `#${row.id} · @${row.public_handle || ''} · ${row.email || ''}`);
    billingCustomerSelect?.addEventListener('change', () => {
        let row = (payload.billingCustomers || []).find((item) => String(item.id) === billingCustomerSelect.value);
        if (!row) { try { row = JSON.parse(billingCustomerSelect.selectedOptions[0]?.dataset.billingRow || 'null'); } catch (error) { row = null; } }
        if (row) {
            setBuyer(row);
            if (row.customer_id && siteUserSelect) siteUserSelect.value = String(row.customer_id);
        }
    });
    siteUserSelect?.addEventListener('change', () => {
        let row = (payload.siteCustomers || []).find((item) => String(item.id) === siteUserSelect.value);
        if (!row) { try { row = JSON.parse(siteUserSelect.selectedOptions[0]?.dataset.billingRow || 'null'); } catch (error) { row = null; } }
        if (row) setBuyer(row, false);
    });

    const sourceType = form.querySelector('[data-billing-source-type]');
    const sourcePicker = form.querySelector('[data-billing-source-picker]');
    const sourceSelect = form.querySelector('[data-billing-source-select]');
    const sourceId = form.querySelector('[data-billing-source-id]');
    const sourceHelp = form.querySelector('[data-billing-source-help]');
    const sourceRows = (type) => {
        const used = new Set(payload.usedSourceKeys || []);
        if (type === 'order') return (payload.orders || []).filter((row) => !used.has(`order:${row.id}`));
        const expected = type === 'payment_crypto' ? 'crypto' : type === 'payment_bank' ? 'bank' : '';
        return (payload.payments || []).filter((row) => row.payment_type === expected
            && !used.has(`${type}:${row.id}`));
    };
    const sourceLabel = (type, row) => {
        const account = `#${row.id} · @${row.public_handle || ''} · ${row.email || ''}`;
        if (type === 'order') return `${account} · ${row.product_name || ''} · ${row.total_amount || 0} ${row.currency_code || ''}`;
        return `${account} · ${row.description || ''} · ${row.amount || 0} ${row.currency_code || ''}`;
    };
    const initialSourceId = sourceId?.value || '';
    let isInitialSourceBuild = true;
    const rebuildSources = () => {
        if (!sourceType || !sourceSelect || !sourcePicker || !sourceId) return;
        const type = sourceType.value;
        if (sourceHelp) sourceHelp.textContent = payload.sourceHelp?.[type] || '';
        sourcePicker.hidden = type === 'independent';
        sourceSelect.innerHTML = '<option value="">—</option>';
        sourceRows(type).forEach((row) => sourceSelect.add(new Option(sourceLabel(type, row), String(row.id))));
        sourceId.value = type === 'independent' ? '0' : sourceId.value;
        if (isInitialSourceBuild && initialSourceId && type !== 'independent' && Array.from(sourceSelect.options).some((option) => option.value === initialSourceId)) {
            sourceSelect.value = initialSourceId;
            sourceId.value = initialSourceId;
        }
        isInitialSourceBuild = false;
        renderSourceAudit();
        renderIssueChecklist();
    };
    sourceType?.addEventListener('change', () => {
        if (sourceId) sourceId.value = '0';
        rebuildSources();
    });
    sourceSelect?.addEventListener('change', () => {
        if (!sourceId || !sourceType) return;
        sourceId.value = sourceSelect.value;
        const row = sourceRows(sourceType.value).find((item) => String(item.id) === sourceSelect.value);
        if (!row) return;
        if (siteUserSelect && row.customer_id) {
            if (!Array.from(siteUserSelect.options).some((option) => option.value === String(row.customer_id))) {
                siteUserSelect.add(new Option(`#${row.customer_id} · ${row.public_handle ? '@' + row.public_handle : row.email || ''}`, String(row.customer_id)));
            }
            siteUserSelect.value = String(row.customer_id);
        }
        form.querySelectorAll('[data-billing-buyer]').forEach((control) => { control.value = ''; });
        const linkedBillingCustomer = (payload.billingCustomers || []).find((item) => Number(item.customer_id || 0) === Number(row.customer_id || 0));
        const linkedSiteCustomer = (payload.siteCustomers || []).find((item) => Number(item.id || 0) === Number(row.customer_id || 0));
        if (linkedBillingCustomer) {
            if (billingCustomerSelect) billingCustomerSelect.value = String(linkedBillingCustomer.id);
            setBuyer(linkedBillingCustomer);
        } else {
            if (billingCustomerSelect) billingCustomerSelect.value = '0';
            setBuyer(linkedSiteCustomer || { display_name: row.public_handle || row.email, email: row.email || '' });
        }
        const sourceItems = Array.from(form.querySelectorAll('[data-billing-item]'));
        sourceItems.slice(1).forEach((item) => item.remove());
        const firstSourceItem = sourceItems[0];
        const description = firstSourceItem?.querySelector('[name="item_description[]"]');
        const quantity = firstSourceItem?.querySelector('[name="item_quantity[]"]');
        const price = firstSourceItem?.querySelector('[name="item_unit_price[]"]');
        const purchasePrice = firstSourceItem?.querySelector('[name="item_purchase_price[]"]');
        if (description) description.value = row.product_name || row.description || '';
        if (quantity) quantity.value = '1';
        if (price) price.value = row.total_amount || row.amount || 0;
        if (purchasePrice) purchasePrice.value = '';
        if (priceMode) priceMode.value = 'gross';
        const currency = form.querySelector('[data-billing-currency]');
        if (currency && row.currency_code) currency.value = row.currency_code;
        const paymentNotes = form.querySelector('[name="payment_notes"]');
        if (paymentNotes) paymentNotes.value = payload.defaultPaymentNotes?.[languageSelect?.value]?.[sourceType.value] || '';
        calculate();
        renderSourceAudit();
        renderIssueChecklist();
    });
    rebuildSources();

    const itemsRoot = form.querySelector('[data-billing-items]');
    const itemCatalogList = form.querySelector('[data-billing-item-catalog]');
    let itemSearchTimer = 0;
    itemsRoot?.addEventListener('input', (event) => {
        const input = event.target.closest('[data-billing-item-description]');
        if (!input || input.value.trim().length < 2) return;
        window.clearTimeout(itemSearchTimer);
        itemSearchTimer = window.setTimeout(async () => {
            try {
                const profileId = profileFilter?.value || payload.selectedProfileId || 0;
                const response = await fetch(`/admin/?page=billing&billing_search=1&type=item&q=${encodeURIComponent(input.value.trim())}&profile_id=${encodeURIComponent(profileId)}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!data.ok || !Array.isArray(data.items)) return;
                payload.itemCatalog = data.items;
                if (itemCatalogList) itemCatalogList.replaceChildren(...data.items.map((row) => {
                    const option = document.createElement('option');
                    option.value = String(row.description || '');
                    return option;
                }));
            } catch (error) { /* local catalog remains available */ }
        }, 220);
    });
    const calculate = () => {
        let subtotal = 0;
        let tax = 0;
        const isMarginScheme = form.querySelector('[data-billing-tax-treatment]')?.value === 'margin_scheme_second_hand';
        itemsRoot?.querySelectorAll('[data-billing-item]').forEach((row) => {
            const quantity = numeric(row.querySelector('[name="item_quantity[]"]')?.value);
            const price = numeric(row.querySelector('[name="item_unit_price[]"]')?.value);
            const purchasePrice = numeric(row.querySelector('[name="item_purchase_price[]"]')?.value);
            const rate = numeric(row.querySelector('[name="item_tax_rate[]"]')?.value);
            const enteredTotal = Math.round(quantity * price * 100) / 100;
            const purchaseTotal = Math.round(quantity * purchasePrice * 100) / 100;
            const marginTotal = Math.max(0, enteredTotal - purchaseTotal);
            const net = isMarginScheme
                ? Math.round((enteredTotal - (rate > 0 ? marginTotal * rate / (100 + rate) : 0)) * 100) / 100
                : (priceMode?.value === 'gross' && rate > 0
                    ? Math.round((enteredTotal / (1 + rate / 100)) * 100) / 100
                    : enteredTotal);
            const lineTax = isMarginScheme
                ? Math.round((enteredTotal - net) * 100) / 100
                : (priceMode?.value === 'gross'
                    ? Math.round((enteredTotal - net) * 100) / 100
                    : Math.round(net * rate) / 100);
            subtotal += net;
            tax += lineTax;
            const output = row.querySelector('[data-billing-line-gross]');
            if (output) output.textContent = money(isMarginScheme ? enteredTotal : net + lineTax);
        });
        const outputs = { '[data-billing-subtotal]': subtotal, '[data-billing-tax-total]': tax, '[data-billing-total]': subtotal + tax };
        Object.entries(outputs).forEach(([selector, value]) => { const output = form.querySelector(selector); if (output) output.textContent = money(value); });
        calculatedTotals = { subtotal, tax, gross: subtotal + tax };
        renderSourceAudit();
        renderIssueChecklist();
    };
    itemsRoot?.addEventListener('input', calculate);
    itemsRoot?.addEventListener('change', (event) => {
        const input = event.target.closest('[data-billing-item-description]');
        if (!input) return;
        const catalogRow = (payload.itemCatalog || []).find((row) => String(row.description).toLowerCase() === input.value.trim().toLowerCase());
        const item = input.closest('[data-billing-item]');
        if (!catalogRow || !item) return;
        const unit = item.querySelector('[name="item_unit[]"]');
        const price = item.querySelector('[name="item_unit_price[]"]');
        const purchasePrice = item.querySelector('[name="item_purchase_price[]"]');
        const tax = item.querySelector('[name="item_tax_rate[]"]');
        if (unit) unit.value = catalogRow.unit_label || '';
        const isMarginScheme = form.querySelector('[data-billing-tax-treatment]')?.value === 'margin_scheme_second_hand';
        if (price) price.value = isMarginScheme
            ? (catalogRow.default_unit_price_gross ?? catalogRow.default_unit_price_net ?? 0)
            : (catalogRow.default_unit_price_net || 0);
        if (purchasePrice) purchasePrice.value = isMarginScheme ? (catalogRow.default_purchase_price_gross ?? '') : '';
        if (tax) tax.value = catalogRow.default_tax_rate_percent || 0;
        if (priceMode) priceMode.value = isMarginScheme ? 'gross' : 'net';
        calculate();
    });
    form.querySelector('[data-billing-currency]')?.addEventListener('change', calculate);
    priceMode?.addEventListener('change', calculate);
    languageSelect?.addEventListener('change', () => {
        const translatedUnits = payload.units?.[languageSelect.value] || payload.units?.en || {};
        itemsRoot?.querySelectorAll('[data-billing-item-unit]').forEach((select) => {
            const selectedOption = select.selectedOptions[0];
            const unitKey = selectedOption?.dataset.unitKey || 'piece';
            const effectiveKey = translatedUnits[unitKey] ? unitKey : 'piece';
            select.replaceChildren(...Object.entries(translatedUnits).map(([key, label]) => {
                const option = new Option(String(label), String(label), false, key === effectiveKey);
                option.dataset.unitKey = key;
                return option;
            }));
        });
        const selectedTreatment = form.querySelector('[data-billing-tax-treatment]')?.value || 'manual';
        const note = form.querySelector('[data-billing-tax-note]');
        const translatedNote = payload.defaultTaxNotesByLanguage?.[languageSelect.value]?.[selectedTreatment];
        if (note && typeof translatedNote === 'string') note.value = translatedNote;
        const paymentNote = form.querySelector('[name="payment_notes"]');
        const defaultPaymentNoteValues = Object.values(payload.defaultPaymentNotes || {}).flatMap((row) => Object.values(row || {}));
        if (paymentNote && sourceType?.value !== 'independent' && (paymentNote.value.trim() === '' || defaultPaymentNoteValues.includes(paymentNote.value))) {
            paymentNote.value = payload.defaultPaymentNotes?.[languageSelect.value]?.[sourceType.value] || '';
        }
        queueDraftSave();
    });
    form.querySelector('[data-billing-add-item]')?.addEventListener('click', () => {
        if (!itemsRoot) return;
        const first = itemsRoot.querySelector('[data-billing-item]');
        if (!first) return;
        const row = first.cloneNode(true);
        row.querySelectorAll('input').forEach((input) => {
            if (input.name === 'item_quantity[]') input.value = '1';
            else if (input.name === 'item_unit[]') input.value = first.querySelector('[name="item_unit[]"]')?.value || 'szt.';
            else if (input.name === 'item_tax_rate[]') input.value = String(payload.defaultTax || 0);
            else input.value = '';
        });
        itemsRoot.appendChild(row);
        calculate();
        queueDraftSave();
    });
    itemsRoot?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-billing-remove-item]');
        if (!button) return;
        if (itemsRoot.querySelectorAll('[data-billing-item]').length > 1) button.closest('[data-billing-item]')?.remove();
        calculate();
        queueDraftSave();
    });
    const taxNotes = Object.values(payload.defaultTaxNotes || {}).reduce((carry, row) => { carry[row.key] = row.value; return carry; }, {});
    const taxNoteFor = (treatment) => payload.defaultTaxNotesByLanguage?.[languageSelect?.value]?.[treatment] ?? taxNotes[treatment] ?? '';
    const supplyType = form.querySelector('[data-billing-supply-type]');
    const buyerType = form.querySelector('[data-billing-buyer-type]');
    const transactionRegion = form.querySelector('[data-billing-transaction-region]');
    const transactionRegionDisplay = form.querySelector('[data-billing-transaction-region-display]');
    const destinationCountry = form.querySelector('[data-billing-destination-country]');
    const buyerCountryName = form.querySelector('[data-billing-buyer-country-name]');
    const buyerTaxId = form.querySelector('[name="buyer_tax_id"]');
    const buyerCompanyName = form.querySelector('[name="buyer_company_name"]');
    const taxTreatment = form.querySelector('[data-billing-tax-treatment]');
    const taxTreatmentHelp = form.querySelector('[data-billing-tax-treatment-help]');
    const taxNote = form.querySelector('[data-billing-tax-note]');
    const vatAssistant = form.querySelector('[data-billing-vat-assistant]');
    const supplyHelp = form.querySelector('[data-billing-supply-help]');
    const zeroRateTreatments = new Set([
        'small_business_exempt', 'eu_b2b_reverse_charge', 'construction_reverse_charge', 'medical_exempt_entity',
        'medical_exempt_professional', 'export_outside_eu',
    ]);
    const goodsSupplyTypes = new Set(payload.goodsSupplyTypes || ['goods', 'used_goods']);
    const digitalSupplyTypes = new Set(payload.digitalSupplyTypes || ['digital_service']);
    const deriveRegion = () => {
        const seller = String(payload.profileCountry || '').toUpperCase();
        const destination = String(destinationCountry?.value || '').toUpperCase();
        let region = 'outside_eu';
        if (!destination || destination === seller) region = 'domestic';
        else if (destination === 'GB') region = 'uk';
        else if ((payload.euCountries || []).includes(seller) && (payload.euCountries || []).includes(destination)) region = 'eu';
        if (transactionRegion) transactionRegion.value = region;
        if (transactionRegionDisplay) transactionRegionDisplay.value = region;
        if (buyerCountryName) {
            const selectedName = destinationCountry?.selectedOptions[0]?.dataset.countryName
                || payload.customerCountries?.[destination]?.name || destination;
            buyerCountryName.value = selectedName || '';
        }
        return region;
    };
    const defaultRateForCountry = (countryCode) => {
        const country = String(countryCode || '').trim().toUpperCase();
        const rows = (payload.taxRates || []).filter((row) => String(row.country_code || '').toUpperCase() === country && Number(row.is_active || 0) === 1);
        const selected = rows.find((row) => Number(row.is_default || 0) === 1) || rows[0];
        return selected ? numeric(selected.rate_percent) : null;
    };
    const allowedTaxTreatments = () => {
        const supply = supplyType?.value || 'digital_service';
        const buyer = buyerType?.value || 'consumer';
        const region = transactionRegion?.value || 'domestic';
        const vatStatus = String(payload.profileVatStatus || 'vat_registered');
        const registered = vatStatus === 'vat_registered';
        const activity = String(payload.profileBusinessActivity || 'general');
        const ossEnabled = Boolean(payload.profileOssEnabled);
        const medicalExemptionEnabled = Boolean(payload.profileMedicalExemptionEnabled);
        const marginSchemeEnabled = Boolean(payload.profileMarginSchemeEnabled);
        const allowed = ['manual'];
        if (region === 'domestic') {
            if (registered) allowed.unshift('domestic_vat');
            else if (['small_business_exempt', 'not_vat_registered'].includes(vatStatus)) allowed.unshift('small_business_exempt');
            if (medicalExemptionEnabled && ['healthcare_service', 'psychological_service'].includes(supply) && ['healthcare', 'psychology'].includes(activity)) {
                allowed.unshift('medical_exempt_professional');
                allowed.push('medical_exempt_entity');
            }
            if (registered && marginSchemeEnabled && supply === 'used_goods' && activity === 'second_hand_goods') allowed.unshift('margin_scheme_second_hand');
        } else if (region === 'eu') {
            if (buyer === 'business' && registered) allowed.unshift(supply === 'construction_service' ? 'construction_reverse_charge' : 'eu_b2b_reverse_charge');
            else if (registered && ossEnabled && digitalSupplyTypes.has(supply)) allowed.unshift('eu_b2c_destination_oss');
            else if (registered && ossEnabled && goodsSupplyTypes.has(supply)) allowed.unshift('eu_distance_goods_oss');
        } else if (region === 'uk') {
            allowed.unshift('uk_manual');
        } else if (region === 'outside_eu' && goodsSupplyTypes.has(supply)) {
            allowed.unshift('export_outside_eu');
        }
        return Array.from(new Set(allowed));
    };
    const vatRecommendation = () => {
        const allowed = allowedTaxTreatments();
        const treatment = allowed.find((value) => value !== 'manual') || 'manual';
        let rate = null;
        let guidanceKey = treatment;
        const supply = supplyType?.value || 'digital_service';
        const buyer = buyerType?.value || 'consumer';
        const region = transactionRegion?.value || 'domestic';
        if (treatment === 'manual' && region === 'eu' && buyer === 'consumer' && goodsSupplyTypes.has(supply)) guidanceKey = 'eu_goods_manual';
        else if (treatment === 'manual' && region === 'eu' && buyer === 'consumer' && digitalSupplyTypes.has(supply) && !payload.profileOssEnabled) guidanceKey = 'oss_disabled';
        else if (treatment === 'manual' && region === 'outside_eu' && !goodsSupplyTypes.has(supply)) guidanceKey = 'outside_service_manual';
        let message = payload.taxTreatmentGuidance?.[guidanceKey] || payload.taxAssistant?.filtered || '';
        if (zeroRateTreatments.has(treatment)) {
            rate = 0;
        } else if (treatment === 'domestic_vat') {
            rate = defaultRateForCountry(payload.profileCountry);
        } else if (['eu_b2c_destination_oss', 'eu_distance_goods_oss'].includes(treatment)) {
            const destination = String(destinationCountry?.value || '').trim().toUpperCase();
            if (!/^[A-Z]{2}$/.test(destination)) message = payload.taxAssistant?.destinationMissing || message;
            else {
                rate = defaultRateForCountry(destination);
                if (rate === null) message = payload.taxAssistant?.noRate || message;
            }
        } else {
            message = payload.taxTreatmentGuidance?.[guidanceKey] || payload.taxAssistant?.manual || message;
        }
        return { allowed, treatment, rate, message, automatic: !['manual', 'uk_manual'].includes(treatment) };
    };
    const renderVatAssistant = () => {
        deriveRegion();
        if (supplyHelp) supplyHelp.textContent = payload.supplyHelp?.[supplyType?.value] || '';
        const recommendation = vatRecommendation();
        if (taxTreatment) {
            const previousTreatment = taxTreatment.value;
            taxTreatment.replaceChildren(...recommendation.allowed.map((value) => new Option(String(payload.taxTreatments?.[value] || value), value)));
            if (recommendation.allowed.includes(previousTreatment)) taxTreatment.value = previousTreatment;
            else {
                taxTreatment.value = recommendation.treatment;
                if (taxNote) taxNote.value = taxNoteFor(taxTreatment.value);
            }
        }
        if (taxTreatmentHelp) {
            taxTreatmentHelp.textContent = payload.taxTreatmentGuidance?.[taxTreatment?.value || recommendation.treatment]
                || payload.taxTreatmentGuidance?.manual
                || '';
        }
        const marginSelected = (taxTreatment?.value || recommendation.treatment) === 'margin_scheme_second_hand';
        if (marginSelected && priceMode) priceMode.value = 'gross';
        itemsRoot?.querySelectorAll('[data-billing-item-purchase]').forEach((input) => {
            input.hidden = !marginSelected;
            const field = input.closest('.admin-billing-item__field');
            if (field) field.hidden = !marginSelected;
        });
        const vatRatesList = form.querySelector('[data-billing-vat-rates]');
        if (vatRatesList) {
            const selectedTreatment = taxTreatment?.value || recommendation.treatment;
            const rateCountry = ['eu_b2c_destination_oss', 'eu_distance_goods_oss'].includes(selectedTreatment)
                ? String(destinationCountry?.value || '').trim().toUpperCase()
                : String(payload.profileCountry || '').toUpperCase();
            const rows = zeroRateTreatments.has(selectedTreatment)
                ? [{ rate_percent: 0, label: '0%' }]
                : (payload.taxRates || []).filter((row) => String(row.country_code || '').toUpperCase() === rateCountry && Number(row.is_active || 0) === 1);
            vatRatesList.replaceChildren(...rows.map((row) => {
                const option = document.createElement('option');
                option.value = String(numeric(row.rate_percent));
                option.label = String(row.label || `${option.value}%`);
                return option;
            }));
        }
        if (!vatAssistant) return;
        const treatmentLabel = taxTreatment?.querySelector(`option[value="${recommendation.treatment}"]`)?.textContent?.trim() || recommendation.treatment;
        const treatmentOutput = vatAssistant.querySelector('[data-billing-vat-treatment]');
        const rateOutput = vatAssistant.querySelector('[data-billing-vat-rate]');
        const messageOutput = vatAssistant.querySelector('[data-billing-vat-message]');
        const stateOutput = vatAssistant.querySelector('[data-billing-vat-state]');
        const contextOutput = vatAssistant.querySelector('[data-billing-vat-context]');
        if (treatmentOutput) treatmentOutput.textContent = `${payload.taxAssistant?.recommended || ''}: ${treatmentLabel}`;
        if (rateOutput) rateOutput.textContent = recommendation.rate === null ? '' : `${payload.taxAssistant?.rate || 'VAT'}: ${recommendation.rate.toLocaleString(undefined, { maximumFractionDigits: 2 })}%`;
        if (messageOutput) messageOutput.textContent = recommendation.message;
        vatAssistant.classList.toggle('is-manual', !recommendation.automatic);
        if (stateOutput) {
            stateOutput.textContent = recommendation.automatic ? (payload.taxAssistant?.automatic || '') : (payload.taxAssistant?.manualReview || '');
            stateOutput.classList.toggle('is-manual', !recommendation.automatic);
        }
        if (contextOutput) {
            const destination = String(destinationCountry?.value || '').toUpperCase() || '?';
            const region = transactionRegion?.value || 'domestic';
            contextOutput.textContent = `${payload.taxAssistant?.context || ''}: ${payload.taxAssistant?.seller || ''} ${payload.profileCountry || '?'} · ${payload.taxAssistant?.buyer || ''} ${destination} (${payload.buyerLabels?.[buyerType?.value || 'consumer'] || ''}) · ${payload.taxAssistant?.supply || ''} ${payload.supplyLabels?.[supplyType?.value || 'digital_service'] || ''} · ${payload.regionLabels?.[region] || region}`;
        }
        const rateButton = vatAssistant.querySelector('[data-billing-apply-vat-rate]');
        if (rateButton) rateButton.disabled = recommendation.rate === null;
        const vatIdReview = form.querySelector('[data-billing-vat-id-review]');
        if (vatIdReview) vatIdReview.hidden = !['eu_b2b_reverse_charge', 'construction_reverse_charge'].includes(taxTreatment?.value || recommendation.treatment);
        const ksefReview = form.querySelector('[data-billing-ksef-review]');
        if (ksefReview) ksefReview.hidden = !(String(payload.profileCountry || '') === 'PL' && buyerType?.value === 'business');
        renderIssueChecklist();
    };
    const applyRecommendedRate = () => {
        const recommendation = vatRecommendation();
        if (recommendation.rate === null) return;
        itemsRoot?.querySelectorAll('[name="item_tax_rate[]"]').forEach((input) => { input.value = String(recommendation.rate); });
        calculate();
        queueDraftSave();
    };
    const selectedSourceRecord = () => {
        if (!sourceType || sourceType.value === 'independent' || !sourceId?.value || sourceId.value === '0') return null;
        return sourceRows(sourceType.value).find((row) => String(row.id) === String(sourceId.value)) || null;
    };
    renderSourceAudit = () => {
        const wrapper = form.querySelector('[data-billing-source-audit]');
        if (!wrapper) return;
        const row = selectedSourceRecord();
        wrapper.hidden = !row;
        if (!row) return;
        const expectedAmount = numeric(sourceType?.value === 'order' ? row.total_amount : row.amount);
        const expectedCurrency = String(row.currency_code || '').toUpperCase();
        const documentCurrency = String(form.querySelector('[data-billing-currency]')?.value || '').toUpperCase();
        const mismatch = expectedCurrency !== documentCurrency || Math.abs(expectedAmount - calculatedTotals.gross) > 0.02;
        wrapper.classList.toggle('is-mismatch', mismatch);
        const expected = wrapper.querySelector('[data-billing-source-expected]');
        const documentValue = wrapper.querySelector('[data-billing-source-document]');
        const message = wrapper.querySelector('[data-billing-source-audit-message]');
        const override = wrapper.querySelector('[data-billing-source-override]');
        if (expected) expected.textContent = `${expectedAmount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${expectedCurrency}`;
        if (documentValue) documentValue.textContent = money(calculatedTotals.gross);
        if (message) message.textContent = mismatch
            ? (payload.locale === 'pl' ? 'Dokument różni się od opłaconego źródła.' : 'The document differs from the paid source.')
            : (payload.locale === 'pl' ? 'Kwota i waluta są zgodne ze źródłem.' : 'Amount and currency match the source.');
        if (override) override.hidden = !mismatch;
    };
    renderIssueChecklist = () => {
        const checklist = form.querySelector('[data-billing-checklist-items]');
        if (!checklist) return;
        const isPl = payload.locale === 'pl';
        const source = selectedSourceRecord();
        const sourceRequired = sourceType?.value !== 'independent';
        const expectedAmount = source ? numeric(sourceType?.value === 'order' ? source.total_amount : source.amount) : 0;
        const expectedCurrency = source ? String(source.currency_code || '').toUpperCase() : '';
        const documentCurrency = String(form.querySelector('[data-billing-currency]')?.value || '').toUpperCase();
        const sourceMismatch = Boolean(source) && (expectedCurrency !== documentCurrency || Math.abs(expectedAmount - calculatedTotals.gross) > 0.02);
        const sourceOverrideChecked = Boolean(form.querySelector('[name="source_total_override_confirmed"]')?.checked);
        const requiredBuyerFields = ['buyer_name', 'buyer_address_line1', 'buyer_postal_code', 'buyer_city', 'destination_country_code'];
        const buyerReady = requiredBuyerFields.every((name) => String(form.querySelector(`[name="${name}"]`)?.value || '').trim() !== '');
        const descriptionsReady = Array.from(form.querySelectorAll('[name="item_description[]"]')).some((input) => input.value.trim() !== '');
        const issueDate = String(form.querySelector('[name="issue_date"]')?.value || '');
        const serviceDate = String(form.querySelector('[name="service_date"]')?.value || '');
        const dueDate = String(form.querySelector('[name="due_date"]')?.value || '');
        const datesReady = Boolean(issueDate && serviceDate) && (sourceRequired || Boolean(dueDate && dueDate >= issueDate));
        const itemRows = Array.from(form.querySelectorAll('[data-billing-item]'));
        const numbersReady = itemRows.length <= 200 && itemRows.every((row) => {
            if (!row.querySelector('[name="item_description[]"]')?.value.trim()) return true;
            const quantity = numeric(row.querySelector('[name="item_quantity[]"]')?.value);
            const price = numeric(row.querySelector('[name="item_unit_price[]"]')?.value);
            const rate = numeric(row.querySelector('[name="item_tax_rate[]"]')?.value);
            return [quantity, price, rate].every(Number.isFinite) && quantity > 0 && quantity < 1000000000 && price >= 0 && price < 100000000 && rate >= 0 && rate <= 100;
        });
        const selectedTreatment = taxTreatment?.value || '';
        const itemRates = Array.from(form.querySelectorAll('[name="item_tax_rate[]"]')).map((input) => Math.round(numeric(input.value) * 100) / 100);
        const applicableRateCountry = ['eu_b2c_destination_oss', 'eu_distance_goods_oss'].includes(selectedTreatment)
            ? String(destinationCountry?.value || '').toUpperCase()
            : String(payload.profileCountry || '').toUpperCase();
        const activeRates = (payload.taxRates || [])
            .filter((row) => String(row.country_code || '').toUpperCase() === applicableRateCountry && Number(row.is_active || 0) === 1)
            .map((row) => Math.round(numeric(row.rate_percent) * 100) / 100);
        const vatRatesReady = zeroRateTreatments.has(selectedTreatment)
            ? itemRates.every((rate) => rate === 0)
            : (!['domestic_vat', 'eu_b2c_destination_oss', 'eu_distance_goods_oss', 'margin_scheme_second_hand'].includes(selectedTreatment)
                || itemRates.every((rate) => activeRates.includes(rate)));
        const marginReady = selectedTreatment !== 'margin_scheme_second_hand' || (priceMode?.value === 'gross'
            && Array.from(form.querySelectorAll('[data-billing-item]')).every((row) => {
                const sale = numeric(row.querySelector('[name="item_unit_price[]"]')?.value);
                const purchaseInput = row.querySelector('[name="item_purchase_price[]"]');
                const purchase = numeric(purchaseInput?.value);
                const rate = numeric(row.querySelector('[name="item_tax_rate[]"]')?.value);
                return purchaseInput?.value !== '' && purchase >= 0 && purchase <= sale && rate > 0;
            }));
        const vatIdReady = !['eu_b2b_reverse_charge', 'construction_reverse_charge'].includes(selectedTreatment)
            || (Boolean(buyerTaxId?.value.trim()) && Boolean(form.querySelector('[name="buyer_vat_id_verified"]')?.checked));
        const foreignCurrencyRequired = calculatedTotals.tax > 0.005 && documentCurrency !== String(payload.profileDefaultCurrency || documentCurrency).toUpperCase();
        const foreignReview = form.querySelector('[data-billing-foreign-currency-review]');
        if (foreignReview) foreignReview.hidden = !foreignCurrencyRequired;
        const foreignReady = !foreignCurrencyRequired || Boolean(form.querySelector('[name="foreign_currency_review_confirmed"]')?.checked);
        const ksefRequired = String(payload.profileCountry || '') === 'PL' && buyerType?.value === 'business';
        const ksefReady = !ksefRequired || Boolean(form.querySelector('[name="external_reference"]')?.value.trim()) || Boolean(form.querySelector('[name="pl_ksef_review_confirmed"]')?.checked);
        const taxNoteReady = selectedTreatment !== '' && (selectedTreatment === 'domestic_vat' || Boolean(taxNote?.value.trim()));
        const checks = [
            [Boolean(payload.profileReadiness?.ready), isPl ? 'Profil wystawcy jest kompletny i aktywny.' : 'Issuer profile is complete and active.'],
            [buyerReady, isPl ? 'Dane i kraj nabywcy są kompletne.' : 'Buyer details and country are complete.'],
            [datesReady, isPl ? 'Daty wystawienia, sprzedaży i termin płatności są poprawne.' : 'Issue, supply and due dates are valid.'],
            [descriptionsReady && numbersReady && Number.isFinite(calculatedTotals.gross) && calculatedTotals.gross >= 0 && calculatedTotals.gross <= 9999999999.99, isPl ? 'Pozycje i suma dokumentu są gotowe.' : 'Items and document total are ready.'],
            sourceRequired ? [Boolean(source), isPl ? 'Wybrano właściwe źródło dokumentu.' : 'The document source is selected.'] : null,
            source ? [!sourceMismatch || sourceOverrideChecked, isPl ? 'Kwota dokumentu jest zgodna ze źródłem albo różnica została potwierdzona.' : 'Source amount matches or the difference is confirmed.'] : null,
            ['eu_b2b_reverse_charge', 'construction_reverse_charge'].includes(selectedTreatment) ? [vatIdReady, isPl ? 'VAT ID dla reverse charge został sprawdzony.' : 'Reverse-charge VAT ID is verified.'] : null,
            [taxNoteReady, isPl ? 'Wybrano sposób opodatkowania i adnotację.' : 'Tax treatment and note are selected.'],
            [vatRatesReady, isPl ? 'Stawki VAT pozycji pasują do wybranego wariantu i kraju.' : 'Item VAT rates match the selected treatment and country.'],
            selectedTreatment === 'margin_scheme_second_hand' ? [marginReady, isPl ? 'Cena nabycia i kalkulacja marży są kompletne.' : 'Purchase cost and margin calculation are complete.'] : null,
            foreignCurrencyRequired ? [foreignReady, isPl ? 'Rozliczenie VAT w obcej walucie zostało potwierdzone.' : 'Foreign-currency VAT reporting is confirmed.'] : null,
        ].filter(Boolean);
        if (ksefRequired) {
            checks.push([ksefReady, isPl ? 'Obsługa KSeF została potwierdzona.' : 'The KSeF workflow is confirmed.']);
        }
        checks.push([Boolean(form.querySelector('[name="tax_review_confirmed"]')?.checked), isPl ? 'Końcowa kontrola dokumentu została potwierdzona.' : 'Final document review is confirmed.']);
        checklist.replaceChildren(...checks.map(([ok, label]) => {
            const item = document.createElement('div');
            item.className = ok ? 'is-ok' : 'is-blocked';
            item.innerHTML = `<i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-circle'}" aria-hidden="true"></i><span></span>`;
            item.querySelector('span').textContent = label;
            return item;
        }));
        const issueButton = form.querySelector('[data-billing-issue-button]');
        if (issueButton) issueButton.disabled = checks.some(([ok]) => !ok);
    };
    [supplyType, buyerType, destinationCountry].forEach((control) => {
        control?.addEventListener('change', renderVatAssistant);
    });
    [buyerTaxId, buyerCompanyName].forEach((control) => control?.addEventListener('input', () => {
        if (buyerType && (buyerTaxId?.value.trim() || buyerCompanyName?.value.trim())) buyerType.value = 'business';
        renderVatAssistant();
    }));
    taxTreatment?.addEventListener('change', () => {
        if (taxNote) taxNote.value = taxNoteFor(taxTreatment.value);
        renderVatAssistant();
        calculate();
    });
    vatAssistant?.querySelector('[data-billing-apply-vat]')?.addEventListener('click', () => {
        const recommendation = vatRecommendation();
        if (taxTreatment) taxTreatment.value = recommendation.treatment;
        if (taxNote) taxNote.value = taxNoteFor(recommendation.treatment);
        applyRecommendedRate();
        renderVatAssistant();
    });
    vatAssistant?.querySelector('[data-billing-apply-vat-rate]')?.addEventListener('click', applyRecommendedRate);
    form.querySelectorAll('[name="source_total_override_confirmed"], [name="buyer_vat_id_verified"], [name="foreign_currency_review_confirmed"], [name="pl_ksef_review_confirmed"], [name="tax_review_confirmed"], [name="external_reference"], [name="buyer_name"], [name="buyer_address_line1"], [name="buyer_postal_code"], [name="buyer_city"], [name="issue_date"], [name="service_date"], [name="due_date"]').forEach((control) => {
        control.addEventListener('input', renderIssueChecklist);
        control.addEventListener('change', renderIssueChecklist);
    });
    form.querySelector('[data-billing-currency]')?.addEventListener('change', () => { renderSourceAudit(); renderIssueChecklist(); });
    renderVatAssistant();
    calculate();
    renderSourceAudit();
    renderIssueChecklist();
});

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-provider-remote]');
    const node = document.getElementById('providerRemoteModal');
    const form = node?.querySelector('[data-provider-remote-form]');
    if (!root || !form || !window.bootstrap?.Modal) return;
    const pl = root.dataset.locale === 'pl';
    const modal = window.bootstrap.Modal.getOrCreateInstance(node);
    const result = root.querySelector('[data-provider-remote-result]');
    const modalResult = node.querySelector('[data-provider-remote-modal-result]');
    const configure = root.querySelector('[data-provider-remote-config]');
    const test = root.querySelector('[data-provider-remote-test]');
    const save = node.querySelector('[data-provider-remote-save]');
    const inspect = node.querySelector('[data-provider-remote-inspect]');
    const inspectResult = node.querySelector('[data-provider-remote-inspect-result]');
    const challengeBox = root.querySelector('[data-provider-remote-challenge]');
    const answer = root.querySelector('[data-provider-remote-answer]');
    const captchaImage = root.querySelector('[data-provider-remote-captcha-image]');
    const complete = root.querySelector('[data-provider-remote-complete]');
    const cancel = root.querySelector('[data-provider-remote-cancel]');
    let challenge = '';
    let challengeTimer = 0;
    let busy = false;
    let configurationLoaded = false;
    const confirmationKind = form.elements.namedItem('success_kind');
    const confirmationValue = form.elements.namedItem('success_value');
    const detectionMode = form.elements.namedItem('mode');
    const updateDetection = () => {
        const automatic = detectionMode.value === 'auto';
        form.querySelector('[data-provider-remote-manual-fields]').hidden = automatic;
        form.querySelector('[data-provider-remote-detection-hint]').textContent = automatic
            ? (pl ? 'Podaj login i hasło. Pola formularza i przycisk logowania rozpoznamy automatycznie na podstawie zapisanego URL providera.' : 'Enter your username and password. Login fields and the submit button are detected from the saved provider URL.')
            : (pl ? 'Używasz ręcznej konfiguracji formularza. Możesz włączyć automatyczne rozpoznawanie w ustawieniach zaawansowanych.' : 'Manual form settings are active. Automatic detection is available in advanced settings.');
    };
    detectionMode.addEventListener('change', updateDetection);
    updateDetection();
    const updateConfirmation = () => {
        const automatic = confirmationKind.value === 'auto';
        confirmationValue.disabled = automatic;
        confirmationValue.required = !automatic;
        confirmationValue.hidden = automatic;
        const hint = form.querySelector('[data-provider-remote-confirmation-hint]');
        if (hint) hint.textContent = automatic
            ? (pl ? 'Potwierdzenie logowania wykrywamy automatycznie. Własny warunek możesz ustawić w opcjach zaawansowanych.' : 'Login is detected automatically. A custom condition is available in advanced settings.')
            : (pl ? 'Używasz własnego warunku potwierdzenia logowania z ustawień zaawansowanych.' : 'A custom login confirmation condition is configured in advanced settings.');
    };
    confirmationKind.addEventListener('change', updateConfirmation);
    updateConfirmation();
    const show = (target, message, ok = false, code = '') => {
        const state = ok ? 'success' : code === 'pending' ? 'pending'
            : ['rejected','captcha_rejected','connection','storage','browser_unavailable'].includes(code) ? 'error'
            : code === 'cancelled' ? 'info' : 'warning';
        const icons = {success:'bi-check-circle-fill',warning:'bi-exclamation-triangle-fill',error:'bi-x-circle-fill',info:'bi-info-circle-fill',pending:'bi-arrow-repeat'};
        const icon = document.createElement('i');
        icon.className = `bi ${icons[state]}`;
        icon.setAttribute('aria-hidden','true');
        const text = document.createElement('span');
        text.textContent = message;
        target.replaceChildren(icon,text);
        target.classList.remove('is-success','is-warning','is-error','is-info','is-pending');
        target.classList.add(`is-${state}`);
        target.hidden = false;
    };
    const request = async (action, data = new FormData()) => {
        data.set('_csrf', root.dataset.csrf);
        data.set('provider_id', root.dataset.providerId);
        data.set('action', action);
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), ['inspect','test','test_complete'].includes(action) ? 65000 : 10000);
        try {
        const response = await fetch('/admin/provider_remote.php', { method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store', signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        let payload;
        try { payload = await response.json(); }
        catch { const error = new Error(pl ? 'Nie otrzymano poprawnej odpowiedzi. Spróbuj ponownie za chwilę.' : 'No valid response. Try again in a moment.'); error.code = 'connection'; throw error; }
        if (!payload || typeof payload !== 'object') { const error = new Error(pl ? 'Nie otrzymano poprawnej odpowiedzi.' : 'No valid response.'); error.code = 'connection'; throw error; }
        if (!response.ok) {
            const error = new Error(payload.message || (pl ? 'Nie udało się wykonać operacji.' : 'Unable to complete the request.'));
            error.code = payload.code; throw error;
        }
        return payload;
        } catch (error) {
            if (error.name === 'AbortError' || error instanceof TypeError) {
                const failure = new Error(pl ? 'Brak odpowiedzi z serwera. Spróbuj ponownie za chwilę.' : 'No response from the server. Try again in a moment.');
                failure.code = error.name === 'AbortError' ? 'limit' : 'connection'; throw failure;
            }
            throw error;
        } finally { window.clearTimeout(timer); }
    };
    const clearChallenge = () => {
        challenge = '';
        window.clearTimeout(challengeTimer);
        challengeBox.hidden = true;
        captchaImage.removeAttribute('src');
        answer.value = '';
    };
    const challengeData = () => { const data = new FormData(); data.set('challenge',challenge); return data; };
    const cancelChallenge = async () => {
        if (!challenge) return;
        const data = challengeData(); clearChallenge();
        try { await request('test_cancel',data); } catch { /* The worker also expires its memory-only session. */ }
    };
    const setBusy = value => {
        busy = value;
        [configure,test,save,inspect,complete,cancel].forEach(button => { if (button) button.disabled = value; });
        if (save) save.disabled = value || !configurationLoaded;
        root.setAttribute('aria-busy', String(value));
    };
    configure?.addEventListener('click', async () => {
        if (busy) return;
        configurationLoaded = false;
        setBusy(true);
        await cancelChallenge();
        form.reset();
        updateDetection();
        form.querySelectorAll('details').forEach(details=>{details.open=false;});
        updateConfirmation();
        modalResult.hidden = true;
        inspectResult.hidden = true;
        node.querySelector('[data-provider-remote-scan-url]').textContent = document.getElementById('product_provider_dashboard_url')?.defaultValue || '';
        modal.show();
        try {
            const payload = await request('load');
            if (!payload.ok) { show(modalResult,payload.message,false,payload.code); return; }
            configurationLoaded = true;
            for (const [name,value] of Object.entries(payload.config || {})) {
                const field = form.elements.namedItem(name);
                if (!field) continue;
                if (field.type === 'checkbox') field.checked = Boolean(value);
                else field.value = String(value);
            }
            form.elements.namedItem('username').value = payload.username || '';
            form.elements.namedItem('password').value = '';
            updateConfirmation();
            updateDetection();
            node.querySelector('[data-provider-remote-password-hint]').textContent = payload.has_password
                ? (pl ? 'Hasło zapisane. Pozostaw puste, aby go nie zmieniać.' : 'Password saved. Leave empty to keep it.')
                : (pl ? 'Hasło będzie zapisane w postaci zaszyfrowanej.' : 'The password will be stored encrypted.');
        } catch (error) { show(modalResult, error.message,false,error.code); }
        finally { setBusy(false); }
    });
    inspect.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true);
        await cancelChallenge();
        show(inspectResult, pl ? 'Sprawdzanie panelu…' : 'Checking panel…',false,'pending');
        try {
            const data = new FormData();
            data.set('login_path', form.elements.namedItem('login_path').value);
            const payload = await request('inspect', data);
            if (payload.ok && payload.code === 'detected') {
                for (const [name, value] of Object.entries(payload.config || {})) {
                    const field = form.elements.namedItem(name);
                    if (!field || ['username','password'].includes(name)) continue;
                    if (field.type === 'checkbox') field.checked = Boolean(value);
                    else field.value = String(value);
                }
                updateDetection(); updateConfirmation();
                modalResult.hidden = true;
            }
            show(inspectResult, payload.message, payload.ok, payload.code);
        } catch (error) { show(inspectResult, error.message,false,error.code); }
        finally { setBusy(false); }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !configurationLoaded) return;
        setBusy(true);
        try {
            const payload = await request('save',new FormData(form));
            show(modalResult,payload.message,payload.ok,payload.code);
            if (payload.ok) {
                form.elements.namedItem('password').value = '';
                result.hidden = true;
                node.querySelector('[data-provider-remote-password-hint]').textContent = pl ? 'Pozostaw puste, aby zachować zapisane hasło.' : 'Leave empty to keep the saved password.';
            }
        } catch (error) { show(modalResult,error.message,false,error.code); }
        finally { setBusy(false); }
    });
    test?.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true);
        await cancelChallenge();
        show(result,pl?'Sprawdzanie logowania…':'Testing login…',false,'pending');
        try {
            const payload = await request('test'); show(result,payload.message,payload.ok,payload.code);
            if (payload.code === 'captcha_required' && payload.challenge && payload.image?.startsWith('data:image/png;base64,')) {
                challenge = payload.challenge;
                captchaImage.src = payload.image;
                challengeBox.hidden = false;
                challengeTimer = window.setTimeout(() => { cancelChallenge(); show(result,pl?'Próba wygasła. Uruchom nowy test.':'The attempt expired. Start a new test.',false,'expired'); },120000);
                answer.focus();
            }
        }
        catch (error) { show(result,error.message,false,error.code); }
        finally { setBusy(false); }
    });
    complete?.addEventListener('click', async () => {
        if (busy || !challenge || !answer.value.trim()) return;
        setBusy(true);
        const data = challengeData(); data.set('answer',answer.value);
        try { const payload = await request('test_complete',data); show(result,payload.message,payload.ok,payload.code); }
        catch (error) { show(result,error.message,false,error.code); }
        finally { clearChallenge(); setBusy(false); }
    });
    cancel?.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true); await cancelChallenge(); setBusy(false);
        show(result,pl?'Test anulowany.':'Test cancelled.',false,'cancelled');
    });
    answer?.addEventListener('keydown',event => { if (event.key==='Enter') { event.preventDefault(); complete.click(); } });
    window.addEventListener('pagehide',() => {
        if (!challenge) return;
        const data = challengeData();
        data.set('_csrf',root.dataset.csrf); data.set('provider_id',root.dataset.providerId); data.set('action','test_cancel');
        navigator.sendBeacon('/admin/provider_remote.php',data);
        clearChallenge();
    });
    node.addEventListener('hidden.bs.modal', () => {
        form.elements.namedItem('password').value = '';
        modalResult.hidden = true;
    });
});
