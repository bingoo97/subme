{if $payment_redirect_url|default:''}
<script>
window.location.replace('{$payment_redirect_url|escape:'javascript'}');
</script>
{/if}

{if $active_v2_crypto_request}
<div class="content-box payment-wizard">
    <div class="payment-wizard__section">
        {if $payment_test_mode_notice_enabled|default:false}
        <div class="alert alert-warning">
            <strong>{$t.payment_test_mode_notice_title|default:'Payment test mode'}</strong><br />
            {$t.payment_test_mode_notice_text|default:'You can still generate payment requests and test the full flow, but please do not send any money right now. The payment details are displayed for testing only.'}
        </div>
        {/if}
        <div class="payment-wizard__header payment-wizard__header--inline-title">
            <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.topbar_pending_payment|default:'Pending payment'}</h1>
            <p>{$t.payment_crypto_details_intro|default:'Use the assigned wallet details below to complete your payment.'}</p>
        </div>

        <div class="payment-request-card">
            <div class="payment-request-card__layout payment-request-card__layout--crypto">
                <div class="payment-request-qr">
                    {if $active_v2_crypto_request.qr_code_url}
                        <img src="{$active_v2_crypto_request.qr_code_url}" alt="QR code" class="payment-request-qr__image" />
                    {/if}
                </div>

                <div class="payment-request-main">
                    <div class="payment-request-card__head">
                        <img src="{$active_v2_crypto_request.crypto_logo_path}" alt="{$active_v2_crypto_request.crypto_name}" />
                        <div>
                            <strong class="payment-request-crypto-title">
                                <span class="payment-request-crypto-name">{$active_v2_crypto_request.crypto_name}</span>
                                <span class="crypto-ticker-badge">{$active_v2_crypto_request.crypto_code}</span>
                            </strong>
                            <p>{$t.payment_status|default:'Status'}: {$active_v2_crypto_request.status}</p>
                        </div>
                    </div>

                    <div class="payment-request-grid">
                        <div>
                            <span class="payment-request-label">{$t.payment_summary_amount|default:'Amount'}</span>
                            <span class="payment-request-value">
                                <button
                                    type="button"
                                    class="payment-copyable"
                                    data-copy-text="{$active_v2_crypto_request.requested_crypto_amount|escape:'html'}"
                                    data-copy-label="{$t.copied|default:'Copied'}"
                                >
                                    {$active_v2_crypto_request.requested_crypto_amount} {$active_v2_crypto_request.crypto_code}
                                </button>
                                <span class="payment-copy-feedback">{$t.copied|default:'Copied'}</span>
                            </span>
                        </div>
                        <div>
                            <span class="payment-request-label">{$t.payment_fiat_value|default:'Transaction value'}</span>
                            <span class="payment-request-value">{$active_v2_crypto_request.requested_fiat_amount} {$active_v2_crypto_request.currency_symbol|default:$active_v2_crypto_request.currency_code|default:$reseller.currency_symbol}</span>
                        </div>
                        <div>
                            <span class="payment-request-label">{$t.payment_wallet_address|default:'Deposit address'}</span>
                            <span class="payment-request-value payment-request-value--break">
                                <button
                                    type="button"
                                    class="payment-copyable payment-copyable--break"
                                    data-copy-text="{$active_v2_crypto_request.wallet_address|escape:'html'}"
                                    data-copy-label="{$t.copied|default:'Copied'}"
                                >
                                    {$active_v2_crypto_request.wallet_address}
                                </button>
                                <span class="payment-copy-feedback">{$t.copied|default:'Copied'}</span>
                            </span>
                        </div>
                        {if $active_v2_crypto_request.wallet_owner_full_name}
                        <div>
                            <span class="payment-request-label">{$t.payment_wallet_owner|default:'Owner'}</span>
                            <span class="payment-request-value">{$active_v2_crypto_request.wallet_owner_full_name}</span>
                        </div>
                        {/if}
                        {if $active_v2_crypto_request.wallet_network_label}
                        <div>
                            <span class="payment-request-label">{$t.payment_wallet_network|default:'Network'}</span>
                            <span class="payment-request-value">{$active_v2_crypto_request.wallet_network_label}</span>
                        </div>
                        {/if}
                        {if $active_v2_crypto_request.wallet_memo_tag}
                        <div>
                            <span class="payment-request-label">{$t.payment_wallet_memo|default:'Memo / Tag'}</span>
                            <span class="payment-request-value">{$active_v2_crypto_request.wallet_memo_tag}</span>
                        </div>
                        {/if}
                    </div>

                    <div class="alert alert-warning payment-crypto-alert">
                        {$t.payment_crypto_scan_notice|default:'Copy the payment details or scan the QR code in your crypto wallet, then enter the transaction amount shown above. If you don\'t know how to pay, contact us on Live Chat.'}
                    </div>

                    <div class="payment-request-actions">
                        <a href="/instructions" class="btn btn-dark btn-lg" title="{$t.payment_how_to_pay|default:'How to pay'}">
                            <i class="fa fa-question-circle" aria-hidden="true"></i> {$t.payment_how_to_pay|default:'How to pay'}
                        </a>
                        <div
                            class="payment-request-countdown"
                            data-payment-countdown="{$active_v2_crypto_request_remaining_seconds|default:0}"
                            data-countdown-expired-label="{$t.payment_countdown_expired|default:'Payment cancelled'}"
                        >
                            <span class="payment-request-countdown__label">{$t.payment_countdown_label|default:'Time left to pay'}</span>
                            <strong class="payment-request-countdown__value">60:00</strong>
                        </div>
                        <form action="" method="post" class="payment-request-cancel-form">
                            <input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
                            <input type="hidden" name="del_crypto" value="{$active_v2_crypto_request.id}" />
                            <input type="hidden" name="del_crypto_kind" value="v2" />
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="fa fa-spinner spin" aria-hidden="true"></i> {$t.payment_cancel_crypto|default:'Cancel payment'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <a href="/" class="btn btn-default btn-lg btn-back payment-wizard__back" title="{$t.close|default:'Close'}"><i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.close|default:'Close'}</a>
</div>

<script>
$(function () {
    function copyPaymentText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var tempInput = document.createElement('textarea');
            tempInput.value = text;
            tempInput.setAttribute('readonly', '');
            tempInput.style.position = 'absolute';
            tempInput.style.left = '-9999px';
            document.body.appendChild(tempInput);
            tempInput.select();

            try {
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                resolve();
            } catch (error) {
                document.body.removeChild(tempInput);
                reject(error);
            }
        });
    }

    $(document).off('click.paymentCopyTopup', '.payment-copyable');
    $(document).on('click.paymentCopyTopup', '.payment-copyable', function (event) {
        var $button = $(this);
        var $feedback = $button.siblings('.payment-copy-feedback');
        var textToCopy = String($button.data('copyText') || '').trim();
        var copiedLabel = String($button.data('copyLabel') || 'Copied');

        event.preventDefault();

        if (!textToCopy) {
            return false;
        }

        copyPaymentText(textToCopy).then(function () {
            $feedback.text(copiedLabel).addClass('is-visible');
            window.clearTimeout($feedback.data('copyTimer'));
            $feedback.data('copyTimer', window.setTimeout(function () {
                $feedback.removeClass('is-visible');
            }, 1400));
        }).catch(function () {});

        return false;
    });

    $('[data-payment-countdown]').each(function () {
        var element = this;
        var $element = $(element);
        var $value = $element.find('.payment-request-countdown__value');
        var remainingSeconds = parseInt($element.attr('data-payment-countdown'), 10) || 0;
        var expiredLabel = String($element.attr('data-countdown-expired-label') || 'Payment cancelled');
        var hasReloadedAfterExpiry = false;

        function renderCountdown() {
            var minutes = Math.floor(remainingSeconds / 60);
            var seconds = remainingSeconds % 60;
            $value.text(String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0'));
        }

        if (remainingSeconds <= 0) {
            $value.text(expiredLabel);
            return;
        }

        renderCountdown();

        window.setInterval(function () {
            remainingSeconds -= 1;

            if (remainingSeconds <= 0) {
                remainingSeconds = 0;
                $value.text(expiredLabel);

                if (!hasReloadedAfterExpiry) {
                    hasReloadedAfterExpiry = true;
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 900);
                }
                return;
            }

            renderCountdown();
        }, 1000);
    });
});
</script>
{else}
<div class="col-md-12">
	{if !$active_payment}
    <p>Aby zapłacić kryptowalutami <strong class="pink">dodaj płatność.</strong></p>
	{else}
	<h4 class="strong"><i class="fa fa-circle-o-notch spin" style="margin-right: 5px;" aria-hidden="true"></i> Pending...</h4>
	{/if}
	{if $active_payment}
	<hr/>
	<img src="/img/scan.gif" class="img-responsive scan" alt="img" />
	<p>Dokonaj płatności przed upływem czasu inaczej konieczna będzie aktualizacja kursu wymiany.<br/>Zeskanuj QR Code w swoim krypto-porfelu i wpisz kwotę do wysłania aby zrealizować płatność.<br/>Jeśli chcesz zapłacić inną kryptowalutą napisz do nas na Live Chat.</p>
	{/if}
	<hr />
	{if !$crypto_payments}
        <div class="alert alert-dismissible alert-info">
          <i class="fa fa-minus-circle"></i> No payments.
        </div>
    {else}
	<div id="tickets">
		{section name=i loop=$crypto_payments}
		{if $crypto_payments[i].status == 0}
		<div class="row active_ticket">
		  <div class="col-md-8 one_ticket">
			<div class="col-sm-3 col-md-4">
				<img src="../{$crypto_payments[i].qr_url}" class="img-responsive thumbnail" alt="img" />
			</div>
			<div class="col-sm-9 col-md-8 text-left">
				<form action="" method="post" class="payments-crypto-remove-form">
					<input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
					<input type="hidden" name="del_crypto" value="{$crypto_payments[i].id}" />
					<input type="hidden" name="del_crypto_kind" value="{$crypto_payments[i].request_kind|default:'legacy'}" />
					<button type="submit" class="remove" title="Remove">
						<i class="fa fa-times-circle" aria-hidden="true"></i>
					</button>
				</form>
				<p class="title"><img src="../{$crypto_payments[i].crypto_logo_url}" class="img-responsive logo" alt="img" /> {$crypto_payments[i].crypto_name} ({$crypto_payments[i].crypto_symbol})</p>
				<p>Zapłać po aktualnym kursie.</p>
				<p>Po upływie czasu kurs wymiany zostanie ponownie zaktualizowany.</p>
				<span class="date_end {if $crypto_payments[i].status == 0}active{/if}">00:00:00</span>
				<p class="price">Cena zakupu: <span class="disc">{$crypto_payments[i].discount_price} €</span> <span class="package">{$crypto_payments[i].package_price} €</span></p>
				<p class="desc">Wyślij płatność w {$crypto_payments[i].crypto_name}:</p>
				<p class="amount">{$crypto_payments[i].crypto_amount} {$crypto_payments[i].crypto_symbol}</p>
				<div id="div_details_{$crypto_payments[i].id}" class="pay_details">
					<p class="address"><i class="fa fa-qrcode" aria-hidden="true"></i> <strong>{$crypto_payments[i].crypto_name} Address:</strong><br/><span>{$crypto_payments[i].address_code}</span></p>
					<p class="note">{$crypto_payments[i].note}</p>
					<p class="rate"><strong>Rate:</strong> {$crypto_payments[i].crypto_rate_formatted} €</p>
					<p class="text-left yellow">Skopiuj dane do płatnosci albo zeskanuj QR kod w swoim portfelu kryptowalutowym. Płatność po aktualnym kursie jest ważna tylko przez 30 min.</p>
				</div>
			</div>
		  </div>
		</div>
		{else}
		<div class="table_ticket">
			<div class="logo">
				<img src="../{$crypto_payments[i].crypto_logo_url}" class="img-responsive logo" alt="img" />
			</div>
			<div class="content">
				{if $crypto_payments[i].status == 1}
				<form action="" method="post" class="payments-crypto-remove-form">
					<input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
					<input type="hidden" name="del_crypto" value="{$crypto_payments[i].id}" />
					<input type="hidden" name="del_crypto_kind" value="{$crypto_payments[i].request_kind|default:'legacy'}" />
					<button type="submit" class="remove" title="Remove">
						<i class="fa fa-times" aria-hidden="true"></i>
					</button>
				</form>
				{/if}
				<p class="amount"><i class="fa fa-long-arrow-right" aria-hidden="true"></i> {$crypto_payments[i].crypto_amount} {$crypto_payments[i].crypto_symbol} <span>{$crypto_payments[i].discount_price} €</span></p>
				<p class="desc">{if $crypto_payments[i].status == 1}No payment{else}{$crypto_payments[i].date}{/if}</p>
				<p class="status">
					{if $crypto_payments[i].status == 2}
					<span class="success">Paid</span>
					{/if}
					{if $crypto_payments[i].status == 1}
					<span class="danger">Expired</span>
					{/if}
				</p>
			</div>
		</div>
		{/if}
		{/section}
	</div>
    {/if}
	{include file = 'profil/how_crypto_modal.tpl'}
	{include file = 'profil/balance_topup_modal.tpl'}
</div>
{/if}
