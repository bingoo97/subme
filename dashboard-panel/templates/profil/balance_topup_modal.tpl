<div id="balanceTopupModal" class="modal fade balance-topup-modal" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{$t.close|default:'Close'}">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h3 class="modal-title">{$t.balance_topup_modal_title|default:'Top up balance'}</h3>
            </div>
            <div class="modal-body">
                {if $payment_test_mode_notice_enabled|default:false}
                <div class="alert alert-warning">
                    <strong>{$t.payment_test_mode_notice_title|default:'Payment test mode'}</strong><br />
                    {$t.payment_test_mode_notice_text|default:'You can still generate payment requests and test the full flow, but please do not send any money right now. The payment details are displayed for testing only.'}
                </div>
                {/if}
                <p class="balance-topup-modal__intro">{$t.balance_topup_modal_intro|default:'Choose the payment method, cryptocurrency and top-up amount.'}</p>

                {if $balance_topup_crypto_assets|@count gt 0}
                <form action="{$balance_topup_action_url|default:'/cryptocurrency'}" method="post" data-balance-topup-form>
                    <input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
                    <input type="hidden" name="create_topup_payment" value="1" />
                    <input type="hidden" name="payment_method" value="crypto" />

                    <div class="balance-topup-step is-active" data-balance-topup-step="method">
                        <div class="payment-step__head balance-topup-step__head">
                            <span class="payment-step__label">{$t.payment_step|default:'Step'} 1</span>
                            <h4>{$t.payment_choose_method_title|default:'Choose payment method'}</h4>
                        </div>

                        <div class="payment-method-start__buttons">
                            <button type="button" class="payment-method-start__button balance-topup-method-btn" data-balance-topup-next="crypto">
                                <i class="fa fa-btc" aria-hidden="true"></i> {$t.payment_method_crypto|default:'Pay with crypto'}
                            </button>
                        </div>

                        <div class="payment-method-hero payment-method-hero--modal">
                            <img src="/img/package.jpg" alt="Payment package" class="payment-method-hero__image" />
                        </div>
                    </div>

                    <div class="balance-topup-step" data-balance-topup-step="crypto">
                        <div class="balance-topup-backrow">
                            <button type="button" class="btn btn-default payment-method-back" data-balance-topup-back="method">
                                <i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.back|default:'Back'}
                            </button>
                        </div>

                        <div class="payment-step__head balance-topup-step__head">
                            <span class="payment-step__label">{$t.payment_step|default:'Step'} 2</span>
                            <h4>{$t.payment_choose_crypto|default:'Choose cryptocurrency'}</h4>
                        </div>

                        <div class="crypto-choice-grid">
                            {section name=i loop=$balance_topup_crypto_assets}
                                <label class="crypto-choice-card">
                                    <input
                                        type="radio"
                                        name="crypto_wallet_assignment_id"
                                        value="{$balance_topup_crypto_assets[i].id}"
                                        data-asset-name="{$balance_topup_crypto_assets[i].name|escape:'html'}"
                                        data-asset-code="{$balance_topup_crypto_assets[i].code|escape:'html'}"
                                        {if $smarty.section.i.first}checked{/if}
                                    >
                                    <span class="crypto-choice-card__inner">
                                        <span class="crypto-choice-card__logo">
                                            <img src="{$balance_topup_crypto_assets[i].logo_path}" alt="{$balance_topup_crypto_assets[i].name}" />
                                        </span>
                                        <span class="crypto-choice-card__name">{$balance_topup_crypto_assets[i].name}</span>
                                        {if $balance_topup_crypto_assets[i].network_label}
                                            <span class="crypto-choice-card__network">{$balance_topup_crypto_assets[i].code} • {$balance_topup_crypto_assets[i].network_label}</span>
                                        {else}
                                            <span class="crypto-choice-card__network">{$balance_topup_crypto_assets[i].code}</span>
                                        {/if}
                                    </span>
                                </label>
                            {/section}
                        </div>

                        <div class="balance-topup-actions">
                            <button type="button" class="btn btn-dark btn-lg" data-balance-topup-next="amount">
                                {$t.balance_topup_continue|default:'Continue'} <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="balance-topup-step" data-balance-topup-step="amount">
                        <div class="balance-topup-backrow">
                            <button type="button" class="btn btn-default payment-method-back" data-balance-topup-back="crypto">
                                <i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.back|default:'Back'}
                            </button>
                        </div>

                        <div class="payment-step__head balance-topup-step__head">
                            <span class="payment-step__label">{$t.payment_step|default:'Step'} 3</span>
                            <h4>{$t.balance_topup_amount_step_title|default:'Enter top-up amount'}</h4>
                        </div>

                        <p class="balance-topup-selected">
                            {$t.balance_topup_selected_crypto|default:'Selected cryptocurrency'}:
                            <strong data-balance-topup-selected>BTC</strong>
                        </p>

                        <label class="balance-topup-amount-label" for="balanceTopupAmount">{$t.payment_summary_amount|default:'Amount'}</label>
                        <input
                            type="number"
                            class="form-control balance-topup-amount-input"
                            id="balanceTopupAmount"
                            name="topup_amount"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="{$t.balance_topup_amount_placeholder|default:'Enter amount'}"
                            required
                        />

                        <span class="balance-topup-amount-label" style="margin-top:14px;">{$t.balance_topup_presets_label|default:'Quick amounts'}</span>
                        <div class="balance-topup-presets">
                            <button type="button" class="balance-topup-preset" data-balance-topup-amount="10">10</button>
                            <button type="button" class="balance-topup-preset" data-balance-topup-amount="25">25</button>
                            <button type="button" class="balance-topup-preset" data-balance-topup-amount="50">50</button>
                            <button type="button" class="balance-topup-preset" data-balance-topup-amount="100">100</button>
                        </div>

                        <div class="balance-topup-actions">
                            <button type="submit" class="btn btn-dark btn-lg balance-topup-submit">
                                {$t.balance_topup_go_to_payment|default:'Go to payment'} <i class="fa fa-long-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </form>
                {else}
                <div class="alert alert-warning payment-support-alert">
                    {$t.balance_topup_unavailable|default:'Balance top-up is currently unavailable.'}
                </div>
                {if $settings.support_chat_enabled == 1}
                    <button type="button" class="btn btn-default btn-lg payment-support-button" onclick="return openMessengerPanel('{$user.id}');">
                        <i class="fa fa-life-ring" aria-hidden="true"></i> {$t.instructions_contact_support|default:'Contact support'}
                    </button>
                {/if}
                {/if}
            </div>
        </div>
    </div>
</div>

<script>
(function ($) {
    function showBalanceTopupStep($modal, stepName) {
        $modal.find('[data-balance-topup-step]').removeClass('is-active');
        $modal.find('[data-balance-topup-step="' + stepName + '"]').addClass('is-active');
    }

    function syncBalanceTopupSelection($modal) {
        var $checked = $modal.find('input[name="crypto_wallet_assignment_id"]:checked');
        var assetName = String($checked.data('assetName') || '').trim();
        var assetCode = String($checked.data('assetCode') || '').trim();
        var label = $.trim(assetName + (assetCode ? ' (' + assetCode + ')' : ''));

        $modal.find('[data-balance-topup-selected]').text(label || assetCode || assetName || 'Crypto');
    }

    function syncBalanceTopupPresetState($modal) {
        var currentValue = parseFloat(String($modal.find('input[name="topup_amount"]').val() || '').replace(',', '.'));

        $modal.find('[data-balance-topup-amount]').each(function () {
            var $button = $(this);
            var buttonValue = parseFloat($button.attr('data-balance-topup-amount'));
            $button.toggleClass('is-active', !isNaN(currentValue) && !isNaN(buttonValue) && currentValue === buttonValue);
        });
    }

    function resetBalanceTopupWizard($modal) {
        showBalanceTopupStep($modal, 'method');
        syncBalanceTopupSelection($modal);
        syncBalanceTopupPresetState($modal);
    }

    $(document).off('click.balanceTopupNext', '[data-balance-topup-next]');
    $(document).on('click.balanceTopupNext', '[data-balance-topup-next]', function () {
        var $modal = $(this).closest('.balance-topup-modal');
        showBalanceTopupStep($modal, String($(this).attr('data-balance-topup-next') || 'method'));
        syncBalanceTopupSelection($modal);
    });

    $(document).off('click.balanceTopupBack', '[data-balance-topup-back]');
    $(document).on('click.balanceTopupBack', '[data-balance-topup-back]', function () {
        var $modal = $(this).closest('.balance-topup-modal');
        showBalanceTopupStep($modal, String($(this).attr('data-balance-topup-back') || 'method'));
    });

    $(document).off('change.balanceTopupCrypto', '[data-balance-topup-form] input[name="crypto_wallet_assignment_id"]');
    $(document).on('change.balanceTopupCrypto', '[data-balance-topup-form] input[name="crypto_wallet_assignment_id"]', function () {
        syncBalanceTopupSelection($(this).closest('.balance-topup-modal'));
    });

    $(document).off('click.balanceTopupPreset', '[data-balance-topup-amount]');
    $(document).on('click.balanceTopupPreset', '[data-balance-topup-amount]', function () {
        var $modal = $(this).closest('.balance-topup-modal');
        var amount = String($(this).attr('data-balance-topup-amount') || '').trim();
        $modal.find('input[name="topup_amount"]').val(amount);
        syncBalanceTopupPresetState($modal);
    });

    $(document).off('input.balanceTopupAmount', '[data-balance-topup-form] input[name="topup_amount"]');
    $(document).on('input.balanceTopupAmount', '[data-balance-topup-form] input[name="topup_amount"]', function () {
        syncBalanceTopupPresetState($(this).closest('.balance-topup-modal'));
    });

    $('#balanceTopupModal').on('shown.bs.modal show.bs.modal', function () {
        resetBalanceTopupWizard($(this));
    });
})(jQuery);
</script>
