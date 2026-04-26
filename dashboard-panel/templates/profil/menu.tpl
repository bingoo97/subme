<div id="profil" class="home_buttons_top">
    <div class="col-md-2 col-sm-4 col-xs-2">
        <a href="/settings" title="{$t.menu_settings}">
            <div class="one_box color_3">
                <i class="fa fa-cogs" aria-hidden="true"></i>
                <p class="title">{$t.menu_settings}</p>
            </div>
        </a>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-2">
        <a href="/orders" title="{$t.menu_orders}">
            <div class="one_box color_3">
                <i class="fa fa-tasks" aria-hidden="true"></i>
                <p class="title">{$t.menu_orders}</p>
            </div>
        </a>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-2">
        <a href="/history" title="{$t.menu_history}">
            <div class="one_box color_3">
                <i class="fa fa-history" aria-hidden="true"></i>
                <p class="title">{$t.menu_history}</p>
            </div>
        </a>
    </div>
    {if $settings.apps_page_enabled}
    <div class="col-md-2 col-sm-4 col-xs-2">
        <a href="/apps" title="{$t.menu_apps|default:'Apps'}">
            <div class="one_box color_3">
                <i class="fa fa-mobile" aria-hidden="true"></i>
                <p class="title">{$t.menu_apps|default:'Apps'}</p>
            </div>
        </a>
    </div>
    {/if}
    {if $settings.referrals_enabled}
    <div class="col-md-2 col-sm-4 col-xs-2">
        <a href="/referrals" title="{$t.menu_referrals}">
            <div class="one_box color_3">
                <i class="fa fa-sitemap" aria-hidden="true"></i>
                <p class="title">{$t.menu_referrals}</p>
            </div>
        </a>
    </div>
    {/if}
    <div class="col-md-2 col-sm-4 col-xs-2">
        <a href="/change-password" title="{$t.menu_change_password}">
            <div class="one_box color_3">
                <i class="fa fa-lock" aria-hidden="true"></i>
                <p class="title">{$t.menu_change_password}</p>
            </div>
        </a>
    </div>
    <div class="col-md-2 col-sm-4 col-xs-2">
        <a href="/logout" title="{$t.menu_logout}">
            <div class="one_box color_3">
                <i class="fa fa-sign-out" aria-hidden="true"></i>
                <p class="title yellow">{$t.menu_logout}</p>
            </div>
        </a>
    </div>
</div>
<div class="clr"></div>
