<div class="content-box settings-view">
    <div class="settings-view__header">
        <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.settings_title}</h1>
    </div>

    <div class="settings-view__grid">
        <div class="settings-card">
            <form action="" method="POST" class="settings-form">
                <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
                {if $settings_can_edit_messenger_identity|default:false}
                <div class="settings-form__field">
                    <div
                        class="settings-avatar-editor"
                        data-settings-avatar-editor
                        data-uploading-label="{$t.settings_avatar_uploading|default:'Trwa wysyłanie avatara...'}"
                        data-upload-success="{$t.settings_avatar_upload_success|default:'Avatar został zaktualizowany.'}"
                        data-upload-error="{$t.settings_avatar_upload_error|default:'Nie udało się zaktualizować avatara.'}"
                        data-upload-invalid-type="{$t.settings_avatar_upload_invalid_type|default:'Wgraj plik JPG, PNG lub WEBP.'}"
                        data-upload-too-large="{$t.settings_avatar_upload_too_large|default:'Plik jest za duży. Maksymalny rozmiar to 5 MB.'}"
                        data-max-bytes="5242880"
                    >
                        <button type="button" class="settings-avatar-editor__trigger" data-settings-avatar-trigger aria-label="{$t.settings_avatar_trigger|default:'Kliknij avatar, aby wgrać zdjęcie'}">
                            <div class="settings-avatar-editor__preview">
                                <img src="{$user.avatar_url|default:''|escape:'html'}" alt="Avatar" class="settings-avatar-editor__image" data-settings-avatar-preview-image{if $user.avatar_url|default:'' eq ''} hidden{/if}>
                                <span class="settings-avatar-editor__fallback{if $user.avatar_url|default:'' ne ''} is-hidden{/if}" data-settings-avatar-preview-fallback data-fallback-source="{$user.public_handle|default:$user.email|default:'U'|escape:'html'}">U</span>
                            </div>
                            <span class="settings-avatar-editor__overlay">
                                <i class="fa fa-camera" aria-hidden="true"></i>
                                <span>{$t.settings_avatar_trigger|default:'Kliknij avatar, aby wgrać zdjęcie'}</span>
                            </span>
                        </button>
                        <input type="file" class="settings-avatar-editor__file" accept="image/jpeg,image/png,image/webp" data-settings-avatar-file>
                        <div class="settings-avatar-editor__status" data-settings-avatar-status aria-live="polite"></div>
                    </div>
                    <p class="settings-form__help">{$t.settings_avatar_help}</p>
                </div>
                <div class="settings-form__field">
                    <label class="settings-form__label">{$t.settings_handle_label}</label>
                    <input type="text" class="form-control settings-form__control" name="public_handle" value="{$user.public_handle|default:''}" placeholder="{$t.settings_handle_placeholder|default:'twoj-login'}" autocomplete="off" data-settings-handle-input>
                    <p class="settings-form__help">{$t.settings_handle_help}</p>
                </div>
                {/if}
                <div class="settings-form__field">
                    <label class="settings-form__label">{$t.settings_email}</label>
                    <input type="text" class="form-control settings-form__control settings-form__control--readonly" value="{$user.email}" readonly="readonly" />
                </div>
                <div class="settings-form__field">
                    <label class="settings-form__label">{$t.language_label}</label>
                    <select name="lang" class="form-control settings-form__control">
                        {foreach from=$supported_locales item=locale}
                            <option value="{$locale.code}" {if $current_locale == $locale.code}selected{/if}>{$locale.native_label}</option>
                        {/foreach}
                    </select>
                    <p class="settings-form__help">{$t.settings_language_help}</p>
                </div>
                <div class="settings-form__field">
                    <label class="settings-form__label" for="settings-email-notification">{$t.settings_notifications_label}</label>
                    <label class="settings-toggle" for="settings-email-notification">
                        <input id="settings-email-notification" class="settings-toggle__input" type="checkbox" name="email_notification" value="1" {if !isset($user.email_notification) || $user.email_notification}checked{/if}>
                        <span class="settings-toggle__slider" aria-hidden="true"></span>
                        <span class="settings-toggle__copy">
                            <strong>{$t.settings_notifications_on}</strong>
                            <span>{$t.settings_notifications_help}</span>
                        </span>
                    </label>
                </div>
                <div class="settings-form__actions">
                    <button type="submit" name="save_settings" class="btn btn-dark btn-lg">{$t.save}</button>
                </div>
            </form>
        </div>

        <div class="settings-card settings-card--meta">
            <div class="settings-card__head">
                <h2 class="settings-card__title">{$t.change_password}</h2>
                <span class="settings-card__badge settings-card__badge--dark">{$t.settings_security_badge|default:'Security'}</span>
            </div>
            <p class="settings-security__text">{$t.change_password_intro}</p>
            <div class="settings-security__actions">
                <button type="button" class="btn btn-dark btn-lg" data-toggle="modal" data-target="#settingsPasswordModal">
                    <i class="fa fa-lock" aria-hidden="true"></i> {$t.change_password}
                </button>
            </div>
            <hr class="settings-card__divider" />
            <div class="settings-meta">
                <div class="settings-meta__row">
                    <span class="settings-meta__label">{$t.settings_registered_at}</span>
                    <span class="settings-meta__value">{$user.date_register}</span>
                </div>
                <div class="settings-meta__row">
                    <span class="settings-meta__label">{$t.settings_last_login}</span>
                    <span class="settings-meta__value">{$user.last_login}</span>
                </div>
                <div class="settings-meta__row">
                    <span class="settings-meta__label">{$t.settings_country}</span>
                    <span class="settings-meta__value">{$user.country|default:'-'}</span>
                </div>
                <div class="settings-meta__row">
                    <span class="settings-meta__label">{$t.settings_ip}</span>
                    <span class="settings-meta__value">{$user.ip}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="settingsPasswordModal" class="modal fade user-order-modal settings-password-modal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{$t.close|default:'Close'}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">{$t.change_password_title}</h4>
                <p class="user-order-modal__subtitle">{$t.change_password_intro}</p>
                <div class="user-order-modal__summary">
                    <span class="user-order-modal__summary-chip">{$t.settings_title}</span>
                    <span class="user-order-modal__summary-chip user-order-modal__summary-chip--status">{$t.change_password}</span>
                </div>
            </div>
            <div class="modal-body">
                <form action="" method="post" class="user-order-modal__stack" autocomplete="off">
                    <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
                    <div>
                        <label class="form-label" for="settings-current-password">{$t.change_password_current}</label>
                        <input id="settings-current-password" type="password" class="form-control" name="current_password" placeholder="{$t.change_password_current}" required />
                    </div>
                    <div>
                        <label class="form-label" for="settings-new-password">{$t.change_password_new}</label>
                        <input id="settings-new-password" type="password" class="form-control" name="new_password" placeholder="{$t.change_password_new}" required />
                    </div>
                    <div>
                        <label class="form-label" for="settings-repeat-password">{$t.change_password_repeat}</label>
                        <input id="settings-repeat-password" type="password" class="form-control" name="new_password_repeat" placeholder="{$t.change_password_repeat}" required />
                    </div>
                    <div class="user-order-modal__actions-stack">
                        <button type="submit" name="change_password" class="btn btn-dark btn-lg btn-block">
                            <i class="fa fa-check-circle" aria-hidden="true"></i> {$t.change_password_submit}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
$(function () {
    {if $settings_open_password_modal|default:false}
    $('#settingsPasswordModal').modal('show');
    {/if}

    var $avatarEditor = $('[data-settings-avatar-editor]');
    var $avatarImage = $('[data-settings-avatar-preview-image]');
    var $avatarFallback = $('[data-settings-avatar-preview-fallback]');
    var $avatarTrigger = $('[data-settings-avatar-trigger]');
    var $avatarFileInput = $('[data-settings-avatar-file]');
    var $avatarStatus = $('[data-settings-avatar-status]');
    var $handleInput = $('[data-settings-handle-input]');
    var csrfToken = $('input[name="_csrf"]').first().val() || '';
    var avatarUploadInFlight = false;
    var currentAvatarUrl = $.trim($avatarImage.attr('src') || '');

    function settingsAvatarFallback() {
        var source = '';

        if ($handleInput.length) {
            source = $.trim($handleInput.val() || '');
        }

        if (!source && $avatarFallback.length) {
            source = $.trim($avatarFallback.attr('data-fallback-source') || '');
        }

        source = source || 'U';
        return source.charAt(0).toUpperCase();
    }

    function setSettingsAvatarStatus(message, type) {
        if (!$avatarStatus.length) {
            return;
        }

        $avatarStatus.removeClass('is-error is-success is-loading').text($.trim(message || ''));
        if (type) {
            $avatarStatus.addClass('is-' + type);
        }
    }

    function showSettingsAvatarFallback() {
        if ($avatarFallback.length) {
            $avatarFallback.text(settingsAvatarFallback()).removeClass('is-hidden');
        }
        if ($avatarImage.length) {
            $avatarImage.attr('hidden', 'hidden');
        }
    }

    function showSettingsAvatarImage(url) {
        if (!url) {
            showSettingsAvatarFallback();
            return;
        }

        $avatarImage
            .off('.settingsAvatar')
            .on('load.settingsAvatar', function () {
                $avatarFallback.addClass('is-hidden');
                $avatarImage.removeAttr('hidden');
            })
            .on('error.settingsAvatar', function () {
                showSettingsAvatarFallback();
            })
            .attr('src', url);
    }

    function restoreSettingsAvatarPreview() {
        if (currentAvatarUrl) {
            showSettingsAvatarImage(currentAvatarUrl);
        } else {
            showSettingsAvatarFallback();
        }
    }

    function applySettingsAvatarUploadSuccess(response, successMessage) {
        if (!(response && response.ok && response.url)) {
            return false;
        }

        currentAvatarUrl = $.trim(response.url || '');
        showSettingsAvatarImage(currentAvatarUrl + (currentAvatarUrl.indexOf('?') === -1 ? '?v=' : '&v=') + Date.now());
        setSettingsAvatarStatus(response.message || successMessage, 'success');
        return true;
    }

    function parseSettingsAvatarUploadResponse(input) {
        var raw = '';
        var marker = '__SETTINGS_JSON__';
        var markerIndex = -1;

        if (input && input.responseJSON && typeof input.responseJSON === 'object') {
            return input.responseJSON;
        }

        if (input && typeof input === 'object' && typeof input.responseText === 'string') {
            raw = $.trim(input.responseText);
        } else if (typeof input === 'string') {
            raw = $.trim(input);
        }

        if (!raw) {
            return null;
        }

        markerIndex = raw.indexOf(marker);
        if (markerIndex !== -1) {
            raw = $.trim(raw.slice(markerIndex + marker.length));
        }

        try {
            return JSON.parse(raw);
        } catch (parseError) {
            return null;
        }
    }

    function uploadSettingsAvatar(file, previewUrl) {
        var formData = new FormData();
        var successMessage = $avatarEditor.attr('data-upload-success') || 'Avatar updated.';
        var errorMessage = $avatarEditor.attr('data-upload-error') || 'Unable to upload avatar.';
        var uploadingMessage = $avatarEditor.attr('data-uploading-label') || 'Uploading avatar...';

        avatarUploadInFlight = true;
        $avatarEditor.addClass('is-uploading');
        setSettingsAvatarStatus(uploadingMessage, 'loading');

        formData.append('action', 'upload_avatar');
        formData.append('_csrf', csrfToken);
        formData.append('avatar_file', file);

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'text'
        }).done(function (responseText) {
            var response = parseSettingsAvatarUploadResponse(responseText);

            if (applySettingsAvatarUploadSuccess(response, successMessage)) {
                return;
            }

            restoreSettingsAvatarPreview();
            setSettingsAvatarStatus(response && response.message ? response.message : errorMessage, 'error');
        }).fail(function (xhr) {
            var response = parseSettingsAvatarUploadResponse(xhr);

            if (applySettingsAvatarUploadSuccess(response, successMessage)) {
                return;
            }

            restoreSettingsAvatarPreview();
            setSettingsAvatarStatus(response && response.message ? response.message : errorMessage, 'error');
        }).always(function () {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }

            avatarUploadInFlight = false;
            $avatarEditor.removeClass('is-uploading');
            $avatarFileInput.val('');
        });
    }

    if ($handleInput.length) {
        $handleInput.on('input', function () {
            if (!currentAvatarUrl) {
                showSettingsAvatarFallback();
            }
        });
    }

    if ($avatarEditor.length && $avatarFileInput.length) {
        $avatarTrigger.on('click', function () {
            if (avatarUploadInFlight) {
                return;
            }

            $avatarFileInput.trigger('click');
        });

        $avatarFileInput.on('change', function () {
            var file = this.files && this.files[0] ? this.files[0] : null;
            var allowedTypes = /^image\/(jpeg|png|webp)$/i;
            var invalidTypeMessage = $avatarEditor.attr('data-upload-invalid-type') || 'Upload a JPG, PNG or WEBP file.';
            var tooLargeMessage = $avatarEditor.attr('data-upload-too-large') || 'File is too large.';
            var maxBytes = parseInt($avatarEditor.attr('data-max-bytes') || '5242880', 10);
            var previewUrl = '';

            if (!file) {
                return;
            }

            if (file.type && !allowedTypes.test(file.type)) {
                setSettingsAvatarStatus(invalidTypeMessage, 'error');
                $avatarFileInput.val('');
                return;
            }

            if (maxBytes > 0 && file.size > maxBytes) {
                setSettingsAvatarStatus(tooLargeMessage, 'error');
                $avatarFileInput.val('');
                return;
            }

            previewUrl = URL.createObjectURL(file);
            showSettingsAvatarImage(previewUrl);
            uploadSettingsAvatar(file, previewUrl);
        });
    }

    restoreSettingsAvatarPreview();
});
</script>
