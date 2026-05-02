<div class="content-box payment-wizard">
{include file='alert.tpl'}
{if $payment_test_mode_notice_enabled|default:false}
<div class="alert alert-warning">
    <strong>{$t.payment_test_mode_notice_title|default:'Payment test mode'}</strong><br />
    {$t.payment_test_mode_notice_text|default:'You can still generate payment requests and test the full flow, but please do not send any money right now. The payment details are displayed for testing only.'}
</div>
{/if}
{if $payment_redirect_url|default:''}
<script>
window.location.replace('{$payment_redirect_url|escape:'javascript'}');
</script>
{/if}
{if $selected}
    {if $payment_active_request_method eq ''}
    <div class="payment-wizard__header">
        <h2><a href="orders"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.payment_title|default:'Payment'}</h2>
        <p>{$t.payment_intro|default:'Choose a payment method for this subscription.'}</p>
    </div>

    <div class="payment-wizard__summary-table-wrap">
        <div class="payment-summary-bar">
            <div class="payment-summary-item">
                <span class="payment-summary-item__label">{$t.payment_summary_order|default:'Order'}</span>
                <span class="payment-summary-item__value">#{$selected.id}</span>
            </div>
            <div class="payment-summary-item payment-summary-item--subscription">
                <span class="payment-summary-item__label">{$t.payment_summary_subscription|default:'Subscription'}</span>
                <span class="payment-summary-item__value">
                    {if $selected.provider_name}<span class="provider">{$selected.provider_name}</span> {/if}{$selected.name}
                </span>
            </div>
            <div class="payment-summary-item">
                <span class="payment-summary-item__label">{$t.payment_summary_amount|default:'Amount'}</span>
                <span class="payment-summary-item__value">{$selected.price} {$selected.currency_symbol|default:$selected.currency_code|default:$reseller.currency_symbol}</span>
            </div>
        </div>
    </div>

    {/if}

    {if $payment_can_request && $payment_active_request_method eq ''}
        <div class="payment-wizard__section">
            <div class="payment-wizard__section-head">
                <h3>{$t.payment_choose_method_title|default:'Choose payment method'}</h3>
                <p>{$t.payment_choose_method_intro|default:'Available methods depend on the active payment data configured by the admin.'}</p>
            </div>

            {if $payment_pending_topup_request}
                <div class="alert alert-warning payment-support-alert">
                    <strong>{$t.payment_pending_topup_block_title|default:'You already have a pending balance top-up payment.'}</strong><br />
                    {$t.payment_pending_topup_block_text|default:'Finish or cancel the pending balance top-up payment before creating a new payment for this order.'}
                </div>
                <div class="payment-request-actions">
                    <a href="{$payment_pending_topup_request.payment_url|default:'/cryptocurrency'}" class="btn btn-danger btn-lg">
                        <i class="fa fa-search" aria-hidden="true"></i> {$t.payment_pending_topup_block_button|default:'Go to top-up payment'}
                    </a>
                </div>
            {elseif $payment_can_use_crypto || $payment_can_use_bank || $payment_can_use_balance}
                <div class="payment-method-start{if $payment_selected_method neq ''} is-hidden{/if}" data-payment-start>
                    <div class="payment-method-start__buttons">
                    {if $payment_can_use_crypto}
                        <button type="button" class="payment-method-start__button" data-payment-target="crypto">
                            <i class="fa fa-btc" aria-hidden="true"></i> {$t.payment_method_crypto|default:'Pay with crypto'}
                        </button>
                    {/if}
                    {if $payment_can_use_bank}
                        <button type="button" class="payment-method-start__button" data-payment-target="bank">
                            <i class="fa fa-university" aria-hidden="true"></i> {$t.payment_method_bank|default:'Pay by bank transfer'}
                        </button>
                    {/if}
                    {if $payment_can_use_balance}
                        <button type="button" class="payment-method-start__button payment-method-start__button--balance" data-payment-target="balance">
                            <i class="fa fa-credit-card" aria-hidden="true"></i> {$t.payment_method_balance|default:'Pay with balance'}
                        </button>
                    {/if}
                    </div>
                    <div class="payment-method-hero">
                        <img src="/img/package.jpg" alt="Payment package" class="payment-method-hero__image" />
                    </div>
                </div>

                {if $payment_can_use_crypto}
                    <div class="payment-method-panel{if $payment_selected_method eq 'crypto'} is-active{/if}" data-payment-panel="crypto">
                        <div class="payment-method-panel__toolbar">
                            <button type="button" class="btn btn-default payment-method-back" data-payment-back>
                                <i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.back|default:'Back'}
                            </button>
                        </div>
                        <form action="" method="post">
                            <input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
                            <input type="hidden" name="id" value="{$selected.id}" />
                            <input type="hidden" name="payment" value="{$selected.id}" />
                            <input type="hidden" name="payment_method" value="crypto" />

                            <div class="payment-step">
                                <div class="payment-step__head">
                                    <span class="payment-step__label">{$t.payment_step|default:'Step'} 1</span>
                                    <h4>{$t.payment_choose_crypto|default:'Choose cryptocurrency'}</h4>
                                </div>
                                {if $payment_has_crypto_assignments}
                                    <div class="crypto-choice-grid{if $payment_crypto_assets|@count == 1} crypto-choice-grid--single{/if}">
                                        {section name=i loop=$payment_crypto_assets}
                                            <label class="crypto-choice-card">
                                                <input type="radio" name="crypto_wallet_assignment_id" value="{$payment_crypto_assets[i].id}" {if $smarty.section.i.first}checked{/if}>
                                                <span class="crypto-choice-card__inner">
                                                    <span class="crypto-choice-card__logo">
                                                        <img src="{$payment_crypto_assets[i].logo_path}" alt="{$payment_crypto_assets[i].name}" />
                                                    </span>
                                                    <span class="crypto-choice-card__name">{$payment_crypto_assets[i].name}</span>
                                                    {if $payment_crypto_assets[i].network_label}
                                                        <span class="crypto-choice-card__network">{$payment_crypto_assets[i].code} • {$payment_crypto_assets[i].network_label}</span>
                                                    {else}
                                                        <span class="crypto-choice-card__network">{$payment_crypto_assets[i].code}</span>
                                                    {/if}
                                                </span>
                                            </label>
                                        {/section}
                                    </div>
                                {else}
                                    <div class="alert alert-warning payment-support-alert">
                                        {$t.payment_no_crypto_wallet|default:'No crypto wallet is currently available for this order. Contact support to activate crypto payment.'}
                                    </div>
                                    {if $settings.support_chat_enabled == 1}
                                        <button type="button" class="btn btn-default btn-lg payment-support-button" onclick="return openMessengerPanel('{$user.id}');">
                                            <i class="fa fa-life-ring" aria-hidden="true"></i> {$t.instructions_contact_support|default:'Contact support'}
                                        </button>
                                    {/if}
                                {/if}
                            </div>

                            <div class="payment-step{if !$payment_has_crypto_assignments} payment-step--disabled{/if}">
                                <div class="payment-step__head">
                                    <span class="payment-step__label">{$t.payment_step|default:'Step'} 2</span>
                                    <h4>{$t.payment_choose_package|default:'Choose package'}</h4>
                                </div>
                                <div class="package-choice-list">
                                    {section name=i loop=$payment_products}
                                        <label class="package-choice-chip">
                                            <input type="radio" name="payment_product_id" value="{$payment_products[i].id}" {if $payment_products[i].id == $payment_selected_product_id}checked{/if}>
                                            <span>{$payment_products[i].payment_label}</span>
                                        </label>
                                    {/section}
                                </div>
                            </div>

                            <div class="payment-step__hint">
                                <p>{$t.payment_action_crypto_hint_line_1|default:'Sprawdź jeszcze raz wybraną kryptowalutę i pakiet przed utworzeniem płatności.'}</p>
                                <p>{$t.payment_action_crypto_hint_line_2|default:'Po kliknięciu pokażemy gotowe dane do wpłaty przypisane do Twojego zamówienia.'}</p>
                            </div>

                            <div class="payment-step__actions">
                                <button type="submit" class="btn btn-success btn-lg" name="create_crypto_payment" {if !$payment_has_crypto_assignments}disabled="disabled"{/if}>
                                    {$t.payment_create_crypto|default:'Create crypto payment'} <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                {/if}

                {if $payment_can_use_bank}
                    <div class="payment-method-panel{if $payment_selected_method eq 'bank_transfer'} is-active{/if}" data-payment-panel="bank">
                        <div class="payment-method-panel__toolbar">
                            <button type="button" class="btn btn-default payment-method-back" data-payment-back>
                                <i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.back|default:'Back'}
                            </button>
                        </div>
                        <form action="" method="post">
                            <input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
                            <input type="hidden" name="id" value="{$selected.id}" />
                            <input type="hidden" name="payment" value="{$selected.id}" />
                            <input type="hidden" name="payment_method" value="bank_transfer" />

                            <div class="payment-step">
                                <div class="payment-step__head">
                                    <span class="payment-step__label">{$t.payment_step|default:'Step'} 1</span>
                                    <h4>{$t.payment_choose_bank_account|default:'Choose bank account'}</h4>
                                </div>
                                <div class="bank-choice-list">
                                    {section name=i loop=$payment_bank_accounts}
                                        <label class="bank-choice-card">
                                            <input type="radio" name="bank_account_assignment_id" value="{$payment_bank_accounts[i].bank_account_assignment_id}" {if $smarty.section.i.first}checked{/if}>
                                            <span class="bank-choice-card__inner">
                                                <strong>{$payment_bank_accounts[i].bank_name}</strong>
                                                <span>{if $payment_bank_accounts[i].iban}{$payment_bank_accounts[i].iban}{else}{$payment_bank_accounts[i].account_number}{/if}</span>
                                            </span>
                                        </label>
                                    {/section}
                                </div>
                            </div>

                            <div class="payment-step">
                                <div class="payment-step__head">
                                    <span class="payment-step__label">{$t.payment_step|default:'Step'} 2</span>
                                    <h4>{$t.payment_choose_package|default:'Choose package'}</h4>
                                </div>
                                <div class="package-choice-list">
                                    {section name=i loop=$payment_products}
                                        <label class="package-choice-chip">
                                            <input type="radio" name="payment_product_id" value="{$payment_products[i].id}" {if $payment_products[i].id == $payment_selected_product_id}checked{/if}>
                                            <span>{$payment_products[i].payment_label}</span>
                                        </label>
                                    {/section}
                                </div>
                            </div>

                            <div class="payment-step__hint">
                                <p>{$t.payment_action_bank_hint_line_1|default:'Sprawdź jeszcze raz wybrane konto bankowe i pakiet przed utworzeniem płatności.'}</p>
                                <p>{$t.payment_action_bank_hint_line_2|default:'Po kliknięciu pokażemy komplet danych do przelewu przypisanych do Twojego zamówienia.'}</p>
                            </div>

                            <div class="payment-step__actions">
                                <button type="submit" class="btn btn-dark btn-lg" name="create_bank_payment">
                                    {$t.payment_create_bank|default:'Create bank transfer'}
                                </button>
                            </div>
                        </form>
                    </div>
                {/if}

                {if $payment_can_use_balance}
                    <div class="payment-method-panel{if $payment_selected_method eq 'balance'} is-active{/if}" data-payment-panel="balance">
                        <div class="payment-method-panel__toolbar">
                            <button type="button" class="btn btn-default payment-method-back" data-payment-back>
                                <i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.back|default:'Back'}
                            </button>
                        </div>
                        <form action="" method="post">
                            <input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
                            <input type="hidden" name="id" value="{$selected.id}" />
                            <input type="hidden" name="payment" value="{$selected.id}" />
                            <input type="hidden" name="payment_method" value="balance" />

                            <div class="payment-step">
                                <div class="payment-step__head">
                                    <span class="payment-step__label">{$t.payment_step|default:'Step'} 1</span>
                                    <h4>{$t.payment_choose_package|default:'Choose package'}</h4>
                                </div>
                                <div class="package-choice-list">
                                    {section name=i loop=$payment_products}
                                        <label class="package-choice-chip">
                                            <input type="radio" name="payment_product_id" value="{$payment_products[i].id}" {if $payment_products[i].id == $payment_selected_product_id}checked{/if}>
                                            <span>{$payment_products[i].payment_label}</span>
                                        </label>
                                    {/section}
                                </div>
                            </div>

                            <div class="payment-step__hint">
                                <p>{$t.payment_balance_hint_line_1|default:'Your account balance will be used immediately after confirming this payment.'}</p>
                                <p>{$t.payment_balance_hint_line_2|default:'After payment, the order will wait for activation by the admin.'}</p>
                                <p class="payment-balance-available">{$t.payment_balance_available|default:'Available balance'}: <strong>{$payment_customer_balance_amount} {$selected.currency_symbol|default:$selected.currency_code|default:$reseller.currency_symbol}</strong></p>
                            </div>

                            <div class="payment-step__actions">
                                <button type="submit" class="btn btn-danger btn-lg" name="create_balance_payment">
                                    {$t.payment_create_balance|default:'Pay from balance'}
                                </button>
                            </div>
                        </form>
                    </div>
                {/if}
            {else}
                <div class="alert alert-warning">
                    {$t.payment_no_method_available|default:'No payment method is currently available for this account. Contact support to activate a crypto wallet or bank account.'}
                </div>
            {/if}
        </div>
    {/if}

    {if $payment_active_request_method eq '' && $payment_state_notice|default:'' neq ''}
        <div class="payment-wizard__section">
            <div class="payment-request-card">
                <div class="alert {if $payment_state_notice eq 'paid_pending_activation'}alert-success{elseif $payment_state_notice eq 'already_paid'}alert-info{else}alert-warning{/if}">
                    {if $payment_state_notice eq 'paid_pending_activation'}
                        <strong>{$t.payment_paid_pending_activation_title|default:'Payment confirmed.'}</strong><br />
                        {$t.payment_paid_pending_activation_text|default:'Your payment has already been marked as paid. The subscription is now waiting for activation by the admin. You do not need to generate a new payment request.'}
                    {elseif $payment_state_notice eq 'already_paid'}
                        <strong>{$t.payment_paid_already_title|default:'This order is already paid.'}</strong><br />
                        {$t.payment_paid_already_text|default:'A new payment request is not needed for this order. If you need more details, open your orders list or contact support.'}
                    {else}
                        <strong>{$t.payment_unavailable_title|default:'Payment is not available for this order.'}</strong><br />
                        {$t.payment_unavailable_text|default:'There is no active payment request for this order right now. If you need help, contact support.'}
                    {/if}
                </div>
                <div class="payment-request-actions">
                    <a href="/orders" class="btn btn-dark btn-lg">
                        <i class="fa fa-list-alt" aria-hidden="true"></i> {$t.orders_actions|default:'Orders'}
                    </a>
                    {if $settings.support_chat_enabled == 1}
                        <button type="button" class="btn btn-default btn-lg payment-support-button" onclick="return openMessengerPanel('{$user.id}');">
                            <i class="fa fa-life-ring" aria-hidden="true"></i> {$t.instructions_contact_support|default:'Contact support'}
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    {if $payment_active_request_method eq 'crypto' && $payment_crypto_request}
        <div class="payment-wizard__section">
            <div class="payment-wizard__header payment-wizard__header--inline-title">
                <h1><a href="orders"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.payment_crypto_details_title|default:'Pending payment'} <i class="fa fa-circle-o-notch fa-spin pull-right" style="margin-top: 5px;" aria-hidden="true"></i></h1>
                <p>{$t.payment_crypto_details_intro|default:'Use the assigned wallet details below to complete your payment.'}</p>
            </div>
            <div class="payment-request-card">
                <div class="payment-request-card__layout payment-request-card__layout--crypto">
                    <div class="payment-request-qr">
                        {if $payment_crypto_request.qr_code_url}
                            <img src="{$payment_crypto_request.qr_code_url}" alt="QR code" class="payment-request-qr__image" />
                        {/if}
                    </div>
                    <div class="payment-request-main">
                        <div class="payment-request-card__head">
                            <img src="{$payment_crypto_request.crypto_logo_path}" alt="{$payment_crypto_request.crypto_name}" />
                            <div>
                                <strong class="payment-request-crypto-title">
                                    <span class="payment-request-crypto-name">{$payment_crypto_request.crypto_name}</span>
                                    <span class="crypto-ticker-badge">{$payment_crypto_request.crypto_code}</span>
                                </strong>
                                <p>{$t.payment_status|default:'Status'}: {$payment_crypto_request.status}</p>
                            </div>
                        </div>
                        <div class="payment-request-grid">
                            {if $payment_crypto_request.wallet_network_label}
                            <div>
                                <span class="payment-request-label">{$t.payment_wallet_network|default:'Network'}</span>
                                <span class="payment-request-value">{$payment_crypto_request.wallet_network_label}</span>
                            </div>
                            {/if}
                            <div>
                                <span class="payment-request-label">{$t.payment_summary_amount|default:'Amount'}</span>
                                <span class="payment-request-value">
                                    <button
                                        type="button"
                                        class="payment-copyable"
                                        data-copy-text="{$payment_crypto_request.requested_crypto_amount|escape:'html'}"
                                        data-copy-label="{$t.copied|default:'Copied'}"
                                    >
                                        {$payment_crypto_request.requested_crypto_amount} {$payment_crypto_request.crypto_code}
                                    </button>
                                    <span class="payment-copy-feedback">{$t.copied|default:'Copied'}</span>
                                </span>
                            </div>
                            <div>
                                <span class="payment-request-label">{$t.payment_fiat_value|default:'Fiat value'}</span>
                                <span class="payment-request-value">{$payment_crypto_request.requested_fiat_amount} {$selected.currency_symbol|default:$selected.currency_code|default:$reseller.currency_symbol}</span>
                            </div>
                            <div>
                                <span class="payment-request-label">{$t.payment_wallet_address|default:'Address'}</span>
                                <span class="payment-request-value payment-request-value--break">
                                    <button
                                        type="button"
                                        class="payment-copyable payment-copyable--break"
                                        data-copy-text="{$payment_crypto_request.wallet_address|escape:'html'}"
                                        data-copy-label="{$t.copied|default:'Copied'}"
                                    >
                                        {$payment_crypto_request.wallet_address}
                                    </button>
                                    <span class="payment-copy-feedback">{$t.copied|default:'Copied'}</span>
                                </span>
                            </div>
                            {if $payment_crypto_request.wallet_owner_full_name}
                            <div>
                                <span class="payment-request-label">{$t.payment_wallet_owner|default:'Owner'}</span>
                                <span class="payment-request-value">{$payment_crypto_request.wallet_owner_full_name}</span>
                            </div>
                            {/if}
                            
                            {if $payment_crypto_request.wallet_memo_tag}
                            <div>
                                <span class="payment-request-label">{$t.payment_wallet_memo|default:'Memo / Tag'}</span>
                                <span class="payment-request-value">{$payment_crypto_request.wallet_memo_tag}</span>
                            </div>
                            {/if}
                        </div>
                        <div class="alert alert-warning payment-crypto-alert">
                            {$t.payment_crypto_scan_notice|default:'Copy the payment details or scan the QR code in your crypto wallet, then enter the exact transaction amount shown above. If you send a different amount, your payment will not be approved. Check the transaction fee in the transaction summary. To avoid high fees, contact us on Live Chat and switch to DOGE or CRO payments.'}
                        </div>
                        <div class="payment-request-actions">
                            <a href="/instructions" class="btn btn-dark btn-lg" title="{$t.payment_how_to_pay|default:'How to pay'}">
                                <i class="fa fa-question-circle" aria-hidden="true"></i> {$t.payment_how_to_pay|default:'How to pay'}
                            </a>
                            <div
                                class="payment-request-countdown"
                                data-payment-countdown="{$payment_active_request_remaining_seconds|default:0}"
                                data-countdown-expired-label="{$t.payment_countdown_expired|default:'Payment cancelled'}"
                            >
                                <span class="payment-request-countdown__label">{$t.payment_countdown_label|default:'Time left to pay'}</span>
                                <strong class="payment-request-countdown__value">60:00</strong>
                            </div>
                            <form action="" method="post" class="payment-request-cancel-form">
                                <input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
                                <input type="hidden" name="id" value="{$selected.id}" />
                                <button type="submit" class="btn btn-danger btn-lg" name="cancel_crypto_payment">
                                    <i class="fa fa-spinner spin" aria-hidden="true"></i> {$t.payment_cancel_crypto|default:'Cancel payment'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {/if}

    {if $payment_active_request_method eq 'bank_transfer' && $payment_bank_request}
        <div class="payment-wizard__section">
            <div class="payment-wizard__section-head">
                <h3>{$t.payment_bank_details_title|default:'Bank transfer details'}</h3>
                <p>{$t.payment_bank_details_intro|default:'Use the assigned account details shown below.'}</p>
            </div>
            <div class="payment-request-card">
                <div class="payment-request-grid">
                    <div>
                        <span class="payment-request-label">{$t.payment_bank_account_holder|default:'Account holder'}</span>
                        <span class="payment-request-value">{$payment_bank_request.account_holder_name}</span>
                    </div>
                    <div>
                        <span class="payment-request-label">{$t.payment_bank_name|default:'Bank'}</span>
                        <span class="payment-request-value">{$payment_bank_request.bank_name}</span>
                    </div>
                    <div>
                        <span class="payment-request-label">IBAN</span>
                        <span class="payment-request-value">{$payment_bank_request.iban}</span>
                    </div>
                    <div>
                        <span class="payment-request-label">SWIFT / BIC</span>
                        <span class="payment-request-value">{$payment_bank_request.swift_bic}</span>
                    </div>
                    <div>
                        <span class="payment-request-label">{$t.payment_summary_amount|default:'Amount'}</span>
                        <span class="payment-request-value">{$payment_bank_request.requested_amount} {$payment_bank_request.currency_code}</span>
                    </div>
                    {if $payment_bank_request.transfer_instructions}
                    <div class="payment-request-full">
                        <span class="payment-request-label">{$t.instructions|default:'Instructions'}</span>
                        <span class="payment-request-value">{$payment_bank_request.transfer_instructions}</span>
                    </div>
                    {/if}
                </div>
                <div class="payment-request-actions">
                    <div
                        class="payment-request-countdown"
                        data-payment-countdown="{$payment_active_request_remaining_seconds|default:0}"
                        data-countdown-expired-label="{$t.payment_countdown_expired|default:'Payment cancelled'}"
                    >
                        <span class="payment-request-countdown__label">{$t.payment_countdown_label|default:'Time left to pay'}</span>
                        <strong class="payment-request-countdown__value">60:00</strong>
                    </div>
                    <form action="" method="post" class="payment-request-cancel-form">
                        <input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
                        <input type="hidden" name="id" value="{$selected.id}" />
                        <button type="submit" class="btn btn-danger btn-lg" name="cancel_bank_payment">
                            <i class="fa fa-spinner spin" aria-hidden="true"></i> {$t.payment_cancel_crypto|default:'Cancel payment'}
                        </button>
                    </form>
                    <div class="alert alert-warning payment-support-alert">
                        {$t.instructions_transfer_confirmation_note|default:'Send your transfer confirmation to the support email address below:'}
                        <strong>{$settings.admin_email|default:$settings.smtp_login}</strong>
                    </div>
                    {if $settings.support_chat_enabled == 1}
                        <button type="button" class="btn btn-danger btn-lg payment-support-button" onclick="return openMessengerPanel('{$user.id}');">
                            <i class="fa fa-life-ring" aria-hidden="true"></i> {$t.instructions_contact_support|default:'Contact support'}
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    <a href="orders" class="btn btn-default btn-lg btn-back payment-wizard__back" title="{$t.close|default:'Close'}"><i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.close|default:'Close'}</a>
{else}
    <h2>{$t.no_access_title|default:'No access!'}</h2>
    <p>{$t.no_access_text|default:'Please return to the previous page or try again.'}</p>
    <hr/>
    <div class="alert alert-dismissible alert-danger">
        <button type="button" class="close" data-dismiss="alert">x</button>
        <i class="fa fa-ban" aria-hidden="true"></i> {$t.no_access_alert|default:'Sorry, no access.'}
    </div>
    <hr />
    <a href="orders" class="btn btn-default btn-lg" title="{$t.close|default:'Close'}"><i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.close|default:'Close'}</a>
{/if}
</div>
<script>
$(function () {
    if (typeof window.openMessengerPanel !== 'function') {
        window.openMessengerPanel = function (userId) {
            var $widget = $('#messanger');
            var $panel = $('#collapseOne');
            var $toggle = $('#panel-heading');
            var $icon = $toggle.find('.messenger-toggle-icon');

            if (!$widget.length || !$panel.length) {
                return false;
            }

            if (!$widget.hasClass('is-open')) {
                $widget.addClass('is-open');
                $toggle.attr('aria-expanded', 'true');
                $panel.attr('aria-hidden', 'false').addClass('in is-visible').stop(true, true).slideDown(180, function () {
                    var chatScroll = document.getElementById('chat_scroll');
                    if (chatScroll) {
                        chatScroll.scrollTop = chatScroll.scrollHeight;
                    }
                });
                $icon.removeClass('fa-angle-down').addClass('fa-angle-up');

                if (typeof check_chat_read === 'function') {
                    try {
                        check_chat_read(userId);
                    } catch (error) {}
                }
            } else {
                var chatScroll = document.getElementById('chat_scroll');
                if (chatScroll) {
                    chatScroll.scrollTop = chatScroll.scrollHeight;
                }
            }

            return false;
        };
    }

    function showPaymentPanel(target) {
        $('[data-payment-start]').addClass('is-hidden');
        $('[data-payment-panel]').removeClass('is-active');
        $('[data-payment-panel="' + target + '"]').addClass('is-active');
    }

    function showPaymentStart() {
        $('[data-payment-panel]').removeClass('is-active');
        $('[data-payment-start]').removeClass('is-hidden');
    }

    var countdownRefreshKey = 'payment-request-expired:' + window.location.pathname;

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

    $(document).on('click', '.payment-copyable', function (event) {
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

    $(document).off('click.paymentSwitch', '[data-payment-target]');
    $(document).on('click.paymentSwitch', '[data-payment-target]', function () {
        var target = $(this).data('payment-target');
        showPaymentPanel(target);
    });

    $(document).off('click.paymentBack', '[data-payment-back]');
    $(document).on('click.paymentBack', '[data-payment-back]', function () {
        showPaymentStart();
    });

    $('[data-payment-countdown]').each(function () {
        var element = this;
        var $element = $(element);
        var $value = $element.find('.payment-request-countdown__value');
        var remainingSeconds = parseInt($element.attr('data-payment-countdown'), 10) || 0;
        var expiredLabel = String($element.attr('data-countdown-expired-label') || 'Payment cancelled');
        var hasRefreshedAfterExpiry = false;

        try {
            hasRefreshedAfterExpiry = window.sessionStorage.getItem(countdownRefreshKey) === '1';
        } catch (error) {}

        function renderCountdown() {
            var minutes = Math.floor(remainingSeconds / 60);
            var seconds = remainingSeconds % 60;
            $value.text(
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0')
            );
        }

        function refreshAfterExpiryOnce() {
            if (hasRefreshedAfterExpiry) {
                return;
            }

            hasRefreshedAfterExpiry = true;
            try {
                window.sessionStorage.setItem(countdownRefreshKey, '1');
            } catch (error) {}

            window.setTimeout(function () {
                window.location.reload();
            }, 900);
        }

        if (remainingSeconds <= 0) {
            $element.addClass('is-expired');
            $value.text(expiredLabel);
            refreshAfterExpiryOnce();
            return;
        }

        try {
            window.sessionStorage.removeItem(countdownRefreshKey);
        } catch (error) {}

        renderCountdown();

        window.setInterval(function () {
            remainingSeconds -= 1;

            if (remainingSeconds <= 0) {
                remainingSeconds = 0;
                $element.addClass('is-expired');
                $value.text(expiredLabel);
                refreshAfterExpiryOnce();
                return;
            }

            renderCountdown();
        }, 1000);
    });
});
</script>
