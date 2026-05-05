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
		voiceRecording: false,
		voiceRecorder: null,
		voiceStream: null,
		voiceChunks: [],
		voiceRecordStartedAt: 0,
		voiceRecordTimer: null,
		voicePendingPointer: false,
		voiceShouldStopAfterStart: false,
		voiceStartInFlight: false,
		voicePointerMode: false,
		voiceAudioContext: null,
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
		replyToMessageId: 0,
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
		groupTargetConversationTitle: '',
		groupTargetConversationAvatarUrl: '',
		groupAvatarFile: null,
		groupAvatarPreviewUrl: '',
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

		voiceButton: function () {
			return $('[data-messenger-voice-preview]');
		},

		voiceStatus: function () {
			return $('#messenger_voice_status');
		},

		voiceTimerLabel: function () {
			return $('#messenger_voice_timer');
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

		replyPreviewBox: function () {
			return $('#messenger_reply_preview');
		},

		replyPreviewSender: function () {
			return $('#messenger_reply_sender');
		},

		replyPreviewText: function () {
			return $('#messenger_reply_text');
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

		canCreateDirectChats: function () {
			return !!(window.MESSENGER_BOOTSTRAP && window.MESSENGER_BOOTSTRAP.groupDirectEnabled);
		},

		canCreateNamedGroups: function () {
			return !!(window.MESSENGER_BOOTSTRAP && window.MESSENGER_BOOTSTRAP.groupNamedEnabled);
		},

		defaultGroupCreationKind: function () {
			if (this.canCreateDirectChats()) {
				return 'direct';
			}

			if (this.canCreateNamedGroups()) {
				return 'group';
			}

			return 'direct';
		},

		refreshGroupCreationModes: function () {
			var allowDirect = this.canCreateDirectChats();
			var allowGroup = this.canCreateNamedGroups();
			var $directButton = this.groupModeButtons().filter('[data-messenger-group-kind="direct"]');
			var $groupButton = this.groupModeButtons().filter('[data-messenger-group-kind="group"]');

			$directButton.toggle(allowDirect).prop('disabled', !allowDirect).attr('aria-hidden', allowDirect ? 'false' : 'true');
			$groupButton.toggle(allowGroup).prop('disabled', !allowGroup).attr('aria-hidden', allowGroup ? 'false' : 'true');
			this.groupModeSwitch().toggle(allowDirect && allowGroup);

			if (!allowDirect && allowGroup) {
				this.groupCreationKind = 'group';
			} else if (allowDirect && !allowGroup) {
				this.groupCreationKind = 'direct';
			}
		},

		groupContextField: function () {
			return $('#messenger_group_context');
		},

		groupContextTitle: function () {
			return $('#messenger_group_context_title');
		},

		groupContextAvatar: function () {
			return $('#messenger_group_context_avatar');
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

		groupSetupStep: function () {
			return $('#messenger_group_setup_step');
		},

		groupInviteStep: function () {
			return $('#messenger_group_invite_step');
		},

		groupCreateLead: function () {
			return $('#messenger_group_create_lead');
		},

		groupInviteLead: function () {
			return $('#messenger_group_invite_lead');
		},

		groupOpenCreatedButton: function () {
			return $('#messenger_group_open_created');
		},

		groupAvatarInput: function () {
			return $('#messenger_group_avatar_file');
		},

		groupAvatarPreview: function () {
			return $('#messenger_group_avatar_preview');
		},

		groupAvatarClearButton: function () {
			return $('[data-messenger-group-avatar-clear]');
		},

		profileModal: function () {
			var $bodyModal = $('body > #messenger_profile_modal').first();
			var $modal = $('#messenger_profile_modal').last();

			if ($bodyModal.length) {
				$('#chat_box #messenger_profile_modal, #content_chat_profil #messenger_profile_modal').remove();
				return $bodyModal;
			}

			if ($modal.length && !$modal.parent().is('body')) {
				$modal.appendTo('body');
			}

			$('#messenger_profile_modal').not($modal).remove();
			return $modal;
		},

		profileAvatar: function () {
			return this.profileModal().find('#messenger_profile_avatar').first();
		},

		profileHandle: function () {
			return this.profileModal().find('#messenger_profile_handle').first();
		},

		profileLastSeen: function () {
			return this.profileModal().find('#messenger_profile_last_seen').first();
		},

		profileActionButton: function () {
			return this.profileModal().find('#messenger_profile_action').first();
		},

		profileSecondaryActionButton: function () {
			return this.profileModal().find('#messenger_profile_action_secondary').first();
		},

		profileNote: function () {
			return this.profileModal().find('#messenger_profile_note').first();
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

		isDesktopDocked: function () {
			return !!(window.matchMedia && window.matchMedia('(min-width: 1200px)').matches);
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

		requestedConversationIdFromUrl: function () {
			var currentUrl;
			var rawValue;

			if (typeof window.URL !== 'function') {
				return 0;
			}

			try {
				currentUrl = new window.URL(window.location.href);
			} catch (error) {
				return 0;
			}

			rawValue = currentUrl.searchParams.get('conversation_id');
			return parseInt(rawValue || '0', 10) || 0;
		},

		syncConversationUrlState: function () {
			var currentUrl;
			var nextUrl;

			if (!window.history || typeof window.history.replaceState !== 'function' || typeof window.URL !== 'function') {
				return;
			}

			try {
				currentUrl = new window.URL(window.location.href);
			} catch (error) {
				return;
			}

			if (this.hasResellerInboxLayout() && this.resellerViewMode === 'conversation' && parseInt(this.activeConversationId || 0, 10) > 0) {
				currentUrl.searchParams.set('conversation_id', String(parseInt(this.activeConversationId || 0, 10) || 0));
			} else {
				currentUrl.searchParams.delete('conversation_id');
			}

			nextUrl = currentUrl.pathname + (currentUrl.search || '') + (currentUrl.hash || '');
			window.history.replaceState({}, '', nextUrl);
		},

		savePanelState: function (isOpen) {
			if (this.isDesktopDocked()) {
				return;
			}
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
			if (this.isDesktopDocked()) {
				return true;
			}
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

		hasActiveConversationSelection: function () {
			if ((parseInt(this.activeConversationId || 0, 10) || 0) > 0) {
				return true;
			}

			return String(this.activeConversationType || '') === 'live_chat';
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

		syncDesktopDockedState: function () {
			var docked = this.isDesktopDocked();
			var $widget = this.widget();
			var $panel = this.panel();
			var $body = $('body');

			$body.toggleClass('messenger-desktop-docked', docked);
			$widget.toggleClass('messenger-desktop-docked', docked);

			if (!docked) {
				if (!this.isOpen()) {
					this.heading().attr('aria-expanded', 'false');
					$panel.attr('aria-hidden', 'true').removeClass('in is-visible').hide();
					this.icon().removeClass('fa-angle-up').addClass('fa-angle-down');
				}
				return;
			}

			if (this.hasResellerInboxLayout()) {
				if (this.resellerViewMode === 'conversation' && this.hasActiveConversationSelection()) {
					this.showConversationView();
				} else {
					this.showConversationList();
				}
			}

			$widget.addClass('is-open');
			this.heading().attr('aria-expanded', 'true');
			$panel.attr('aria-hidden', 'false').addClass('in is-visible').show();
			this.icon().removeClass('fa-angle-down').addClass('fa-angle-up');
		},

		refreshOpenLayout: function (stickToBottom) {
			var self = this;
			var raf = window.requestAnimationFrame || function (callback) {
				return window.setTimeout(callback, 16);
			};

			this.updateViewportMetrics();
			this.syncDesktopDockedState();

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
			var $backButton = $('.admin-chat-inbox__back');
			var $backUnread = $backButton.find('.messenger-unread-badge-back');
			var total = parseInt(count || 0, 10);

			if ($badge.length) {
				if (total > 0) {
					$badge.text(total).show();
				} else {
					$badge.text('0').hide();
				}
			}

			if ($backButton.length && $backUnread.length) {
				if (total > 0) {
					$backButton.addClass('has-unread');
					$backUnread.text(total).show();
				} else {
					$backButton.removeClass('has-unread');
					$backUnread.text('0').hide();
				}
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

		shouldAutoScrollDuringFaqReply: function () {
			return !this.userBrowsingHistory && this.isNearBottom(96);
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
					if (self.hasResellerInboxLayout() && self.hasActiveConversationSelection() && self.resellerViewMode === 'conversation') {
						self.showConversationView();
					}
					self.refreshOpenLayout(true);
					self.scrollToBottom();
				}, delay);
			});

			raf(function () {
				if (self.hasResellerInboxLayout() && self.hasActiveConversationSelection() && self.resellerViewMode === 'conversation') {
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
			var match = String(value || '').match(/(?:^|[^\w@])((?:https?:\/\/|www\.)[^\s<]+|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?:\/[^\s<]*)?)/i);
			if (!match || !match[1]) {
				return '';
			}

			return this.normalizePreviewUrl(match[1]);
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

			if (!/^https?:\/\//i.test(normalized) && /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?:\/.*)?$/i.test(normalized)) {
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
			var $appendedNodes;

			if (!$list.length || !safeMessage) {
				return;
			}

			$appendedNodes = $(
				'<li class="messenger-time-anchor messenger-time-anchor--local"><span>' + currentTime + '</span></li>' +
				'<li class="messenger-item messenger-item--sent messenger-item--pending">' +
					'<div class="messenger-bubble">' +
						'<div class="messenger-text">' + safeMessage + '</div>' +
					'</div>' +
					'<div class="messenger-time-detail">' + currentTime + '</div>' +
				'</li>'
			);
			$list.append($appendedNodes);
			this.animateMessageEntry($appendedNodes);

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

		clearReplyTarget: function () {
			this.replyToMessageId = 0;
			this.replyPreviewSender().text('');
			this.replyPreviewText().text('');
			this.replyPreviewBox().hide().attr('data-reply-to-message-id', '0');
		},

		setReplyTarget: function (messageId, sender, text) {
			var safeMessageId = parseInt(messageId || 0, 10) || 0;
			if (!safeMessageId) {
				this.clearReplyTarget();
				return;
			}

			this.replyToMessageId = safeMessageId;
			this.replyPreviewSender().text($.trim(sender || ''));
			this.replyPreviewText().text($.trim(text || ''));
			this.replyPreviewBox().attr('data-reply-to-message-id', String(safeMessageId)).show();
		},

		closeMessageActions: function () {
			this.chatBox().find('.messenger-item.is-actions-open').removeClass('is-actions-open');
		},

		captureRenderedMessageIds: function () {
			var ids = {};

			this.chatBox().find('.messenger-item[data-message-id]').each(function () {
				var safeId = String($(this).attr('data-message-id') || '').trim();
				if (safeId) {
					ids[safeId] = true;
				}
			});

			return ids;
		},

		animateMessageEntry: function ($nodes) {
			if (!$nodes || !$nodes.length) {
				return;
			}

			$nodes.addClass('is-entering');
			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () {
					$nodes.addClass('is-entering-active');
				});
			});
			window.setTimeout(function () {
				$nodes.removeClass('is-entering is-entering-active');
			}, 340);
		},

		animateNewMessagesFromRender: function (previousIds, previousConversationId, options) {
			var enteringNodes = [];
			var sameConversation = previousConversationId > 0 && previousConversationId === this.activeConversationId;
			var previousKeys = previousIds ? Object.keys(previousIds) : [];
			options = options || {};

			if (!sameConversation || !previousKeys.length || !!options.preservePrependOffset) {
				return;
			}

			this.chatBox().find('.messenger-item[data-message-id]').each(function () {
				var $item = $(this);
				var safeId = String($item.attr('data-message-id') || '').trim();
				var $timeAnchor;

				if (!safeId || previousIds[safeId]) {
					return;
				}

				$timeAnchor = $item.prev('.messenger-time-anchor');
				if ($timeAnchor.length) {
					enteringNodes.push($timeAnchor.get(0));
				}
				enteringNodes.push($item.get(0));
			});

			if (enteringNodes.length) {
				this.animateMessageEntry($(enteringNodes));
			}
		},

		toggleMessageActions: function (messageId, forceOpen) {
			var safeMessageId = parseInt(messageId || 0, 10) || 0;
			var $item;
			var shouldOpen;

			if (!safeMessageId) {
				this.closeMessageActions();
				return;
			}

			$item = this.chatBox().find('[data-chat-message-item][data-message-id="' + safeMessageId + '"]').first();
			if (!$item.length) {
				return;
			}

			shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !$item.hasClass('is-actions-open');
			this.closeMessageActions();
			if (shouldOpen) {
				$item.addClass('is-actions-open');
			}
		},

		flashMessageItem: function ($item) {
			if (!$item || !$item.length) {
				return;
			}

			$item.addClass('messenger-item--focus');
			window.setTimeout(function () {
				$item.removeClass('messenger-item--focus');
			}, 1800);
		},

		scrollToMessage: function (messageId, attempt) {
			var self = this;
			var safeMessageId = parseInt(messageId || 0, 10) || 0;
			var tries = parseInt(attempt || 0, 10) || 0;
			var $item;
			var $scrollContainer;
			var nextLimit;

			if (!safeMessageId) {
				return false;
			}

			$item = this.chatBox().find('[data-message-id="' + safeMessageId + '"]').first();
			if ($item.length) {
				$scrollContainer = this.chatScroll();
				if ($scrollContainer.length) {
					$scrollContainer.stop(true).animate({
						scrollTop: Math.max(0, $item.position().top + $scrollContainer.scrollTop() - 40)
					}, 220);
				}
				this.flashMessageItem($item);
				return true;
			}

			if (!this.hasMoreMessages || !this.activeConversationId || tries >= 6) {
				return false;
			}

			nextLimit = Math.max(this.activeConversationMessageLimit + this.loadOlderBatchSize + 10, this.messagePageSize);
			if (this.activeConversationTotalMessages > 0) {
				nextLimit = Math.min(nextLimit, this.activeConversationTotalMessages);
			}

			this.fetch({
				force: true,
				conversationId: this.activeConversationId,
				messageLimit: nextLimit,
				preservePrependOffset: true,
				scrollToBottom: false
			}).done(function () {
				self.scrollToMessage(safeMessageId, tries + 1);
			});

			return false;
		},

		toggleReaction: function (messageId, reactionCode) {
			var self = this;
			var cfg = this.config();
			var safeMessageId = parseInt(messageId || 0, 10) || 0;
			var safeReactionCode = $.trim(reactionCode || '');

			if (!cfg.userId || !this.activeConversationId || !safeMessageId || !safeReactionCode || this.sendInFlight || this.uploadInFlight || this.fetchInFlight || this.faqPendingKey) {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'toggle_reaction',
					format: 'json',
					message_id: safeMessageId,
					reaction_code: safeReactionCode,
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
				}
			}).fail(function () {
				self.showNotice(window.MESSENGER_BOOTSTRAP.reactionFailedMessage || 'Nie udało się zapisać reakcji.');
			});

			return false;
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
			this.syncConversationUrlState();
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
			var $widget = this.widget();

			if (!this.hasResellerInboxLayout()) {
				return;
			}

			if (this.voiceRecording || this.voiceStartInFlight || this.uploadInFlight) {
				return;
			}

			this.resellerViewMode = 'list';
			this.closeMessageActions();
			this.setConversationLoadingState(false);
			this.listView().prop('hidden', false);
			this.conversationView().prop('hidden', true);
			$widget.addClass('messenger-inbox-mode').removeClass('messenger-conversation-mode');
			this.saveActiveConversationState();
			this.syncConversationUrlState();
		},

		showConversationView: function () {
			var $widget = this.widget();

			if (!this.hasResellerInboxLayout()) {
				return;
			}

			this.resellerViewMode = 'conversation';
			this.listView().prop('hidden', true);
			this.conversationView().prop('hidden', false);
			$widget.addClass('messenger-conversation-mode').removeClass('messenger-inbox-mode');
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

		captureActiveAudioPlayers: function () {
			var players = {};
			this.chatBox().find('[data-message-id]').each(function () {
				var $message = $(this);
				var messageId = String($message.attr('data-message-id') || '');
				var audio = $message.find('[data-chat-audio-player]').get(0);
				if (!messageId || !audio || !audio.currentSrc) {
					return;
				}
				players[messageId] = {
					node: audio,
					src: audio.currentSrc,
					currentTime: audio.currentTime || 0,
					wasPlaying: !audio.paused && !audio.ended
				};
			});
			return players;
		},

		restoreActiveAudioPlayers: function (players) {
			var self = this;
			if (!players) {
				return;
			}

			$.each(players, function (messageId, state) {
				var $message = self.chatBox().find('[data-message-id="' + String(messageId) + '"]').first();
				var replacement = $message.find('[data-chat-audio-player]').get(0);
				var restoredNode;
				if (!$message.length || !replacement || !state || !state.node) {
					return;
				}
				if (replacement.currentSrc && state.src && replacement.currentSrc !== state.src) {
					return;
				}

				restoredNode = state.node;
				try {
					restoredNode.currentTime = state.currentTime || 0;
				} catch (error) {
					// ignore currentTime restore failures
				}
				replacement.parentNode.replaceChild(restoredNode, replacement);
				if (state.wasPlaying && typeof restoredNode.play === 'function') {
					var playResult = restoredNode.play();
					if (playResult && typeof playResult.catch === 'function') {
						playResult.catch(function () {
							return undefined;
						});
					}
				}
			});
		},

		updateVoiceButtonIcon: function () {
			var $icon = this.voiceButton().find('i').first();
			if (!$icon.length) {
				return;
			}

			$icon.removeClass('fa-microphone fa-stop fa-stop-circle');
			if (this.voiceRecording) {
				$icon.addClass('fa fa-stop-circle');
				return;
			}

			$icon.addClass('fa fa-microphone');
		},

		prepareAudioPlayers: function () {
			this.chatBox().find('[data-chat-audio-player]').each(function () {
				var audio = this;
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
		},

		voiceFeatureEnabled: function () {
			return String(window.MESSENGER_BOOTSTRAP.voiceEnabled || '0') === '1';
		},

		voiceMinDurationSeconds: function () {
			return Math.max(2, parseInt(window.MESSENGER_BOOTSTRAP.voiceMinDurationSeconds || 2, 10) || 2);
		},

		isDesktopVoiceToggleMode: function () {
			return !!(window.matchMedia && window.matchMedia('(pointer:fine)').matches);
		},

		playVoiceCue: function (kind) {
			var ContextCtor = window.AudioContext || window.webkitAudioContext;
			var context;
			var oscillator;
			var gain;
			var startAt;
			if (!ContextCtor) {
				return;
			}
			try {
				if (!this.voiceAudioContext) {
					this.voiceAudioContext = new ContextCtor();
				}
				context = this.voiceAudioContext;
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
		},

		resetVoiceRecordingState: function () {
			window.clearInterval(this.voiceRecordTimer);
			this.voiceRecordTimer = null;
			this.voiceRecording = false;
			this.voiceStartInFlight = false;
			this.voicePendingPointer = false;
			this.voiceShouldStopAfterStart = false;
			this.voicePointerMode = false;
			this.voiceChunks = [];
			this.voiceRecorder = null;
			this.voiceRecordStartedAt = 0;
			if (this.voiceStream) {
				(this.voiceStream.getTracks() || []).forEach(function (track) {
					track.stop();
				});
			}
			this.voiceStream = null;
			this.voiceStatus().prop('hidden', true).removeClass('is-recording');
			this.voiceTimerLabel().text('0:00');
			this.updateComposerAvailability();
		},

		updateVoiceTimer: function () {
			var maxDurationSeconds = parseInt(window.MESSENGER_BOOTSTRAP.voiceMaxDurationSeconds || 30, 10) || 30;
			var elapsedSeconds = 0;
			if (this.voiceRecordStartedAt > 0) {
				elapsedSeconds = Math.max(0, Math.floor((Date.now() - this.voiceRecordStartedAt) / 1000));
			}
			this.voiceTimerLabel().text(Math.floor(elapsedSeconds / 60) + ':' + String(elapsedSeconds % 60).padStart(2, '0'));
			if (elapsedSeconds >= maxDurationSeconds && this.voiceRecorder && this.voiceRecorder.state === 'recording') {
				this.stopVoiceRecording(true);
			}
		},

		startVoiceRecording: function () {
			var self = this;
			var mediaErrorMessage = '';
			if (!this.voiceFeatureEnabled()) {
				this.showNotice($('[data-messenger-voice-preview]').attr('data-disabled-tooltip') || 'Opcja nagrywania wiadomości głosowych jest obecnie wyłączona.');
				return false;
			}
			if (this.voiceRecording || this.voiceStartInFlight || this.sendInFlight || this.uploadInFlight || !this.activeCanSend || this.activeConversationType !== 'live_chat') {
				return false;
			}
			if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function' || typeof window.MediaRecorder === 'undefined') {
				this.showNotice(window.MESSENGER_BOOTSTRAP.voiceUnsupportedMessage || 'Voice messages are not supported in this browser.');
				return false;
			}

			this.voiceStartInFlight = true;
			this.voicePendingPointer = true;
			this.voiceShouldStopAfterStart = false;

			navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
				var preferredMimeTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/ogg'];
				var recorderOptions = {};
				var mimeType = '';
				var recorder;
				var stopHandled = false;

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
				self.voiceStream = stream;
				self.voiceRecorder = recorder;
				self.voiceChunks = [];
				self.voiceRecording = true;
				self.voiceStartInFlight = false;
				self.voiceRecordStartedAt = Date.now();
				self.voiceStatus().prop('hidden', false).addClass('is-recording');
				self.updateVoiceTimer();
				self.playVoiceCue('start');
				window.clearInterval(self.voiceRecordTimer);
				self.voiceRecordTimer = window.setInterval(function () {
					self.updateVoiceTimer();
				}, 250);
				self.updateComposerAvailability();

				recorder.addEventListener('dataavailable', function (event) {
					if (event.data && event.data.size > 0) {
						self.voiceChunks.push(event.data);
					}
				});
				recorder.addEventListener('stop', function () {
					var durationMs = Math.max(0, Date.now() - self.voiceRecordStartedAt);
					var minDurationSeconds = self.voiceMinDurationSeconds();
					var durationSeconds = Math.max(1, Math.round(durationMs / 1000));
					var blob;
					var fileName;
					stopHandled = true;
					if (!self.voiceChunks.length) {
						self.resetVoiceRecordingState();
						return;
					}
					if (durationMs < (minDurationSeconds * 1000)) {
						self.showNotice(window.MESSENGER_BOOTSTRAP.voiceTooShortMessage || 'Przytrzymaj nagrywanie przez co najmniej 2 sekundy.');
						self.resetVoiceRecordingState();
						return;
					}
					blob = new Blob(self.voiceChunks, { type: recorder.mimeType || 'audio/webm' });
					fileName = 'voice-message.' + ((blob.type || '').indexOf('ogg') >= 0 ? 'ogg' : ((blob.type || '').indexOf('mp4') >= 0 ? 'm4a' : 'webm'));
					self.uploadVoiceMessage(blob, fileName, durationSeconds);
					self.resetVoiceRecordingState();
				});
				recorder.addEventListener('error', function () {
					if (stopHandled || self.voiceChunks.length > 0 || (self.voiceRecorder && self.voiceRecorder.state === 'inactive')) {
						return;
					}
					self.showNotice(window.MESSENGER_BOOTSTRAP.voiceRecordFailedMessage || 'Voice message recording failed.');
					self.resetVoiceRecordingState();
				});
				recorder.start();
				if (self.voiceShouldStopAfterStart) {
					self.stopVoiceRecording(false);
				}
			}).catch(function (error) {
				var errorName = error && error.name ? String(error.name) : '';
				self.voiceStartInFlight = false;
				self.voicePendingPointer = false;
				self.voicePointerMode = false;
				if (window.isSecureContext === false) {
					mediaErrorMessage = 'Mikrofon wymaga bezpiecznego połączenia HTTPS.';
				} else if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError') {
					mediaErrorMessage = 'Nie wykryto mikrofonu w tym urządzeniu.';
				} else if (errorName === 'NotReadableError' || errorName === 'TrackStartError') {
					mediaErrorMessage = 'Mikrofon jest zajęty albo chwilowo niedostępny.';
				} else if (errorName === 'SecurityError') {
					mediaErrorMessage = 'Przeglądarka zablokowała dostęp do mikrofonu.';
				} else if (errorName === 'AbortError') {
					mediaErrorMessage = 'Nagrywanie zostało przerwane.';
				} else {
					mediaErrorMessage = window.MESSENGER_BOOTSTRAP.voicePermissionDeniedMessage || 'Microphone access was denied.';
				}
				self.showNotice(mediaErrorMessage);
				self.updateComposerAvailability();
			});

			return false;
		},

		stopVoiceRecording: function (keepPendingPointer) {
			if (!keepPendingPointer) {
				this.voicePendingPointer = false;
			}
			if (this.voiceStartInFlight && !this.voiceRecording) {
				this.voiceShouldStopAfterStart = true;
				return false;
			}
			if (!this.voiceRecorder || this.voiceRecorder.state !== 'recording') {
				return false;
			}
			this.playVoiceCue('stop');
			this.voiceRecorder.stop();
			return false;
		},

		uploadVoiceMessage: function (blob, fileName, durationSeconds) {
			var self = this;
			var cfg = this.config();
			var formData;

			if (!cfg.userId || !blob || this.sendInFlight || this.uploadInFlight) {
				return false;
			}

			this.uploadInFlight = true;
			formData = new FormData();
			formData.append('action', 'voice_upload');
			formData.append('format', 'json');
			formData.append('conversation_id', String(this.activeConversationId || 0));
			formData.append('conversation_type', String(this.activeConversationType || 'live_chat'));
			formData.append('reply_to_message_id', String(this.replyToMessageId || 0));
			formData.append('audio_duration_seconds', String(durationSeconds || 0));
			formData.append('_csrf', cfg.csrfToken);
			formData.append('voice_file', blob, fileName || 'voice-message.webm');

			$.ajax({
				type: 'POST',
				processData: false,
				contentType: false,
				url: cfg.endpoint,
				dataType: 'json',
				data: formData
			}).done(function (payload) {
				if (payload && payload.ok) {
					self.uploadInFlight = false;
					self.updateComposerAvailability();
					self.clearReplyTarget();
					self.renderPayload(payload, {
						force: true,
						scrollToBottom: true
					});
				} else if (payload && payload.message) {
					self.uploadInFlight = false;
					self.updateComposerAvailability();
					self.showNotice(payload.message);
				}
			}).fail(function () {
				self.uploadInFlight = false;
				self.updateComposerAvailability();
				self.showNotice(window.MESSENGER_BOOTSTRAP.voiceUploadFailedMessage || 'Voice message upload failed.');
			}).always(function () {
				self.uploadInFlight = false;
				self.updateComposerAvailability();
			});

			return false;
		},

		updateComposerAvailability: function () {
			var writeMessagePlaceholder = window.MESSENGER_BOOTSTRAP.writeMessagePlaceholder || 'Write message...';
			var readOnlyPlaceholder = window.MESSENGER_BOOTSTRAP.groupReadOnlyPlaceholder || 'This group is read only.';
			var directPendingPlaceholder = window.MESSENGER_BOOTSTRAP.groupDirectPendingPlaceholder || 'This conversation is waiting for invite acceptance.';
			var directRejectedPlaceholder = window.MESSENGER_BOOTSTRAP.groupDirectRejectedPlaceholder || 'Invitation rejected.';
			var groupPendingPlaceholder = window.MESSENGER_BOOTSTRAP.groupPendingPlaceholder || 'Waiting for invite confirmation...';
			var blockedPlaceholder = window.MESSENGER_BOOTSTRAP.blockedPlaceholder || 'Account blocked.';
			var disabledByCooldown = !!this.cooldownTimer;
			var isBlockedCustomer = String(this.contentRoot().attr('data-chat-customer-is-blocked') || '0') === '1';
			var disabled = !this.activeCanSend || disabledByCooldown;
			var $input = this.input();
			var $sendButton = $('#btn-chat');
			var $uploadButton = $('[data-messenger-upload-open]');
			var $voiceButton = this.voiceButton();
			var activeType = String(this.contentRoot().attr('data-chat-active-conversation-type') || '');
			var activeDirectStatus = String(this.contentRoot().attr('data-chat-active-conversation-direct-status') || 'none');
			var pendingMemberCount = parseInt(this.contentRoot().attr('data-chat-active-conversation-pending-member-count') || '0', 10) || 0;
			var isPendingDirect = activeType === 'group_chat' && (activeDirectStatus === 'pending_invited' || activeDirectStatus === 'pending');
			var isRejectedDirect = activeType === 'group_chat' && activeDirectStatus === 'rejected';
			var isPendingGroup = activeType === 'group_chat' && !isPendingDirect && pendingMemberCount > 0;

			if (!$input.length) {
				return;
			}

			$input.prop('disabled', disabled);
			$sendButton.prop('disabled', disabled);
			$uploadButton.prop('disabled', disabled);
			if ($voiceButton.length) {
				var featureEnabled = this.voiceFeatureEnabled();
				var canRecordVoice = featureEnabled && !disabled && activeType === 'live_chat' && !!window.MediaRecorder && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
				var disabledVoiceTooltip = String($voiceButton.attr('data-disabled-tooltip') || '');
				var unavailableConversationLabel = window.MESSENGER_BOOTSTRAP.voiceUnavailableConversationLabel || 'Voice messages are available only in Support Chat.';
				var activeVoiceLabel = this.voiceRecording
					? (window.MESSENGER_BOOTSTRAP.voiceRecordDesktopStopLabel || 'Click to stop recording')
					: (window.MESSENGER_BOOTSTRAP.voiceRecordDesktopStartLabel || 'Click to start recording');
				var voiceLabel = !featureEnabled
					? disabledVoiceTooltip
					: (activeType !== 'live_chat' ? unavailableConversationLabel : activeVoiceLabel);
				$voiceButton.toggleClass('is-disabled', !canRecordVoice);
				$voiceButton.toggleClass('is-recording', !!this.voiceRecording);
				$voiceButton.prop('disabled', !canRecordVoice && !this.voiceRecording);
				$voiceButton.attr('aria-disabled', !canRecordVoice ? 'true' : 'false');
				$voiceButton.attr('title', voiceLabel);
				$voiceButton.attr('aria-label', voiceLabel);
				this.updateVoiceButtonIcon();
			}

			if (isBlockedCustomer) {
				$input.attr('placeholder', blockedPlaceholder);
				return;
			}

			if (!this.activeCanSend) {
				$input.attr('placeholder', isPendingDirect ? directPendingPlaceholder : (isRejectedDirect ? directRejectedPlaceholder : (isPendingGroup ? groupPendingPlaceholder : readOnlyPlaceholder)));
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

			$box.html($.map(this.groupEmails, function (entry, index) {
				var label = entry && typeof entry === 'object' ? String(entry.label || entry.value || '') : String(entry || '');
				return '' +
					'<button type="button" class="messenger-group-member" data-messenger-group-remove data-index="' + index + '">' +
						'<span>' + self.escapeHtml(label) + '</span>' +
						'<i class="fa fa-times" aria-hidden="true"></i>' +
					'</button>';
			}).join(''));
		},

		clearGroupAvatarPreviewUrl: function () {
			if (this.groupAvatarPreviewUrl && String(this.groupAvatarPreviewUrl).indexOf('blob:') === 0 && window.URL && typeof window.URL.revokeObjectURL === 'function') {
				window.URL.revokeObjectURL(this.groupAvatarPreviewUrl);
			}
			this.groupAvatarPreviewUrl = '';
		},

		renderGroupAvatarPreview: function (previewUrl) {
			var $preview = this.groupAvatarPreview();
			var $clearButton = this.groupAvatarClearButton();
			var safeUrl = $.trim(String(previewUrl || ''));

			if (!$preview.length) {
				return;
			}

			if (safeUrl) {
				$preview.addClass('has-image').html('<img src="' + this.escapeHtml(safeUrl) + '" alt="">');
				$clearButton.show();
				return;
			}

			$preview.removeClass('has-image').html('<span><i class="fa fa-camera" aria-hidden="true"></i></span>');
			$clearButton.hide();
			this.renderGroupContextSummary();
		},

		resetGroupAvatar: function () {
			this.groupAvatarFile = null;
			this.clearGroupAvatarPreviewUrl();
			this.groupAvatarInput().val('');
			this.renderGroupAvatarPreview('');
		},

		handleGroupAvatarSelection: function (fileInput) {
			var files = fileInput && fileInput.files ? fileInput.files : [];
			var file = files && files.length ? files[0] : null;
			var objectUrl = '';

			if (!file) {
				this.resetGroupAvatar();
				return false;
			}

			this.groupAvatarFile = file;
			this.clearGroupAvatarPreviewUrl();

			if (window.URL && typeof window.URL.createObjectURL === 'function') {
				objectUrl = window.URL.createObjectURL(file);
				this.groupAvatarPreviewUrl = objectUrl;
			}

			this.renderGroupAvatarPreview(objectUrl);
			this.renderGroupContextSummary();
			return false;
		},

		groupInviteValues: function () {
			return $.map(this.groupEmails, function (entry) {
				if (entry && typeof entry === 'object') {
					return String(entry.value || '').toLowerCase();
				}

				return String(entry || '').toLowerCase();
			});
		},

		setGroupTargetConversation: function (conversationId, title, avatarUrl) {
			this.groupTargetConversationId = parseInt(conversationId || 0, 10) || 0;
			this.groupTargetConversationTitle = $.trim(String(title || ''));
			this.groupTargetConversationAvatarUrl = $.trim(String(avatarUrl || ''));
		},

		groupContextAvatarHtml: function () {
			var avatarUrl = $.trim(String(this.groupTargetConversationAvatarUrl || this.groupAvatarPreviewUrl || ''));
			var title = $.trim(String(this.groupTargetConversationTitle || this.groupNameInput().val() || 'Group'));
			var initial = title ? title.charAt(0).toUpperCase() : 'G';

			if (avatarUrl) {
				return '<img src="' + this.escapeHtml(avatarUrl) + '" alt="">';
			}

			return '<span>' + this.escapeHtml(initial) + '</span>';
		},

		renderGroupContextSummary: function () {
			var title = $.trim(String(this.groupTargetConversationTitle || this.groupNameInput().val() || ''));
			var $title = this.groupContextTitle();
			var $avatar = this.groupContextAvatar();

			if ($title.length) {
				$title.text(title || (window.MESSENGER_BOOTSTRAP.groupInviteNameLabel || 'Group'));
			}

			if ($avatar.length) {
				$avatar.html(this.groupContextAvatarHtml());
			}
		},

		refreshGroupModalLayout: function () {
			var isInviteMode = this.groupModalMode === 'invite';
			var isGroupKind = this.groupCreationKind === 'group';
			var isDirectCreate = !isInviteMode && !isGroupKind;
			var isGroupSetup = !isInviteMode && isGroupKind;

			this.groupSetupStep().prop('hidden', !isGroupSetup);
			this.groupInviteStep().prop('hidden', !(isInviteMode || isDirectCreate));
			this.groupContextField().toggle(isInviteMode);
			this.groupOpenCreatedButton().toggle(isInviteMode && this.groupTargetConversationId > 0);

			if (isInviteMode) {
				this.groupModeSwitch().hide();
				this.groupModalTitle().text(window.MESSENGER_BOOTSTRAP.groupInviteTitle || 'Add members to group');
				this.groupInviteLead().text(window.MESSENGER_BOOTSTRAP.groupInviteLead || 'Now add members. Invitations work just like in 1:1 conversations and require acceptance.');
				this.groupContextLabel().text(window.MESSENGER_BOOTSTRAP.groupInviteNameLabel || 'Group');
				this.groupEmailLabel().text(window.MESSENGER_BOOTSTRAP.groupGroupEmailLabel || 'Add participant by email');
				this.groupHint().text(window.MESSENGER_BOOTSTRAP.groupGroupHint || 'You can search by email or @handle. Invitations must be accepted before someone joins the group.');
				this.groupSubmitLabel().text(window.MESSENGER_BOOTSTRAP.groupInviteSubmit || 'Send invitations');
				this.renderGroupContextSummary();
				return;
			}

			this.refreshGroupCreationModes();
			this.groupContextField().hide();

			if (isGroupSetup) {
				this.groupModalTitle().text(window.MESSENGER_BOOTSTRAP.groupCreateTitle || 'Create group chat');
				this.groupCreateLead().text(window.MESSENGER_BOOTSTRAP.groupCreateLead || 'Set the group name, logo and auto-delete time first.');
				this.groupSubmitLabel().text(window.MESSENGER_BOOTSTRAP.groupCreateSubmit || 'Create group');
				return;
			}

			this.groupModalTitle().text(window.MESSENGER_BOOTSTRAP.groupDirectTitle || 'Start direct conversation');
			this.groupInviteLead().text(window.MESSENGER_BOOTSTRAP.groupDirectHint || 'Add one user email or @handle to start a direct conversation right away.');
			this.groupEmailLabel().text(window.MESSENGER_BOOTSTRAP.groupDirectEmailLabel || 'Add user by email');
			this.groupHint().text(window.MESSENGER_BOOTSTRAP.groupDirectHint || 'Add one user email or @handle to start a direct conversation right away.');
			this.groupSubmitLabel().text(window.MESSENGER_BOOTSTRAP.groupDirectSubmit || 'Start conversation');
		},

		setGroupCreationKind: function (kind) {
			var preferredKind = String(kind || '').toLowerCase() === 'group' ? 'group' : 'direct';
			var normalizedKind = preferredKind;
			var isGroupKind = normalizedKind === 'group';

			if (isGroupKind && !this.canCreateNamedGroups()) {
				normalizedKind = this.canCreateDirectChats() ? 'direct' : 'group';
				isGroupKind = normalizedKind === 'group';
			}

			if (!isGroupKind && !this.canCreateDirectChats()) {
				normalizedKind = this.canCreateNamedGroups() ? 'group' : 'direct';
				isGroupKind = normalizedKind === 'group';
			}

			this.groupCreationKind = normalizedKind;
			this.refreshGroupCreationModes();
			this.groupModeButtons().removeClass('is-active').attr('aria-pressed', 'false');
			this.groupModeButtons().filter('[data-messenger-group-kind="' + normalizedKind + '"]').addClass('is-active').attr('aria-pressed', 'true');

			if (!isGroupKind) {
				this.groupNameInput().val('');
				this.resetGroupAvatar();
				if (this.groupEmails.length > 1) {
					this.groupEmails = this.groupEmails.slice(0, 1);
					this.renderGroupMembers();
				}
			}

			if (isGroupKind && !this.groupRetentionCreate().val()) {
				this.groupRetentionCreate().val('24h');
			}

			this.refreshGroupModalLayout();
		},

		resetGroupModal: function () {
			this.groupEmails = [];
			this.groupEmailRequests = {};
			this.groupModalMode = 'create';
			this.groupCreationKind = 'direct';
			this.setGroupTargetConversation(0, '', '');
			this.groupNameInput().val('');
			this.groupEmailInput().val('');
			this.groupRetentionCreate().val('24h');
			this.groupOpenCreatedButton().hide();
			this.resetGroupAvatar();
			this.renderGroupMembers();
			this.showGroupAlert('', false);
			this.refreshGroupCreationModes();
			this.setGroupCreationKind(this.defaultGroupCreationKind());
		},

		openGroupModal: function (mode, options) {
			var self = this;
			var targetMode = String(mode || 'create');
			var targetOptions = options || {};
			if (targetMode === 'create' && !window.MESSENGER_BOOTSTRAP.groupCreateEnabled) {
				return false;
			}

			this.resetGroupModal();
			this.groupModalMode = targetMode === 'invite' ? 'invite' : 'create';
			this.setGroupTargetConversation(
				parseInt(targetOptions.conversationId || 0, 10) || 0,
				String(targetOptions.title || this.activeConversationTitle || ''),
				String(targetOptions.avatarUrl || '')
			);

			if (this.groupModalMode === 'invite') {
				this.groupCreationKind = 'group';
			} else {
				this.refreshGroupCreationModes();
				this.setGroupCreationKind(this.defaultGroupCreationKind());
			}

			this.refreshGroupModalLayout();

			this.groupModal().addClass('is-open').attr('aria-hidden', 'false');
			$('body').addClass('messenger-upload-open');
			this.renderGroupContextSummary();
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

		openProfileModal: function () {
			this.profileModal().addClass('is-open').attr('aria-hidden', 'false');
			$('body').addClass('messenger-modal-open');
			return false;
		},

		closeProfileModal: function () {
			this.profileModal().removeClass('is-open').attr('aria-hidden', 'true');
			$('body').removeClass('messenger-modal-open');
			this.profileOpenContext = '';
			return false;
		},

		renderProfileModal: function (payload) {
			var avatarHtml = '';
			var displayHandle = payload.public_handle ? ('@' + String(payload.public_handle)) : String(payload.display_label || '@user');
			var buttonLabel = 'Wyślij zaproszenie';
			var secondaryLabel = '';
			var noteText = '';
			var avatarTheme = String(payload.avatar_theme || 'theme-1');
			var actionKind = String(payload.action_kind || 'invite');
			var directStatus = String(payload.direct_status || '');
			var profileContext = String(payload.profile_context || this.profileOpenContext || '');
			var activeType = String(this.contentRoot().attr('data-chat-active-conversation-type') || '');
			var activeIsDirectConversation = String(this.contentRoot().attr('data-chat-active-conversation-is-direct') || '0') === '1';
			var activeDirectStatus = String(this.contentRoot().attr('data-chat-active-conversation-direct-status') || 'none');
			var activeConversationId = parseInt(this.activeConversationId || 0, 10) || 0;
			var shouldUseActiveDirectContext = activeType === 'group_chat' && activeIsDirectConversation && profileContext !== 'group-member';
			var isPendingDirectConversation = shouldUseActiveDirectContext && activeDirectStatus === 'pending_invited' && activeConversationId > 0;
			var isRejectedDirectConversation = shouldUseActiveDirectContext && activeDirectStatus === 'rejected' && activeConversationId > 0;
			var isAcceptedDirectConversation = shouldUseActiveDirectContext && activeConversationId > 0 && !isPendingDirectConversation && !isRejectedDirectConversation;
			var $primaryButton = this.profileActionButton();
			var $secondaryButton = this.profileSecondaryActionButton();
			var $note = this.profileNote();

			this.profileAvatar().removeClass('theme-1 theme-2 theme-3 theme-4 theme-5 theme-6').addClass(avatarTheme);

			if (payload.avatar_url) {
				avatarHtml = '<img src="' + this.escapeHtml(String(payload.avatar_url)) + '" alt="' + this.escapeHtml(displayHandle) + '">';
			} else {
				avatarHtml = '<span>' + this.escapeHtml(String(payload.avatar_text || 'U')) + '</span>';
			}

			if (actionKind === 'open') {
				buttonLabel = directStatus === 'pending' ? 'Otwórz zaproszenie' : 'Wyślij wiadomość';
			}

			if ((profileContext === 'group-member' || profileContext === 'message-author') && actionKind === 'invite') {
				buttonLabel = 'Wyślij wiadomość';
			}

			if (isAcceptedDirectConversation && actionKind !== 'respond_invite') {
				actionKind = 'open';
				payload.conversation_id = activeConversationId;
				buttonLabel = 'Wyślij wiadomość';
			}

			if (isPendingDirectConversation && actionKind !== 'respond_invite') {
				actionKind = 'respond_invite';
				payload.conversation_id = activeConversationId;
			}

			if (isRejectedDirectConversation && actionKind !== 'respond_invite') {
				actionKind = 'reinvite';
				payload.conversation_id = activeConversationId;
			}

			if (actionKind === 'respond_invite') {
				noteText = String(window.MESSENGER_BOOTSTRAP.profileInviteMessagePrefix || 'You were invited to a conversation by') + ' ' + displayHandle + '.\n\n' + String(window.MESSENGER_BOOTSTRAP.profileInviteHint || 'You can already see this conversation in your inbox. If you do not want to stay in it, you can reject the invite.');
				buttonLabel = String(window.MESSENGER_BOOTSTRAP.profileAcceptLabel || 'Accept');
				secondaryLabel = String(window.MESSENGER_BOOTSTRAP.profileRejectLabel || 'Reject');
			} else if (actionKind === 'reinvite' || directStatus === 'rejected') {
				actionKind = 'reinvite';
				noteText = String(window.MESSENGER_BOOTSTRAP.profileReinviteHint || 'This invitation was rejected. You can send it again.');
				buttonLabel = String(window.MESSENGER_BOOTSTRAP.profileReinviteLabel || 'Send invite again');
			}

			this.profileAvatar().html(avatarHtml);
			this.profileHandle().text(displayHandle);
			this.profileLastSeen().text(String(payload.last_seen_label || 'Offline'));
			$note.text(noteText).toggle(!!noteText);
			$primaryButton
				.attr('data-target-customer-id', parseInt(payload.customer_id || 0, 10) || 0)
				.attr('data-action-kind', actionKind === 'respond_invite' ? 'respond_invite_accept' : actionKind)
				.attr('data-conversation-id', parseInt(payload.conversation_id || 0, 10) || 0)
				.text(buttonLabel);
			$secondaryButton
				.attr('data-target-customer-id', parseInt(payload.customer_id || 0, 10) || 0)
				.attr('data-action-kind', actionKind === 'respond_invite' ? 'respond_invite_reject' : '')
				.attr('data-conversation-id', parseInt(payload.conversation_id || 0, 10) || 0)
				.text(secondaryLabel || String(window.MESSENGER_BOOTSTRAP.profileRejectLabel || 'Reject'))
				.toggleClass('btn-default', true)
				.toggle(actionKind === 'respond_invite');
		},

		fetchParticipantProfile: function (participantType, targetCustomerId, profileContext) {
			var self = this;
			var cfg = this.config();
			var safeCustomerId = parseInt(targetCustomerId || 0, 10) || 0;

			if (participantType !== 'customer' || !safeCustomerId) {
				return false;
			}

			this.profileOpenContext = $.trim(String(profileContext || ''));

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'participant_profile',
					format: 'json',
					participant_type: 'customer',
					target_customer_id: safeCustomerId,
					conversation_id: this.activeConversationId,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || 'Unable to load user profile.');
					return;
				}

				self.renderProfileModal(payload);
				self.openProfileModal();
			}).fail(function () {
				self.showNotice('Unable to load user profile.');
			});

			return false;
		},

		submitProfileAction: function (sourceButton) {
			var self = this;
			var cfg = this.config();
			var $button = sourceButton && sourceButton.length ? sourceButton : this.profileActionButton();
			var targetCustomerId = parseInt($button.attr('data-target-customer-id') || '0', 10) || 0;
			var actionKind = String($button.attr('data-action-kind') || 'invite');
			var conversationId = parseInt($button.attr('data-conversation-id') || '0', 10) || 0;

			if (!targetCustomerId && actionKind.indexOf('respond_invite_') !== 0) {
				return false;
			}

			if (actionKind === 'open' && conversationId > 0) {
				this.closeProfileModal();
				this.selectConversation(conversationId, 'group_chat');
				return false;
			}

			if ((actionKind === 'respond_invite_accept' || actionKind === 'respond_invite_reject') && conversationId > 0) {
				this.closeProfileModal();
				return this.respondToGroupInvite(conversationId, actionKind === 'respond_invite_accept' ? 'accept' : 'reject');
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'start_direct_chat',
					format: 'json',
					target_customer_id: targetCustomerId,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || 'Unable to send invite.');
					return;
				}

				self.closeProfileModal();
				self.renderPayload(payload, {
					force: true,
					scrollToBottom: true
				});
				if (payload.conversation_id) {
					self.selectConversation(payload.conversation_id, 'group_chat');
				}
				if (payload.message) {
					self.showNotice(payload.message);
				}
			}).fail(function () {
				self.showNotice('Unable to send invite.');
			});

			return false;
		},

		resendDirectInvite: function (targetCustomerId) {
			var self = this;
			var cfg = this.config();
			var safeCustomerId = parseInt(targetCustomerId || 0, 10) || 0;

			if (!safeCustomerId) {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'start_direct_chat',
					format: 'json',
					target_customer_id: safeCustomerId,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || 'Unable to send invite.');
					return;
				}

				self.renderPayload(payload, {
					force: true,
					scrollToBottom: true
				});
				if (payload.conversation_id) {
					self.selectConversation(payload.conversation_id, 'group_chat');
				}
				if (payload.message) {
					self.showNotice(payload.message);
				}
			}).fail(function () {
				self.showNotice('Unable to send invite.');
			});

			return false;
		},

		addGroupEmail: function () {
			var self = this;
			var cfg = this.config();
			var email = $.trim(String(this.groupEmailInput().val() || '')).toLowerCase();
			var existingInviteValues = this.groupInviteValues();
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

			if ($.inArray(email, existingInviteValues) !== -1) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailDuplicate || 'This invitation is already added.', true);
				return false;
			}

			if (this.groupModalMode !== 'invite' && this.groupCreationKind === 'direct' && this.groupEmails.length >= 1) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupDirectLimit || 'Direct conversation allows only one user.', true);
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
					self.showGroupAlert((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupEmailNotFound || 'No user with Messenger access was found for this email or handle.'), true);
					return;
				}

				if ($.inArray(String(payload.email || email).toLowerCase(), self.groupInviteValues()) !== -1) {
					self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailDuplicate || 'This invitation is already added.', true);
					return;
				}

				self.groupEmails.push({
					value: String(payload.email || email).toLowerCase(),
					label: String(payload.display_name || payload.email || email)
				});
				self.groupEmailInput().val('').trigger('focus');
				self.renderGroupMembers();
				self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailAdded || 'Invitation prepared. It will expire after 24 hours if not accepted.', false);
			}).fail(function () {
				self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupEmailNotFound || 'No user with Messenger access was found for this email or handle.', true);
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
			if (this.shouldAutoScrollDuringFaqReply()) {
				this.scrollToBottom();
			}
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
			if (this.shouldAutoScrollDuringFaqReply()) {
				this.scrollToBottom();
			}

			this.typewriterTimer = window.setInterval(function () {
				currentIndex += 1;
				$typingText.html(self.formatTextHtml(rawMessage.slice(0, currentIndex)));
				if (self.shouldAutoScrollDuringFaqReply()) {
					self.scrollToBottom();
				}

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
			var shouldMarkRead = !this.hasResellerInboxLayout() || (this.resellerViewMode === 'conversation' && this.hasActiveConversationSelection());
			if (this.isDesktopDocked()) {
				this.syncDesktopDockedState();
				this.refreshOpenLayout(true);
				if (shouldMarkRead) {
					this.markRead();
				}
				this.fetch({
					force: true,
					scrollToBottom: true
				});
				return false;
			}
			if (this.hasResellerInboxLayout()) {
				if (this.resellerViewMode === 'conversation' && this.hasActiveConversationSelection()) {
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
			if (shouldMarkRead) {
				this.markRead();
			}
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
			if (this.isDesktopDocked()) {
				this.syncDesktopDockedState();
				return false;
			}
			this.widget().removeClass('is-open');
			this.heading().attr('aria-expanded', 'false');
			this.panel().attr('aria-hidden', 'true').removeClass('in is-visible').stop(true, true).slideUp(180);
			this.icon().removeClass('fa-angle-up').addClass('fa-angle-down');
			this.savePanelState(false);
			return false;
		},

		toggle: function () {
			if (this.isDesktopDocked()) {
				this.syncDesktopDockedState();
				return false;
			}
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
			var previousConversationId = this.activeConversationId;
			var previousMessageIds = this.captureRenderedMessageIds();
			var activeAudioPlayers = this.captureActiveAudioPlayers();
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
				this.restoreActiveAudioPlayers(activeAudioPlayers);
				this.prepareAudioPlayers();
				this.closeMessageActions();
				this.closeGroupMenu();
				this.closeGroupSettingsMenu();
				this.closeGroupMembersPopover();
				this.syncConversationStateFromMarkup();
				if (previousConversationId && this.activeConversationId && previousConversationId !== this.activeConversationId) {
					this.clearReplyTarget();
				}
				if (preservePrependOffset && this.activeConversationLoadedCount <= previousLoadedCount) {
					this.hasMoreMessages = false;
				}
				this.bindChatScrollHandler();
				if (this.hasResellerInboxLayout()) {
					if (this.resellerViewMode === 'conversation' && this.hasActiveConversationSelection()) {
						this.showConversationView();
					} else {
						this.showConversationList();
					}
				}
				this.startDeleteCountdowns();
				this.setConversationLoadingState(false);
				this.animateNewMessagesFromRender(previousMessageIds, previousConversationId, options);
				if (options.animateConversation) {
					this.playConversationEntryAnimation();
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
			if (this.hasResellerInboxLayout() && this.resellerViewMode === 'conversation' && this.hasActiveConversationSelection()) {
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
			var requestConversationType;
			var requestData;
			options = options || {};

			if (!cfg.userId || this.fetchInFlight || this.sendInFlight || this.uploadInFlight || this.faqPendingKey || this.voiceRecording || this.voiceStartInFlight) {
				return $.Deferred().resolve().promise();
			}

			this.fetchInFlight = true;
			requestConversationId = options.hasOwnProperty('conversationId') ? (parseInt(options.conversationId || 0, 10) || 0) : this.activeConversationId;
			requestConversationType = options.hasOwnProperty('conversationType') ? String(options.conversationType || '') : String(this.activeConversationType || '');
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
				if (requestConversationType) {
					requestData.conversation_type = requestConversationType;
				}
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

		markRead: function (conversationId, conversationType) {
			return this.fetch({
				markRead: true,
				conversationId: typeof conversationId === 'undefined' ? this.activeConversationId : conversationId,
				conversationType: typeof conversationType === 'undefined' ? this.activeConversationType : conversationType,
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

			if (!cfg.userId || !message || this.sendInFlight || this.uploadInFlight || this.faqPendingKey || this.voiceRecording || this.voiceStartInFlight || !this.activeCanSend) {
				return false;
			}

			this.sendInFlight = true;
			this.closeMessageActions();
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
					reply_to_message_id: this.replyToMessageId || 0,
					link_preview_url: previewUrl,
					link_preview_removed: previewRemoved,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (payload && payload.ok) {
					self.clearReplyTarget();
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

			if (!faqKey || this.fetchInFlight || this.sendInFlight || this.uploadInFlight || this.faqPendingKey || this.voiceRecording || this.voiceStartInFlight) {
				return false;
			}

			if (this.scrollToExistingFaqMessage(faqQuestionText)) {
				return false;
			}

			this.faqPendingKey = faqKey;
			this.setFaqButtonsDisabled(true);

			this.requestFaqAction('faq_prompt', faqKey).done(function (payload) {
				if (!payload || !payload.ok) {
					self.faqPendingKey = '';
					self.setFaqButtonsDisabled(false);
					self.fetch({
						force: true,
						scrollToBottom: true
					});
					return;
				}
				self.faqPendingKey = '';
				self.setFaqButtonsDisabled(false);
				self.scheduleSyncAfterSend();
			}).fail(function () {
				self.faqPendingKey = '';
				self.setFaqButtonsDisabled(false);
				self.fetch({
					force: true,
					scrollToBottom: true
				});
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

			if (!cfg.userId || !fileInput || !fileInput.files || !fileInput.files[0] || this.sendInFlight || this.uploadInFlight || this.faqPendingKey || this.voiceRecording || this.voiceStartInFlight || !this.activeCanSend) {
				return false;
			}

			this.uploadInFlight = true;
			this.closeMessageActions();
			$('#button_upload2').prop('disabled', true);
			this.updateUploadProgress(0);

			var formData = new FormData();
			formData.append('action', 'upload');
			formData.append('format', 'json');
			formData.append('conversation_id', String(this.activeConversationId || 0));
			formData.append('reply_to_message_id', String(this.replyToMessageId || 0));
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
					self.clearReplyTarget();
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
			var $messageNode;
			var $timeAnchor;
			var sendDeleteRequest;

			if (!cfg.userId || !messageId || this.sendInFlight || this.uploadInFlight || this.fetchInFlight || this.faqPendingKey) {
				return false;
			}

			sendDeleteRequest = function () {
				$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'delete_message',
					format: 'json',
					message_id: messageId,
					conversation_id: self.activeConversationId,
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
				}).always(function () {
					if ($messageNode && $messageNode.length) {
						$messageNode.removeClass('is-removing');
					}
					if ($timeAnchor && $timeAnchor.length) {
						$timeAnchor.removeClass('is-removing');
					}
				});
			};

			$messageNode = this.chatBox().find('.messenger-item[data-message-id="' + String(messageId) + '"]').first();
			$timeAnchor = $messageNode.prev('.messenger-time-anchor');
			this.closeMessageActions();

			if ($messageNode.length) {
				$messageNode.addClass('is-removing');
				if ($timeAnchor.length) {
					$timeAnchor.addClass('is-removing');
				}
				window.setTimeout(sendDeleteRequest, 180);
			} else {
				sendDeleteRequest();
			}

			return false;
		},

		selectConversation: function (conversationId, conversationType) {
			var nextConversationId = parseInt(conversationId || 0, 10) || 0;
			var nextConversationType = String(conversationType || 'live_chat');
			var self = this;

			if (this.voiceRecording || this.voiceStartInFlight || this.uploadInFlight) {
				this.showNotice(window.MESSENGER_BOOTSTRAP.voiceRecordBusyMessage || 'Finish sending the voice message first.');
				return false;
			}

			this.activeConversationId = nextConversationId;
			this.activeConversationType = nextConversationType;
			this.clearReplyTarget();
			this.closeMessageActions();
			this.activeConversationMessageLimit = this.messagePageSize;
			this.activeConversationLoadedCount = 0;
			this.activeConversationTotalMessages = 0;
			this.oldestMessageId = 0;
			this.hasMoreMessages = false;
			if (this.hasResellerInboxLayout()) {
				this.resellerViewMode = 'conversation';
				this.saveActiveConversationState();
				this.syncConversationUrlState();
				this.showConversationView();
				this.setConversationLoadingState(true);
			}
			this.fetch({
				force: true,
				conversationId: nextConversationId,
				conversationType: nextConversationType,
				scrollToBottom: true,
				animateConversation: true
			}).done(function () {
				self.markRead(nextConversationId, nextConversationType);
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
			var inviteValues = this.groupInviteValues();
			var retentionValue = $.trim(String(this.groupRetentionCreate().val() || '24h')) || '24h';
			var formData = new window.FormData();
			var isCreateMode = modalMode !== 'invite';
			var isNamedGroupCreate = isCreateMode && this.groupCreationKind === 'group';
			var isDirectCreate = isCreateMode && this.groupCreationKind === 'direct';

			if (isNamedGroupCreate && !groupName) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupNameRequired || 'Group name is required.', true);
				return false;
			}

			if ((modalMode === 'invite' || isDirectCreate) && !inviteValues.length) {
				this.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupParticipantsRequired || 'Add at least one participant.', true);
				return false;
			}

			formData.append('action', action);
			formData.append('format', 'json');
			formData.append('group_name', groupName);
			formData.append('group_kind', this.groupCreationKind);
			formData.append('conversation_id', String(this.groupModalMode === 'invite' ? this.groupTargetConversationId : 0));
			formData.append('participant_emails_json', JSON.stringify(inviteValues));
			formData.append('retention_hours', retentionValue);
			formData.append('_csrf', cfg.csrfToken);

			if (isNamedGroupCreate && this.groupAvatarFile) {
				formData.append('group_avatar_file', this.groupAvatarFile);
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: formData,
				processData: false,
				contentType: false
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showGroupAlert((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupCreateError || 'Unable to create group chat.'), true);
					return;
				}

				self.renderPayload(payload, {
					force: true,
					scrollToBottom: modalMode === 'invite'
				});

				if (isNamedGroupCreate) {
					self.groupModalMode = 'invite';
					self.groupEmails = [];
					self.groupEmailRequests = {};
					self.renderGroupMembers();
					self.groupEmailInput().val('');
					self.setGroupTargetConversation(
						parseInt(payload.conversation_id || 0, 10) || 0,
						String(payload.conversation_title || payload.title || groupName || ''),
						String(payload.conversation_avatar_url || payload.avatar_url || '')
					);
					self.refreshGroupModalLayout();
					self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupCreateSuccess || 'Group created. Now you can invite members.', false);
					window.setTimeout(function () {
						self.groupEmailInput().trigger('focus');
					}, 20);
					return;
				}

				if (modalMode === 'invite') {
					self.groupEmails = [];
					self.groupEmailRequests = {};
					self.renderGroupMembers();
					self.groupEmailInput().val('');
					self.showGroupAlert(window.MESSENGER_BOOTSTRAP.groupInviteSuccess || 'Invitations sent.', false);
					return;
				}

				self.closeGroupModal();
				if (payload.conversation_id) {
					self.selectConversation(payload.conversation_id, 'group_chat');
				}
			}).fail(function () {
				self.showGroupAlert(
					(isNamedGroupCreate && self.groupAvatarFile)
						? (window.MESSENGER_BOOTSTRAP.groupLogoUploadFailed || 'Group logo upload failed.')
						: (window.MESSENGER_BOOTSTRAP.groupCreateError || 'Unable to create group chat.'),
					true
				);
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

		removeGroup: function (conversationId) {
			var self = this;
			var cfg = this.config();
			var safeConversationId = parseInt(conversationId || this.activeConversationId || 0, 10) || 0;

			if (!safeConversationId) {
				return false;
			}

			if (!window.confirm(window.MESSENGER_BOOTSTRAP.groupRemoveConfirm || 'Remove this conversation only from your inbox?')) {
				return false;
			}

			$.ajax({
				type: 'POST',
				url: cfg.endpoint,
				dataType: 'json',
				data: {
					action: 'remove_group',
					format: 'json',
					conversation_id: safeConversationId,
					_csrf: cfg.csrfToken
				}
			}).done(function (payload) {
				if (!payload || !payload.ok) {
					self.showNotice((payload && payload.message) || (window.MESSENGER_BOOTSTRAP.groupRemoveError || 'Unable to remove the conversation from the inbox.'));
					return;
				}

				self.renderPayload(payload, {
					force: true,
					scrollToBottom: true
				});
			}).fail(function () {
				self.showNotice(window.MESSENGER_BOOTSTRAP.groupRemoveError || 'Unable to remove the conversation from the inbox.');
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

		updateGroupRetention: function (retentionValue) {
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
					retention_hours: String(retentionValue || '24h'),
					retention_token: String(retentionValue || '24h'),
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

			if (this._voiceButtonNode && this._boundVoiceClickHandler) {
				this._voiceButtonNode.removeEventListener('click', this._boundVoiceClickHandler);
			}

			this._voiceButtonNode = this.voiceButton().get(0) || null;
			this._boundVoiceClickHandler = function (event) {
				var $button = self.voiceButton();
				event.preventDefault();
				event.stopPropagation();
				if (!$button.length || $button.hasClass('is-disabled') || $button.prop('disabled')) {
					return false;
				}
				if (self.voiceRecording || self.voiceStartInFlight) {
					return self.stopVoiceRecording(false);
				}
				return self.startVoiceRecording();
			};

			if (this._voiceButtonNode) {
				this._voiceButtonNode.addEventListener('click', this._boundVoiceClickHandler);
			}

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

			$(document).on('click.messengerUi', '[data-chat-message-bubble]', function (event) {
				var $target = $(event.target);
				var $item = $(this).closest('[data-chat-message-item]');
				if ($target.closest('a, button, input, textarea, label').length) {
					return;
				}
				if (!$item.length) {
					return;
				}
				event.preventDefault();
				self.toggleMessageActions(parseInt($item.attr('data-message-id') || '0', 10) || 0);
			});

			$(document).on('touchstart.messengerUi', '[data-chat-message-bubble]', function (event) {
				var target = event.target;
				var messageId = parseInt($(this).closest('[data-chat-message-item]').attr('data-message-id') || '0', 10) || 0;
				if (!messageId || $(target).closest('a, button, input, textarea, label').length) {
					return;
				}
				window.clearTimeout(self._messageTouchPressTimer);
				self._messageTouchPressTimer = window.setTimeout(function () {
					self.toggleMessageActions(messageId, true);
				}, 360);
			});

			$(document).on('touchend.messengerUi touchmove.messengerUi touchcancel.messengerUi', '[data-chat-message-bubble]', function () {
				window.clearTimeout(self._messageTouchPressTimer);
			});

			$(document).on('click.messengerUi', '[data-chat-reply-open]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.setReplyTarget(
					parseInt($(this).attr('data-message-id') || '0', 10) || 0,
					String($(this).attr('data-reply-sender') || ''),
					String($(this).attr('data-reply-text') || '')
				);
				self.closeMessageActions();
				self.input().trigger('focus');
			});

			$(document).on('click.messengerUi', '[data-chat-reply-clear]', function (event) {
				event.preventDefault();
				self.clearReplyTarget();
			});

			$(document).on('click.messengerUi', '[data-chat-scroll-to-message]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.scrollToMessage(parseInt($(this).attr('data-chat-scroll-to-message') || '0', 10) || 0);
			});

			$(document).on('click.messengerUi', '[data-chat-reaction-toggle]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.toggleReaction(
					parseInt($(this).attr('data-message-id') || '0', 10) || 0,
					String($(this).attr('data-chat-reaction-toggle') || '')
				);
				self.closeMessageActions();
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
				self.updateGroupRetention(String($(this).val() || '24h'));
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

			$(document).on('click.messengerUi', '[data-chat-profile-open]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				self.fetchParticipantProfile(
					String($(this).attr('data-participant-type') || 'customer'),
					parseInt($(this).attr('data-target-customer-id') || '0', 10) || 0,
					String($(this).attr('data-chat-profile-context') || '')
				);
			});

			$(document).on('click.messengerUi', '[data-chat-profile-close]', function (event) {
				event.preventDefault();
				self.closeProfileModal();
			});

			$(document).on('click.messengerUi', '#messenger_profile_action', function (event) {
				event.preventDefault();
				self.submitProfileAction($(this));
			});

			$(document).on('click.messengerUi', '#messenger_profile_action_secondary', function (event) {
				event.preventDefault();
				self.submitProfileAction($(this));
			});

			$(document).on('click.messengerUi', '[data-chat-direct-reinvite]', function (event) {
				event.preventDefault();
				self.resendDirectInvite(parseInt($(this).attr('data-target-customer-id') || '0', 10) || 0);
			});

			$(document).on('click.messengerUi', '[data-messenger-group-add]', function (event) {
				event.preventDefault();
				self.addGroupEmail();
			});

			$(document).on('click.messengerUi', '[data-messenger-group-avatar-open]', function (event) {
				event.preventDefault();
				self.groupAvatarInput().trigger('click');
			});

			$(document).on('click.messengerUi', '[data-messenger-group-avatar-clear]', function (event) {
				event.preventDefault();
				self.resetGroupAvatar();
			});

			$(document).on('change.messengerUi', '#messenger_group_avatar_file', function () {
				self.handleGroupAvatarSelection(this);
			});

			$(document).on('input.messengerUi', '#messenger_group_name', function () {
				self.renderGroupContextSummary();
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

			$(document).on('click.messengerUi', '[data-messenger-group-open-created]', function (event) {
				var targetConversationId;
				event.preventDefault();
				targetConversationId = parseInt(self.groupTargetConversationId || 0, 10) || 0;
				if (targetConversationId > 0) {
					self.closeGroupModal();
					self.selectConversation(targetConversationId, 'group_chat');
				}
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

			$(document).on('click.messengerUi', '[data-chat-remove-group]', function (event) {
				event.preventDefault();
				self.closeGroupMenu();
				self.closeGroupSettingsMenu();
				self.removeGroup($(this).attr('data-conversation-id'));
			});

			$(document).on('click.messengerUi', function (event) {
				if (!$(event.target).closest('[data-chat-message-item]').length) {
					self.closeMessageActions();
				}
				if (!$(event.target).closest('[data-chat-header-actions]').length) {
					self.closeGroupMenu();
					self.closeGroupSettingsMenu();
				}
				if (!$(event.target).closest('.messenger-conversation-members').length) {
					self.closeGroupMembersPopover();
				}
				if (!$(event.target).closest('.messenger-profile-modal__dialog, [data-chat-profile-open]').length) {
					self.closeProfileModal();
				}
			});
		},

		init: function () {
			var shouldRestoreOpenState;
			var requestedConversationId;
			if (this.initDone) {
				this.refreshOpenLayout(false);
				this.schedulePoll();
				return;
			}

			shouldRestoreOpenState = this.restorePanelState();
			requestedConversationId = this.requestedConversationIdFromUrl();
			this.restoreOpenScrollToBottomPending = !!shouldRestoreOpenState;
			this.updateViewportMetrics();
			this.syncConversationStateFromMarkup(true);
			if (this.hasResellerInboxLayout()) {
				if (!(requestedConversationId > 0 && (this.activeConversationId > 0 || this.hasActiveConversationSelection()))) {
					this.restoreActiveConversationState();
				} else {
					this.resellerViewMode = 'conversation';
					this.saveActiveConversationState();
					this.syncConversationUrlState();
				}
				if (this.resellerViewMode === 'conversation' && this.hasActiveConversationSelection() && (shouldRestoreOpenState || this.isDesktopDocked() || requestedConversationId > 0 || this.activeConversationType === 'live_chat')) {
					this.showConversationView();
				} else {
					this.showConversationList();
				}
			}
			this.syncDesktopDockedState();
			this.resetGroupModal();
			this.bind();
			if (shouldRestoreOpenState || this.isDesktopDocked()) {
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
