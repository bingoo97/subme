<?php
$panelPaymentHistory=$db->select_full_user('SELECT id,tier,months,status,amount_fiat,currency_code,asset_code,received_crypto,quoted_days,granted_days,new_paid_until FROM panel_subscription_requests ORDER BY id DESC LIMIT 10')?:[];
if ($settingsAdminMode && $panelPaymentHistory): ?>
<section class="admin-panel-tiers-settings">
<strong><?php echo admin_e(admin_panel_tiers_text('history')); ?></strong>
<div class="table-responsive mt-3"><table class="table table-sm mb-0" style="font-size:11px"><thead><tr><th>ID</th><th>Tier</th><th><?php echo admin_e(admin_panel_tiers_text('amount')); ?></th><th><?php echo admin_e(admin_panel_tiers_text('received')); ?></th><th><?php echo admin_e(admin_panel_tiers_text('days')); ?></th><th>Status</th></tr></thead><tbody>
<?php foreach($panelPaymentHistory as $receipt): ?><tr>
<td>#<?php echo (int)$receipt['id']; ?></td><td><?php echo (int)$receipt['tier']; ?></td>
<td><?php echo admin_e($receipt['amount_fiat'].' '.$receipt['currency_code']); ?></td><td><?php echo admin_e(admin_decimal_input_value((float)$receipt['received_crypto'],9).' '.$receipt['asset_code']); ?></td>
<td><?php echo (int)($receipt['granted_days']??0).' / '.(int)$receipt['quoted_days']; ?></td><td><?php echo admin_e(admin_panel_tiers_text(in_array($receipt['status'],['pending','partial','confirming','paid','expired','review','cancelled','cancelled_review','cancelled_expired'],true)?$receipt['status']:'review_closed')); ?>
<?php if(in_array($receipt['status'],['review','cancelled_review'],true)): ?><form method="post" class="mt-2"><input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>"><button type="submit" name="admin_panel_close_review" value="<?php echo (int)$receipt['id']; ?>" class="btn btn-sm btn-outline-warning" style="font-size:11px"><?php echo admin_e(admin_panel_tiers_text('close_review')); ?></button></form><?php endif; ?>
</td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
