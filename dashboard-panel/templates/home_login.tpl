<div id="homepage" class="home_login">
    {if $settings.page_logo}
    <div class="logo col-md-12 center">
        <a href="/" title="Home">
            <img src="{$settings.page_logo}" class="img-responsive" alt="{$settings.page_name|default:$t.brand_fallback}" />
        </a>
    </div>
    {else}
    <div class="logo col-md-12 center">
        <a href="/" title="Home">
            <img src="/img/logo.svg" class="img-responsive" alt="{$settings.page_name|default:$t.brand_fallback}" />
        </a>
    </div>
    {/if}

    <div class="logo-name col-md-12 center">
        <h1>{$reseller.name|default:$settings.site_name|default:$t.brand_fallback}</h1>
    </div>

    <div class="home_buttons home_login__buttons">
        <div class="col-sm-12">
            <a href="/login" title="{$t.login}">
                <div class="one_box">
                    <i class="fa fa-sign-in" aria-hidden="true"></i>
                    <p class="title">{$t.login}</p>
                </div>
            </a>
        </div>
        {if $settings.active_register == 1}
        <div class="col-sm-12">
            <a href="/register" title="{$t.register}">
                <div class="one_box">
                    <i class="fa fa-id-card-o" aria-hidden="true"></i>
                    <p class="title">{$t.register}</p>
                </div>
            </a>
        </div>
        {/if}
        {if $settings.contact_form_enabled == 1}
        <div class="col-sm-12">
            <a href="/contact" title="{$t.support}">
                <div class="one_box">
                    <i class="fa fa-envelope-o" aria-hidden="true"></i>
                    <p class="title">{$t.support}</p>
                </div>
            </a>
        </div>
        {/if}
    </div>
</div>
{if $homepage_service_overview_enabled|default:false}
<section class="homepage-service-overview" aria-labelledby="homepageServiceOverviewTitle">
    <div class="homepage-service-overview__card">
        <div class="homepage-service-overview__eyebrow">{$t.home_service_overview.eyebrow|escape:'html'}</div>
        <h2 id="homepageServiceOverviewTitle">{$t.home_service_overview.title|escape:'html'}</h2>
        <p class="homepage-service-overview__intro">{$t.home_service_overview.intro|escape:'html'}</p>
        <div class="homepage-service-overview__features">
            {foreach from=$t.home_service_overview.features item=feature}
            <article class="homepage-service-overview__feature"><span class="homepage-service-overview__feature-icon" aria-hidden="true"><i class="fa {$feature.icon|escape:'html'}"></i></span><h3>{$feature.title|escape:'html'}</h3><p>{$feature.text|escape:'html'}</p></article>
            {/foreach}
        </div>
        <div class="homepage-service-overview__steps"><h3>{$t.home_service_overview.steps_title|escape:'html'}</h3><ol>{foreach from=$t.home_service_overview.steps item=step}<li>{$step|escape:'html'}</li>{/foreach}</ol></div>
        <p class="homepage-service-overview__footnote">{$t.home_service_overview.footer|escape:'html'}</p>
    </div>
</section>
{/if}
<div class="clr"></div>
<div id="home-footer">
    <div class="lang">
        <span class="lang-label">{$t.guest_choose_language}:</span>
        <div class="guest-locale-switch">
            {foreach from=$supported_locales item=locale}
                <a href="/?lang={$locale.code}" class="{if $current_locale == $locale.code}active{/if}" title="{$locale.native_label}">
                    {$locale.code|upper}
                </a>
            {/foreach}
        </div>
    </div>
</div>

<div class="ip_content">
    <p class="desc">{$t.guest_language_note}</p>
    <p class="desc">Copyright &copy; 2026. {$t.copyright}</p>
</div>
