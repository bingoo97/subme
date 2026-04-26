		<div class="select">
			<div class="qr-hold">
                <img src="{$payment.qr_code}" class="img-responsive qrcode" alt="QR Code">
            </div>
			<input type="text" id="input-price" value="{$payment.btc_price}" />
			<p id="copy_alert_2" class="alert-copy" style="display:none;"><i class="fa fa-check green" aria-hidden="true"></i> Copied.</p>
			<p><span class="btc"><span id="price_btc" class="price_btc" onclick="copy_price();">{$payment.btc_price}</span> BTC</span> </p>
			{if $payment.status == 2}
			<p class="detect">Payment confirmed</p>
			{else}
			<p>Please send <img src="{$crypto.logo_url}" class="img-responsive icon_crypto" alt="" /> Bitcoin to address: </p> 
			{/if}
			{if $payment.status <> 2}
			<input type="text" id="copy_address" class="add" value="{$payment.btc_address}" />
			<p id="address" onclick="copy_address();">{$payment.btc_address}</p/>
			<p id="copy_alert" class="alert-copy" style="display:none;"><i class="fa fa-check green" aria-hidden="true"></i> Address copied.</p>
			{/if}
			
			<div id="status">
				<p>{if $payment.status <> 2}<i class="fa fa-circle-o-notch rotating" aria-hidden="true"></i> {/if}Status {$status}</p> 
				{$info}  
				<div id="info"></div>
			</div>
		</div>