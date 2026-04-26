<div id="page_register" class="auth-shell auth-shell-login container-fluid px-0">
    <div class="row justify-content-center mx-0">
        <div class="col-12 col-sm-11 col-md-10 col-lg-8 col-xl-7 px-0">
            <div class="auth-card auth-card-register center">
                {if $settings.page_logo}
                <div class="auth-logo-wrap auth-logo-wrap-login">
                    <img src="{$settings.page_logo}" class="img-responsive auth-logo auth-logo-login" alt="{$settings.page_name|default:$t.brand_fallback}" />
                </div>
                {/if}

                <p class="auth-page-name">{$settings.page_name|default:$t.brand_fallback}</p>
                <h2 class="auth-title auth-title-login">{$t.register_title}</h2>

                <form class="auth-form" action="" method="post" autocomplete="on">
                    <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
                    <div class="form-group auth-group">
                        <input id="register-email" type="email" class="form-control auth-input" name="email" value="" placeholder="{$t.register_email}" required="required" autocomplete="email" />
                    </div>

                    <div class="form-group auth-group">
                        <input id="register-password" type="password" class="form-control auth-input" name="password" value="" placeholder="{$t.register_password}" required="required" autocomplete="new-password" />
                    </div>

                    <div class="form-group auth-group">
                        <input id="register-password-repeat" type="password" class="form-control auth-input" name="password_repeat" value="" placeholder="{$t.register_password_repeat}" required="required" autocomplete="new-password" />
                    </div>

                    {if $referrals_enabled}
                    <div class="form-group auth-group">
                        <input id="register-referral" type="email" class="form-control auth-input" name="referral_email" {if $ref}value="{$ref.email}" readonly{/if} placeholder="{$t.register_referral}" />
                    </div>
                    {/if}

                    <div id="captcha" class="auth-captcha">
                        <div class="auth-captcha-image">
                            {$captcha}
                        </div>
                        <div class="form-group auth-group">
                            <input type="text" class="form-control auth-input auth-input-captcha" name="captcha" maxlength="4" placeholder="{$t.register_captcha}" inputmode="numeric" autocomplete="off">
                        </div>
                        <a href="/register" class="auth-link auth-link-small" title="{$t.register_refresh_captcha}">
                            {$t.register_refresh_captcha}
                        </a>
                    </div>

                    <button type="submit" name="register" class="btn btn-dark btn-lg btn-block auth-submit auth-submit-login">
                        {$t.create_account}
                    </button>
                </form>

                <p class="auth-register-copy">
                    <span class="auth-register-prompt">{$t.login_register_prompt|default:"Already have an account?"}</span>
                    <a href="/login" class="auth-register-link" title="{$t.login_button}">
                        {$t.login_button|default:'Log in'}
                    </a>
                </p>

                <a href="/" class="auth-back auth-back-bottom" title="{$t.back}">
                    <i class="fa fa-chevron-left" aria-hidden="true"></i> {$t.back}
                </a>
            </div>
        </div>
    </div>
</div>
