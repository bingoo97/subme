<?php
require_once dirname(__DIR__).'/crypto_quick.php';$cryptoQuickLabels=admin_crypto_quick_labels();
$cryptoQuickCounts=admin_crypto_quick_counts($db);
$cryptoQuickPriority=admin_crypto_quick_priority($cryptoQuickCounts);
$cryptoQuickBadgeCount=$cryptoQuickCounts[$cryptoQuickPriority];
$cryptoQuickAutoAcceptOff=!admin_crypto_auto_accept_enabled($appSettings);
if($cryptoQuickAutoAcceptOff) $cryptoQuickLabels['new_help'].=' '.$cryptoQuickLabels['auto_accept_help'];
$cryptoQuickHints=[];
foreach(['new'=>'bi-clock-history','expired'=>'bi-exclamation-circle-fill','cancelled'=>'bi-x-circle-fill','payout'=>'bi-check-circle-fill'] as $kind=>$icon){$cryptoQuickHints[$kind]=['title'=>$cryptoQuickLabels[$kind==='payout'?'received_title':$kind.'_title'],'text'=>$cryptoQuickLabels[$kind==='payout'?'payout_help':$kind.'_help'],'icon'=>$icon];}
?>
<div class="admin-topbar-tool" data-admin-topbar-flyout-root>
    <button type="button" class="admin-chat-inbox__toggle" data-admin-topbar-flyout-toggle="adminCryptoQuick" aria-expanded="false" aria-controls="adminCryptoQuick" title="<?php echo admin_e($cryptoQuickLabels['title']); ?>" aria-label="<?php echo admin_e($cryptoQuickLabels['title']); ?>"><i class="bi bi-wallet2" aria-hidden="true"></i><span class="admin-chat-inbox__badge admin-crypto-quick__badge" data-crypto-quick-badge data-tone="<?php echo admin_e($cryptoQuickPriority); ?>"<?php echo $cryptoQuickBadgeCount>0?'':' hidden'; ?> aria-hidden="true"><?php echo $cryptoQuickBadgeCount; ?></span></button>
    <div class="admin-topbar-flyout admin-crypto-quick" id="adminCryptoQuick" data-admin-topbar-flyout data-crypto-quick data-admin-id="<?php echo (int)($adminUser['id'] ?? 0); ?>" data-counts="<?php echo admin_e(json_encode($cryptoQuickCounts)); ?>" data-labels="<?php echo admin_e(json_encode(array_intersect_key($cryptoQuickLabels,array_flip(['title','new','expired','payout','cancelled'])),JSON_UNESCAPED_UNICODE)); ?>" data-url="/admin/crypto_quick.php" data-loading="<?php echo admin_e($cryptoQuickLabels['loading']); ?>" data-error="<?php echo admin_e($cryptoQuickLabels['error']); ?>" data-action-error="<?php echo admin_e($cryptoQuickLabels['action_error']); ?>" role="dialog" aria-labelledby="adminCryptoQuickTitle" hidden>
        <div class="admin-topbar-flyout__header"><div><div class="admin-crypto-quick__heading-title"><strong id="adminCryptoQuickTitle"><?php echo admin_e($cryptoQuickLabels['title']); ?></strong><span class="admin-crypto-quick__category-count" data-crypto-quick-category-count data-tone="<?php echo admin_e($cryptoQuickPriority); ?>" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php echo admin_e($cryptoQuickLabels[$cryptoQuickPriority].': '.$cryptoQuickBadgeCount); ?>"><?php echo $cryptoQuickBadgeCount; ?></span></div><p><?php echo admin_e($cryptoQuickLabels['intro']); ?></p></div><div class="admin-topbar-flyout__header-actions"><button type="button" class="btn-close" data-admin-topbar-flyout-close aria-label="<?php echo admin_e($cryptoQuickLabels['close']); ?>"></button></div></div>
        <div class="admin-crypto-quick__filters" role="group" aria-label="<?php echo admin_e($cryptoQuickLabels['title']); ?>"><?php foreach(['new','expired','cancelled','payout'] as $quickFilter): ?><button type="button" data-crypto-quick-filter="<?php echo $quickFilter; ?>" aria-pressed="<?php echo $quickFilter===$cryptoQuickPriority?'true':'false'; ?>"><?php echo admin_e($cryptoQuickLabels[$quickFilter]); ?></button><?php endforeach; ?></div>
        <div class="admin-crypto-quick__hint" data-crypto-quick-hint data-tone="new" data-hints="<?php echo admin_e(json_encode($cryptoQuickHints,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?>" hidden><span class="admin-crypto-quick__hint-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span><div><strong data-crypto-quick-hint-title><?php echo admin_e($cryptoQuickLabels['new_title']); ?></strong><p data-crypto-quick-hint-text><?php echo admin_e($cryptoQuickLabels['new_help']); ?></p><?php if($cryptoQuickAutoAcceptOff): ?><a class="admin-crypto-quick__settings-link" data-crypto-quick-settings-link href="/admin/?page=settings#crypto_auto_accept_enabled"><i class="bi bi-gear" aria-hidden="true"></i><?php echo admin_e($cryptoQuickLabels['auto_accept_link']); ?></a><?php endif; ?></div></div>
        <p class="admin-crypto-quick__result" data-crypto-quick-result role="status" hidden></p>
        <div class="admin-crypto-quick__list" data-crypto-quick-list aria-live="polite"></div>
        <?php $createCopy=admin_crypto_create_labels(); ?>
        <section class="admin-crypto-create" data-crypto-create data-copy="<?php echo admin_e(json_encode($createCopy,JSON_UNESCAPED_UNICODE)); ?>" data-csrf="<?php echo admin_e($csrfToken); ?>" hidden aria-label="<?php echo admin_e($createCopy['title']); ?>">
            <div class="admin-crypto-create__heading"><button type="button" data-create-back class="btn btn-light btn-sm"><i class="bi bi-chevron-left" aria-hidden="true"></i> <?php echo admin_e($createCopy['back']); ?></button><strong><?php echo admin_e($createCopy['title']); ?></strong></div>
            <ol class="admin-crypto-create__steps" aria-label="<?php echo admin_e($createCopy['title']); ?>"><?php foreach(['user','purpose','details'] as $i=>$key): ?><li data-create-step="<?php echo $i; ?>"><span><?php echo $i+1; ?></span><?php echo admin_e($createCopy[$key]); ?></li><?php endforeach; ?></ol>
            <p class="admin-crypto-create__notice" role="status" data-create-notice hidden></p>
            <a class="btn btn-outline-dark btn-sm admin-crypto-create__wallet-link" data-create-wallet hidden href="/admin/?page=crypto-wallets&amp;new_wallet=1"><i class="bi bi-wallet2" aria-hidden="true"></i> <?php echo admin_e($createCopy['add_wallet']); ?></a>
            <div data-create-screen="user"><input type="search" class="form-control" data-create-search aria-label="<?php echo admin_e($createCopy['user']); ?>" placeholder="<?php echo admin_e($createCopy['search']); ?>" autocomplete="off" maxlength="128"><div data-create-results></div></div>
            <div data-create-customer hidden></div>
            <div data-create-screen="purpose" hidden class="admin-crypto-create__choices">
                <button type="button" data-create-purpose="topup"><i class="bi bi-wallet2" aria-hidden="true"></i><strong><?php echo admin_e($createCopy['topup']); ?></strong><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                <button type="button" data-create-purpose="order"><i class="bi bi-box-seam" aria-hidden="true"></i><strong><?php echo admin_e($createCopy['order']); ?></strong><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
            <form data-create-screen="details" data-create-form hidden>
                <div class="admin-crypto-create__segmented" data-create-order-mode><button type="button" data-create-mode="new_order"><?php echo admin_e($createCopy['new']); ?></button><button type="button" data-create-mode="existing_order"><?php echo admin_e($createCopy['existing']); ?></button></div>
                <p class="admin-crypto-create__help" data-create-help></p>
                <label data-create-product-wrap><?php echo admin_e($createCopy['product']); ?><select class="form-select" data-create-product></select></label>
                <label data-create-order-wrap hidden><?php echo admin_e($createCopy['existing']); ?><select class="form-select" data-create-order></select></label>
                <label data-create-amount-wrap hidden><span data-create-amount-label><?php echo admin_e($createCopy['amount']); ?></span><input class="form-control" type="number" min="1" max="200" step="0.01" inputmode="decimal" data-create-amount></label>
                <fieldset><legend><?php echo admin_e($createCopy['asset']); ?></legend><div class="admin-crypto-create__assets" data-create-assets></div></fieldset>
                <p class="admin-crypto-create__help"><?php echo admin_e($createCopy['review']); ?></p>
                <button type="submit" class="btn btn-dark admin-crypto-create__submit" data-create-submit disabled><i class="bi bi-plus-lg" aria-hidden="true"></i> <?php echo admin_e($createCopy['create']); ?></button>
            </form>
        </section>
        <a class="admin-crypto-quick__footer" href="/admin/?page=payments&amp;payment_type_filter=crypto"><?php echo admin_e($cryptoQuickLabels['all']); ?><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        <div class="admin-crypto-quick__preferences">
            <label for="adminCryptoQuickHints"><?php echo admin_e($cryptoQuickLabels['show_hints']); ?></label>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="adminCryptoQuickHints" data-crypto-quick-hints-toggle checked aria-checked="true"></div>
        </div>
    </div>
</div>

<?php $cryptoReminderCopy=admin_crypto_reminder_copy(); ?>
<dialog class="admin-crypto-reminder" data-crypto-reminder data-enabled="<?php echo admin_setting_is_enabled($appSettings,'admin_action_reminders_enabled',true)?'1':'0'; ?>" aria-labelledby="adminCryptoReminderTitle">
    <button type="button" class="btn-close admin-crypto-reminder__close" data-reminder-close aria-label="<?php echo admin_e($cryptoReminderCopy['close']); ?>"></button>
    <div class="admin-crypto-reminder__icon"><i class="bi bi-wallet2" data-reminder-icon aria-hidden="true"></i></div>
    <h2 id="adminCryptoReminderTitle" data-reminder-title></h2>
    <p class="admin-crypto-reminder__text" data-reminder-text></p>
    <div class="admin-crypto-reminder__receipt" data-reminder-receipt data-detected-label="<?php echo admin_e($cryptoReminderCopy['detected_notice']); ?>" data-approved-label="<?php echo admin_e($cryptoReminderCopy['approved_notice']); ?>" role="status" hidden><i class="bi bi-check-circle-fill" aria-hidden="true"></i><div class="admin-crypto-reminder__receipt-content"><strong data-reminder-receipt-title hidden></strong><a class="admin-crypto-quick__purpose" data-reminder-receipt-purpose hidden></a><span data-reminder-receipt-text></span></div></div>
    <div class="admin-crypto-reminder__payment" data-reminder-payment></div>
    <a class="btn admin-crypto-reminder__primary" data-reminder-primary href="/admin/?page=payments" data-single-label="<?php echo admin_e($cryptoReminderCopy['single']); ?>"></a>
    <button type="button" class="admin-crypto-reminder__later" data-reminder-close><?php echo admin_e($cryptoReminderCopy['later']); ?></button>
    <p class="admin-crypto-quick__result" data-reminder-error role="status" hidden></p>
</dialog>
