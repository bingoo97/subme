(function (window, document, $) {
	'use strict';

	if (!$) {
		return;
	}

	if (window.MessengerUI && typeof window.MessengerUI.init === 'function') {
		window.MessengerUI.init();
		return;
	}

	var MessengerUI = {
		pollTimer: null,
		pollIntervalOpen: 5000,
		pollIntervalClosed: 12000,
		fetchInFlight: false,
		sendInFlight: false,
		uploadInFlight: false,
		lastMessageId: 0,
		lastRenderedHtml: '',
		faqPendingKey: '',
		faqReplyTimer: null,
		typewriterTimer: null,
		deleteCountdownTimer: null,
		cooldownTimer: null,
		noticeTimer: null,
		lastUnreadCount: 0,
		lastKnownScrollTop: 0,
		userBrowsingHistory: false,
		linkPreviewTimer: null,
		linkPreviewUrl: '',
		linkPreviewData: null,
		linkPreviewDismissedUrl: '',
		linkPreviewLoading: false,
		activeConversationId: 0,
		activeConversationType: 'live_chat',
		activeCanSend: true,
		activeCanManageGroup: false,
		activeConversationTitle: '',
		groupEmails: [],
		groupEmailRequests: {},
		groupModalMode: 'create',
		groupCreationKind: 'direct',
		groupTargetConversationId: 0,
		resellerViewMode: 'list',
		conversationTransitionTimer: null,
		restoreOpenScrollToBottomPending: false,
		messagePageSize: 10,
		loadOlderBatchSize: 5,
		activeConversationMessageLimit: 10,
		activeConversationLoadedCount: 0,
		activeConversationTotalMessages: 0,
		oldestMessageId: 0,
		hasMoreMessages: false,
		loadingOlderMessages: false,
		olderMessagesIntentTimer: null,
		olderMessagesRetryTimer: null,
		initDone: false,

		config: function () {
			window.MESSENGER_BOOTSTRAP = window.MESSENGER_BOOTSTRAP || {};
			return {
				userId: parseInt(window.MESSENGER_BOOTSTRAP.userId || $('#panel-heading').data('user-id') || 0, 10),
				endpoint: window.MESSENGER_BOOTSTRAP.endpoint || 'check_chat.php',
				csrfToken: String(window.MESSENGER_BOOTSTRAP.csrfToken || $('#messenger_upload_csrf').val() || '')
			};
		},

		widget: function () {
			return $('#messanger');
		},

		panel: function () {
			return $('#collapseOne');
		},

		heading: function () {
			return $('#panel-heading');
		},

		icon: function () {
			return this.heading().find('.messenger-toggle-icon');
		},

		toggleButton: function () {
			return $('[data-messenger-toggle-button]');
		},

		input: function () {
			return $('#tresc');
		},

		alertBox: function () {
			return $('#messenger_alert');
		},

		previewBox: function () {
			return $('#messenger_link_preview');
		},

		contentRoot: function () {
			return $('#content_chat_profil');
		},

		chatBox: function () {
			return $('#chat_box');
		},

		listView: function () {
			return $('[data-chat-list-view]');
		},

		conversationView: function () {
			return $('[data-chat-conversation-view]');
		},

		conversationBody: function () {
			return $('[data-chat-conversation-body]');
		},

		conversationStage: function () {
			return $('[data-chat-conversation-stage]');
		},

		conversationTransition: function () {
			return $('[data-chat-conversation-transition]');
		},

		chatScroll: function () {
			return $('#chat_scroll');
		},

		bindChatScrollHandler: function () {
			var self = this;
			var $scroll = this.chatScroll();

			if (!$scroll.length) {
				return;
			}

			$scroll.off('.messengerUiScroll');
			$scroll.on('scroll.messengerUiScroll', function () {
				var distanceFromBottom = Math.max(0, this.scrollHeight - this.clientHeight - this.scrollTop);
				self.lastKnownScrollTop = this.scrollTop;
				self.userBrowsingHistory = distanceFromBottom > 24;
				self.queueOlderMessagesLoad();
			});
		},

		clearOlderMessagesTimers: function () {
			window.clearTimeout(this.olderMessagesIntentTimer);
			window.clearTimeout(this.olderMessagesRetryTimer);
			this.olderMessagesIntentTimer = null;
			this.olderMessagesRetryTimer = null;
		},

		queueOlderMessagesLoad: function (delay) {
			var self = this;
			var metrics = this.getScrollMetrics();
			var safeDelay = typeof delay === 'number' ? delay : 180;

			if (!this.isOpen() || !this.isConversationHistoryActive()) {
				this.clearOlderMessagesTimers();
				return;
			}

			if (!this.activeConversationId || !this.hasMoreMessages || this.loadingOlderMessages) {
				this.clearOlderMessagesTimers();
				return;
			}

			if (!metrics || metrics.scrollTop > 72) {
				this.clearOlderMessagesTimers();
				return;
			}

			window.clearTimeout(this.olderMessagesIntentTimer);
			this.olderMessagesIntentTimer = window.setTimeout(function () {
				self.olderMessagesIntentTimer = null;
				self.maybeLoadOlderMessages();
			}, Math.max(0, safeDelay));
		},

		uploadProgressBox: function () {
			return $('#messenger_upload_progress');
		},

		uploadProgressFill: function () {
			return $('#messenger_upload_progress_fill');
		},

		uploadProgressValue: function () {
			return $('#messenger_upload_progress_value');
		},

		groupModal: function () {
			var $modal = $('#messenger_group_modal');
			if ($modal.length && !$modal.parent().is('body')) {
				$modal.appendTo('body');
			}
			return $modal;
		},

		groupAlertBox: function () {
			return $('#messenger_group_alert');
		},

		groupNameInput: function () {
			return $('#messenger_group_name');
		},

		groupNameField: function () {
			return $('#messenger_group_name_field');
		},

		groupModeSwitch: function () {
			return $('#messenger_group_mode_switch');
		},

		groupModeButtons: function () {
			return $('[data-messenger-group-kind]');
		},

		groupContextField: function () {
			return $('#messenger_group_context');
		},

		groupContextTitle: function () {
			return $('#messenger_group_context_title');
		},

		groupContextLabel: function () {
			return $('#messenger_group_context_label');
		},

		groupModalTitle: function () {
			return $('#messenger_group_modal_title');
		},

		groupSubmitLabel: function () {
			return $('#messenger_group_submit_label');
		},

		groupEmailInput: function () {
			return $('#messenger_group_email');
		},

		groupEmailLabel: function () {
			return $('#messenger_group_email_label');
		},

		groupHint: function () {
			return $('#messenger_group_hint');
		},

		groupRetentionField: function () {
			return $('#messenger_group_retention_field');
		},

		groupRetentionCreate: function () {
			return $('#messenger_group_retention_create');
		},

		emailNotificationsToggle: function () {
			return $('[data-chat-email-notifications-toggle]');
		},

		retentionSelect: function () {
			return $('[data-chat-retention-select]');
		},

		groupMembersBox: function () {
			return $('#messenger_group_members');
		},

		uploadModal: function () {
			var $modal = $('#messanger_upload');
			if ($modal.length && !$modal.parent().is('body')) {
				$modal.appendTo('body');
			}
			return $modal;
		},

		isOpen: function () {
			return this.widget().hasClass('is-open');
		},

		storageAvailable: function () {
			try {
				return !!window.localStorage;
			} catch (error) {
				return false;
			}
		},

		panelStateStorageKey: function () {
			var cfg = this.config();
			return 'messenger:panel-open:' + String(cfg.userId || 0);
		},

		activeConversationStorageKey: function () {
			var cfg = this.config();
			return 'messenger:active-conversation:' + String(cfg.userId || 0);
		},

		savePanelState: function (isOpen) {
			if (!this.storageAvailable()) {
				return;
			}
			try {
				window.localStorage.setItem(this.panelStateStorageKey(), isOpen ? '1' : '0');
			} catch (error) {
				return;
			}
		},

		restorePanelState: function () {
			if (!this.storageAvailable()) {
				return false;
			}
			try {
				return window.localStorage.getItem(this.panelStateStorageKey()) === '1';
			} catch (error) {
				return false;
			}
		},

		saveActiveConversationState: function () {
			var payload;
			if (!this.storageAvailable() || !this.hasResellerInboxLayout()) {
				return;
			}

			payload = {
				id: parseInt(this.activeConversationId || 0, 10) || 0,
				type: String(this.activeConversationType || 'live_chat'),
				view: this.resellerViewMode === 'conversation' ? 'conversation' : 'list',
				messageLimit: parseInt(this.activeConversationMessageLimit || this.messagePageSize || 10, 10) || 10
			};

			try {
				window.localStorage.setItem(this.activeConversationStorageKey(), JSON.stringify(payload));
			} catch (error) {
				return;
			}
		},

		restoreActiveConversationState: function () {
			var storedValue;
			var parsed;

			if (!this.storageAvailable() || !this.hasResellerInboxLayout()) {
				return false;
			}

			try {
				storedValue = window.localStorage.getItem(this.activeConversationStorageKey());
			} catch (error) {
				return false;
			}

			if (!storedValue) {
				return false;
			}

			try {
				parsed = JSON.parse(storedValue);
			} catch (error) {
				return false;
			}

			if (!parsed || typeof parsed !== 'object') {
				return false;
			}

			this.activeConversationId = parseInt(parsed.id || 0, 10) || 0;
			this.activeConversationType = String(parsed.type || 'live_chat');
			this.resellerViewMode = parsed.view === 'conversation' ? 'conversation' : 'list';
			this.activeConversationMessageLimit = parseInt(parsed.messageLimit || this.messagePageSize || 10, 10) || this.messagePageSize;
			if (this.activeConversationMessageLimit < this.messagePageSize) {
				this.activeConversationMessageLimit = this.messagePageSize;
			}

			return true;
		},

		updateViewportMetrics: function () {
			var $widget = this.widget();
			var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
			var viewportOffsetTop = 0;

			if (window.visualViewport) {
				if (window.visualViewport.height) {
					viewportHeight = window.visualViewport.height;
				}
				if (typeof window.visualViewport.offsetTop === 'number') {
					viewportOffsetTop = window.visualViewport.offsetTop;
				}
			}

			viewportHeight = Math.max(0, Math.round(viewportHeight));
			viewportOffsetTop = Math.max(0, Math.round(viewportOffsetTop));

			if ($widget.length) {
				$widget.css('--messenger-mobile-vh', viewportHeight + 'px');
				$widget.css('--messenger-mobile-offset-top', viewportOffsetTop + 'px');
			}
		},

		refreshOpenLayout: function (stickToBottom) {
			var self = this;
			var raf = window.requestAnimationFrame || function (callback) {
				return window.setTimeout(callback, 16);
			};

			this.updateViewportMetrics();

			if (!this.isOpen()) {
				return;
			}

			raf(function () {
				if (stickToBottom) {
					self.scrollToBottom();
				} else if (self.lastKnownScrollTop > 0) {
					self.restoreScrollPosition(self.lastKnownScrollTop);
				}
			});
		},

		updateUnreadBadge: function (count) {
			var $badge = $('.messenger-unread-badge');
			var total = parseInt(count || 0, 10);

			if (!$badge.length) {
				return;
			}

			if (total > 0) {
				$badge.text(total).show();
			} else {
				$badge.text('0').hide();
			}

			this.updateToggleAttention(total);
		},

		updateToggleAttention: function (count) {
			var total = parseInt(count || 0, 10);
			var $button = this.toggleButton();
			var hasNewUnread = total > this.lastUnreadCount && !this.isOpen();

			if (!$button.length) {
				this.lastUnreadCount = total;
				return;
			}

			if (total > 0) {
				$button.addClass('is-unread');
				if (hasNewUnread) {
					$button.removeClass('is-attention');
					window.setTimeout(function () {
						$button.addClass('is-attention');
					}, 10);
				}
			} else {
				$button.removeClass('is-unread is-attention');
			}

			this.lastUnreadCount = total;
		},

		startDeleteCountdowns: function () {
			var self = this;

			window.clearInterval(this.deleteCountdownTimer);

			var tick = function () {
				self.chatBox().find('.messenger-delete-button').each(function () {
					var $button = $(this);
					if ($button.hasClass('messenger-delete-button--icon')) {
						return;
					}

					var deleteUntil = parseInt($button.data('deleteUntil') || 0, 10);
					var defaultLabel = String($button.data('deleteLabel') || 'Delete');

					if (deleteUntil <= 0) {
						return;
					}

					var remaining = deleteUntil - Math.floor(Date.now() / 1000);

					if (remaining <= 0) {
						$button.remove();
						return;
					}

					$button.text(defaultLabel + ' (' + remaining + 's)');
				});
			};

			tick();
			this.deleteCountdownTimer = window.setInterval(tick, 1000);
		},

		getScrollMetrics: function () {
			var element = this.chatScroll().get(0);

			if (!element) {
				return null;
			}

			return {
				element: element,
				scrollTop: element.scrollTop,
				scrollHeight: element.scrollHeight,
				clientHeight: element.clientHeight,
				distanceFromBottom: Math.max(0, element.scrollHeight - element.clientHeight - element.scrollTop)
			};
		},

		isNearBottom: function (threshold) {
			var metrics = this.getScrollMetrics();
			var safeThreshold = parseInt(threshold || 0, 10) || 0;

			if (!metrics) {
				return true;
			}

			return metrics.distanceFromBottom <= safeThreshold;
		},

		scrollToBottom: function () {
			var metrics = this.getScrollMetrics();
			if (metrics && metrics.element) {
				metrics.element.scrollTop = metrics.element.scrollHeight;
				this.lastKnownScrollTop = metrics.element.scrollTop;
				this.userBrowsingHistory = false;
			}
		},

		restoreScrollPosition: function (scrollTop) {
			var metrics = this.getScrollMetrics();
			var safeScrollTop = Math.max(0, parseInt(scrollTop || 0, 10) || 0);

			if (!metrics || !metrics.element) {
				return;
			}

			metrics.element.scrollTop = Math.min(safeScrollTop, Math.max(0, metrics.element.scrollHeight - metrics.element.clientHeight));
			this.lastKnownScrollTop = metrics.element.scrollTop;
			this.userBrowsingHistory = Math.max(0, metrics.element.scrollHeight - metrics.element.clientHeight - metrics.element.scrollTop) > 24;
		},

		scheduleScrollToBottom: function () {
			var self = this;
			var raf = window.requestAnimationFrame || function (callback) {
				return window.setTimeout(callback, 16);
			};
			var delays = [0, 80, 220, 480, 900];

			if (window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
				delays = delays.concat([1300, 1800]);
			}

			delays.forEach(function (delay) {
				window.setTimeout(function () {
					if (self.hasResellerInboxLayout() && self.activeConversationId > 0 && self.resellerViewMode === 'conversation') {
						self.showConversationView();
					}
					self.refreshOpenLayout(true);
					self.scrollToBottom();
				}, delay);
			});

			raf(function () {
				if (self.hasResellerInboxLayout() && self.activeConversationId > 0 && self.resellerViewMode === 'conversation') {
					self.showConversationView();
				}
				self.refreshOpenLayout(true);
				self.scrollToBottom();
			});
		},

		bindScrollMedia: function (stickToBottom) {
			var self = this;

			this.chatBox().find('.messenger-image').each(function () {
				var image = this;

				if (image.complete) {
					return;
				}

				$(image).one('load.messengerUi error.messengerUi', function () {
					if (stickToBottom) {
						self.scheduleScrollToBottom();
						return;
					}

					if (self.isOpen()) {
						self.restoreScrollPosition(self.lastKnownScrollTop);
					}
				});
			});
		},

		normalizeMessageText: function (value) {
			return String(value || '')
				.replace(/\s+/g, ' ')
				.trim();
		},

		escapeHtml: function (value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;');
		},

		formatTextHtml: function (value) {
			return this.escapeHtml(value).replace(/\n/g, '<br>');
		},

		extractFirstUrl: function (value) {
			var match = String(value || '').match(/(?:https?:\/\/|www\.)[^\s<]+/i);
			if (!match || !match[0]) {
				return '';
			}

			return this.normalizePreviewUrl(match[0]);
		},

		normalizePreviewUrl: function (value) {
			var normalized = $.trim(String(value || ''));

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
		},

		resetComposerLinkPreview: function () {
			window.clearTimeout(this.linkPreviewTimer);
			this.linkPreviewTimer = null;
			this.linkPreviewUrl = '';
			this.linkPreviewData = null;
			this.linkPreviewDismissedUrl = '';
			this.linkPreviewLoading = false;
			this.previewBox().hide().empty();
		},

		renderComposerLinkPreview: function () {
			var $box = this.previewBox();
			var preview = this.linkPreviewData;
			var removeLabel = window.MESSENGER_BOOTSTRAP.linkPreviewRemove || 'Send without preview';
			var loadingLabel = window.MESSENGER_BOOTSTRAP.linkPreviewLoading || 'Loading link preview...';
			var shouldStickToBottom = this.isOpen() && this.isNearBottom(120);

			if (!$box.length) {
				return;
			}

			if (this.linkPreviewLoading && this.linkPreviewUrl) {
				$box.html(
					'<div class="chat-link-preview-composer">' +
						'<div class="chat-link-preview-composer__state">' + this.escapeHtml(loadingLabel) + '</div>' +
					'</div>'
				).show();
				this.refreshOpenLayout(shouldStickToBottom);
				return;
			}

			if (!preview || !this.linkPreviewUrl || this.linkPreviewDismissedUrl === this.linkPreviewUrl) {
				$box.hide().empty();
				this.refreshOpenLayout(shouldStickToBottom);
				return;
			}

			$box.html(
				'<div class="chat-link-preview-composer">' +
					'<button type="button" class="chat-link-preview-composer__remove" data-messenger-link-preview-remove>' + this.escapeHtml(removeLabel) + '</button>' +
					this.buildPreviewCardHtml(preview) +
				'</div>'
			).show();
			this.refreshOpenLayout(shouldStickToBottom);
		},

		buildPreviewCardHtml: function (preview) {
			if (!preview || !preview.url) {
				return '';
			}

			var html = '<a href="' + this.escapeHtml(preview.url) + '" target="_blank" rel="noopener noreferrer" class="chat-link-preview chat-link-preview--composer">';
			if (preview.image_url) {
				html += '<span class="chat-link-preview__media"><img src="' + this.escapeHtml(preview.image_url) + '" alt="" loading="lazy"></span>';
			}
			html += '<span class="chat-link-preview__content">';
			if (preview.site_name) {
				html += '<span class="chat-link-preview__site">' + this.escapeHtml(preview.site_name) + '</span>';
			}
			if (preview.title) {
				html += '<span class="chat-link-preview__title">' + this.escapeHtml(preview.title) + '</span>';
			}
			if (preview.description) {
				html += '<span class="chat-link-preview__description">' + this.escapeHtml(preview.description) + '</span>';
			}
			if (preview.display_url) {
				html += '<span class="chat-link-preview__url">' + this.escapeHtml(preview.display_url) + '</span>';
			}
			html += '</span></a>';
			return html;
		},

		dismissComposerLinkPreview: function () {
			if (!this.linkPreviewUrl) {
				return;
			}

			this.linkPreviewDismissedUrl = this.linkPreviewUrl;
			this.linkPreviewLoading = false;
			this.renderComposerLinkPreview();
		},

		queueLinkPreviewRefresh: function () {
			var self = this;
			var cfg = this.config();
			var message = $.trim(this.input().val());
			var detectedUrl = this.extractFirstUrl(message);

			window.clearTimeout(this.linkPreviewTimer);
			this.linkPreviewTimer = null;

			if (!message || !detectedUrl) {
				this.linkPreviewUrl = '';
				this.linkPreviewData = null;
				this.linkPreviewLoading = false;
				if (!message) {
					this.linkPreviewDismissedUrl = '';
				}
				this.renderComposerLinkPreview();
				return;
			}

			if (this.linkPreviewDismissedUrl === detectedUrl) {
				this.linkPreviewUrl = detectedUrl;
				this.linkPreviewData = null;
				this.linkPreviewLoading = false;
				this.renderComposerLinkPreview();
				return;
			}

			if (this.linkPreviewUrl === detectedUrl && this.linkPreviewData) {
				this.linkPreviewLoading = false;
				this.renderComposerLinkPreview();
				return;
			}

			this.linkPreviewUrl = detectedUrl;
			this.linkPreviewData = null;
			this.linkPreviewLoading = true;
			this.renderComposerLinkPreview();

			this.linkPreviewTimer = window.setTimeout(function () {
				$.ajax({
					type: 'POST',
					url: cfg.endpoint,
					dataType: 'json',
					data: {
						action: 'link_preview',
						format: 'json',
						url: detectedUrl,
						_csrf: cfg.csrfToken
					}
				}).done(function (payload) {
					var currentUrl = self.extractFirstUrl($.trim(self.input().val()));
					if (currentUrl !== detectedUrl || self.linkPreviewDismissedUrl === detectedUrl) {
						return;
					}

					self.linkPreviewLoading = false;
					self.linkPreviewData = payload && payload.ok && payload.preview ? payload.preview : null;
					self.renderComposerLinkPreview();
				}).fail(function () {
					if (self.linkPreviewUrl !== detectedUrl) {
						return;
					}

					self.linkPreviewLoading = false;
					self.linkPreviewData = null;
					self.renderComposerLinkPreview();
				});
			}, 260);
		},

		appendLocalSentMessage: function (message) {
			var $list = this.chatBox().find('.messenger-list');
			var now = new Date();
			var hours = String(now.getHours()).padStart(2, '0');
			var minutes = String(now.getMinutes()).padStart(2, '0');
			var currentTime = hours + ':' + minutes;
			var safeMessage = this.escapeHtml(message).replace(/\n/g, '<br>');

			if (!$list.length || !safeMessage) {
				return;
			}

			$list.append(
				'<li class="messenger-time-anchor messenger-time-anchor--local"><span>' + currentTime + '</span></li>' +
				'<li class="messenger-item messenger-item--sent messenger-item--pending">' +
					'<div class="messenger-bubble">' +
						'<div class="messenger-text">' + safeMessage + '</div>' +
					'</div>' +
					'<div class="messenger-time-detail">' + currentTime + '</div>' +
				'</li>'
			);

			this.scrollToBottom();
		},

		scrollToExistingFaqMessage: function (questionText) {
			var normalizedQuestion = this.normalizeMessageText(questionText);
			var $messages = this.chatBox().find('.messenger-item .messenger-text');
			var $match = $();
			var $scrollContainer = this.chatScroll();

			if (!normalizedQuestion || !$messages.length || !$scrollContainer.length) {
				return false;
			}

			$messages.each(function () {
				var $text = $(this);
				if ($match.length) {
					return false;
				}

				if ($text.closest('.messenger-item--intro, .messenger-typing, .messenger-typed-reply').length) {
					return;
				}

				if (MessengerUI.normalizeMessageText($text.text()) === normalizedQuestion) {
					$match = $text.closest('.messenger-item');
				}
			});

			if (!$match.length) {
				return false;
			}

			$scrollContainer.stop(true).animate({
				scrollTop: Math.max(0, $match.position().top + $scrollContainer.scrollTop() - 24)
			}, 220);

			$match.addClass('messenger-item--focus');
			window.setTimeout(function () {
				$match.removeClass('messenger-item--focus');
			}, 1600);

			return true;
		},

		scheduleSyncAfterSend: function () {
			var self = this;
			[150, 800, 2000].forEach(function (delay) {
				window.setTimeout(function () {
					self.fetch({
						force: true,
						scrollToBottom: false
					});
				}, delay);
			});
		},

		setFaqButtonsDisabled: function (disabled) {
			$('[data-chat-faq-key]').prop('disabled', !!disabled).toggleClass('is-disabled', !!disabled);
		},

		showNotice: function (message, options) {
			var self = this;
			var $alert = this.alertBox();
			var hideAfter = options && options.persistent ? 0 : 5000;

			if (!$alert.length || !message) {
				return;
			}

			window.clearTimeout(this.noticeTimer);
			$alert.html(this.escapeHtml(message)).stop(true, true).fadeIn(120);

			if (hideAfter > 0) {
				this.noticeTimer = window.setTimeout(function () {
					self.clearNotice();
				}, hideAfter);
			}
		},

		clearNotice: function () {
			var $alert = this.alertBox();
			window.clearTimeout(this.noticeTimer);
			if ($alert.length) {
				$alert.stop(true, true).fadeOut(120, function () {
					$alert.text('');
				});
			}
		},

		syncConversationStateFromMarkup: function (skipPersist) {
			var $content = this.contentRoot();
			var conversationId = 0;
			var canSend = true;
			var canManageGroup = false;
			var conversationTitle = '';

			if ($content.length) {
				conversationId = parseInt($content.attr('data-chat-active-conversation-id') || '0', 10) || 0;
				this.activeConversationType = String($content.attr('data-chat-active-conversation-type') || 'live_chat');
				conversationTitle = String($content.attr('data-chat-active-conversation-title') || '');
				canSend = String($content.attr('data-chat-can-send') || '1') !== '0';
				canManageGroup = String($content.attr('data-chat-can-manage-group') || '0') === '1';
			} else {
				this.activeConversationType = 'live_chat';
			}

			this.activeConversationId = conversationId;
			this.activeCanSend = canSend;
			this.activeCanManageGroup = canManageGroup;
			this.activeConversationTitle = conversationTitle;
			this.oldestMessageId = parseInt($content.attr('data-chat-oldest-id') || '0', 10) || 0;
			this.activeConversationMessageLimit = parseInt($content.attr('data-chat-message-limit') || String(this.activeConversationMessageLimit || this.messagePageSize || 10), 10) || this.messagePageSize;
			this.activeConversationLoadedCount = parseInt($content.attr('data-chat-loaded-message-count') || '0', 10) || 0;
			this.activeConversationTotalMessages = parseInt($content.attr('data-chat-total-message-count') || '0', 10) || 0;
			this.hasMoreMessages = String($content.attr('data-chat-has-more-messages') || '0') === '1';
			if (!this.hasMoreMessages && this.activeConversationTotalMessages > this.activeConversationLoadedCount) {
				this.hasMoreMessages = true;
			}
			if (!this.hasMoreMessages && this.oldestMessageId > 0 && this.activeConversationLoadedCount >= this.activeConversationMessageLimit) {
				this.hasMoreMessages = true;
			}
			this.updateComposerAvailability();
			this.updateConversationIntroVisibility();
			if (this.hasResellerInboxLayout() && !skipPersist) {
				this.saveActiveConversationState();
			}
		},

		updateConversationIntroVisibility: function () {
			var shouldHide;
			var $intro = this.chatBox().find('.messenger-item--intro');

			if (!$intro.length) {
				return;
			}

			shouldHide = this.activeConversationTotalMessages > this.messagePageSize
				|| this.activeConversationLoadedCount > this.messagePageSize
				|| this.hasMoreMessages;

			$intro.toggleClass('is-collapsed', !!shouldHide);
		},

		ensureScrollableHistory: function () {
			var metrics = this.getScrollMetrics();
			var shouldPrefetchTop = false;

			if (!this.isOpen() || !this.isConversationHistoryActive()) {
				return;
			}

			if (!this.activeConversationId || !this.hasMoreMessages || this.loadingOlderMessages) {
				return;
			}

			if (!metrics) {
				return;
			}

			shouldPrefetchTop = metrics.scrollTop <= 48;
			if (!shouldPrefetchTop && metrics.scrollHeight > (metrics.clientHeight + 40)) {
				return;
			}

			this.queueOlderMessagesLoad(shouldPrefetchTop ? 100 : 180);
		},

		maybeLoadOlderMessages: function () {
			var self = this;
			var metrics = this.getScrollMetrics();
			var nextLimit;

			if (!this.isOpen() || !this.isConversationHistoryActive()) {
				return;
			}

			if (!this.activeConversationId || !this.hasMoreMessages || this.loadingOlderMessages) {
				return;
			}

			if (!metrics || metrics.scrollTop > 72) {
				return;
			}

			if (this.fetchInFlight || this.sendInFlight || this.uploadInFlight || this.faqPendingKey) {
				window.clearTimeout(this.olderMessagesRetryTimer);
				this.olderMessagesRetryTimer = window.setTimeout(function () {
					self.olderMessagesRetryTimer = null;
					self.maybeLoadOlderMessages();
				}, 220);
				return;
			}

			nextLimit = Math.max(this.activeConversationMessageLimit + this.loadOlderBatchSize, this.messagePageSize);
			this.clearOlderMessagesTimers();
			this.loadingOlderMessages = true;
			this.fetch({
				force: true,
				conversationId: this.activeConversationId,
				messageLimit: nextLimit,
				preservePrependOffset: true,
				scrollToBottom: false
			}).always(function () {
				self.loadingOlderMessages = false;
				self.ensureScrollableHistory();
			});
		},

		hasResellerInboxLayout: function () {
			return this.listView().length > 0 && this.conversationView().length > 0;
		},

		isConversationHistoryActive: function () {
			if (this.hasResellerInboxLayout()) {
				return this.resellerViewMode === 'conversation';
			}

			return true;
		},

		showConversationList: function () {
			if (!this.hasResellerInboxLayout()) {
				return;
			}

			this.resellerViewMode = 'list';
			this.setConversationLoadingState(false);
			this.listView().prop('hidden', false);
			this.conversationView().prop('hidden', true);
			this.saveActiveConversationState();
		},

		showConversationView: function () {
			if (!this.hasResellerInboxLayout()) {
				return;
			}

			this.resellerViewMode = 'conversation';
			this.listView().prop('hidden', true);
			this.conversationView().prop('hidden', false);
			this.saveActiveConversationState();
		},

		setConversationLoadingState: function (isLoading) {
			var $chatBox = this.chatBox();
			var $loader = this.conversationTransition();
			var $stage = this.conversationStage();
			var loading = !!isLoading;

			if ($chatBox.length) {
				$chatBox.toggleClass('is-conversation-loading', loading);
			}

			if ($loader.length) {
				$loader.prop('hidden', !loading).toggleClass('is-visible', loading);
			}

			if ($stage.length) {
				$stage.toggleClass('is-dimmed', loading);
			}
		},

		playConversationEntryAnimation: function () {
			var self = this;
			var $stage = this.conversationStage();

			if (!$stage.length) {
				return;
			}

			window.clearTimeout(this.conversationTransitionTimer);
			$stage.removeClass('is-entering');
			if ($stage[0] && typeof $stage[0].offsetWidth !== 'undefined') {
				$stage[0].offsetWidth;
			}
			$stage.addClass('is-entering');
			this.conversationTransitionTimer = window.setTimeout(function () {
				self.conversationStage().removeClass('is-entering');
			}, 280);
		},

		updateComposerAvailability: function () {
			var writeMessagePlaceholder = window.MESSENGER_BOOTSTRAP.writeMessagePlaceholder || 'Write message...';
			var readOnlyPlaceholder = window.MESSENGER_BOOTSTRAP.groupReadOnlyPlaceholder || 'This group is read only.';
			var disabledByCooldown = !!this.cooldownTimer;
			var disabled = !this.activeCanSend || disabledByCooldown;
			var $input = this.input();
			var $sendButton = $('#btn-chat');
			var $uploadButton = $('[data-messenger-upload-open]');

			if (!$input.length) {
				return;
			}

			$input.prop('disabled', disabled);
			$sendButton.prop('disabled', disabled);
			$uploadButton.prop('disabled', disabled);

			if (!this.activeCanSend) {
				$input.attr('placeholder', readOnlyPlaceholder);
				return;
			}

			if (!disabledByCooldown) {
				$input.attr('placeholder', writeMessagePlaceholder);
			}
		},

		showGroupAlert: function (message, isError) {
			var $alert = this.groupAlertBox();

			if (!$alert.length) {
				return;
			}

			$alert.text(message || '');
			$alert.toggleClass('messenger-alert--error', !!isError);
			$alert.toggle(!!message);
		},

		renderGroupMembers: function () {
			var $box = this.groupMembersBox();
			var self = this;

			if (!$box.length) {
				return;
			}

			if (!this.groupEmails.length) {
				$box.empty();
				return;
			}

			$box.html($.map(this.groupEmails, function (email, index) {
				return '' +
					'<button type="button" class="messenger-group-member" data-messenger-group-remove data-index="' + index + '">' +
						'<span>' + self.escapeHtml(email) + '</span>' +
						'<i class="fa fa-times" aria-hidden="true"></i>' +
					'</button>';
			}).join(''));
		},

		setGroupCreationKind: function (kind) {
			var normalizedKind = String(kind || '').toLowerCase() === 'group' ? 'group' : 'direct';
			var isGroupKind = normalizedKind === 'group';

			this.groupCreationKind = normalizedKind;
			this.groupModeButtons().removeClass('is-active').attr('aria-pressed', 'false');
			this.groupModeButtons().filter('[data-messenger-group-kind="' + normalizedKind + '"]').addClass('is-active').attr('aria-pressed', 'true');

			if (this.groupModalMode === 'invite') {
				return;
			}

			this.groupNameField().toggle(isGroupKind);
			this.groupModalTitle().text(isGroupKind
				? (window.MESSENGER_BOOTSTRAP.groupCreateTitle || 'Create group chat')
				: (window.MESSENGER_BOOTSTRAP.groupDirectTitle || 'Start direct conversation'));
			this.groupSubmitLabel().text(isGroupKind
				? (window.MESSENGER_BOOTSTRAP.groupCreateSubmit || 'Create group')
				: (window.MESSENGER_BOOTSTRAP.groupDirectSubmit || 'Start conversation'));
			this.groupEmailLabel().text(isGroupKind
				? (window.MESSENGER_BOOTSTRAP.groupGroupEmailLabel || 'Add participant by email')
				: (window.MESSENGER_BOOTSTRAP.groupDirectEmailLabel || 'Add reseller by email'));
			this.groupHint().text(isGroupKind
				? (window.MESSENGER_BOOTSTRAP.groupGroupHint || 'Each invitation is valid for 24 hours. If nobody accepts it in time, it is removed automatically.')
				: (window.MESSENGER_BOOTSTRAP.groupDirectHint || 'Add one reseller email to start a direct conversation right away.'));
			this.groupRetentionField().toggle(isGroupKind);

			if (!isGroupKind) {
				this.groupNameInput().val('');
				this.groupRetentionCreate().val('0');
				if (this.groupEmails.length > 1) {
					this.groupEmails = this.groupEmails.slice(0, 1);
					this.renderGroupMembers();
				}
			}
		},

		resetGroupModal: function () {
			this.groupEmails = [];
			this.groupEmailRequests = {};
			this.groupModalMode = 'create';
			this.groupCreationKind = 'direct';
			this.groupTargetConversationId = 0;
			this.groupNameInput().val('');
			this.groupEmailInput().val('');
			this.groupModeSwitch().show();
			this.groupNameField().show();
			this.groupRetentionField().hide();
			this.groupRetentionCreate().val('0');
			this.groupContextField().hide();
			this.groupContextTitle().text('');
			this.renderGroupMembers();
			this.showGroupAlert('', false);
			this.setGroupCreationKind('direct');
		},

		openGroupModal: function (mode, options) {
			var targetMode = String(mode || 'create');
			var targetOptions = options || {};
			if (targetMode === 'create' && !window.MESSENGER_BOOTSTRAP.groupCreateEnabled) {
				return false;
			}

			this.resetGroupModal();
			this.groupModalMode = targetMode === 'invite' ? 'invite' : 'create';
			this.groupTargetConversationId = parseInt(targetOptions.conversationId || 0, 10) || 0;

			if (this.groupModalMode === 'invite') {
				this.groupModeSwitch().hide();
				this.groupNameField().hide();
				this.groupRetentionField().hide();
				this.groupContextField().show();
				this.groupContextLabel().text(window.MESSENGER_BOOTSTRAP.groupInviteNameLabel || 'Group');
				this.groupContextTitle().text(String(targetOptions.title || this.activeConversationTitle || 'Group chat'));
				this.groupModalTitle().text(window.MESSENGER_BOOTSTRAP.groupInviteTitle || 'Add members to group');
				this.groupSubmitLabel().text(window.MESSENGER_BOOTSTRAP.groupInviteSubmit || 'Send invitations');
			}

			this.groupModal().addClass('is-open').attr('aria-hidden', 'false');
			$('body').addClass('messenger-upload-open');
			window.setTimeout(function () {
				if (targetMode === 'invite' || self.groupCreationKind === 'direct') {
					$('#messenger_group_email').trigger('focus');
					return;
				}
				$('#messenger_group_name').trigger('focus');
			}, 20);
			return false;
		},

		closeGroupModal: function () {
			this.groupModal().removeClass('is-open').attr('aria-hidden', 'true');
			$('body').removeClass('messenger-upload-open');
			this.resetGroupModal();
			return false;
		},

		addGroupEmail: function () {
			var self = this;
			var cfg = this.config();
			var email = $.trim(String(this.groupEmailInput().val() || '')).toLowerCase();
			if (!email) {
				return false;
			}

			if (email.charAt(0) === '@') {
				email = '@' + email.slice(1).replace(/[^a-z0-9._-]+/g, '');
			}

			if (!(/^@[a-z0-9._-]{2,}$/i.test(email) || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailInvalid || 'Enter a valid email address or handle starting with @.', true);
				return false;
			}

			if (this.groupEmails.indexOf(email) !== -1) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailDuplicate || 'This invitation is already added.', true);
				return false;
			}

			if (this.groupModalMode !== 'invite' && this.groupCreationKind === 'direct' && this.groupEmails.length >= 1) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupDirectLimit || 'Direct conversation allows only one reseller.', true);
				return false;
			}

			if (this.groupEmailRequests[email]) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailChecking || 'Checking user...', false);
				return false;
			}

			this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailChecking || 'Checking user...', false);
			this.groupEmailRequests[email] = true;

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'validate_group_email',
					format: 'json',
					email: email,
					conversation_id: this.groupModalMode === 'invite' ? this.groupTargetConversationId : 0,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showGroupAlert((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupEmailNotFound || 'No reseller or admin account was found for this email.'), true);
					return;
				}

				if (self.groupEmails.indexOf(String(payload.email || email).toLowerCase()) !== -1) {
					self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailDuplicate || 'This invitation is already added.', true);
					return;
				}

				self.groupEmails.push(String(payload.email || email).toLowerCase());
				self.groupEmailInput().val('').trigger('focus');
				self.renderGroupMembers();
				self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailAdded || 'Invitation prepared. It will expire after 24 hours if not accepted.', false);
			}).fail(function () {
				self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailNotFound || 'No reseller or admin account was found for this email.', true);
			}).always(function () {
				delete self.groupEmailRequests[email];
			});

			return false;
		},

		toggleGroupMenu: function () {
			var $button = $('[data-chat-group-menu-toggle]');
			var $menu = $('[data-chat-group-menu]');
			var isOpen = $menu.hasClass('is-open');

			this.closeGroupMembersPopover();
			this.closeGroupSettingsMenu();
			$button.attr('aria-expanded', isOpen ? 'false' : 'true');
			$menu.toggleClass('is-open', !isOpen);
			return false;
		},

		closeGroupMenu: function () {
			$('[data-chat-group-menu-toggle]').attr('aria-expanded', 'false');
			$('[data-chat-group-menu]').removeClass('is-open');
		},

		toggleGroupSettingsMenu: function () {
			var $button = $('[data-chat-group-settings-toggle]');
			var $menu = $('[data-chat-group-settings-menu]');
			var isOpen = $menu.hasClass('is-open');

			this.closeGroupMenu();
			this.closeGroupMembersPopover();
			$button.attr('aria-expanded', isOpen ? 'false' : 'true');
			$menu.toggleClass('is-open', !isOpen);
			return false;
		},

		closeGroupSettingsMenu: function () {
			$('[data-chat-group-settings-toggle]').attr('aria-expanded', 'false');
			$('[data-chat-group-settings-menu]').removeClass('is-open');
		},

		toggleGroupMembersPopover: function () {
			var $button = $('[data-chat-group-members-toggle]');
			var $popover = $('[data-chat-group-members-popover]');
			var isOpen = $popover.hasClass('is-open');

			this.closeGroupMenu();
			this.closeGroupSettingsMenu();
			$button.attr('aria-expanded', isOpen ? 'false' : 'true');
			$popover.toggleClass('is-open', !isOpen);
			return false;
		},

		closeGroupMembersPopover: function () {
			$('[data-chat-group-members-toggle]').attr('aria-expanded', 'false');
			$('[data-chat-group-members-popover]').removeClass('is-open');
		},

		updateHomeInvites: function (html) {
			var markup = $.trim(String(html || ''));
			var $slot = $('[data-group-chat-invites-slot]').first();
			var $existing = $('#group_chat_invites_home');
			var $balance = $('.balance').first();

			if ($slot.length) {
				$slot.html(markup);
				return;
			}

			if ($existing.length) {
				if (markup) {
					$existing.replaceWith(markup);
				} else {
					$existing.remove();
				}
				return;
			}

			if (markup && $balance.length) {
				$balance.after(markup);
			}
		},

		updateUploadProgress: function (percent) {
			var safePercent = Math.max(0, Math.min(100, parseInt(percent || 0, 10)));
			this.uploadProgressBox().show();
			this.uploadProgressFill().css('width', safePercent + '%');
			this.uploadProgressValue().text(safePercent + '%');
		},

		resetUploadProgress: function () {
			this.uploadProgressBox().hide();
			this.uploadProgressFill().css('width', '0%');
			this.uploadProgressValue().text('0%');
		},

		applyCooldown: function (seconds, message) {
			var self = this;
			var remaining = parseInt(seconds || 0, 10);
			var $input = this.input();
			var $sendButton = $('#btn-chat');
			var writeMessagePlaceholder = window.MESSENGER_BOOTSTRAP.writeMessagePlaceholder || 'Write message...';

			window.clearInterval(this.cooldownTimer);

			if (remaining <= 0) {
				$input.prop('disabled', false).attr('placeholder', writeMessagePlaceholder);
				$sendButton.prop('disabled', false);
				self.setFaqButtonsDisabled(false);
				self.updateComposerAvailability();
				return;
			}

			$input.prop('disabled', true).attr('placeholder', 'Cooldown: ' + remaining + 's');
			$sendButton.prop('disabled', true);
			self.setFaqButtonsDisabled(true);

			if (message) {
				this.showNotice(message, { persistent: true });
			}

			this.cooldownTimer = window.setInterval(function () {
				remaining -= 1;

				if (remaining <= 0) {
					window.clearInterval(self.cooldownTimer);
					self.cooldownTimer = null;
					$input.prop('disabled', false).attr('placeholder', writeMessagePlaceholder);
					$sendButton.prop('disabled', false);
					self.setFaqButtonsDisabled(false);
					self.clearNotice();
					self.updateComposerAvailability();
					return;
				}

				$input.attr('placeholder', 'Cooldown: ' + remaining + 's');
			}, 1000);
		},

		showTypingIndicator: function () {
			var $list = this.chatBox().find('.messenger-list');
			var supportName = window.MESSENGER_BOOTSTRAP.supportName || 'Support';

			if (!$list.length || $list.find('.messenger-typing').length) {
				return;
			}

			$list.append(
				'<li class="messenger-item messenger-item--received messenger-typing">' +
					'<div class="messenger-bubble">' +
						'<div class="messenger-author">' + this.escapeHtml(supportName) + '</div>' +
						'<div class="messenger-typing-dots" aria-hidden="true"><span></span><span></span><span></span></div>' +
					'</div>' +
				'</li>'
			);
			this.scrollToBottom();
		},

		clearTypingIndicator: function () {
			this.chatBox().find('.messenger-typing').remove();
		},

		startTypedReply: function (message) {
			var self = this;
			var $list = this.chatBox().find('.messenger-list');
			var supportName = window.MESSENGER_BOOTSTRAP.supportName || 'Support';
			var rawMessage = String(message || '');
			var totalChars = rawMessage.length;
			var duration = 3000;
			var stepDelay = totalChars > 0 ? Math.max(18, Math.floor(duration / totalChars)) : duration;
			var currentIndex = 0;
			var $typingItem;
			var $typingText;

			window.clearInterval(this.typewriterTimer);
			this.clearTypingIndicator();

			if (!$list.length || !rawMessage) {
				return;
			}

			$typingItem = $(
				'<li class="messenger-item messenger-item--received messenger-typed-reply">' +
					'<div class="messenger-bubble">' +
						'<div class="messenger-author">' + this.escapeHtml(supportName) + '</div>' +
						'<div class="messenger-text"></div>' +
					'</div>' +
				'</li>'
			);
			$typingText = $typingItem.find('.messenger-text');
			$list.append($typingItem);
			this.scrollToBottom();

			this.typewriterTimer = window.setInterval(function () {
				currentIndex += 1;
				$typingText.html(self.formatTextHtml(rawMessage.slice(0, currentIndex)));
				self.scrollToBottom();

				if (currentIndex >= totalChars) {
					window.clearInterval(self.typewriterTimer);
					self.typewriterTimer = null;
				}
			}, stepDelay);
		},

		openUploadModal: function () {
			var $modal = this.uploadModal();
			if (!$modal.length) {
				return false;
			}

			$('body').addClass('messenger-upload-open');
			$modal.addClass('is-open').attr('aria-hidden', 'false');

			return false;
		},

		closeUploadModal: function () {
			var $modal = this.uploadModal();
			if (!$modal.length) {
				return false;
			}

			$modal.removeClass('is-open').attr('aria-hidden', 'true');
			$('body').removeClass('messenger-upload-open');
			this.resetUploadState();

			return false;
		},

		open: function () {
			var self = this;
			if (this.hasResellerInboxLayout()) {
				if (this.activeConversationId > 0) {
					this.showConversationView();
				} else {
					this.showConversationList();
				}
			}
			this.refreshOpenLayout(true);
			this.widget().addClass('is-open');
			this.heading().attr('aria-expanded', 'true');
			this.panel().attr('aria-hidden', 'false').addClass('in is-visible').stop(true, true).slideDown(180, function () {
				self.refreshOpenLayout(true);
				self.scheduleScrollToBottom();
			});
			this.icon().removeClass('fa-angle-down').addClass('fa-angle-up');
			this.markRead();
			this.savePanelState(true);
			this.fetch({
				force: true,
				scrollToBottom: true
			});
			window.setTimeout(function () {
				if (self.isOpen()) {
					self.scheduleScrollToBottom();
				}
			}, 360);
			return false;
		},

		close: function () {
			this.widget().removeClass('is-open');
			this.heading().attr('aria-expanded', 'false');
			this.panel().attr('aria-hidden', 'true').removeClass('in is-visible').stop(true, true).slideUp(180);
			this.icon().removeClass('fa-angle-up').addClass('fa-angle-down');
			this.savePanelState(false);
			return false;
		},

		toggle: function () {
			return this.isOpen() ? this.close() : this.open();
		},

		handlePaymentCardRedirect: function (url) {
			var targetUrl = $.trim(String(url || ''));
			var self = this;

			if (!targetUrl) {
				return false;
			}

			this.closeGroupMenu();
			this.closeGroupSettingsMenu();
			this.closeGroupMembersPopover();
			this.close();

			window.setTimeout(function () {
				window.location.assign(targetUrl);
			}, 190);

			window.setTimeout(function () {
				self.close();
			}, 20);

			return false;
		},

		renderPayload: function (payload, options) {
			var previousMetrics = this.getScrollMetrics();
			var keepBottom = false;
			var preservePrependOffset = false;
			var previousScrollHeight = previousMetrics ? previousMetrics.scrollHeight : 0;
			var previousScrollTop = previousMetrics ? previousMetrics.scrollTop : 0;
			var previousLoadedCount = this.activeConversationLoadedCount;
			options = options || {};
			if (!payload || typeof payload.html !== 'string') {
				return;
			}

			if (previousMetrics) {
				this.lastKnownScrollTop = previousMetrics.scrollTop;
				keepBottom = !!options.scrollToBottom || (this.isOpen() && !this.userBrowsingHistory && previousMetrics.distanceFromBottom <= 48);
			} else {
				keepBottom = !!options.scrollToBottom;
			}

			if (this.restoreOpenScrollToBottomPending && this.isOpen()) {
				keepBottom = true;
			}

			preservePrependOffset = !!options.preservePrependOffset && !!previousMetrics;
			if (preservePrependOffset) {
				keepBottom = false;
			}

			if (payload.html !== this.lastRenderedHtml || options.force) {
				this.chatBox().html(payload.html);
				this.lastRenderedHtml = payload.html;
				this.closeGroupMenu();
				this.closeGroupSettingsMenu();
				this.closeGroupMembersPopover();
				this.syncConversationStateFromMarkup();
				if (preservePrependOffset && this.activeConversationLoadedCount <= previousLoadedCount) {
					this.hasMoreMessages = false;
				}
				this.bindChatScrollHandler();
				if (this.hasResellerInboxLayout()) {
					if (this.resellerViewMode === 'conversation' && this.activeConversationId > 0) {
						this.showConversationView();
					} else {
						this.showConversationList();
					}
				}
				this.startDeleteCountdowns();
				this.setConversationLoadingState(false);
				if (options.animateConversation) {
					this.playConversationEntryAnimation();
				}
				if (this.faqPendingKey) {
					this.showTypingIndicator();
				}

				if (preservePrependOffset) {
					var currentMetrics = this.getScrollMetrics();
					if (currentMetrics && currentMetrics.element) {
						currentMetrics.element.scrollTop = Math.max(0, previousScrollTop + (currentMetrics.scrollHeight - previousScrollHeight));
						this.lastKnownScrollTop = currentMetrics.element.scrollTop;
					}
				} else if (keepBottom) {
					this.scheduleScrollToBottom();
				} else if (previousMetrics) {
					this.restoreScrollPosition(previousMetrics.scrollTop);
				}

				this.bindScrollMedia(keepBottom);
				this.ensureScrollableHistory();
			}

			if (keepBottom && this.restoreOpenScrollToBottomPending) {
				if (this.hasResellerInboxLayout() && this.activeConversationId > 0) {
					this.showConversationView();
				}
				this.restoreOpenScrollToBottomPending = false;
				this.scheduleScrollToBottom();
			}

			if (!keepBottom) {
				this.ensureScrollableHistory();
			}

			this.lastMessageId = parseInt(payload.last_message_id || 0, 10);
			this.updateUnreadBadge(payload.unread_count || 0);
			if (typeof payload.group_invites_html === 'string') {
				this.updateHomeInvites(payload.group_invites_html);
			}
		},

		fetch: function (options) {
			var self = this;
			var cfg = this.config();
			var requestConversationId;
			var requestData;
			options = options || {};

			if (!cfg.userId || this.fetchInFlight || this.sendInFlight || this.uploadInFlight || this.faqPendingKey) {
				return $.Deferred().resolve().promise();
			}

			this.fetchInFlight = true;
			requestConversationId = options.hasOwnProperty('conversationId') ? (parseInt(options.conversationId || 0, 10) || 0) : this.activeConversationId;
			requestData = {
				action: options.markRead ? 'read' : 'fetch',
				format: 'json',
				message_limit: parseInt(options.messageLimit || this.activeConversationMessageLimit || this.messagePageSize || 10, 10) || this.messagePageSize
			};

			if (options.markRead) {
				requestData.user = cfg.userId;
				requestData._csrf = cfg.csrfToken;
			}

			if (requestConversationId > 0 || options.hasOwnProperty('conversationId')) {
				requestData.conversation_id = requestConversationId;
			}

			return $.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: requestData
			}).done(function (payload) {
				if (payload && payload.ok) {
					self.renderPayload(payload, {
						force: !!options.force,
						scrollToBottom: !!options.scrollToBottom,
						preservePrependOffset: !!options.preservePrependOffset,
						animateConversation: !!options.animateConversation
					});
				} else if (payload && payload.cooldown_active) {
					self.applyCooldown(payload.cooldown_seconds || 30, payload.message || '');
				}
			}).always(function () {
				self.fetchInFlight = false;
			});
		},

		markRead: function (conversationId) {
			return this.fetch({
				markRead: true,
				conversationId: typeof conversationId === 'undefined' ? this.activeConversationId : conversationId,
				scrollToBottom: true
			});
		},

		sendMessage: function () {
			var self = this;
			var cfg = this.config();
			var message = $.trim(this.input().val());
			var detectedUrl = this.extractFirstUrl(message);
			var previewUrl = this.linkPreviewData && this.linkPreviewUrl === detectedUrl && this.linkPreviewDismissedUrl !== detectedUrl ? this.linkPreviewUrl : '';
			var previewRemoved = detectedUrl && this.linkPreviewDismissedUrl === detectedUrl ? 1 : 0;

			if (!cfg.userId || !message || this.sendInFlight || this.uploadInFlight || this.faqPendingKey || !this.activeCanSend) {
				return false;
			}

			this.sendInFlight = true;
			$('#btn-chat').prop('disabled', true);
			this.input().prop('disabled', true);
			this.input().val('');
			this.appendLocalSentMessage(message);

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'send',
					format: 'json',
					id_usera: cfg.userId,
					tresc: message,
					conversation_id: this.activeConversationId,
					link_preview_url: previewUrl,
					link_preview_removed: previewRemoved,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (payload && payload.ok) {
					self.resetComposerLinkPreview();
					self.renderPayload(payload, {
						force: true,
						scrollToBottom: true
					});
					if (payload.cooldown_active) {
						self.applyCooldown(payload.cooldown_seconds || 30, payload.message || '');
					} else {
						self.scheduleSyncAfterSend();
					}
				} else if (payload && payload.cooldown_active) {
					self.input().val(message);
					self.queueLinkPreviewRefresh();
					self.fetch({
						force: true,
						scrollToBottom: true
					});
					self.applyCooldown(payload.cooldown_seconds || 30, payload.message || '');
				} else {
					self.input().val(message);
					self.queueLinkPreviewRefresh();
					self.fetch({
						force: true,
						scrollToBottom: true
					});
				}
			}).fail(function () {
				self.input().val(message);
				self.queueLinkPreviewRefresh();
				self.fetch({
					force: true,
					scrollToBottom: true
				});
			}).always(function () {
				self.sendInFlight = false;
				$('#btn-chat').prop('disabled', false);
				self.input().prop('disabled', false).trigger('focus');
				self.updateComposerAvailability();
			});

			return false;
		},

		requestFaqAction: function (action, faqKey) {
			var self = this;
			var cfg = this.config();

			return $.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: action,
					format: 'json',
					faq_key: faqKey,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (payload && payload.ok) {
					self.renderPayload(payload, {
						force: true,
						scrollToBottom: true
					});
				} else if (payload && payload.cooldown_active) {
					self.applyCooldown(payload.cooldown_seconds || 30, payload.message || '');
				}
			});
		},

		startFaqFlow: function (faqKey) {
			var self = this;
			var $faqButton = $('[data-chat-faq-key="' + faqKey + '"]').first();
			var faqQuestionText = $.trim($faqButton.text());

			if (!faqKey || this.fetchInFlight || this.sendInFlight || this.uploadInFlight || this.faqPendingKey) {
				return false;
			}

			if (this.scrollToExistingFaqMessage(faqQuestionText)) {
				return false;
			}

			this.setFaqButtonsDisabled(true);

			this.requestFaqAction('faq_prompt', faqKey).done(function (payload) {
				self.faqPendingKey = faqKey;
				self.startTypedReply(payload && payload.faq_answer_text ? payload.faq_answer_text : '');
				window.clearTimeout(self.faqReplyTimer);
				self.faqReplyTimer = window.setTimeout(function () {
					self.faqPendingKey = '';
					self.fetch({
						force: true,
						scrollToBottom: true
					}).always(function () {
						self.setFaqButtonsDisabled(false);
					});
				}, 3100);
			}).fail(function () {
				self.faqPendingKey = '';
				self.setFaqButtonsDisabled(false);
			});

			return false;
		},

		previewFile: function () {
			var preview = document.querySelector('#preview_img');
			var fileInput = document.querySelector('#file');
			var file = fileInput && fileInput.files ? fileInput.files[0] : null;
			var validImageTypes = ['image/gif', 'image/jpeg', 'image/png'];

			if (!file) {
				$('#preview_img').hide().attr('src', '');
				$('#button_upload2').prop('disabled', true);
				this.resetUploadProgress();
				return;
			}

			if ($.inArray(file.type, validImageTypes) < 0) {
				this.showNotice(window.MESSENGER_BOOTSTRAP.invalidImageMessage || 'The selected file is not a supported image.');
				$('#file').val('');
				$('#preview_img').hide().attr('src', '');
				$('#button_upload2').prop('disabled', true);
				this.resetUploadProgress();
				return;
			}

			var reader = new FileReader();
			reader.addEventListener('load', function () {
				if (preview) {
					preview.src = reader.result;
				}
				$('#preview_img').show();
				$('#button_upload2').prop('disabled', false);
			}, false);
			this.resetUploadProgress();
			reader.readAsDataURL(file);
		},

		resetUploadState: function () {
			$('#file').val('');
			$('#preview_img').hide().attr('src', '');
			$('#button_upload2').prop('disabled', true);
			this.resetUploadProgress();
		},

		uploadAttachment: function () {
			var self = this;
			var cfg = this.config();
			var fileInput = document.getElementById('file');

			if (!cfg.userId || !fileInput || !fileInput.files || !fileInput.files[0] || this.sendInFlight || this.uploadInFlight || this.faqPendingKey || !this.activeCanSend) {
				return false;
			}

			this.uploadInFlight = true;
			$('#button_upload2').prop('disabled', true);
			this.updateUploadProgress(0);

			var formData = new FormData();
			formData.append('action', 'upload');
			formData.append('format', 'json');
			formData.append('conversation_id', String(this.activeConversationId || 0));
			formData.append('_csrf', cfg.csrfToken);
			formData.append('file', fileInput.files[0]);

			$.ajax({
				type: 'POST',
				processData: false,
				contentType: false,
				xhr: function () {
					var xhr = $.ajaxSettings.xhr();
					if (xhr && xhr.upload) {
						xhr.upload.addEventListener('progress', function (event) {
							if (event.lengthComputable) {
								self.updateUploadProgress(Math.round((event.loaded / event.total) * 100));
							}
						}, false);
					}
					return xhr;
				},
				url: cfg.endpoint,
				dataType: 'json',
				data: formData
			}).done(function (payload) {
				if (payload && payload.ok) {
					self.updateUploadProgress(100);
					self.renderPayload(payload, {
						force: true,
						scrollToBottom: true
					});
					self.closeUploadModal();
					self.resetUploadState();
				} else if (payload && payload.cooldown_active) {
					self.applyCooldown(payload.cooldown_seconds || 30, payload.message || '');
				} else if (payload && payload.message) {
					self.showNotice(payload.message);
				}
			}).always(function () {
				self.uploadInFlight = false;
				$('#button_upload2').prop('disabled', false);
				self.updateComposerAvailability();
			});

			return false;
		},

		deleteMessage: function (messageId) {
			var self = this;
			var cfg = this.config();

			if (!cfg.userId || !messageId || this.sendInFlight || this.uploadInFlight || this.fetchInFlight || this.faqPendingKey) {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'delete_message',
					format: 'json',
					message_id: messageId,
					conversation_id: this.activeConversationId,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (payload && payload.ok) {
					self.renderPayload(payload, {
						force: true,
						scrollToBottom: false
					});
				} else if (payload && payload.message) {
					self.showNotice(payload.message);
					self.fetch({ force: true });
				}
			}).fail(function () {
				self.showNotice(window.MESSENGER_BOOTSTRAP.deleteFailedMessage || 'Message deletion failed.');
				self.fetch({ force: true });
			});

			return false;
		},

		selectConversation: function (conversationId, conversationType) {
			var nextConversationId = parseInt(conversationId || 0, 10) || 0;
			var nextConversationType = String(conversationType || 'live_chat');
			var self = this;

			this.activeConversationId = nextConversationId;
			this.activeConversationType = nextConversationType;
			this.activeConversationMessageLimit = this.messagePageSize;
			this.activeConversationLoadedCount = 0;
			this.activeConversationTotalMessages = 0;
			this.oldestMessageId = 0;
			this.hasMoreMessages = false;
			if (this.hasResellerInboxLayout()) {
				this.resellerViewMode = 'conversation';
				this.saveActiveConversationState();
				this.showConversationView();
				this.setConversationLoadingState(true);
			}
			this.fetch({
				force: true,
				conversationId: nextConversationId,
				scrollToBottom: true,
				animateConversation: true
			}).done(function () {
				self.markRead(nextConversationId);
			}).always(function () {
				self.setConversationLoadingState(false);
			});
			return false;
		},

		submitGroupCreate: function () {
			var self = this;
			var cfg = this.config();
			var groupName = $.trim(this.groupNameInput().val());
			var action = this.groupModalMode === 'invite' ? 'invite_to_group' : 'create_group';
			var modalMode = this.groupModalMode;

			if (!this.groupEmails.length) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupParticipantsRequired || 'Add at least one participant.', true);
				return false;
			}

			if (this.groupModalMode !== 'invite' && this.groupCreationKind === 'group' && !groupName) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupNameRequired || 'Group name is required.', true);
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: action,
					format: 'json',
					group_name: groupName,
					conversation_id: this.groupModalMode === 'invite' ? this.groupTargetConversationId : 0,
					participant_emails_json: JSON.stringify(this.groupEmails),
					retention_hours: this.groupModalMode === 'invite' ? '0' : (this.groupRetentionCreate().val() || '0'),
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showGroupAlert((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupCreateError || 'Unable to create group chat.'), true);
					return;
				}

				self.closeGroupModal();
				if (modalMode === 'invite') {
					self.showNotice(window.MESSENGER_BOOTSTRAP.groupInviteSuccess || 'Invitations sent.');
				}
				self.renderPayload(payload, {
					force: true,
					scrollToBottom: true
				});
			}).fail(function () {
				self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupCreateError || 'Unable to create group chat.', true);
			});

			return false;
		},

		deleteGroup: function (conversationId) {
			var self = this;
			var cfg = this.config();
			var safeConversationId = parseInt(conversationId || this.activeConversationId || 0, 10) || 0;

			if (!safeConversationId) {
				return false;
			}

			if (!window.confirm(window.MESSENGER_BOOTSTRAP.groupDeleteConfirm || 'Remove this group for all participants?')) {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'delete_group',
					format: 'json',
					conversation_id: safeConversationId,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupDeleteError || 'Unable to remove the group chat.'));
					return;
				}

				self.renderPayload(payload, {
					force: true,
					scrollToBottom: true
				});
			}).fail(function () {
				self.showNotice(window.MESSENGER_BOOTSTRAP.groupDeleteError || 'Unable to remove the group chat.');
			});

			return false;
		},

		respondToGroupInvite: function (conversationId, decision) {
			var self = this;
			var cfg = this.config();
			var safeConversationId = parseInt(conversationId || 0, 10) || 0;

			if (!safeConversationId) {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'respond_group_invite',
					format: 'json',
					conversation_id: safeConversationId,
					decision: decision === 'accept' ? 'accept' : 'reject',
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupInviteError || 'Unable to update invitation.'));
					return;
				}

				self.renderPayload(payload, {
					force: true,
					scrollToBottom: decision === 'accept'
				});
			}).fail(function () {
				self.showNotice(window.MESSENGER_BOOTSTRAP.groupInviteError || 'Unable to update invitation.');
			});

			return false;
		},

		leaveGroup: function (conversationId) {
			var self = this;
			var cfg = this.config();
			var safeConversationId = parseInt(conversationId || this.activeConversationId || 0, 10) || 0;

			if (!safeConversationId) {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'leave_group',
					format: 'json',
					conversation_id: safeConversationId,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupLeaveError || 'Unable to leave the group chat.'));
					return;
				}

				self.renderPayload(payload, {
					force: true,
					scrollToBottom: true
				});
			}).fail(function () {
				self.showNotice(window.MESSENGER_BOOTSTRAP.groupLeaveError || 'Unable to leave the group chat.');
			});

			return false;
		},

		updateGroupEmailNotifications: function (enabled) {
			var self = this;
			var cfg = this.config();

			if (!this.activeConversationId || this.activeConversationType !== 'group_chat') {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'set_group_email_notifications',
					format: 'json',
					conversation_id: this.activeConversationId,
					enabled: enabled ? 1 : 0,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupSettingsError || 'Unable to save settings.'));
					self.fetch({ force: true });
					return;
				}

				self.renderPayload(payload, {
					force: true,
					scrollToBottom: false
				});
				if (payload.message) {
					self.showNotice(payload.message);
				}
			}).fail(function () {
				self.showNotice(window.MESSENGER_BOOTSTRAP.groupSettingsError || 'Unable to save settings.');
				self.fetch({ force: true });
			});

			return false;
		},

		updateGroupRetention: function (hours) {
			var self = this;
			var cfg = this.config();

			if (!this.activeConversationId || this.activeConversationType !== 'group_chat') {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'set_group_retention',
					format: 'json',
					conversation_id: this.activeConversationId,
					retention_hours: String(hours || 0),
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupRetentionError || 'Unable to save settings.'));
					self.fetch({ force: true });
					return;
				}

				self.renderPayload(payload, {
					force: true,
					scrollToBottom: false
				});
				if (payload.message) {
					self.showNotice(payload.message);
				}
			}).fail(function () {
				self.showNotice(window.MESSENGER_BOOTSTRAP.groupRetentionError || 'Unable to save settings.');
				self.fetch({ force: true });
			});

			return false;
		},

		schedulePoll: function () {
			var self = this;
			window.clearTimeout(this.pollTimer);
			this.pollTimer = window.setTimeout(function () {
				if (document.hidden) {
					self.schedulePoll();
					return;
				}

				self.fetch().always(function () {
					self.schedulePoll();
				});
			}, this.isOpen() ? this.pollIntervalOpen : this.pollIntervalClosed);
		},

		bind: function () {
			var self = this;

			$(document).off('.messengerUi');
			$(window).off('.messengerViewport');
			if (window.visualViewport && typeof window.visualViewport.removeEventListener === 'function' && this._boundVisualViewportHandler) {
				window.visualViewport.removeEventListener('resize', this._boundVisualViewportHandler);
				window.visualViewport.removeEventListener('scroll', this._boundVisualViewportHandler);
			}

			this._boundVisualViewportHandler = function () {
				self.refreshOpenLayout(false);
			};

			$(document).on('click.messengerUi', '[data-messenger-toggle]', function (event) {
				event.preventDefault();
				self.toggle();
			});

			$(document).on('click.messengerUi', '[data-messenger-toggle-button]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.toggle();
			});

			$(document).on('click.messengerUi', '#btn-chat', function (event) {
				event.preventDefault();
				self.sendMessage();
			});

			$(document).on('keydown.messengerUi', '#tresc', function (event) {
				if (event.which === 13 && !event.shiftKey) {
					event.preventDefault();
					self.sendMessage();
				}
			});

			$(document).on('input.messengerUi', '#tresc', function () {
				self.queueLinkPreviewRefresh();
			});

			$(document).on('focusin.messengerUi click.messengerUi', '#tresc', function () {
				window.setTimeout(function () {
					self.refreshOpenLayout(false);
				}, 60);
			});

			$(document).on('click.messengerUi', '[data-messenger-link-preview-remove]', function (event) {
				event.preventDefault();
				self.dismissComposerLinkPreview();
			});

			$(document).on('change.messengerUi', '#file', function () {
				self.previewFile();
			});

			$(document).on('click.messengerUi', '[data-messenger-upload-open]', function (event) {
				event.preventDefault();
				if (!self.activeCanSend) {
					return false;
				}
				self.openUploadModal();
			});

			$(document).on('click.messengerUi', '[data-messenger-upload-close]', function (event) {
				event.preventDefault();
				self.closeUploadModal();
			});

			$(document).on('click.messengerUi', '[data-chat-faq-key]', function (event) {
				event.preventDefault();
				self.startFaqFlow($(this).data('chatFaqKey'));
			});

			$(document).on('click.messengerUi', '#button_upload2', function (event) {
				event.preventDefault();
				self.uploadAttachment();
			});

			$(document).on('visibilitychange.messengerUi', function () {
				if (!document.hidden) {
					self.refreshOpenLayout(false);
					self.fetch({ force: true });
				}
			});

			$(window).on('resize.messengerViewport orientationchange.messengerViewport', function () {
				self.refreshOpenLayout(false);
			});

			if (window.visualViewport && typeof window.visualViewport.addEventListener === 'function') {
				window.visualViewport.addEventListener('resize', this._boundVisualViewportHandler);
				window.visualViewport.addEventListener('scroll', this._boundVisualViewportHandler);
			}

			$(document).on('click.messengerUi', '.messenger-bubble', function () {
				var $item = $(this).closest('.messenger-item');
				var $detail = $item.children('.messenger-time-detail');
				if ($detail.length) {
					if ($detail.is(':visible')) {
						$item.removeClass('messenger-item--time-open');
						$detail.stop(true, true).slideUp(120);
					} else {
						$item.addClass('messenger-item--time-open');
						$detail.stop(true, true).slideDown(120);
					}
				}
			});

			$(document).on('click.messengerUi', '.messenger-delete-button', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.deleteMessage(parseInt($(this).data('messageId') || 0, 10));
			});

			$(document).on('click.messengerUi', '.chat-payment-card__button', function (event) {
				var href = $(this).attr('href') || '';

				event.preventDefault();
				event.stopPropagation();

				if (!href) {
					return false;
				}

				return self.handlePaymentCardRedirect(href);
			});

			$(document).on('click.messengerUi', '[data-chat-conversation-tab]', function (event) {
				event.preventDefault();
				self.closeGroupMenu();
				self.closeGroupSettingsMenu();
				self.closeGroupMembersPopover();
				self.selectConversation($(this).attr('data-conversation-id'), $(this).attr('data-conversation-type'));
			});

			$(document).on('click.messengerUi', '[data-chat-back]', function (event) {
				event.preventDefault();
				self.closeGroupMenu();
				self.closeGroupSettingsMenu();
				self.closeGroupMembersPopover();
				self.showConversationList();
			});

			$(document).on('change.messengerUi', '[data-chat-email-notifications-toggle]', function () {
				self.closeGroupSettingsMenu();
				self.updateGroupEmailNotifications($(this).is(':checked'));
			});

			$(document).on('change.messengerUi', '[data-chat-retention-select]', function () {
				self.closeGroupSettingsMenu();
				self.updateGroupRetention(parseInt($(this).val() || '0', 10) || 0);
			});

			$(document).on('click.messengerUi', '[data-messenger-group-open]', function (event) {
				event.preventDefault();
				self.closeGroupMenu();
				self.openGroupModal($(this).attr('data-group-mode') || 'create', {
					conversationId: $(this).attr('data-conversation-id'),
					title: $(this).attr('data-group-title')
				});
			});

			$(document).on('click.messengerUi', '[data-messenger-group-close]', function (event) {
				event.preventDefault();
				self.closeGroupModal();
			});

			$(document).on('click.messengerUi', '[data-chat-group-menu-toggle]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.toggleGroupMenu();
			});

			$(document).on('click.messengerUi', '[data-chat-group-settings-toggle]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.toggleGroupSettingsMenu();
			});

			$(document).on('click.messengerUi', '[data-chat-group-members-toggle]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.toggleGroupMembersPopover();
			});

			$(document).on('click.messengerUi', '[data-messenger-group-add]', function (event) {
				event.preventDefault();
				self.addGroupEmail();
			});

			$(document).on('click.messengerUi', '[data-messenger-group-kind]', function (event) {
				event.preventDefault();
				self.setGroupCreationKind($(this).attr('data-messenger-group-kind'));
			});

			$(document).on('keydown.messengerUi', '#messenger_group_email', function (event) {
				if (event.which === 13) {
					event.preventDefault();
					self.addGroupEmail();
				}
			});

			$(document).on('click.messengerUi', '[data-messenger-group-remove]', function (event) {
				var index;
				event.preventDefault();
				index = parseInt($(this).attr('data-index') || '-1', 10);
				if (index >= 0) {
					self.groupEmails.splice(index, 1);
					self.renderGroupMembers();
				}
			});

			$(document).on('click.messengerUi', '[data-messenger-group-submit]', function (event) {
				event.preventDefault();
				self.submitGroupCreate();
			});

			$(document).on('click.messengerUi', '[data-group-chat-invite-action]', function (event) {
				event.preventDefault();
				self.respondToGroupInvite($(this).attr('data-conversation-id'), $(this).attr('data-group-chat-invite-action'));
			});

			$(document).on('click.messengerUi', '[data-chat-leave-group]', function (event) {
				event.preventDefault();
				self.closeGroupMenu();
				self.closeGroupSettingsMenu();
				self.leaveGroup($(this).attr('data-conversation-id'));
			});

			$(document).on('click.messengerUi', '[data-chat-delete-group]', function (event) {
				event.preventDefault();
				self.closeGroupMenu();
				self.closeGroupSettingsMenu();
				self.deleteGroup($(this).attr('data-conversation-id'));
			});

			$(document).on('click.messengerUi', function (event) {
				if (!$(event.target).closest('.messenger-conversation-meta__actions').length) {
					self.closeGroupMenu();
					self.closeGroupSettingsMenu();
				}
				if (!$(event.target).closest('.messenger-conversation-members').length) {
					self.closeGroupMembersPopover();
				}
			});
		},

		init: function () {
			var shouldRestoreOpenState;
			if (this.initDone) {
				this.refreshOpenLayout(false);
				this.schedulePoll();
				return;
			}

			shouldRestoreOpenState = this.restorePanelState();
			this.restoreOpenScrollToBottomPending = !!shouldRestoreOpenState;
			this.updateViewportMetrics();
			this.syncConversationStateFromMarkup(true);
			if (this.hasResellerInboxLayout()) {
				this.restoreActiveConversationState();
				if (this.resellerViewMode === 'conversation' && this.activeConversationId > 0 && shouldRestoreOpenState) {
					this.showConversationView();
				} else {
					this.showConversationList();
				}
			}
			this.resetGroupModal();
			this.bind();
			if (shouldRestoreOpenState) {
				this.open();
			} else {
				this.fetch({ force: true });
			}
			this.schedulePoll();
			this.initDone = true;
		}
	};

	window.MessengerUI = MessengerUI;
	window.toggleMessengerPanel = function () {
		return MessengerUI.toggle();
	};
	window.openMessengerPanel = function () {
		return MessengerUI.open();
	};
			window.openMessengerUpload = function () {
		return MessengerUI.openUploadModal();
	};
	window.closeMessengerUpload = function () {
		return MessengerUI.closeUploadModal();
	};
	window.chatFaqPrompt = function (faqKey) {
		return MessengerUI.startFaqFlow(faqKey);
	};
	window.send_message = function () {
		return MessengerUI.sendMessage();
	};
	window.check_chat_read = function () {
		return MessengerUI.markRead();
	};
	window.check_chat = function () {
		return MessengerUI.fetch({ force: true });
	};
	window.previewFile = function () {
		return MessengerUI.previewFile();
	};
	window.upload2 = function () {
		return MessengerUI.uploadAttachment();
	};
	window.upload = function () {
		return MessengerUI.uploadAttachment();
	};

	$(function () {
		MessengerUI.init();
	});
})(window, document, window.jQuery);
