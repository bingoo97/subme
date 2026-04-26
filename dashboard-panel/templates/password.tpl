<div id="page_password" class="auth-shell auth-shell-login container-fluid px-0">
    <div class="row justify-content-center mx-0">
        <div class="col-12 col-sm-11 col-md-10 col-lg-8 col-xl-7 px-0">
            <div class="auth-card auth-card-password center">
                {if $settings.page_logo}
                <div class="auth-logo-wrap auth-logo-wrap-login">
                    <img src="{$settings.page_logo}" class="img-responsive auth-logo auth-logo-login" alt="{$settings.page_name|default:$t.brand_fallback}" />
                </div>
                {/if}

                <p class="auth-page-name">{$settings.page_name|default:$t.brand_fallback}</p>
                <h2 class="auth-title auth-title-login">{$t.password_title}</h2>
                <p class="auth-intro">{$t.password_intro}</p>
                <p class="auth-intro auth-intro-secondary">{$t.password_intro_secondary}</p>

                <form class="auth-form" action="" method="post" autocomplete="on">
                    <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
                    <div class="form-group auth-group">
                        <input id="password-email" type="email" class="form-control auth-input" name="email" value="" placeholder="{$t.password_email_placeholder}" required="required" autocomplete="email">
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-dark btn-lg btn-block auth-submit auth-submit-login">
                        {$t.password_submit}
                    </button>
                </form>

                <a href="/login" class="auth-back auth-back-bottom" title="{$t.back}">
                    <i class="fa fa-chevron-left" aria-hidden="true"></i> {$t.back}
                </a>
            </div>
        </div>
    </div>
</div>
