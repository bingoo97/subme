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
    <div class="admin-topbar-flyout admin-crypto-quick" id="adminCryptoQuick" data-admin-topbar-flyout data-crypto-quick data-admin-id="<?php echo (int)($adminUser['id'] ?? 0); ?>" data-counts="<?php echo admin_e(json_encode($cryptoQuickCounts)); ?>" data-labels="<?php echo admin_e(json_encode(array_intersect_key($cryptoQuickLabels,array_flip(['title','new','expired','payout','cancelled'])),JSON_UNESCAPED_UNICODE)); ?>" data-url="/admin/crypto_quick.php" data-loading="<?php echo admin_e($cryptoQuickLabels['loading']); ?>" data-error="<?php echo admin_e($cryptoQuickLabels['error']); ?>" role="dialog" aria-labelledby="adminCryptoQuickTitle" hidden>
        <div class="admin-topbar-flyout__header"><div><strong id="adminCryptoQuickTitle"><?php echo admin_e($cryptoQuickLabels['title']); ?></strong><p><?php echo admin_e($cryptoQuickLabels['intro']); ?></p></div><div class="admin-topbar-flyout__header-actions"><button type="button" class="btn-close" data-admin-topbar-flyout-close aria-label="<?php echo admin_e($cryptoQuickLabels['close']); ?>"></button></div></div>
        <div class="admin-crypto-quick__filters" role="group" aria-label="<?php echo admin_e($cryptoQuickLabels['title']); ?>"><?php foreach(['new','expired','cancelled','payout'] as $quickFilter): ?><button type="button" data-crypto-quick-filter="<?php echo $quickFilter; ?>" aria-pressed="<?php echo $quickFilter===$cryptoQuickPriority?'true':'false'; ?>"><?php echo admin_e($cryptoQuickLabels[$quickFilter]); ?></button><?php endforeach; ?></div>
        <div class="admin-crypto-quick__hint" data-crypto-quick-hint data-tone="new" data-hints="<?php echo admin_e(json_encode($cryptoQuickHints,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?>" hidden><span class="admin-crypto-quick__hint-icon" aria-hidden="true"><i class="bi bi-clock-history"></i></span><div><strong data-crypto-quick-hint-title><?php echo admin_e($cryptoQuickLabels['new_title']); ?></strong><p data-crypto-quick-hint-text><?php echo admin_e($cryptoQuickLabels['new_help']); ?></p><?php if($cryptoQuickAutoAcceptOff): ?><a class="admin-crypto-quick__settings-link" data-crypto-quick-settings-link href="/admin/?page=settings#crypto_auto_accept_enabled"><i class="bi bi-gear" aria-hidden="true"></i><?php echo admin_e($cryptoQuickLabels['auto_accept_link']); ?></a><?php endif; ?></div></div>
        <p class="admin-crypto-quick__result" data-crypto-quick-result role="status" hidden></p>
        <div class="admin-crypto-quick__list" data-crypto-quick-list aria-live="polite"></div>
        <a class="admin-crypto-quick__footer" href="/admin/?page=payments&amp;payment_type_filter=crypto"><?php echo admin_e($cryptoQuickLabels['all']); ?><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        <div class="admin-crypto-quick__preferences">
            <label for="adminCryptoQuickHints"><?php echo admin_e($cryptoQuickLabels['show_hints']); ?></label>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="adminCryptoQuickHints" data-crypto-quick-hints-toggle checked aria-checked="true"></div>
        </div>
    </div>
</div>
