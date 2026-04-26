document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    if (typeof window.bootstrap === 'undefined') {
        console.warn('Bootstrap not loaded');
        return;
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

    function toQuery(params) {
        var search = new URLSearchParams();
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== null && params[key] !== undefined) {
                search.append(key, params[key]);
            }
        });
        return search.toString();
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
        var status = q('[data-admin-chat-conversation-status]', root);
        var conversationBadge = q('[data-admin-chat-conversation-badge]', root);
        var composerAlert = q('[data-admin-chat-alert]', root);
        var composerInput = q('[data-admin-chat-input]', root);
        var composerPreview = q('[data-admin-chat-link-preview]', root);
        var uploadInput = q('[data-admin-chat-file]', root);
        var cryptoOpenButton = q('[data-admin-chat-crypto-open]', root);
        var cryptoLoader = q('[data-admin-chat-crypto-loader]', root);
        var cryptoTooltip = q('[data-admin-chat-crypto-tooltip]', root);
        var bankOpenButton = q('[data-admin-chat-bank-open]', root);
        var csrfToken = root.getAttribute('data-csrf-token') || '';
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
        var groupMembers = q('[data-admin-chat-group-members]', root);
        var groupReadonlyInput = q('[data-admin-chat-group-readonly]', root);
        var readonlyToggle = q('[data-admin-chat-readonly-toggle]', root);
        var leaveGroupButton = q('[data-admin-chat-leave-group]', root);
        var groupInvitesWrap = q('[data-admin-chat-group-invites]', root);
        var groupEmails = [];
        var groupEmailRequests = {};
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

        function showGroupAlert(message, isError) {
            if (!groupAlert) {
                return;
            }
            groupAlert.textContent = message || '';
            groupAlert.classList.toggle('alert-danger', !!isError);
            groupAlert.classList.toggle('alert-success', !isError && !!message);
            setHidden(groupAlert, !message);
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

        function resetGroupModal() {
            groupEmails = [];
            groupEmailRequests = {};
            if (groupNameInput) {
                groupNameInput.value = '';
            }
            if (groupEmailInput) {
                groupEmailInput.value = '';
            }
            if (groupReadonlyInput) {
                groupReadonlyInput.checked = false;
            }
            renderGroupMembers();
            showGroupAlert('', false);
        }

        function openGroupModal() {
            if (!groupModal) {
                return;
            }
            setHidden(groupModal, false);
            window.setTimeout(function () {
                if (groupNameInput) {
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

        function addGroupEmail() {
            var email = groupEmailInput ? groupEmailInput.value.trim().toLowerCase() : '';
            if (!email) {
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showGroupAlert('Enter a valid email address.', true);
                return;
            }
            if (groupEmails.indexOf(email) !== -1) {
                showGroupAlert('This invitation is already added.', true);
                return;
            }

            if (groupEmailRequests[email]) {
                showGroupAlert('Checking user...', false);
                return;
            }

            showGroupAlert('Checking user...', false);
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
                    _csrf: csrfToken
                })
            }).then(function (payload) {
                if (!payload.ok) {
                    clearPendingGroupEmail();
                    showGroupAlert(payload.message || 'No reseller or admin account was found for this email.', true);
                    return;
                }

                if (groupEmails.indexOf(String(payload.email || email).toLowerCase()) !== -1) {
                    clearPendingGroupEmail();
                    showGroupAlert('This invitation is already added.', true);
                    return;
                }

                groupEmails.push(String(payload.email || email).toLowerCase());
                if (groupEmailInput) {
                    groupEmailInput.value = '';
                    groupEmailInput.focus();
                }
                renderGroupMembers();
                showGroupAlert('Invitation prepared. It will expire after 24 hours if not accepted.', false);
                clearPendingGroupEmail();
            }).catch(function () {
                showGroupAlert('No reseller or admin account was found for this email.', true);
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
            if (panel) {
                setHidden(panel, false);
            }
            if (toggle) {
                toggle.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
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
            if (readonlyToggle) {
                setHidden(readonlyToggle, true);
            }
            if (leaveGroupButton) {
                setHidden(leaveGroupButton, true);
            }
            resetLinkPreview();
            closeQuickModal();
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
            if (conversationBadge) {
                conversationBadge.textContent = root.getAttribute('data-group-chat-badge') || 'Admin group';
                setHidden(conversationBadge, activeConversationType !== 'group_chat');
            }
            if (status) {
                var groupPresenceLabel = root.getAttribute('data-group-chat-status-label') || 'Group chat';
                status.innerHTML = activeConversationType === 'group_chat'
                    ? renderPresenceDot({ class_name: 'admin-chat-presence admin-chat-presence--online', label: groupPresenceLabel })
                    : renderPresenceDot(payload.presence || null);
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
                            '</div>' +
                            '<button type="button" class="btn btn-dark btn-sm admin-chat-inbox__quick-send" data-admin-chat-send-quick-reply data-quick-reply-id="' + escapeHtml(item.id) + '">Send</button>' +
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

        qa('[data-admin-chat-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                openPanel(button.hasAttribute('data-admin-chat-focus-search'));
                showList();
            });
        });

        if (q('[data-admin-chat-group-open]', root)) {
            q('[data-admin-chat-group-open]', root).addEventListener('click', function () {
                openGroupModal();
            });
        }

        qa('[data-admin-chat-group-close]', root).forEach(function (button) {
            button.addEventListener('click', function () {
                closeGroupModal();
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
                            '<button type="button" class="admin-chat-inbox__search-item" data-admin-chat-search-result data-customer-id="' + escapeHtml(item.customer_id) + '" data-conversation-id="' + escapeHtml(item.conversation_id) + '">' +
                                '<strong>' + escapeHtml(item.display_name || item.email || '') + '</strong>' +
                                '<span>' + escapeHtml(item.email || '') + '</span>' +
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
            groupEmailInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addGroupEmail();
                }
            });
        }

        if (q('[data-admin-chat-group-submit]', root)) {
            q('[data-admin-chat-group-submit]', root).addEventListener('click', function () {
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({
                        action: 'create_group',
                        group_name: groupNameInput ? groupNameInput.value.trim() : '',
                        participant_emails_json: JSON.stringify(groupEmails),
                        is_group_read_only: groupReadonlyInput && groupReadonlyInput.checked ? 1 : 0,
                        _csrf: csrfToken
                    })
                }).then(function (payload) {
                    if (!payload.ok) {
                        showGroupAlert(payload.message || root.getAttribute('data-chat-group-create-error') || 'Unable to create the group chat.', true);
                        return;
                    }
                    closeGroupModal();
                    if (payload.conversation_id) {
                        fetchConversation(payload.conversation_id);
                    }
                }).catch(function () {
                    showGroupAlert(root.getAttribute('data-chat-group-create-error') || 'Unable to create the group chat.', true);
                });
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
                if (existingConversationId > 0) {
                    fetchConversation(existingConversationId);
                    return;
                }
                if (!customerId) {
                    return;
                }
                jsonFetch(chatUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: toQuery({ action: 'start_conversation', customer_id: customerId, _csrf: csrfToken })
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

            var deleteMessage = closest(event.target, '[data-admin-chat-delete-message]');
            if (deleteMessage) {
                var deleteConversationId = parseInt(deleteMessage.getAttribute('data-conversation-id') || '0', 10) || 0;
                var deleteMessageId = parseInt(deleteMessage.getAttribute('data-message-id') || '0', 10) || 0;
                var deleteConfirm = root.getAttribute('data-chat-delete-message-confirm') || 'Delete this message?';
                if (!deleteConversationId || !deleteMessageId || !window.confirm(deleteConfirm)) {
                    return;
                }

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
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-delete-message-error') || 'Unable to delete this message.', true);
                        return;
                    }
                    renderConversation(payload);
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-delete-message-error') || 'Unable to delete this message.', true);
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
                if (!quickReplyId || !activeConversationId) {
                    return;
                }

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
                    if (!payload.ok) {
                        showComposerAlert(root.getAttribute('data-chat-quick-replies-send-error') || 'Unable to send quick reply.', true);
                        return;
                    }
                    closeQuickModal();
                    renderConversation(payload);
                }).catch(function () {
                    showComposerAlert(root.getAttribute('data-chat-quick-replies-send-error') || 'Unable to send quick reply.', true);
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

            if (panel && !panel.hasAttribute('hidden')) {
                if (!closest(event.target, '[data-admin-chat-inbox]') && !closest(event.target, '[data-admin-chat-open]') && !closest(event.target, '[data-admin-chat-open-conversation]')) {
                    closePanel();
                }
            }
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
    initProviderUrlReplacement();
    initProductTypeForms();
    initOrderStatusForms();
    initWalletCustomerPickers();
    initSearch();
    initChat();
});
