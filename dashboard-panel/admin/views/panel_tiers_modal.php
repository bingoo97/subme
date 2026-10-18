<?php
$panelTierLabels=[];
foreach(['title','intro','usage','month','quarter','near','too_early','unavailable','generate','pending','confirming','review','review_help','paid','expired','check','checking','scan_help','refresh','error','network','amount','address','rate_label','until','copy','per_month','partial','remaining','received','invoice_amount','partial_help','confirming_help','period_label','crypto_label','access_until','invoice_label','copy_error','cancel','cancelled','cancelled_review','send_network','scan_confirm','time_left'] as $key)$panelTierLabels[$key]=admin_panel_tiers_text($key);
?>
<div class="modal fade admin-panel-tier-modal" id="adminPanelTierPaymentModal" tabindex="-1" aria-labelledby="adminPanelTierPaymentTitle" aria-hidden="true" data-panel-tiers data-csrf="<?php echo admin_e($csrfToken); ?>" data-labels="<?php echo admin_e(json_encode($panelTierLabels,JSON_UNESCAPED_UNICODE)); ?>">
<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><span class="admin-panel-tier-modal__eyebrow">SUBME · TIERS</span><h5 class="modal-title" id="adminPanelTierPaymentTitle"><?php echo admin_e(admin_panel_tiers_text('title')); ?></h5></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="<?php echo admin_e(admin_t($messages,'close','Close')); ?>"></button></div>
    <div class="modal-body"><p class="admin-panel-tier-modal__intro"><?php echo admin_e(admin_panel_tiers_text('intro')); ?></p>
        <div data-panel-summary></div><button type="button" class="btn btn-outline-secondary w-100 mb-3" data-panel-retry hidden><?php echo admin_e(admin_panel_tiers_text('check')); ?></button><div data-panel-notice-top><div class="admin-panel-tier-notice" data-panel-notice role="status" aria-live="polite" hidden></div></div>
        <form data-panel-form>
            <fieldset class="admin-panel-tier-choices"><legend><?php echo admin_e(admin_panel_tiers_text('period_label')); ?></legend><div class="admin-panel-tier-periods" data-panel-periods></div></fieldset>
            <fieldset class="admin-panel-tier-choices"><legend><?php echo admin_e(admin_panel_tiers_text('crypto_label')); ?></legend><div class="admin-panel-tier-assets" data-panel-assets></div></fieldset>
            <button class="btn btn-dark w-100 admin-panel-tier-modal__generate" type="submit" data-panel-generate><i class="bi bi-qr-code" aria-hidden="true"></i> <?php echo admin_e(admin_panel_tiers_text('generate')); ?></button>
        </form>
        <section data-panel-invoice hidden aria-label="<?php echo admin_e(admin_panel_tiers_text('invoice_label')); ?>">
            <div class="admin-panel-tier-modal__payment-head" data-panel-payment-head></div>
            <div class="admin-panel-tier-modal__payment-body">
                <div class="admin-panel-tier-modal__qr-wrap"><div data-panel-qr class="admin-panel-tier-modal__qr"></div><p class="admin-panel-tier-modal__qr-help"><?php echo admin_e(admin_panel_tiers_text('qr_help')); ?></p></div>
                <div class="admin-panel-tier-modal__payment-details">
                    <dl data-panel-invoice-details></dl>
                    <p class="admin-panel-tier-modal__help" data-panel-payment-help><?php echo admin_e(admin_panel_tiers_text('scan_help')); ?></p>
                    <p class="admin-panel-tier-modal__network-reminder" data-panel-network-reminder></p>
                </div>
            </div>
            <div data-panel-notice-payment></div>
            <button class="btn btn-dark w-100" type="button" data-panel-check><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> <?php echo admin_e(admin_panel_tiers_text('check')); ?></button>
            <button class="btn btn-link w-100 mt-2" type="button" data-panel-cancel hidden><i class="bi bi-arrow-left" aria-hidden="true"></i> <?php echo admin_e(admin_panel_tiers_text('cancel')); ?></button>
        </section>
        <a href="/admin/?page=dashboard" class="btn btn-dark w-100" data-panel-refresh hidden><?php echo admin_e(admin_panel_tiers_text('refresh')); ?></a>
    </div>
</div></div></div>
<script src="/assets/vendor/qrcode-generator/qrcode.js?v=<?php echo admin_public_asset_version('assets/vendor/qrcode-generator/qrcode.js'); ?>"></script>
<script defer src="/assets/js/panel-subscription.js?v=<?php echo admin_public_asset_version('assets/js/panel-subscription.js'); ?>"></script>
