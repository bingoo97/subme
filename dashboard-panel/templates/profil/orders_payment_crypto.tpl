<div class="content-box">
	{if $settings.active_sale == 1}
		<h5 style="font-size:20px;">
			<a href="order-payment-{$selected.id}"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i> Bitcoin</a> 
			<span class="payment_price right">{$selected.price} {$reseller.currency_symbol}</span>
		</h5>
		<hr/>
		<input type="hidden" id="code" value="{$code}" />
		<div id="payment_content">
			{include file = 'profil/payment_content.tpl'}
		</div>
		{include file = 'profil/orders_payments_faq.tpl'}
	{else}
		<h2><a href="orders"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> Payment</h2>
		<hr/>
		<div class="alert alert-dismissible alert-danger">
			  <i class="fa fa-ban" aria-hidden="true"></i> All payments are disabled.
		</div>
		<a href="orders" class="btn btn-default btn-lg btn-back" title="Close"><i class="fa fa-angle-double-left" aria-hidden="true"></i> Close</a>
	{/if}
</div> 