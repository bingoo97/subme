<div class="content-box settings-view">
    <div class="settings-view__header">
        <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.settings_title}</h1>
        <p class="settings-view__intro">{$t.settings_language_help}</p>
    </div>

    <div class="settings-view__grid">
        <div class="settings-card">
            <div class="settings-card__head">
                <h2 class="settings-card__title">{$t.settings_title}</h2>
                <span class="settings-card__badge">{$t.settings_email}</span>
            </div>
            <form action="" method="POST" class="settings-form">
                <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
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
});
</script>
