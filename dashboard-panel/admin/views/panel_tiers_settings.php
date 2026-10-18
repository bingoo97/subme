<?php if ($settingsAdminMode): ?>
<div class="admin-panel-tiers-settings">
    <div class="admin-panel-tiers-settings__heading">
        <div><strong><?php echo admin_e(admin_panel_tiers_text('settings_title')); ?></strong><p><?php echo admin_e(admin_panel_tiers_text('settings_help')); ?></p></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="panel_auto_enabled" id="panelAutoEnabled" value="1" <?php echo !empty($panelTiersConfig['enabled'])?'checked':''; ?>><label class="form-check-label" for="panelAutoEnabled">ON / OFF</label></div>
    </div>
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="panel_notify_admin" id="panelNotifyAdmin" value="1" <?php echo !empty($panelTiersConfig['notify_admin']??1)?'checked':''; ?>><label class="form-check-label" for="panelNotifyAdmin"><?php echo admin_e(admin_panel_tiers_text('email_notice')); ?></label></div>
    <div class="admin-panel-tiers-settings__tiers">
    <?php foreach ([1,2,3] as $tier): ?>
        <section class="admin-panel-tier-config"><strong>Tier <?php echo $tier; ?></strong>
            <label class="form-label" for="panelPrice<?php echo $tier; ?>"><?php echo admin_e($adminDefaultCurrencyCode.' '.admin_panel_tiers_text('per_month')); ?></label>
            <input class="form-control" id="panelPrice<?php echo $tier; ?>" name="panel_price_<?php echo $tier; ?>" type="number" step="0.01" min="0.01" max="9999999.99" required value="<?php echo admin_e((string)($panelTiersConfig['price_'.$tier]??[1=>'29.00',2=>'59.00',3=>'99.00'][$tier])); ?>">
            <?php if ($tier<3): ?><label class="form-label mt-2" for="panelLimit<?php echo $tier; ?>"><?php echo admin_e(admin_panel_tiers_text('usage')); ?> · max</label>
            <input class="form-control" id="panelLimit<?php echo $tier; ?>" name="panel_limit_<?php echo $tier; ?>" type="number" min="1" max="10000000" required value="<?php echo (int)($panelTiersConfig['limit_'.$tier]??($tier===1?50:250)); ?>">
            <?php else: ?><p class="mt-2 mb-0"><?php echo admin_e(admin_panel_tiers_text('unlimited')); ?></p><?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div>
    <p class="mt-3"><?php echo admin_e(admin_panel_tiers_text('wallet_help')); ?></p>
    <div class="row g-3">
    <?php foreach(app_panel_tiers_assets() as $asset=>$spec): ?>
        <div class="col-md-6"><label class="form-label" for="panelWallet<?php echo $asset; ?>"><?php echo admin_e($asset.' · '.$spec['label']); ?></label>
        <input class="form-control" id="panelWallet<?php echo $asset; ?>" name="panel_<?php echo $spec['wallet']; ?>" value="<?php echo admin_e((string)($panelTiersConfig[$spec['wallet']]??'')); ?>" autocomplete="off" spellcheck="false" maxlength="191" placeholder="<?php echo admin_e($asset==='USDT'?'0x…':admin_panel_tiers_text('address')); ?>"></div>
    <?php endforeach; ?>
    </div>
<p class="mt-3"><?php echo admin_e(admin_panel_tiers_text('manual_help')); ?></p>
</div>
<script>
(() => {
    const toggle = document.getElementById('panelAutoEnabled');
    const sync = () => { const date = document.getElementById('admin_panel_subscription_paid_until'); if (date) date.readOnly = toggle.checked; };
    document.addEventListener('DOMContentLoaded', sync, { once: true });
    toggle.addEventListener('change', sync);
})();
</script>
<?php endif; ?>
