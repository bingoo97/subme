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
{include file='profil/group_chat_invites.tpl'}
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
{if $balance_topup_enabled|default:false}
	{include file='profil/balance_topup_modal.tpl'}
{/if}
