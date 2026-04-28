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

    function initRichTextEditors() {
        var allowedTags = {
            P: true,
            BR: true,
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
            DIV: true,
            SPAN: true
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

            return lower.indexOf('http://') === 0
                || lower.indexOf('https://') === 0
                || lower.indexOf('mailto:') === 0
                || lower.indexOf('tel:') === 0
                || lower.indexOf('/') === 0
                || lower.indexOf('#') === 0;
        }

        function sanitizeStyle(value) {
            var style = String(value || '');
            var match = style.match(/text-align\s*:\s*(left|center|right|justify)/i);
            return match ? 'text-align:' + match[1].toLowerCase() + ';' : '';
        }

        function sanitizeHtml(html) {
            var template = doc.createElement('template');
            template.innerHTML = String(html || '');

            function walk(node) {
                var children = Array.prototype.slice.call(node.childNodes || []);
                children.forEach(function (child) {
                    if (child.nodeType === 1) {
                        var tag = child.tagName ? child.tagName.toUpperCase() : '';
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

                            if (attrName === 'style') {
                                var safeStyle = sanitizeStyle(attrValue);
                                if (safeStyle) {
                                    child.setAttribute('style', safeStyle);
                                } else {
                                    child.removeAttribute('style');
                                }
                                return;
                            }

                            if (tag === 'A' && (attrName === 'target' || attrName === 'rel' || attrName === 'title')) {
                                return;
                            }

                            if (tag === 'IMG' && (attrName === 'alt' || attrName === 'title' || attrName === 'width' || attrName === 'height')) {
                                return;
                            }

                            child.removeAttribute(attribute.name);
                        });

                        if (tag === 'A' && child.getAttribute('href')) {
                            child.setAttribute('target', '_blank');
                            child.setAttribute('rel', 'noopener noreferrer');
                        }

                        walk(child);
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

        qa('textarea[data-admin-rich-editor]').forEach(function (textarea) {
            if (!textarea || textarea.getAttribute('data-admin-rich-editor-ready') === '1') {
                return;
            }

            textarea.setAttribute('data-admin-rich-editor-ready', '1');

            var allowImages = textarea.getAttribute('data-admin-rich-editor-images') !== '0';
            var wrapper = doc.createElement('div');
            wrapper.className = 'admin-rich-editor';

            var toolbar = doc.createElement('div');
            toolbar.className = 'admin-rich-editor__toolbar';

            var tools = [
                { action: 'paragraph', label: 'Paragraph', html: '<strong>P</strong>' },
                { action: 'h2', label: 'Heading 2', html: '<strong>H2</strong>' },
                { action: 'h3', label: 'Heading 3', html: '<strong>H3</strong>' },
                { action: 'separator', label: '', html: '' },
                { action: 'bold', label: 'Bold', html: '<i class="bi bi-type-bold"></i>' },
                { action: 'italic', label: 'Italic', html: '<i class="bi bi-type-italic"></i>' },
                { action: 'underline', label: 'Underline', html: '<i class="bi bi-type-underline"></i>' },
                { action: 'separator', label: '', html: '' },
                { action: 'unorderedList', label: 'Bullet list', html: '<i class="bi bi-list-ul"></i>' },
                { action: 'orderedList', label: 'Numbered list', html: '<i class="bi bi-list-ol"></i>' },
                { action: 'quote', label: 'Quote', html: '<strong>&ldquo;</strong>' },
                { action: 'link', label: 'Insert link', html: '<i class="bi bi-link-45deg"></i>' },
                { action: 'separator', label: '', html: '' },
                { action: 'alignLeft', label: 'Align left', html: '<strong>L</strong>' },
                { action: 'alignCenter', label: 'Align center', html: '<strong>C</strong>' },
                { action: 'alignRight', label: 'Align right', html: '<strong>R</strong>' },
                { action: 'clear', label: 'Clear formatting', html: '<i class="bi bi-eraser"></i>' }
            ];

            if (allowImages) {
                tools.push({ action: 'image', label: 'Insert image from disk', html: '<i class="bi bi-image"></i>' });
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
                toolbar.appendChild(renderToolbarButton(tool));
            });

            var surface = doc.createElement('div');
            surface.className = 'admin-rich-editor__surface';
            surface.contentEditable = 'true';
            surface.setAttribute('data-placeholder', 'Write content here...');

            var source = doc.createElement('textarea');
            source.className = 'admin-rich-editor__source form-control';
            source.setAttribute('hidden', 'hidden');

            var fileInput = doc.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = 'image/*';
            fileInput.hidden = true;

            wrapper.appendChild(toolbar);
            wrapper.appendChild(surface);
            wrapper.appendChild(source);
            wrapper.appendChild(fileInput);
            textarea.insertAdjacentElement('afterend', wrapper);
            textarea.hidden = true;

            function isSourceMode() {
                return !source.hasAttribute('hidden');
            }

            function syncTextareaFromSurface() {
                textarea.value = sanitizeHtml(surface.innerHTML);
            }

            function syncSourceFromSurface() {
                syncTextareaFromSurface();
                source.value = textarea.value;
            }

            function syncSurfaceFromSource() {
                surface.innerHTML = sanitizeHtml(source.value);
                syncTextareaFromSurface();
            }

            function updateToggleButton() {
                qa('[data-rich-editor-action="source"]', toolbar).forEach(function (button) {
                    button.classList.toggle('is-active', isSourceMode());
                });
            }

            function runCommand(command, value) {
                if (isSourceMode()) {
                    return;
                }

                surface.focus();
                doc.execCommand('styleWithCSS', false, true);
                if (command === 'formatBlock') {
                    doc.execCommand(command, false, value);
                } else if (command === 'insertHTML') {
                    doc.execCommand(command, false, value);
                } else {
                    doc.execCommand(command, false, value || null);
                }
                syncTextareaFromSurface();
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

                    var imageHtml = '<p><img src="' + escapeHtml(result) + '" alt="' + escapeHtml(file.name || 'image') + '"></p>';
                    if (isSourceMode()) {
                        source.value += (source.value ? '\n' : '') + imageHtml;
                        syncSurfaceFromSource();
                    } else {
                        runCommand('insertHTML', imageHtml);
                    }
                };
                reader.readAsDataURL(file);
            }

            surface.innerHTML = sanitizeHtml(textarea.value);
            syncTextareaFromSurface();
            updateToggleButton();

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
                if (action === 'image') {
                    fileInput.click();
                    return;
                }
                if (action === 'source') {
                    toggleSourceMode();
                }
            });

            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (file) {
                    insertImageFromFile(file);
                }
                fileInput.value = '';
            });

            surface.addEventListener('input', syncTextareaFromSurface);
            surface.addEventListener('blur', syncTextareaFromSurface);
            surface.addEventListener('paste', function () {
                window.setTimeout(syncTextareaFromSurface, 0);
            });

            source.addEventListener('input', function () {
                textarea.value = source.value;
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

    function initProviderUrlReplacement() {
        qa('[data-provider-url-replacement-scope]').forEach(function (scope) {
            var toggle = q('[data-provider-url-replacement-toggle]', scope);
            var section = q('[data-provider-url-replacement-section]', scope);
            if (!toggle || !section) {
                return;
            }

            var sync = function () {
                var visible = !!toggle.checked;
                setHidden(section, !visible);
                qa('input, textarea, select', section).forEach(function (field) {
                    field.disabled = !visible;
                });
            };

            toggle.addEventListener('change', sync);
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

            var syncTrialConstraint = function () {
                var productType = String(typeField.value || 'subscription').toLowerCase() === 'credits' ? 'credits' : 'subscription';
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
                var productType = String(typeField.value || 'subscription').toLowerCase() === 'credits' ? 'credits' : 'subscription';

                qa('[data-product-type-section]', scope).forEach(function (section) {
                    var sectionType = String(section.getAttribute('data-product-type-section') || '').toLowerCase();
                    var visible = sectionType === productType;

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
            var fulfillmentField = q('[data-admin-order-fulfillment-status]', form);
            var paymentPill = q('[data-admin-order-payment-pill]', form);
            var fulfillmentPill = q('[data-admin-order-fulfillment-pill]', form);

            if (!statusField || !paymentField || !fulfillmentField) {
                return;
            }

            var originalPayment = String(paymentField.value || '').toLowerCase();
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

            var sync = function () {
                var statusValue = String(statusField.value || '').toLowerCase();

                if (statusValue === 'active') {
                    paymentField.value = 'paid';
                    fulfillmentField.value = 'delivered';
                } else if (statusValue === 'pending_payment') {
                    paymentField.value = 'unpaid';
                    fulfillmentField.value = 'pending';
                } else {
                    paymentField.value = originalPayment;
                    fulfillmentField.value = originalFulfillment;
                }

                applyPill(paymentPill, paymentField.value, 'payment', paymentLabels);
                applyPill(fulfillmentPill, fulfillmentField.value, 'fulfillment', fulfillmentLabels);
            };

            statusField.addEventListener('change', sync);
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

        function resetSearch() {
            input.value = '';
            setHidden(results, true);
            results.innerHTML = '';
            defaultContent.removeAttribute('hidden');
            if (reset) {
                setHidden(reset, true);
            }
        }

        function runSearch() {
            var query = input.value.trim();
            if (reset) {
                setHidden(reset, query === '');
            }

            if (query === '') {
                resetSearch();
                return;
            }

            requestIndex += 1;
            var activeRequest = requestIndex;
            defaultContent.setAttribute('hidden', 'hidden');
            setHidden(results, false);
            results.innerHTML = '<div class="admin-panel-card"><div class="admin-search-feedback">' + escapeHtml(loadingText) + '</div></div>';

            jsonFetch(searchUrl + '?' + toQuery({ q: query })).then(function (payload) {
                if (activeRequest !== requestIndex) {
                    return;
                }

                if (!payload.ok) {
                    results.innerHTML = '<div class="admin-panel-card"><div class="admin-search-feedback"><strong>' + escapeHtml(errorTitle) + '</strong><span>' + escapeHtml(errorText) + '</span></div></div>';
                    return;
                }

                results.innerHTML = payload.html || '';
                setHidden(results, !payload.html);
                if (!payload.html) {
                    defaultContent.removeAttribute('hidden');
                }
            }).catch(function () {
                if (activeRequest !== requestIndex) {
                    return;
                }
                results.innerHTML = '<div class="admin-panel-card"><div class="admin-search-feedback"><strong>' + escapeHtml(errorTitle) + '</strong><span>' + escapeHtml(errorText) + '</span></div></div>';
            });
        }

        input.addEventListener('input', debounce(runSearch, 250));
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

    function initAdminHelpModal() {
        var modal = q('#adminHelpModal');
        var input = q('[data-admin-help-search]', modal);
        var results = q('[data-admin-help-results]', modal);
        var dataNode = q('#adminHelpTopicsData');
        if (!modal || !input || !results || !dataNode) {
            return;
        }

        var topics = [];
        try {
            topics = JSON.parse(dataNode.textContent || '[]');
        } catch (error) {
            topics = [];
        }

        function normalizeText(value) {
            return String(value || '').toLowerCase().trim();
        }

        function audienceClass(code) {
            if (code === 'admin') {
                return 'admin-help-modal__badge admin-help-modal__badge--admin';
            }
            if (code === 'client') {
                return 'admin-help-modal__badge admin-help-modal__badge--client';
            }
            if (code === 'reseller') {
                return 'admin-help-modal__badge admin-help-modal__badge--reseller';
            }
            return 'admin-help-modal__badge admin-help-modal__badge--all';
        }

        function canEditTopics() {
            return !!doc.body
                && doc.body.getAttribute('data-admin-help-edit-allowed') === '1'
                && doc.body.getAttribute('data-admin-sensitive-unlocked') === '1';
        }

        function render(items) {
            if (!items.length) {
                results.innerHTML = '<div class="admin-help-modal__empty"><strong>'
                    + escapeHtml(modal.getAttribute('data-empty-title') || 'Brak wyników')
                    + '</strong><span>'
                    + escapeHtml(modal.getAttribute('data-empty-text') || 'Spróbuj wpisać inne słowa kluczowe.')
                    + '</span></div>';
                return;
            }

            results.innerHTML = items.map(function (topic) {
                var showEditor = canEditTopics();
                var topicId = parseInt(topic.id || '0', 10) || 0;
                return '<article class="admin-help-modal__topic">'
                    + '<div class="admin-help-modal__topic-shell" data-admin-help-topic data-topic-id="' + topicId + '">'
                    + '<div class="admin-help-modal__topic-meta">'
                    + '<span class="' + audienceClass(String(topic.audience_code || 'all')) + '">'
                    + escapeHtml(String(topic.audience_label || 'All'))
                    + '</span>'
                    + '</div>'
                    + '<h3 class="admin-help-modal__question">' + escapeHtml(String(topic.question || '')) + '</h3>'
                    + '<div class="admin-help-modal__answer" data-admin-help-answer>' + String(topic.answer_html || '') + '</div>'
                    + (showEditor
                        ? '<div class="admin-help-modal__topic-actions">'
                            + '<a href="#" class="admin-help-modal__edit-link" data-admin-help-edit-topic data-topic-id="' + topicId + '">' + escapeHtml(modal.getAttribute('data-edit-label') || 'Edit') + '</a>'
                            + '<span class="admin-help-modal__saved" data-admin-help-saved hidden>' + escapeHtml(modal.getAttribute('data-saved-label') || 'Saved') + '</span>'
                        + '</div>'
                        : '')
                    + (showEditor
                        ? '<div class="admin-help-modal__editor" data-admin-help-editor hidden>'
                            + '<textarea class="form-control" rows="8" spellcheck="false" data-admin-help-edit-input>' + escapeHtml(String(topic.answer_html || '')) + '</textarea>'
                            + '<div class="admin-help-modal__editor-actions">'
                                + '<button type="button" class="btn btn-dark btn-sm" data-admin-help-save-topic data-topic-id="' + topicId + '"><i class="bi bi-save" aria-hidden="true"></i><span>' + escapeHtml(modal.getAttribute('data-save-label') || 'Save') + '</span></button>'
                            + '</div>'
                        + '</div>'
                        : '')
                    + '</div>'
                    + '</article>';
            }).join('');
        }

        function applySearch() {
            var query = normalizeText(input.value);
            if (!query) {
                render(topics);
                return;
            }

            render(topics.filter(function (topic) {
                return normalizeText(topic.search_text || '').indexOf(query) !== -1;
            }));
        }

        modal.addEventListener('click', function (event) {
            var editLink = closest(event.target, '[data-admin-help-edit-topic]');
            if (editLink) {
                var topicNode = closest(editLink, '[data-admin-help-topic]');
                var editorNode = q('[data-admin-help-editor]', topicNode);
                var inputNode = q('[data-admin-help-edit-input]', topicNode);
                var savedNode = q('[data-admin-help-saved]', topicNode);

                event.preventDefault();
                if (!topicNode || !editorNode || !inputNode) {
                    return;
                }

                if (!canEditTopics()) {
                    window.alert(modal.getAttribute('data-locked-error') || 'Unlock Settings access first to edit help content.');
                    return;
                }

                qa('[data-admin-help-topic]', results).forEach(function (otherTopicNode) {
                    if (otherTopicNode !== topicNode) {
                        var otherEditorNode = q('[data-admin-help-editor]', otherTopicNode);
                        var otherSavedNode = q('[data-admin-help-saved]', otherTopicNode);
                        if (otherEditorNode) {
                            otherEditorNode.hidden = true;
                        }
                        if (otherSavedNode) {
                            otherSavedNode.hidden = true;
                        }
                    }
                });

                editorNode.hidden = false;
                if (savedNode) {
                    savedNode.hidden = true;
                }
                inputNode.focus();
                inputNode.setSelectionRange(inputNode.value.length, inputNode.value.length);
                return;
            }

            var saveButton = closest(event.target, '[data-admin-help-save-topic]');
            if (saveButton) {
                var saveTopicId = parseInt(saveButton.getAttribute('data-topic-id') || '0', 10) || 0;
                var saveTopicNode = closest(saveButton, '[data-admin-help-topic]');
                var saveEditorNode = q('[data-admin-help-editor]', saveTopicNode);
                var saveInputNode = q('[data-admin-help-edit-input]', saveTopicNode);
                var saveAnswerNode = q('[data-admin-help-answer]', saveTopicNode);
                var saveSavedNode = q('[data-admin-help-saved]', saveTopicNode);
                var formData = new window.FormData();

                event.preventDefault();
                if (!saveTopicId || !saveTopicNode || !saveEditorNode || !saveInputNode || !saveAnswerNode) {
                    return;
                }

                if (!canEditTopics()) {
                    window.alert(modal.getAttribute('data-locked-error') || 'Unlock Settings access first to edit help content.');
                    return;
                }

                saveButton.disabled = true;
                formData.append('admin_update_help_topic_ajax', '1');
                formData.append('_csrf', (doc.body && doc.body.getAttribute('data-admin-sensitive-csrf')) || '');
                formData.append('topic_id', String(saveTopicId));
                formData.append('answer_html', saveInputNode.value);

                jsonFetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (payload) {
                    var updatedTopic;

                    saveButton.disabled = false;
                    if (!payload || !payload.ok || !payload.topic) {
                        window.alert((payload && payload.message) ? payload.message : (modal.getAttribute('data-save-error') || 'Unable to save help content.'));
                        return;
                    }

                    updatedTopic = payload.topic;
                    topics = topics.map(function (topic) {
                        return (parseInt(topic.id || '0', 10) || 0) === saveTopicId ? updatedTopic : topic;
                    });

                    saveAnswerNode.innerHTML = String(updatedTopic.answer_html || '');
                    saveInputNode.value = String(updatedTopic.answer_html || '');
                    saveEditorNode.hidden = true;
                    if (saveSavedNode) {
                        saveSavedNode.hidden = false;
                    }
                }).catch(function () {
                    saveButton.disabled = false;
                    window.alert(modal.getAttribute('data-save-error') || 'Unable to save help content.');
                });
            }
        });

        modal.addEventListener('shown.bs.modal', function () {
            applySearch();
            window.setTimeout(function () {
                input.focus();
                input.select();
            }, 80);
        });

        modal.addEventListener('hidden.bs.modal', function () {
            input.value = '';
        });

        input.addEventListener('input', debounce(applySearch, 90));
        render(topics);
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
                    var meta = [customer.email || '', customer.status || ''].filter(Boolean).join(' • ');
                    return '' +
                        '<button type="button" class="admin-wallet-customer-picker__result' + (disabled ? ' is-disabled' : '') + '"' +
                            ' data-admin-wallet-customer-result' +
                            ' data-customer-id="' + escapeHtml(customer.id) + '"' +
                            (disabled ? ' disabled' : '') + '>' +
                            '<strong>' + escapeHtml(customer.email || '') + '</strong>' +
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

                jsonFetch(searchUrl + '&' + toQuery({ q: query })).then(function (payload) {
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

    function initOrderCreateForms() {
        qa('[data-admin-order-create]').forEach(function (form) {
            var searchInput = q('[data-admin-order-customer-search]', form);
            var results = q('[data-admin-order-customer-results]', form);
            var customerIdInput = q('[data-admin-order-customer-id]', form);
            var selectedWrap = q('[data-admin-order-customer-selected]', form);
            var selectedLink = q('[data-admin-order-customer-link]', form);
            var selectedEmail = q('[data-admin-order-customer-email]', form);
            var searchUrl = form.getAttribute('data-search-url') || '';
            var createUrl = form.getAttribute('data-create-url') || '';
            var csrfInput = q('input[name="_csrf"]', form);
            var requestIndex = 0;
            var createIndex = 0;
            var selectedCustomerEmail = String(searchInput && searchInput.value ? searchInput.value : '').trim();

            if (!searchInput || !results || !customerIdInput || !selectedWrap || !selectedLink || !selectedEmail || searchUrl === '') {
                return;
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
                selectedWrap.classList.remove('is-selected');
                selectedLink.classList.add('is-disabled');
                selectedLink.setAttribute('href', '#');
                selectedLink.setAttribute('aria-disabled', 'true');
                selectedEmail.textContent = selectedWrap.getAttribute('data-empty-label') || 'No customer assigned';
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
            }

            function renderEmpty(message) {
                results.innerHTML = '<div class="admin-order-picker__empty">' + escapeHtml(message) + '</div>';
                setHidden(results, false);
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
                        '<button type="button" class="admin-order-picker__result" data-admin-order-customer-result data-customer-id="' + escapeHtml(customer.id) + '">' +
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
                jsonFetch(searchUrl + '&' + toQuery({ q: query })).then(function (payload) {
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
                        email: q('strong', customerButton) ? q('strong', customerButton).textContent : ''
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
        });

        function ctypeDigit(value) {
            return /^[0-9]+$/.test(String(value || '').trim());
        }
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
            var article = document.createElement('article');
            article.className = 'admin-topbar-notifications__item admin-topbar-notifications__item--compact';
            var inlineText = activeFlyout ? (activeFlyout.getAttribute('data-topbar-order-created-text') || 'paid and is waiting for approval. Click to open') : 'paid and is waiting for approval. Click to open';
            var openOrderText = activeFlyout ? (activeFlyout.getAttribute('data-topbar-open-order-inline-text') || 'order') : 'order';
            var openPaymentText = activeFlyout ? (activeFlyout.getAttribute('data-topbar-open-payment-inline-text') || 'payment') : 'payment';
            var paymentJoinerText = activeFlyout ? (activeFlyout.getAttribute('data-topbar-open-payment-joiner-text') || 'or') : 'or';
            var customerLabel = String(order.customer_email || '').trim();
            var paymentLink = String(order.payment_url || '').trim();
            article.innerHTML =
                '<div class="admin-topbar-notifications__item-main">'
                + '<p>'
                + '<a class="admin-inline-link admin-payment-summary__email" href="/admin/?page=users&amp;customer_id=' + encodeURIComponent(String(order.customer_id || 0)) + '">'
                + escapeHtml(customerLabel)
                + '</a>'
                + ' ' + escapeHtml(inlineText) + ' '
                + '<a href="' + escapeHtml(String(order.order_url || '/admin/?page=orders')) + '">' + escapeHtml(openOrderText) + '</a>'
                + (paymentLink !== '' ? ' <span>' + escapeHtml(paymentJoinerText) + '</span> <a href="' + escapeHtml(paymentLink) + '">' + escapeHtml(openPaymentText) + '</a>' : '')
                + '</p>'
                + '</div>';
            return article;
        }

        function moveAcceptedPaymentToOrders(flyout, button, payload) {
            var order = payload && payload.order ? payload.order : null;
            var paymentItem = closest(button, '[data-admin-topbar-payment-item]');
            var paymentsSection = q('[data-admin-topbar-payments-section]', flyout);
            var ordersSection = q('[data-admin-topbar-orders-section]', flyout);
            var paymentsList = q('[data-admin-topbar-payments-list]', paymentsSection);
            var paymentsEmpty = q('[data-admin-topbar-payments-empty]', paymentsSection);
            var ordersList = q('[data-admin-topbar-orders-list]', ordersSection);
            var ordersEmpty = q('[data-admin-topbar-orders-empty]', ordersSection);

            if (paymentItem) {
                paymentItem.remove();
            }

            if (paymentsList && !q('[data-admin-topbar-payment-item]', paymentsList)) {
                paymentsList.remove();
                if (!paymentsEmpty && paymentsSection) {
                    paymentsEmpty = document.createElement('div');
                    paymentsEmpty.className = 'admin-topbar-notifications__empty';
                    paymentsEmpty.setAttribute('data-admin-topbar-payments-empty', '1');
                    paymentsEmpty.textContent = flyout.getAttribute('data-topbar-no-payments-text') || 'No pending payments right now.';
                    paymentsSection.appendChild(paymentsEmpty);
                } else if (paymentsEmpty) {
                    paymentsEmpty.removeAttribute('hidden');
                }
            }

            if (order && ordersSection) {
                if (ordersEmpty) {
                    ordersEmpty.remove();
                }
                if (!ordersList) {
                    ordersList = document.createElement('div');
                    ordersList.className = 'admin-topbar-notifications__list';
                    ordersList.setAttribute('data-admin-topbar-orders-list', '1');
                    ordersSection.appendChild(ordersList);
                }
                ordersList.insertBefore(renderTopbarOrderItem(order), ordersList.firstChild || null);
                updateOrdersCount(ordersSection, 1);
            }
        }

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
                jsonFetch(activeStateUrl, { credentials: 'same-origin' }).then(function (payload) {
                    if (payload && payload.__status === 404 && stateUrlIndex < fallbackStateUrls.length - 1) {
                        stateUrlIndex += 1;
                        return pollTopbarState(force);
                    }
                    if (!payload || !payload.ok) {
                        return;
                    }
                    updateToggleBadge(toggle, payload.badge_count);
                }).catch(function () {
                    return;
                }).then(function () {
                    pollInFlight = false;
                });
            }

            if (stateUrl) {
                pollTopbarState(true);
                pollTimer = window.setInterval(function () {
                    pollTopbarState(false);
                }, 15000);

                if (pollTimer) {
                    doc.addEventListener('visibilitychange', function () {
                        if (!doc.hidden) {
                            pollTopbarState(true);
                        }
                    });
                }
            }
        });

        qa('[data-admin-converter-root]').forEach(function (root) {
            var openButton = q('[data-admin-converter-open]', root);
            var modal = q('[data-admin-converter-modal]', root);
            var fiatInput = q('[data-admin-converter-fiat-input]', root);
            var assetSelect = q('[data-admin-converter-asset-select]', root);
            var result = q('[data-admin-converter-crypto-result]', root);
            var copyFeedback = q('[data-admin-converter-copy-feedback]', root);
            var rateHint = q('[data-admin-converter-rate-hint]', root);
            var assetLogo = q('[data-admin-converter-asset-logo]', root);
            var updatedLine = q('[data-admin-converter-updated-line]', root);
            var refreshButton = q('[data-admin-converter-refresh]', root);
            var assets = [];
            var assetsRaw = root.getAttribute('data-assets') || '[]';
            var currencyCode = root.getAttribute('data-currency-code') || 'USD';
            var csrfNode = q('input[name="_csrf"]');
            var csrfToken = csrfNode ? String(csrfNode.value || '') : '';
            var refreshBusy = false;
            var copyFeedbackTimer = 0;
            var copyValue = '';

            if (!openButton || !modal || !fiatInput || !assetSelect || !result || !copyFeedback || !rateHint || !assetLogo || !updatedLine || !refreshButton) {
                return;
            }

            try {
                assets = JSON.parse(assetsRaw);
            } catch (error) {
                assets = [];
            }

            assetSelect.innerHTML = assets.map(function (item) {
                return '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.code || item.name || 'Crypto') + '</option>';
            }).join('');

            function closeConverter() {
                setHidden(modal, true);
                hideCopyFeedback();
            }

            function openConverter() {
                if (activeFlyout) {
                    closeFlyout(activeFlyout);
                }
                setHidden(modal, false);
                updateConverter();
                window.setTimeout(function () {
                    fiatInput.focus();
                    fiatInput.select();
                }, 20);
            }

            function renderAssetLogo(item) {
                var text = item && item.code ? String(item.code).slice(0, 1) : 'C';
                if (item && item.logo_url) {
                    return '<img src="' + escapeHtml(String(item.logo_url)) + '" alt="' + escapeHtml(text) + '">';
                }
                return '<span>' + escapeHtml(text) + '</span>';
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

            function formatCryptoDisplay(value) {
                var numeric = parseFloat(value || '0');
                var label = '';

                if (!isFinite(numeric) || numeric <= 0) {
                    return '0';
                }

                label = numeric.toFixed(6).replace(/\.?0+$/, '');
                if (label === '0') {
                    label = numeric.toFixed(8).replace(/\.?0+$/, '');
                }
                return label;
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

            function fallbackCopyText(text) {
                var helper = document.createElement('textarea');
                helper.value = text;
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

            function replaceAssets(items) {
                var previousValue = String(assetSelect.value || '');
                assets = Array.isArray(items) ? items : [];
                assetSelect.innerHTML = assets.map(function (item) {
                    return '<option value="' + escapeHtml(item.id) + '">' + escapeHtml(item.code || item.name || 'Crypto') + '</option>';
                }).join('');
                if (previousValue) {
                    assetSelect.value = previousValue;
                }
                if (!assetSelect.value && assets.length) {
                    assetSelect.value = String(assets[0].id);
                }
            }

            function updateConverter() {
                var amount = parseFloat(fiatInput.value || '0');
                var selectedId = String(assetSelect.value || '');
                var selectedAsset = null;

                assets.forEach(function (item) {
                    if (String(item.id) === selectedId) {
                        selectedAsset = item;
                    }
                });

                if (!selectedAsset) {
                    result.textContent = '0.00000000';
                    copyValue = '';
                    hideCopyFeedback();
                    rateHint.textContent = root.getAttribute('data-empty-text') || 'Choose a crypto asset to calculate the amount.';
                    assetLogo.innerHTML = '<span>C</span>';
                    renderUpdatedLine(null);
                    return;
                }

                assetLogo.innerHTML = renderAssetLogo(selectedAsset);
                renderUpdatedLine(selectedAsset);

                if (!isFinite(amount) || amount <= 0 || !selectedAsset.rate || parseFloat(selectedAsset.rate) <= 0) {
                    result.textContent = '0 ' + String(selectedAsset.code || '');
                    copyValue = '';
                    hideCopyFeedback();
                    rateHint.textContent = (selectedAsset.rate_label || '') ? (selectedAsset.rate_label + ' / 1 ' + currencyCode) : '';
                    return;
                }

                copyValue = formatCryptoDisplay(amount / parseFloat(selectedAsset.rate));
                result.textContent = copyValue + ' ' + String(selectedAsset.code || '');
                hideCopyFeedback();
                rateHint.textContent = '1 ' + String(selectedAsset.code || '') + ' ≈ ' + String(selectedAsset.rate_label || '') + ' · ' + currencyCode;
            }

            openButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openConverter();
            });

            qa('[data-admin-converter-close]', modal).forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    closeConverter();
                });
            });

            fiatInput.addEventListener('input', updateConverter);
            assetSelect.addEventListener('change', updateConverter);
            result.addEventListener('click', function () {
                if (!copyValue) {
                    return;
                }

                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(copyValue).then(function () {
                        showCopyFeedback();
                    }).catch(function () {
                        fallbackCopyText(copyValue);
                        showCopyFeedback();
                    });
                    return;
                }

                fallbackCopyText(copyValue);
                showCopyFeedback();
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
        var uploadInput = q('[data-admin-chat-file]', root);
        var cryptoOpenButton = q('[data-admin-chat-crypto-open]', root);
        var cryptoLoader = q('[data-admin-chat-crypto-loader]', root);
        var cryptoTooltip = q('[data-admin-chat-crypto-tooltip]', root);
        var bankOpenButton = q('[data-admin-chat-bank-open]', root);
        var csrfToken = root.getAttribute('data-csrf-token') || '';
        var chatStateUrl = root.getAttribute('data-admin-chat-state-url') || '/admin/chat.php?action=inbox_state';
        var activeConversationId = 0;
        var activeCustomerId = 0;
        var activeConversationType = 'live_chat';
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
        var createCustomerSendEmailInput = q('[data-admin-chat-create-customer-send-email]', root);
        var readonlyToggle = q('[data-admin-chat-readonly-toggle]', root);
        var leaveGroupButton = q('[data-admin-chat-leave-group]', root);
        var groupInvitesWrap = q('[data-admin-chat-group-invites]', root);
        var groupEmails = [];
        var groupEmailRequests = {};
        var groupMode = 'create';
        var groupModalWasOpenBeforeCreate = false;
        var groupModalDefaultTitle = groupModalTitle ? groupModalTitle.textContent : 'Create group';
        var groupModalDefaultIntro = groupModalIntro ? groupModalIntro.textContent : 'Add reseller or admin email, or start typing a handle with @, to create a compact group chat.';
        var groupModalDefaultSubmit = groupSubmitButton ? groupSubmitButton.textContent : 'Create group';
        var linkPreviewUrl = '';
        var linkPreviewData = null;
        var linkPreviewDismissedUrl = '';
        var linkPreviewLoading = false;
        var linkPreviewRequestId = 0;
        var linkPreviewTimer = 0;
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
            return 'admin-chat:panel-open:' + String(window.location.pathname || '/admin/');
        }

        function refreshAdminChatViewportHeight() {
            var viewportOffsetTop = 0;
            var viewportHeight = window.visualViewport && window.visualViewport.height
                ? window.visualViewport.height
                : window.innerHeight;
            if (window.visualViewport && typeof window.visualViewport.offsetTop === 'number') {
                viewportOffsetTop = window.visualViewport.offsetTop;
            }

            document.documentElement.style.setProperty('--admin-chat-viewport-height', Math.max(320, Math.round(viewportHeight)) + 'px');
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
            var unreadLabel = root.getAttribute('data-chat-unread-label') || 'Unread';

            (Array.isArray(items) ? items : []).forEach(function (item) {
                itemMap[String(parseInt(item.conversation_id || 0, 10) || 0)] = item;
            });

            qa('[data-admin-chat-item]', root).forEach(function (itemNode) {
                var conversationId = String(parseInt(itemNode.getAttribute('data-conversation-id') || '0', 10) || 0);
                var payload = itemMap[conversationId] || null;
                var unreadCount = payload ? Math.max(0, parseInt(payload.unread_count || 0, 10) || 0) : 0;
                var meta = q('.admin-chat-inbox__meta', itemNode);
                var unreadNode = q('.admin-chat-inbox__unread', itemNode);
                var presenceNode = q('[data-admin-chat-presence-dot]', itemNode);

                itemNode.classList.toggle('is-unread', unreadCount > 0);

                if (presenceNode && payload && payload.presence) {
                    presenceNode.innerHTML = renderPresenceDot(payload.presence);
                }

                if (unreadCount > 0) {
                    if (!unreadNode && meta) {
                        unreadNode = document.createElement('span');
                        unreadNode.className = 'admin-chat-inbox__unread';
                        meta.insertBefore(unreadNode, meta.firstChild || null);
                    }
                    if (unreadNode) {
                        unreadNode.textContent = unreadLabel + ': ' + String(unreadCount);
                    }
                } else if (unreadNode) {
                    unreadNode.remove();
                }
            });
        }

        function pollChatInboxState(force) {
            if (!chatStateUrl || doc.hidden && !force) {
                return;
            }

            jsonFetch(chatStateUrl, { credentials: 'same-origin' }).then(function (payload) {
                if (!payload || !payload.ok) {
                    return;
                }

                updateChatToggleBadge(payload.badge_count);
                updateChatInboxItems(payload.items);
            }).catch(function () {
                return;
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
            if (!adminChatStorageAvailable()) {
                return false;
            }

            try {
                return window.localStorage.getItem(adminChatPanelStateKey()) === '1';
            } catch (error) {
                return false;
            }
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
            return (symbol ? symbol : '') + trimmedAmount + (code ? ' ' + code : '');
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
                    modalState.sendButton.disabled = false;
                }
            }).catch(function () {
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
                if (!payload.ok) {
                    setPaymentPreview(modalState, renderPaymentStateHtml(payload.message || root.getAttribute('data-chat-payment-preview-error') || 'Unable to prepare payment preview.', true));
                    return;
                }

                hydratePaymentModal(type, payload);
            }).catch(function () {
                setPaymentPreview(modalState, renderPaymentStateHtml(root.getAttribute('data-chat-payment-preview-error') || 'Unable to prepare payment preview.', true));
            });
        }

        function submitPaymentRequest(type) {
            var modalState = paymentModals[type];
            var postBody;
            var errorMessage;

            if (!modalState || !activeConversationId || !modalState.sendButton) {
                return;
            }

            if (!modalState.previewPayload || !modalState.previewPayload.ok) {
                refreshPaymentPreview(type);
                return;
            }

            modalState.sendButton.disabled = true;
            errorMessage = root.getAttribute('data-chat-payment-send-error') || 'Unable to create payment request.';
            postBody = {
                conversation_id: activeConversationId,
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
                renderConversation(payload);
            }).catch(function () {
                modalState.sendButton.disabled = false;
                setPaymentPreview(modalState, renderPaymentStateHtml(errorMessage, true));
                showComposerAlert(errorMessage, true);
            });
        }

        function renderGroupMembers() {
            if (!groupMembers) {
                return;
            }
            if (!groupEmails.length) {
                groupMembers.innerHTML = '';
                return;
            }

            groupMembers.innerHTML = groupEmails.map(function (email, index) {
                return '' +
                    '<button type="button" class="admin-chat-group-modal__member" data-admin-chat-group-remove data-index="' + index + '">' +
                        '<span>' + escapeHtml(email) + '</span>' +
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

            if (groupNameLabel) {
                setHidden(groupNameLabel, isInviteMode);
            }

            if (groupReadonlyLabel) {
                setHidden(groupReadonlyLabel, isInviteMode);
            }

            if (groupRetentionCreateLabel) {
                setHidden(groupRetentionCreateLabel, isInviteMode);
            }
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
                groupRetentionCreateInput.value = '0';
            }
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
            if (createCustomerSendEmailInput) {
                createCustomerSendEmailInput.checked = true;
            }
            showCreateCustomerAlert('', false);
        }

        function openGroupModal() {
            if (!groupModal) {
                return;
            }
            closeCreateCustomerModal();
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
            if (!groupSettingsModal || activeConversationType !== 'group_chat') {
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
            formData.append('send_password_email', createCustomerSendEmailInput && createCustomerSendEmailInput.checked ? '1' : '0');

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
                    successMessage += ' '
                        + ((payload.email_notification && (payload.email_notification.ok || payload.email_notification.queued))
                            ? (root.getAttribute('data-chat-create-user-email-queued') || 'Password email has been queued.')
                            : (root.getAttribute('data-chat-create-user-email-failed') || 'Password email could not be queued.'));
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
            if (!email) {
                return;
            }
            if (!isValidInviteIdentifier(email)) {
                showGroupAlert(invalidInviteText, true);
                hideGroupSearchResults();
                return;
            }
            if (groupEmails.indexOf(email) !== -1) {
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

                if (groupEmails.indexOf(String(payload.email || email).toLowerCase()) !== -1) {
                    clearPendingGroupEmail();
                    showGroupAlert(duplicateInviteText, true);
                    return;
                }

                groupEmails.push(String(payload.email || email).toLowerCase());
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

            if (!/^https?:\/\//i.test(normalized) || /\s/.test(normalized)) {
                return '';
            }

            return normalized;
        }

        function extractFirstUrl(value) {
            var match = String(value || '').match(/(?:https?:\/\/|www\.)[^\s<]+/i);
            if (!match || !match[0]) {
                return '';
            }

            return normalizePreviewUrl(match[0]);
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
            if (panel) {
                setHidden(panel, true);
            }
            if (toggle) {
                toggle.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
            saveAdminChatPanelState(false);
            syncAdminChatBodyLock();
            closeQuickModal();
            closeAllPaymentModals();
        }

        function showList() {
            if (listView) {
                setHidden(listView, false);
            }
            if (conversationView) {
                setHidden(conversationView, true);
            }
            activeConversationId = 0;
            activeCustomerId = 0;
            activeConversationType = 'live_chat';
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
                retentionInlineSelect.value = '0';
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
        }

        function showConversation() {
            if (listView) {
                setHidden(listView, true);
            }
            if (conversationView) {
                setHidden(conversationView, false);
            }
        }

        function scrollConversationToBottom() {
            if (!body) {
                return;
            }
            window.setTimeout(function () {
                body.scrollTop = body.scrollHeight;
            }, 20);
        }

        function renderConversation(payload) {
            activeConversationId = parseInt(payload.conversation_id || 0, 10) || 0;
            activeCustomerId = parseInt(payload.customer_id || 0, 10) || 0;
            activeConversationType = payload.conversation_type || 'live_chat';
            if (body) {
                body.innerHTML = payload.html || '';
            }
            if (title) {
                title.textContent = payload.title || title.textContent;
                title.setAttribute('href', activeConversationType === 'live_chat' && activeCustomerId > 0 ? '/admin/?page=live-chat&user_id=' + activeCustomerId : '#');
            }
            if (conversationHandle) {
                var handleText = '';
                if (activeConversationType === 'live_chat' && payload.customer_public_handle) {
                    handleText = '@' + String(payload.customer_public_handle);
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
            if (groupSettingsOpenButton) {
                setHidden(groupSettingsOpenButton, activeConversationType !== 'group_chat');
            }
            if (groupRetentionSelect) {
                groupRetentionSelect.value = payload.retention_hours ? String(payload.retention_hours) : '0';
            }
            if (conversationMeta) {
                setHidden(conversationMeta, activeConversationType !== 'group_chat');
            }
            if (conversationMembersCount) {
                conversationMembersCount.textContent = payload.member_count_label || '';
            }
            renderConversationMembers(Array.isArray(payload.members) ? payload.members : []);
            if (emailNotificationsToggle) {
                emailNotificationsToggle.checked = payload.email_notifications_enabled !== false;
            }
            if (retentionInlineSelect) {
                retentionInlineSelect.value = payload.retention_hours ? String(payload.retention_hours) : '0';
                retentionInlineSelect.disabled = !(activeConversationType === 'group_chat' && !!payload.can_manage_group);
            }
            if (retentionInlineLabel) {
                setHidden(retentionInlineLabel, !(activeConversationType === 'group_chat' && !!payload.can_manage_group));
            }
            closeMembersPopover();
            if (composerInput) {
                composerInput.value = '';
            }
            resetLinkPreview();
            closeAllPaymentModals();
            syncPaymentHeaderActions(payload);
            showConversation();
            openPanel(false);
            scrollConversationToBottom();
        }

        function fetchConversation(conversationId) {
            if (!conversationId) {
                return;
            }
            openPanel(false);
            showComposerAlert('', false);
            if (body) {
                body.innerHTML = '<div class="admin-chat-conversation__empty">' + escapeHtml(root.getAttribute('data-loading-conversation') || 'Loading conversation...') + '</div>';
            }
            showConversation();

            jsonFetch(chatUrl + '?' + toQuery({ conversation_id: conversationId })).then(function (payload) {
                if (!payload.ok) {
                    showComposerAlert(root.getAttribute('data-chat-load-error') || 'Unable to load this conversation.', true);
                    return;
                }
                renderConversation(payload);
            }).catch(function () {
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
                formData.append('_csrf', csrfToken);
                options.body = formData;
            } else {
                var payload = extra || {};
                payload.action = action;
                payload.conversation_id = activeConversationId;
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

        if (restoreAdminChatPanelState()) {
            openPanel(false);
            showList();
        }

        window.setInterval(function () {
            pollChatInboxState(false);
        }, 12000);
        doc.addEventListener('visibilitychange', function () {
            if (!doc.hidden) {
                pollChatInboxState(true);
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
                        return identifier && groupEmails.indexOf(identifier) === -1;
                    });

                    if (!items.length) {
                        setGroupSearchResultsHtml('<div class="admin-chat-group-modal__search-empty">' + escapeHtml(searchEmpty) + '</div>', true);
                        return;
                    }

                    setGroupSearchResultsHtml(items.map(function (item) {
                        return '' +
                            '<button type="button" class="admin-chat-group-modal__search-item" data-admin-chat-group-search-item data-invite-email="' + escapeHtml(item.email || '') + '">' +
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
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: requestAction,
                        conversation_id: groupMode === 'invite' ? activeConversationId : 0,
                        group_name: groupNameInput ? groupNameInput.value.trim() : '',
                        participant_emails_json: JSON.stringify(groupEmails),
                        is_group_read_only: groupReadonlyInput && groupReadonlyInput.checked ? 1 : 0,
                        retention_hours: groupRetentionCreateInput ? groupRetentionCreateInput.value : '0',
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showGroupAlert(payload.message || (groupMode === 'invite'
                            ? (root.getAttribute('data-chat-group-invite-error') || 'Unable to invite members to the group.')
                            : (root.getAttribute('data-chat-group-create-error') || 'Unable to create the group chat.')), true);
                        return;
                    }
                    if (groupMode === 'invite') {
                        var invitedConversationId = activeConversationId;
                        closeGroupModal();
                        if (invitedConversationId) {
                            fetchConversation(invitedConversationId);
                        }
                        showComposerAlert(root.getAttribute('data-chat-group-invite-members-success') || 'Invitations were sent successfully.', false);
                        return;
                    }
                    closeGroupModal();
                    if (payload.conversation_id) {
                        fetchConversation(payload.conversation_id);
                    }
                }).catch(function () {
                    showGroupAlert(groupMode === 'invite'
                        ? (root.getAttribute('data-chat-group-invite-error') || 'Unable to invite members to the group.')
                        : (root.getAttribute('data-chat-group-create-error') || 'Unable to create the group chat.'), true);
                });
            });
        }

        if (groupRetentionSelect) {
            groupRetentionSelect.addEventListener('change', function () {
                if (!activeConversationId || activeConversationType !== 'group_chat') {
                    return;
                }

                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'set_group_retention',
                        conversation_id: activeConversationId,
                        retention_hours: groupRetentionSelect.value || '0',
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showGroupSettingsAlert(payload.message || (root.getAttribute('data-chat-group-retention-save-error') || 'Unable to update auto-delete settings.'), true);
                        return;
                    }
                    showGroupSettingsAlert(payload.message || '', false);
                    renderConversation(payload);
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
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        emailNotificationsToggle.checked = !emailNotificationsToggle.checked;
                        showComposerAlert(payload.message || 'Unable to update email notifications.', true);
                        return;
                    }
                    showComposerAlert('', false);
                    renderConversation(payload);
                }).catch(function () {
                    emailNotificationsToggle.checked = !emailNotificationsToggle.checked;
                    showComposerAlert('Unable to update email notifications.', true);
                });
            });
        }

        if (retentionInlineSelect) {
            retentionInlineSelect.addEventListener('change', function () {
                if (!activeConversationId || activeConversationType !== 'group_chat') {
                    return;
                }

                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'set_group_retention',
                        conversation_id: activeConversationId,
                        retention_hours: retentionInlineSelect.value || '0',
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(payload.message || (root.getAttribute('data-chat-group-retention-save-error') || 'Unable to update auto-delete settings.'), true);
                        return;
                    }
                    showComposerAlert('', false);
                    renderConversation(payload);
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
                if (event.key === 'Enter' && !event.shiftKey) {
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

        if (q('[data-admin-chat-send]', root)) {
            q('[data-admin-chat-send]', root).addEventListener('click', function () {
                var message = composerInput ? composerInput.value.trim() : '';
                var detectedUrl = extractFirstUrl(message);
                var previewUrl = linkPreviewData && linkPreviewUrl === detectedUrl && linkPreviewDismissedUrl !== detectedUrl ? linkPreviewUrl : '';
                var previewRemoved = detectedUrl && linkPreviewDismissedUrl === detectedUrl ? 1 : 0;
                if (!message) {
                    return;
                }

                postConversation('send', {
                    message: message,
                    link_preview_url: previewUrl,
                    link_preview_removed: previewRemoved
                }, false).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-send-error') || 'Unable to send the message.', true);
                        return;
                    }
                    if (composerInput) {
                        composerInput.value = '';
                    }
                    resetLinkPreview();
                    showComposerAlert('', false);
                    renderConversation(payload);
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-send-error') || 'Unable to send the message.', true);
                });
            });
        }

        if (q('[data-admin-chat-upload-button]', root) && uploadInput) {
            q('[data-admin-chat-upload-button]', root).addEventListener('click', function () {
                uploadInput.click();
            });

            uploadInput.addEventListener('change', function () {
                if (!uploadInput.files || !uploadInput.files[0] || !activeConversationId) {
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'upload');
                formData.append('conversation_id', String(activeConversationId));
                formData.append('_csrf', csrfToken);
                formData.append('file', uploadInput.files[0]);

                postConversation('upload', formData, true).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-upload-error') || 'Unable to upload the image.', true);
                        return;
                    }
                    uploadInput.value = '';
                    showComposerAlert('', false);
                    renderConversation(payload);
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-upload-error') || 'Unable to upload the image.', true);
                });
            });
        }

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

                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'start_conversation',
                        participant_type: 'customer',
                        customer_id: targetCustomerId,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-start-error') || 'Unable to start conversation.', true);
                        return;
                    }
                    renderConversation(payload);
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-start-error') || 'Unable to start conversation.', true);
                });
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
                    renderConversation(payload);
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

            var deleteMessage = closest(event.target, '[data-admin-chat-delete-message]');
            if (deleteMessage) {
                var deleteConversationId = parseInt(deleteMessage.getAttribute('data-conversation-id') || '0', 10) || 0;
                var deleteMessageId = parseInt(deleteMessage.getAttribute('data-message-id') || '0', 10) || 0;
                if (!deleteConversationId || !deleteMessageId) {
                    return;
                }

                deleteMessage.disabled = true;
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'delete_message',
                        conversation_id: deleteConversationId,
                        message_id: deleteMessageId,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    deleteMessage.disabled = false;
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-delete-message-error') || 'Unable to delete this message.', true);
                        return;
                    }
                    renderConversation(payload);
                }).catch(function () {
                    deleteMessage.disabled = false;
                    showComposerAlert(root.getAttribute('data-chat-delete-message-error') || 'Unable to delete this message.', true);
                });
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
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    saveMessage.disabled = false;
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-edit-message-error') || 'Unable to save this message.', true);
                        return;
                    }
                    renderConversation(payload);
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
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    quickSend.disabled = false;
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-quick-replies-send-error') || 'Unable to send quick reply.', true);
                        return;
                    }
                    closeQuickModal();
                    renderConversation(payload);
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
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-group-readonly-error') || 'Unable to update read only mode.', true);
                        return;
                    }
                    renderConversation(payload);
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

            if (conversationMembersPopover && !conversationMembersPopover.hasAttribute('hidden')) {
                if (!closest(event.target, '[data-admin-chat-members-toggle]') && !closest(event.target, '[data-admin-chat-members-popover]')) {
                    closeMembersPopover();
                }
            }

            if (panel && !panel.hasAttribute('hidden')) {
                if (!closest(event.target, '[data-admin-chat-inbox]') && !closest(event.target, '[data-admin-chat-open]') && !closest(event.target, '[data-admin-chat-open-conversation]')) {
                    closePanel();
                }
            }
        });
    }

    initSensitiveRouteProtection();
    initTopbarTools();

    if (!hasBootstrap) {
        return;
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
    initProviderUrlReplacement();
    initProviderLogoManagers();
    initProductTypeForms();
    initOrderStatusForms();
    initOrderCreateForms();
    initWalletCustomerPickers();
    initRichTextEditors();
    initSearch();
    initAdminHelpModal();
    initChat();
});
