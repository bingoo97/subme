<script>
  <?php $chatSupportName = function_exists('chat_default_support_label') ? chat_default_support_label($db, $reseller ?? []) : 'Support'; ?>
  window.MESSENGER_BOOTSTRAP = window.MESSENGER_BOOTSTRAP || {};
  window.MESSENGER_BOOTSTRAP.userId = <?php echo isset($user['id']) ? (int)$user['id'] : 0; ?>;
  window.MESSENGER_BOOTSTRAP.endpoint = 'check_chat.php';
  window.MESSENGER_BOOTSTRAP.typingLabel = <?php echo json_encode($t['chat_typing_label'] ?? 'Support is typing...'); ?>;
  window.MESSENGER_BOOTSTRAP.supportName = <?php echo json_encode($chatSupportName); ?>;
  window.MESSENGER_BOOTSTRAP.writeMessagePlaceholder = <?php echo json_encode($t['chat_write_message'] ?? 'Write message...'); ?>;
  window.MESSENGER_BOOTSTRAP.deleteLabel = <?php echo json_encode($t['chat_delete'] ?? 'Delete'); ?>;
  window.MESSENGER_BOOTSTRAP.invalidImageMessage = <?php echo json_encode($t['chat_invalid_image'] ?? 'The selected file is not a supported image.'); ?>;
  window.MESSENGER_BOOTSTRAP.cooldownMessage = <?php echo json_encode($t['chat_cooldown_notice'] ?? 'Please wait before sending another message.'); ?>;
  window.MESSENGER_BOOTSTRAP.deleteFailedMessage = <?php echo json_encode($t['chat_delete_failed'] ?? 'Message deletion failed.'); ?>;

if (typeof window.toggleMessengerPanel !== 'function') {
	window.toggleMessengerPanel = function () {
		var $widget = window.jQuery ? window.jQuery('#messanger') : null;
		var $panel = window.jQuery ? window.jQuery('#collapseOne') : null;
		var $heading = window.jQuery ? window.jQuery('#panel-heading') : null;
		var $icon = window.jQuery ? window.jQuery('.messenger-toggle-icon') : null;

		if (!$widget || !$widget.length || !$panel || !$panel.length) {
			return false;
		}

		if ($widget.hasClass('is-open')) {
			$widget.removeClass('is-open');
			$heading.attr('aria-expanded', 'false');
			$panel.attr('aria-hidden', 'true').removeClass('in is-visible').stop(true, true).slideUp(180);
			if ($icon && $icon.length) {
				$icon.removeClass('fa-angle-up').addClass('fa-angle-down');
			}
			return false;
		}

		$widget.addClass('is-open');
		$heading.attr('aria-expanded', 'true');
		$panel.attr('aria-hidden', 'false').addClass('in is-visible').stop(true, true).slideDown(180, function () {
			var chatScroll = document.getElementById('chat_scroll');
			if (chatScroll) {
				chatScroll.scrollTop = chatScroll.scrollHeight;
			}
		});
		if ($icon && $icon.length) {
			$icon.removeClass('fa-angle-down').addClass('fa-angle-up');
		}
		return false;
	};
}

if (typeof window.openMessengerPanel !== 'function') {
	window.openMessengerPanel = function () {
		var $widget = window.jQuery ? window.jQuery('#messanger') : null;
		if ($widget && $widget.length && !$widget.hasClass('is-open')) {
			return window.toggleMessengerPanel();
		}
		return false;
	};
}

if (typeof window.showMessengerNotice !== 'function') {
	window.showMessengerNotice = function (message, persistent) {
		var $ = window.jQuery;
		var $alert = $ ? $('#messenger_alert') : null;

		if (!$alert || !$alert.length || !message) {
			return false;
		}

		$alert.stop(true, true).html(String(message)).fadeIn(120);

		if (!persistent) {
			window.clearTimeout(window.messengerNoticeTimer);
			window.messengerNoticeTimer = window.setTimeout(function () {
				window.clearMessengerNotice();
			}, 5000);
		}

		return false;
	};
}

if (typeof window.clearMessengerNotice !== 'function') {
	window.clearMessengerNotice = function () {
		var $ = window.jQuery;
		var $alert = $ ? $('#messenger_alert') : null;

		window.clearTimeout(window.messengerNoticeTimer);
		if ($alert && $alert.length) {
			$alert.stop(true, true).fadeOut(120, function () {
				$alert.text('');
			});
		}
		return false;
	};
}

if (typeof window.openMessengerUpload !== 'function') {
	window.openMessengerUpload = function () {
		var $ = window.jQuery;
		var $modal = $ ? $('#messanger_upload') : null;

		if (!$modal || !$modal.length) {
			return false;
		}

		if (!$modal.parent().is('body')) {
			$modal.appendTo('body');
		}

		$('body').addClass('messenger-upload-open');
		$modal.addClass('is-open').attr('aria-hidden', 'false');
		return false;
	};
}

if (typeof window.closeMessengerUpload !== 'function') {
	window.closeMessengerUpload = function () {
		var $ = window.jQuery;
		var $modal = $ ? $('#messanger_upload') : null;

		if (!$modal || !$modal.length) {
			return false;
		}

		$modal.removeClass('is-open').attr('aria-hidden', 'true');
		$('body').removeClass('messenger-upload-open');
		$('#file').val('');
		$('#preview_img').hide().attr('src', '');
		$('#button_upload2').prop('disabled', true);
		$('#messenger_upload_progress').hide();
		$('#messenger_upload_progress_fill').css('width', '0%');
		$('#messenger_upload_progress_value').text('0%');
		return false;
	};
}

if (typeof window.previewFile !== 'function') {
	window.previewFile = function () {
		var $ = window.jQuery;
		var preview = document.getElementById('preview_img');
		var fileInput = document.getElementById('file');
		var file = fileInput && fileInput.files ? fileInput.files[0] : null;
		var validImageTypes = ['image/gif', 'image/jpeg', 'image/png'];

		if (!$) {
			return false;
		}

		if (!file) {
			$('#preview_img').hide().attr('src', '');
			$('#button_upload2').prop('disabled', true);
			$('#messenger_upload_progress').hide();
			$('#messenger_upload_progress_fill').css('width', '0%');
			$('#messenger_upload_progress_value').text('0%');
			return false;
		}

		if ($.inArray(file.type, validImageTypes) < 0) {
			window.showMessengerNotice(window.MESSENGER_BOOTSTRAP.invalidImageMessage || 'The selected file is not a supported image.');
			$('#file').val('');
			$('#preview_img').hide().attr('src', '');
			$('#button_upload2').prop('disabled', true);
			$('#messenger_upload_progress').hide();
			$('#messenger_upload_progress_fill').css('width', '0%');
			$('#messenger_upload_progress_value').text('0%');
			return false;
		}

		var reader = new FileReader();
		reader.addEventListener('load', function () {
			if (preview) {
				preview.src = reader.result;
			}
			$('#preview_img').show();
			$('#button_upload2').prop('disabled', false);
		}, false);
		$('#messenger_upload_progress').hide();
		$('#messenger_upload_progress_fill').css('width', '0%');
		$('#messenger_upload_progress_value').text('0%');
		reader.readAsDataURL(file);
		return false;
	};
}

if (typeof window.upload2 !== 'function') {
	window.upload2 = function () {
		var $ = window.jQuery;
		var endpoint = window.MESSENGER_BOOTSTRAP ? window.MESSENGER_BOOTSTRAP.endpoint : 'check_chat.php';
		var fileInput = document.getElementById('file');

		if (!$ || !fileInput || !fileInput.files || !fileInput.files[0]) {
			return false;
		}

		var formData = new FormData();
		formData.append('action', 'upload');
		formData.append('format', 'json');
		formData.append('file', fileInput.files[0]);

		$('#button_upload2').prop('disabled', true);
		$('#messenger_upload_progress').show();
		$('#messenger_upload_progress_fill').css('width', '0%');
		$('#messenger_upload_progress_value').text('0%');

		$.ajax({
			type: 'POST',
			processData: false,
			contentType: false,
			xhr: function () {
				var xhr = $.ajaxSettings.xhr();
				if (xhr && xhr.upload) {
					xhr.upload.addEventListener('progress', function (event) {
						if (event.lengthComputable) {
							var percent = Math.max(0, Math.min(100, Math.round((event.loaded / event.total) * 100)));
							$('#messenger_upload_progress_fill').css('width', percent + '%');
							$('#messenger_upload_progress_value').text(percent + '%');
						}
					}, false);
				}
				return xhr;
			},
			url: endpoint,
			dataType: 'json',
			data: formData
		}).done(function (payload) {
			if (payload && payload.ok && typeof payload.html === 'string') {
				$('#messenger_upload_progress_fill').css('width', '100%');
				$('#messenger_upload_progress_value').text('100%');
				$('#chat_box').html(payload.html);
				var chatScroll = document.getElementById('chat_scroll');
				if (chatScroll) {
					chatScroll.scrollTop = chatScroll.scrollHeight;
				}
				window.closeMessengerUpload();
			} else if (payload && payload.cooldown_active) {
				window.showMessengerNotice(payload.message || window.MESSENGER_BOOTSTRAP.cooldownMessage || 'Please wait before sending another message.', true);
			} else if (payload && payload.message) {
				window.showMessengerNotice(payload.message);
			}
		}).always(function () {
			$('#button_upload2').prop('disabled', false);
		});

		return false;
	};
}

if (typeof window.upload !== 'function') {
	window.upload = function () {
		return window.upload2();
	};
}

if (typeof window.chatFaqPrompt !== 'function') {
	window.chatFaqPrompt = function (faqKey) {
		var $ = window.jQuery;
		var endpoint = window.MESSENGER_BOOTSTRAP ? window.MESSENGER_BOOTSTRAP.endpoint : 'check_chat.php';
		var $faqButton = $('[data-chat-faq-key="' + faqKey + '"]').first();
		var questionText = $.trim($faqButton.text());
		var normalizedQuestion = String(questionText || '').replace(/\s+/g, ' ').trim();

		if (!$ || !faqKey) {
			return false;
		}

		var $existingMessage = $();
		$('#chat_box').find('.messenger-item .messenger-text').each(function () {
			var $text = $(this);
			var normalizedText = String($text.text() || '').replace(/\s+/g, ' ').trim();

			if ($existingMessage.length) {
				return false;
			}

			if ($text.closest('.messenger-item--intro, .messenger-typing, .messenger-typed-reply').length) {
				return;
			}

			if (normalizedText === normalizedQuestion) {
				$existingMessage = $text.closest('.messenger-item');
			}
		});

		if ($existingMessage.length) {
			var $chatScroll = $('#chat_scroll');
			$chatScroll.stop(true).animate({
				scrollTop: Math.max(0, $existingMessage.position().top + $chatScroll.scrollTop() - 24)
			}, 220);
			$existingMessage.addClass('messenger-item--focus');
			window.setTimeout(function () {
				$existingMessage.removeClass('messenger-item--focus');
			}, 1600);
			return false;
		}

		$('[data-chat-faq-key]').prop('disabled', true).addClass('is-disabled');

		$.ajax({
			type: 'POST',
			url: endpoint,
			dataType: 'json',
			data: {
				action: 'faq_prompt',
				format: 'json',
				faq_key: faqKey
			}
		}).done(function (payload) {
			if (!(payload && payload.ok && typeof payload.html === 'string')) {
				if (payload && payload.cooldown_active) {
					window.showMessengerNotice(payload.message || window.MESSENGER_BOOTSTRAP.cooldownMessage || 'Please wait before sending another message.', true);
				}
				$('[data-chat-faq-key]').prop('disabled', false).removeClass('is-disabled');
				return;
			}

			$('#chat_box').html(payload.html);

			var $list = $('#chat_box').find('.messenger-list');
			var typingLabel = window.MESSENGER_BOOTSTRAP && window.MESSENGER_BOOTSTRAP.typingLabel
				? window.MESSENGER_BOOTSTRAP.typingLabel
				: 'Support is typing...';

			if ($list.length) {
				$list.append(
					'<li class="messenger-item messenger-item--received messenger-typing">' +
						'<div class="messenger-bubble">' +
							'<div class="messenger-author">' + typingLabel + '</div>' +
							'<div class="messenger-typing-dots" aria-hidden="true"><span></span><span></span><span></span></div>' +
						'</div>' +
					'</li>'
				);
			}

			var chatScroll = document.getElementById('chat_scroll');
			if (chatScroll) {
				chatScroll.scrollTop = chatScroll.scrollHeight;
			}

			window.setTimeout(function () {
				$.ajax({
					type: 'POST',
					url: endpoint,
					dataType: 'json',
					data: {
						action: 'faq_reply',
						format: 'json',
						faq_key: faqKey
					}
				}).done(function (replyPayload) {
					if (replyPayload && replyPayload.ok && typeof replyPayload.html === 'string') {
						$('#chat_box').html(replyPayload.html);
						var updatedScroll = document.getElementById('chat_scroll');
						if (updatedScroll) {
							updatedScroll.scrollTop = updatedScroll.scrollHeight;
						}
					}
				}).always(function () {
					$('[data-chat-faq-key]').prop('disabled', false).removeClass('is-disabled');
				});
			}, 3000);
		}).fail(function () {
			$('[data-chat-faq-key]').prop('disabled', false).removeClass('is-disabled');
		});

		return false;
	};
}
</script>
