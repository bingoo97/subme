<section class="admin-topbar-notifications__section admin-topbar-tasks" data-admin-topbar-orders-section data-task-total="<?php echo (int)$taskTotal; ?>"<?php echo !$taskRows ? ' hidden' : ''; ?>>
    <div class="admin-topbar-notifications__section-head">
        <span><i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?php echo admin_e(admin_t($messages, 'topbar_tasks_title', 'To do')); ?></span>
        <span class="admin-status-pill admin-status-pill--warning" data-admin-topbar-orders-count><?php echo (int)$taskTotal; ?></span>
    </div>
    <div class="admin-topbar-notifications__list" data-admin-topbar-orders-list>
        <?php foreach ($taskRows as $taskRow): $task = admin_topbar_order_task($taskRow, $messages); $taskId = (int)$taskRow['id']; ?>
        <article class="admin-topbar-notifications__item admin-topbar-notifications__item--order admin-topbar-task" data-admin-topbar-order-item="<?php echo $taskId; ?>" data-task-kind="<?php echo admin_e($task['kind']); ?>">
            <div class="admin-topbar-notifications__item-main">
                <div class="admin-topbar-notifications__order-topline">
                    <span class="admin-topbar-task__label"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><?php echo admin_e($task['title']); ?></span>
                    <a class="admin-topbar-notifications__order-reference" href="<?php echo admin_e($task['order_url']); ?>">#<?php echo $taskId; ?></a>
                </div>
                <div class="admin-topbar-notifications__order-identity">
                    <a class="admin-topbar-notifications__order-handle" href="/admin/?page=users&amp;customer_id=<?php echo (int)$taskRow['customer_id']; ?>"><?php echo admin_e(!empty($taskRow['public_handle']) ? '@' . ltrim($taskRow['public_handle'], '@') : (string)($taskRow['customer_email'] ?? '')); ?></a>
                </div>
                <div class="admin-topbar-task__product-line">
                    <strong class="admin-topbar-task__product"><?php echo admin_e($task['product']); ?></strong>
                    <strong class="admin-topbar-task__amount"><?php echo admin_e($task['amount']); ?></strong>
                </div>
                <?php $paidAt = strtotime((string)($taskRow['task_waiting_since'] ?? '')); if ($paidAt !== false): ?>
                    <span class="admin-topbar-task__since"><?php echo admin_e(admin_t($messages, 'topbar_task_paid_since', 'Paid')); ?> <?php echo admin_e(date('d.m.Y H:i', $paidAt)); ?></span>
                <?php endif; ?>
                <ol class="admin-topbar-task__steps">
                    <li><?php echo admin_e($task['step_one']); ?></li>
                    <li><?php echo admin_e($task['step_two']); ?></li>
                </ol>
                <div class="admin-topbar-notifications__order-links">
                    <a class="admin-topbar-notifications__order-action admin-topbar-task__primary" href="<?php echo admin_e($task['order_url']); ?>"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i><?php echo admin_e(admin_t($messages, 'topbar_task_open_order', 'Complete in Orders')); ?></a>
                    <?php if ($task['provider_url'] !== ''): ?><a class="admin-topbar-notifications__order-action admin-topbar-notifications__order-action--secondary" href="<?php echo admin_e($task['provider_url']); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><?php echo admin_e(admin_t($messages, 'topbar_task_open_provider', 'Provider panel')); ?></a><?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php if ($taskTotal > count($taskRows)): ?><a class="admin-topbar-task__all" href="/admin/?page=orders"><?php echo admin_e(admin_t($messages, 'topbar_tasks_all', 'Open Orders to see all pending tasks')); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
</section>
