<div id="page_login" class="auth-shell auth-shell-login container-fluid px-0">
    <div class="row justify-content-center mx-0">
        <div class="col-12 col-sm-11 col-md-10 col-lg-8 col-xl-7 px-0">
            <div class="auth-card auth-card-login center">
                {if $settings.page_logo}
                <div class="auth-logo-wrap auth-logo-wrap-login">
                    <img src="{$settings.page_logo}" class="img-responsive auth-logo auth-logo-login" alt="{$settings.page_name|default:$t.brand_fallback}" />
                </div>
                {/if}

                <p class="auth-page-name">{$settings.page_name|default:$t.brand_fallback}</p>
                <h2 class="auth-title auth-title-login">{$t.login_heading|default:"Sign into your account"}</h2>

                <form class="auth-form" action="" method="post" autocomplete="on">
                    <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
                    <div class="form-group auth-group">
                        <input id="login-email" type="email" class="form-control auth-input" name="email" value="" placeholder="{$t.login_email}" required="required" autocomplete="email">
                    </div>

                    <div class="form-group auth-group auth-group-password">
                        <input id="login-password" type="password" class="form-control auth-input" name="password" value="" maxlength="72" placeholder="{$t.login_password}" required="required" autocomplete="current-password">
                    </div>

                    <div class="auth-meta">
                        <a href="/contact" class="auth-meta-link" title="{$t.contact|default:'Support'}">
                            {$t.contact|default:'Support'}
                        </a>

                        <a href="/password" class="auth-meta-link" title="{$t.login_forgot_password}">
                            {$t.login_forgot_password}?
                        </a>
                    </div>

                    <button type="submit" name="login" class="btn btn-dark btn-lg btn-block auth-submit auth-submit-login">
                        {$t.login_button}
                    </button>
                </form>

                {if $settings.active_register == 1}
                <p class="auth-register-copy">
                    <span class="auth-register-prompt">{$t.login_register_prompt|default:"Don't have an account?"}</span>
                    <a href="/register" class="auth-register-link" title="{$t.login_create_account}">
                        {$t.login_register_here|default:'Register here'}
                    </a>
                </p>
                {/if}

                <a href="/" class="auth-back auth-back-bottom" title="{$t.back}">
                    <i class="fa fa-chevron-left" aria-hidden="true"></i> {$t.back}
                </a>
            </div>
        </div>
    </div>
</div>
