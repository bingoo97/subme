<div class="balance">
    <div class="info">
		<p class="amount">{$user.balance_amount|default:'0.00'} {$reseller.currency_symbol|default:$reseller.currency_short}</p>
		<p class="balance-label">{$t.account_balance_label|default:'Account balance'}</p>
		{if $settings.active_sale == 1}
			{if $balance_topup_enabled|default:false}
				<button type="button" class="balance-topup-link" data-toggle="modal" data-target="#balanceTopupModal" title="{$t.top_up|default:'Top up'}">{$t.top_up|default:'Top up'}</button>
			{else}
				<a href="{$balance_topup_action_url|default:'/cryptocurrency'}" title="{$t.top_up|default:'Top up'}">{$t.top_up|default:'Top up'}</a>
			{/if}
		{else}
			<span class="balance-note text-muted">{$t.sales_disabled_notice|default:'Sales are currently unavailable.'}</span>
		{/if}
	</div>
</div>
{include file='alert.tpl'}
<div class="home_buttons">
	<div class="col-md-12">
		<a href="/news" title="{$t.home_welcome_news}">
			<div class="one_box" data-news-home-card>
				<i class="fa fa-file-text-o" aria-hidden="true"></i>
				<p class="title">{$t.home_welcome_news} <span class="btn btn-xs" data-news-home-badge style="display:none;">0</span></p>
			</div>
		</a>
    </div>
	<div class="col-sm-12">
		<a href="/orders" title="{$t.orders}">
			<div class="one_box">
				<i class="fa fa-tasks" aria-hidden="true"></i>
				<p class="title">{$t.orders}</p>
			</div>
		</a>
    </div>
	<div class="col-sm-12">
		<a href="/instructions" title="{$t.instructions|default:'Instructions'}">
			<div class="one_box">
				<i class="fa fa-book" aria-hidden="true"></i>
				<p class="title">{$t.instructions|default:'Instructions'}</p>
			</div>
		</a>
    </div>
	{if $settings.apps_page_enabled}
	<div class="col-sm-12">
		<a href="/apps" title="{$t.menu_apps|default:'Apps'}">
			<div class="one_box">
				<i class="fa fa-television" aria-hidden="true"></i>
				<p class="title">{$t.menu_apps|default:'Apps'}</p>
			</div>
		</a>
    </div>
	{/if}
	<div class="col-sm-12">
		<a href="/history" title="{$t.history}">
			<div class="one_box">
				<i class="fa fa-history" aria-hidden="true"></i>
				<p class="title">{$t.history}</p>
			</div>
		</a>
    </div>
	{if $settings.referrals_enabled}
	<div class="col-sm-12">
		<a href="/referrals" title="{$t.menu_referrals}">
			<div class="one_box">
				<i class="fa fa-sitemap" aria-hidden="true"></i>
				<p class="title">{$t.menu_referrals}</p>
			</div>
		</a>
    </div>
	{/if}
	<div class="col-sm-12">
		<a href="/settings" title="{$t.settings}">
			<div class="one_box">
				<i class="fa fa-cog" aria-hidden="true"></i>
				<p class="title">{$t.settings}</p>
			</div>
		</a>
    </div>
	<div class="col-sm-12">
		<a href="/logout" title="{$t.logout}">
			<div class="one_box logout">
				<i class="fa fa-unlock-alt" aria-hidden="true"></i>
				<p class="title">{$t.logout}</p>
			</div>
		</a>
	</div>
</div>
{if $user.logged && $settings.customer_type_switch_enabled}
    <div class="home-role-switch">
        <div class="alert alert-dismissible alert-info">
            <button type="button" class="close" data-dismiss="alert">x</button>
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            {$t.customer_type_switch_info|default:'This is a test switch for your account view. OFF shows the regular client mode, while ON switches your account into reseller mode so you can preview reseller-only sections.'}
        </div>
        <form action="/" method="post" class="home-role-switch__form">
            <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
            <input type="hidden" name="customer_type_switch_submit" value="1">
            <div class="home-role-switch__card">
                <div class="home-role-switch__copy">
                    <strong>{$t.customer_type_switch_title|default:'Client / reseller test mode'}</strong>
                    <p>{$t.customer_type_switch_current|default:'Current mode'}: <span class="home-role-switch__current">{if $user.customer_type|default:'client' eq 'reseller'}{$t.customer_type_switch_mode_reseller|default:'Reseller'}{else}{$t.customer_type_switch_mode_client|default:'Client'}{/if}</span></p>
                    <div class="home-role-switch__meta">
                        <span>{$t.customer_type_switch_off_label|default:'OFF = Client'}</span>
                        <span>{$t.customer_type_switch_on_label|default:'ON = Reseller'}</span>
                    </div>
                </div>
                <label class="home-role-switch__control" for="customer_type_mode">
                    <span class="sr-only">{$t.customer_type_switch_title|default:'Client / reseller test mode'}</span>
                    <input type="checkbox" id="customer_type_mode" name="customer_type_mode" value="reseller"{if $user.customer_type|default:'client' eq 'reseller'} checked{/if}>
                    <span class="home-role-switch__toggle" aria-hidden="true">
                        <span class="home-role-switch__toggle-text home-role-switch__toggle-text--off">OFF</span>
                        <span class="home-role-switch__toggle-track">
                            <span class="home-role-switch__toggle-thumb"></span>
                        </span>
                        <span class="home-role-switch__toggle-text home-role-switch__toggle-text--on">ON</span>
                    </span>
                </label>
            </div>
        </form>
        <script>
            (function () {
                var form = document.querySelector('.home-role-switch__form');
                var toggle = document.getElementById('customer_type_mode');
                if (!form || !toggle) {
                    return;
                }

                toggle.addEventListener('change', function () {
                    form.submit();
                });
            })();
        </script>
    </div>
{/if}
{if $balance_topup_enabled|default:false}
	{include file='profil/balance_topup_modal.tpl'}
{/if}
