        <?php $createCopy=admin_crypto_create_labels(); ?>
        <section class="admin-crypto-create" data-crypto-create data-copy="<?php echo admin_e(json_encode($createCopy,JSON_UNESCAPED_UNICODE)); ?>" data-csrf="<?php echo admin_e($csrfToken); ?>" hidden aria-label="<?php echo admin_e($createCopy['title']); ?>">
            <div class="admin-crypto-create__heading"><button type="button" data-create-back class="btn btn-light btn-sm"><i class="bi bi-chevron-left" aria-hidden="true"></i> <?php echo admin_e($createCopy['back']); ?></button><strong><?php echo admin_e($createCopy['title']); ?></strong></div>
            <ol class="admin-crypto-create__steps" aria-label="<?php echo admin_e($createCopy['title']); ?>"><?php foreach(['user','purpose','details'] as $i=>$key): ?><li data-create-step="<?php echo $i; ?>"><span><?php echo $i+1; ?></span><?php echo admin_e($createCopy[$key]); ?></li><?php endforeach; ?></ol>
            <p class="admin-crypto-create__notice" role="status" data-create-notice hidden></p>
            <a class="btn btn-outline-dark btn-sm admin-crypto-create__wallet-link" data-create-pending hidden href="/admin/?page=payments&amp;payment_scope=new"><i class="bi bi-search" aria-hidden="true"></i> <?php echo admin_e($createCopy['payment_details']); ?></a>
            <a class="btn btn-success btn-sm admin-crypto-create__wallet-link" data-create-wallet hidden href="/admin/?page=crypto-wallets&amp;new_wallet=1"><i class="bi bi-wallet2" aria-hidden="true"></i> <?php echo admin_e($createCopy['add_wallet']); ?></a>
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
