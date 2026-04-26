<div class="content-box orders-view{if $order_catalog_product_type|default:'subscription' eq 'credits'} orders-view-history{else} orders-view-modern{/if}">
    <div class="orders-header{if $order_catalog_product_type|default:'subscription' eq 'credits'} orders-header--history{/if}">
        <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.orders|default:'Orders'}</h1>
        {if $order_sales_available}
            <form action="" method="post">
                <input type="hidden" name="_csrf" value="{$csrf_token|default:''}">
                <button type="submit" class="btn btn-dark btn-lg" name="order_add">
                    {if $order_catalog_product_type|default:'subscription' eq 'credits'}
                        {$t.order_add_credits|default:'Buy credits'} <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                    {else}
                        {$t.order_add_subscription|default:'Add subscription'} <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                    {/if}
                </button>
            </form>
        {else}
            <div class="text-muted">
                {if $order_catalog_product_type|default:'subscription' eq 'credits'}
                    {$t.credits_sales_disabled_notice|default:'Credits sales are currently unavailable.'}
                {else}
                    {$t.sales_disabled_notice|default:'Sales are currently unavailable.'}
                {/if}
            </div>
        {/if}
    </div>

    {if $order_catalog_product_type|default:'subscription' eq 'credits'}
        {include file='profil/orders_reseller.tpl'}
    {else}
    <hr />
    {if $wygrane}
        <div class="table-responsive">
            <table id="orders" class="table align-middle orders-user-table">
                <thead>
                    <tr>
                        <th class="orders-user-table__id-col text-center">{$t.history_badge_order|default:'ID'}</th>
                        <th class="orders-user-table__product-col">{$t.orders_product|default:'Product'}</th>
                        <th class="orders-user-table__amount-col">{$t.orders_amount|default:'Amount'}</th>
                        <th class="orders-user-table__date-col hidden-xs hidden-sm hidden-md text-center"></th>
                        <th class="orders-user-table__actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                    {section name=i loop=$wygrane}
                        <tr>
                            <td data-label="{$t.history_badge_order|default:'Order'}">
                                <span class="badge badge-secondary">#{$wygrane[i].id}</span>
                            </td>
                            <td data-label="{$t.orders_product|default:'Product'}" class="{if $wygrane[i].status == 0}orders-user-cell-muted{/if}">
                                <div class="orders-user-summary">
                                    <div class="orders-user-summary__title-row">
                                        <strong>{$wygrane[i].provider_name} {$wygrane[i].name}</strong>
                                        {if $wygrane[i].payment_waiting_activation}
                                            <span class="orders-user-new-badge orders-user-new-badge--success">{$t.orders_status_payment_confirmed_short|default:'PAID'}</span>
                                        {elseif $wygrane[i].status == 0}
                                            <span class="orders-user-new-badge">NEW</span>
                                        {/if}
                                    </div>
                                    {if $wygrane[i].status == 2}
                                    <span class="btn btn-danger btn-xs text-left d-block d-sm-none">Expiried <i class="fa fa-angle-double-right"></i></span>
                                    {else}
                                    {if $wygrane[i].payment_waiting_activation}
                                        <div class="orders-user-summary__note orders-user-summary__note--success">
                                            <i class="fa fa-check-circle" aria-hidden="true"></i> {$t.orders_payment_confirmed_waiting_activation|default:'Payment confirmed. Waiting for activation.'}
                                        </div>
                                    {/if}
                                    {if $wygrane[i].note neq ''}
                                        <div class="orders-user-summary__note">{$wygrane[i].note}</div>
                                    {/if}
                                    {if $wygrane[i].link_url neq ''}
                                        <div class="orders-user-summary__note"><i class="fa fa-check text-success" aria-hidden="true"></i> {$t.order_delivery_enabled|default:'m3u enabled'}</div>
                                    {/if}
                                    {if $wygrane[i].status == 1 && $wygrane[i].product_type|default:'subscription' neq 'credits' && $wygrane[i].progress.has_expiry}
                                        <div class="orders-user-progress">
                                            <div class="orders-user-progress__days orders-user-progress__days--{$wygrane[i].progress.tone|default:'neutral'}">
                                                {$wygrane[i].progress.remaining_days|default:0}
                                            </div>
                                            <div class="orders-user-progress__track">
                                                <div class="orders-user-progress__meta">
                                                    <span>{$t.orders_days_label|default:'Days'}</span>
                                                    <span>
                                                        {if $wygrane[i].expiry}
                                                            {$wygrane[i].expiry}
                                                        {else}
                                                            {$t.order_no_expiry|default:'No expiry'}
                                                        {/if}
                                                    </span>
                                                </div>
                                                <div class="orders-user-progress__bar">
                                                    <span style="width: {$wygrane[i].progress.percent|default:0}%; background: {$wygrane[i].progress.color|default:'#d1d5db'};"></span>
                                                </div>
                                            </div>
                                        </div>
                                    {/if}
                                    {/if}
                                </div>
                            </td>
                            <td data-label="{$t.orders_amount|default:'Amount'}" class="orders-user-table__amount-col">
                                <div class="orders-user-amount">
                                    <strong class="{if $wygrane[i].status == 1}text-success{elseif $wygrane[i].status == 2}text-danger{else}text-dark{/if}">{$wygrane[i].price_label|default:$wygrane[i].price}</strong>
                                    {if $wygrane[i].status != 2}
                                        <i class="{$wygrane[i].status_visual.icon|default:'fa fa-circle'} orders-user-status-icon {$wygrane[i].status_visual.class|default:'orders-status-neutral'}" aria-hidden="true" title="{$t[$wygrane[i].status_visual.label_key]|default:$wygrane[i].status_visual.label|default:'Status'}"></i>
                                    {/if}
                                </div>
                            </td>
                            <td class="orders-user-table__date-col center hidden-xs hidden-sm hidden-md" data-label="{$t.history_column_date|default:'Date'}">
                                    {if $wygrane[i].status == 2}
                                        <span class="badge badge-danger">Expired</span>
                                    {else}
                                        {$wygrane[i].created_display}
                                    {/if}
                            </td>
                            <td data-label="{$t.orders_actions|default:'Actions'}" class="orders-user-table__actions-col">
                                {if $wygrane[i].status != 1}
                                <a href="/order-payment-{$wygrane[i].id}" class="btn btn-danger" aria-label="Pay" style="width: 40px; height: 40px;">
                                    <i class="fa fa-credit-card" style="margin-left:-1px;" aria-hidden="true"></i>
                                </a>
                                {else}
                                <button type="button" class="btn {if $wygrane[i].status == 1}btn-success{elseif $wygrane[i].status == 2}btn-danger{else}btn-dark{/if} user-order-modal-trigger" data-toggle="modal" data-target="#orderModal{$wygrane[i].id}" data-order-modal-open="#orderModal{$wygrane[i].id}" aria-label="Details" style="width: 40px; height: 40px;">
                                    <i class="fa fa-search" aria-hidden="true"></i>
                                </button>
                                {/if}
                            </td>
                        </tr>
                    {/section}
                </tbody>
            </table>
        </div>

        {$generator}
    {else}
        <p>{$t.orders_empty|default:'No available packages.'}</p>
    {/if}

    <div class="alert alert-dismissible alert-info">
        <i class="fa fa-info-circle" aria-hidden="true"></i> {$t.orders_info_subscription_notice|default:'After your subscription expires, you will receive an email notification.'}
    </div>
    {/if}
</div>

{if $wygrane}
    {section name=i loop=$wygrane}
        {assign var="has_delivery_access" value=($wygrane[i].delivery_show_credentials || $wygrane[i].link_url neq '')}
        {assign var="can_pending_payment_actions" value=($wygrane[i].status == 0 and $wygrane[i].shipment == 0 and not $wygrane[i].payment_waiting_activation and ($wygrane[i].id <> $id_zamowienia_zaplac))}
        <div id="orderModal{$wygrane[i].id}" class="modal fade user-order-modal" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">{$wygrane[i].provider_name} {$wygrane[i].name}</h4>
                        <div class="user-order-modal__summary">
                            <span class="user-order-modal__summary-chip btn-outline-dark">#{$wygrane[i].id}</span>
                            <span class="user-order-modal__summary-chip btn-outline-dark">{$wygrane[i].price_label|default:$wygrane[i].price}</span>
                        </div>
                    </div>
                    <div class="modal-body">
                        <ul class="nav nav-tabs user-order-modal__tabs" role="tablist">
                            {if $has_delivery_access}
                                <li role="presentation" class="nav-item">
                                    <a class="nav-link" href="#orderAccess{$wygrane[i].id}" aria-controls="orderAccess{$wygrane[i].id}" role="tab" data-bs-toggle="tab" data-toggle="tab">{$t.orders_tab_access|default:'Access'}</a>
                                </li>
                            {/if}
                            <li role="presentation" class="nav-item">
                                <a class="nav-link active" href="#orderInfo{$wygrane[i].id}" aria-controls="orderInfo{$wygrane[i].id}" role="tab" data-bs-toggle="tab" data-toggle="tab">Details</a>
                            </li>
                            {if $order_sales_available && ($can_pending_payment_actions || (($wygrane[i].status == 1 || $wygrane[i].status == 2) && $wygrane[i].product_type|default:'subscription' neq 'credits'))}
                                <li role="presentation" class="nav-item">
                                    <a class="nav-link" href="#orderActions{$wygrane[i].id}" aria-controls="orderActions{$wygrane[i].id}" role="tab" data-bs-toggle="tab" data-toggle="tab">
                                        {if $can_pending_payment_actions}
                                            {$t.orders_action_payment|default:'Payment'}
                                        {elseif $wygrane[i].status == 2 && $wygrane[i].product_type|default:'subscription' neq 'credits'}
                                            {$t.orders_action_renew|default:'Renew'}
                                            <span class="user-order-modal__tab-badge user-order-modal__tab-badge--danger">!</span>
                                        {else}
                                            {$t.orders_action_extend|default:'Extend'}
                                            <span class="user-order-modal__tab-badge">!</span>
                                        {/if}
                                    </a>
                                </li>
                            {/if}
                        </ul>

                        <div class="tab-content user-order-modal__tab-content">
                            {if $has_delivery_access}
                                <div role="tabpanel" class="tab-pane fade" id="orderAccess{$wygrane[i].id}">
                                    <div class="user-order-modal__stack">
                                        {if $wygrane[i].delivery_show_credentials}
                                            <div>
                                                <label class="form-label">{$t.order_delivery_credentials_label|default:'Login details'}</label>
                                                <p class="user-order-modal__access-copy">
                                                    {$t.order_delivery_access_copy|default:'Use these login details in a compatible player app on your device. If you need a ready app, open the apps page and choose the version for your platform.'}
                                                    {if $settings.apps_page_enabled} <a href="/apps">{$t.menu_apps|default:'Apps'}</a>.{/if}
                                                </p>
                                                <div class="row">
                                                    <div class="col-sm-6">
                                                        <label class="form-label">{$t.order_delivery_login_label|default:'Login'}</label>
                                                        <input type="text" class="form-control user-order-modal__readonly" value="{$wygrane[i].delivery_login}" readonly>
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <label class="form-label">{$t.order_delivery_password_label|default:'Password'}</label>
                                                        <input type="text" class="form-control user-order-modal__readonly" value="{$wygrane[i].delivery_password}" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        {/if}
                                        {if $wygrane[i].link_url neq ''}
                                            <div>
                                                <label class="form-label">{$t.order_delivery_link_label|default:'URL link'}</label>
                                                <div class="user-order-modal__url-row">
                                                    <input type="text" class="form-control user-order-modal__readonly" value="{$wygrane[i].link_url}" readonly>
                                                    <a href="{$wygrane[i].link_url}" target="_blank" rel="noopener noreferrer" class="btn btn-default">{$t.orders_open_link|default:'Open'}</a>
                                                </div>
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            {/if}
                            <div role="tabpanel" class="tab-pane fade show active" id="orderInfo{$wygrane[i].id}">
                                <div class="user-order-modal__stack">
                                    {if $wygrane[i].payment_waiting_activation}
                                        <div class="alert alert-success">
                                            <strong>{$t.payment_paid_pending_activation_title|default:'Payment confirmed.'}</strong><br />
                                            {$t.payment_paid_pending_activation_text|default:'This order has already been marked as paid. The subscription is now waiting for activation by the admin and you do not need to create a new payment request.'}
                                        </div>
                                    {/if}
                                    <div>
                                        <label class="form-label">{$t.orders_status|default:'Status'}</label>
                                        <div class="user-order-modal__status-value {$wygrane[i].status_visual.class|default:'orders-status-neutral'}">
                                            <i class="{$wygrane[i].status_visual.icon|default:'fa fa-circle'}" aria-hidden="true"></i>
                                            <span>{$t[$wygrane[i].status_visual.label_key]|default:$wygrane[i].status_visual.label|default:'Status'}</span>
                                        </div>
                                        {if $wygrane[i].status == 2}
                                            <span class="badge badge-danger mt-2">Expired</span>
                                        {/if}
                                    </div>
                                    <div>
                                        <label class="form-label">{$t.history_column_date|default:'Date'}</label>
                                        <input type="text" class="form-control user-order-modal__readonly" value="{$wygrane[i].created_display}" readonly>
                                    </div>
                                    <div>
                                        <label class="form-label">{$t.order_expires_at|default:'Expires at'}</label>
                                        <input type="text" class="form-control user-order-modal__readonly" value="{if $wygrane[i].expiry}{$wygrane[i].expiry}{else}{$t.order_no_expiry|default:'No expiry'}{/if}" readonly>
                                    </div>
                                    <div>
                                        <label class="form-label">{$t.orders_note|default:'Note'}</label>
                                        <textarea class="form-control user-order-modal__readonly" rows="3" readonly>{if $wygrane[i].note neq ''}{$wygrane[i].note}{else}{$t.orders_note_empty|default:'No note added.'}{/if}</textarea>
                                    </div>
                                    {if $can_pending_payment_actions}
                                        <div class="user-order-modal__danger-action">
                                            <a href="del-order-{$wygrane[i].id}" class="btn btn-danger btn-lg btn-block w-100 remove">
                                                <i class="fa fa-trash" aria-hidden="true"></i> {$t.orders_action_remove|default:'Remove'}
                                            </a>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                            {if $order_sales_available && ($can_pending_payment_actions || (($wygrane[i].status == 1 || $wygrane[i].status == 2) && $wygrane[i].product_type|default:'subscription' neq 'credits'))}
                                <div role="tabpanel" class="tab-pane fade" id="orderActions{$wygrane[i].id}">
                                    <div class="user-order-modal__actions-stack">
                                        {if $can_pending_payment_actions}
                                            <a href="order-payment-{$wygrane[i].id}" class="btn btn-dark btn-lg btn-block">
                                                <i class="fa fa-credit-card" aria-hidden="true"></i> {$t.orders_action_payment|default:'Payment'}
                                            </a>
                                            <a href="del-order-{$wygrane[i].id}" class="btn btn-default btn-lg btn-block remove">
                                                <i class="fa fa-trash" aria-hidden="true"></i> {$t.orders_action_remove|default:'Remove'}
                                            </a>
                                        {/if}
                                        {if $wygrane[i].status == 1 && $wygrane[i].product_type|default:'subscription' neq 'credits'}
                                            <a href="orders?order_extend={$wygrane[i].id}" class="btn btn-success btn-lg btn-block">
                                                <i class="fa fa-history" aria-hidden="true"></i> {$t.orders_action_extend|default:'Extend'}
                                            </a>
                                        {/if}
                                        {if $wygrane[i].status == 2 && $wygrane[i].product_type|default:'subscription' neq 'credits'}
                                            <a href="orders?order_renew={$wygrane[i].id}" class="btn btn-danger btn-lg btn-block">
                                                <i class="fa fa-refresh" aria-hidden="true"></i> {$t.orders_action_renew|default:'Renew'}
                                            </a>
                                        {/if}
                                        {if $wygrane[i].extend}
                                            <div class="user-order-modal__extend-history">
                                                <strong>{$t.orders_extensions|default:'Extensions'}</strong>
                                                {foreach from=$wygrane[i].extend item=foo}
                                                    <p><i class="fa fa-history" aria-hidden="true"></i> {$foo.date}</p>
                                                {/foreach}
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {/section}
{/if}
