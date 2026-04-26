{if !$wygrane}
    <hr />
    <p>{$t.orders_empty|default:'No available packages.'}</p>
{else}
    <hr />
    <div class="table-responsive">
        <table id="orders" class="table table-bordered table-striped orders-history-table">
        <thead>
            <tr class="center">
                <td>#</td>
                <td class="text-left">{$t.orders_product|default:'Product'}</td>
                <td>{$t.history_column_date|default:'Date'}</td>
                <td>{$t.orders_amount|default:'Amount'}</td>
                <td class="orders-payment-col">{$t.payment_title|default:'Payment'}</td>
                <td class="orders-delivery-col">Wysyłka</td>
                <td></td>
            </tr>
        </thead>
        <tbody>
        {section name=i loop=$wygrane}
            {assign var="can_pending_payment_actions" value=($wygrane[i].status == 0 and $wygrane[i].shipment == 0 and not $wygrane[i].payment_waiting_activation and ($wygrane[i].id <> $id_zamowienia_zaplac))}
            <tr align="center">
                <td>#{$wygrane[i].id}</td>
                <td class="text-left">
                    <strong>{$wygrane[i].provider_name} {$wygrane[i].name}</strong>
                    {if $wygrane[i].note neq ''}
                        <br><span class="text-muted">{$wygrane[i].note}</span>
                    {/if}
                    <div class="orders-product-badges">
                        <span class="badge badge-secondary">#{$wygrane[i].id}</span>
                        <span class="badge badge-secondary">{$wygrane[i].created_display}</span>
                    </div>
                </td>
                <td>{$wygrane[i].created_display}</td>
                <td>{$wygrane[i].price_label|default:$wygrane[i].price}</td>
                <td class="orders-payment-col">
                    {if $wygrane[i].payment_waiting_activation || $wygrane[i].payment == 2}
                        <i class="fa fa-check" style="color: green;"></i>
                    {else}
                        <i class="fa fa-times" style="color: red;"></i>
                    {/if}
                </td>
                <td class="orders-delivery-col">
                    {if $wygrane[i].shipment == 1}
                        <i class="fa fa-check" style="color: green;"></i>
                    {else}
                        <i class="fa fa-times" style="color: red;"></i>
                    {/if}
                </td>
                <td class="orders-history-actions">
                    {if $can_pending_payment_actions}
                        <a href="order-payment-{$wygrane[i].id}" class="btn btn-dark btn-xs">{$t.orders_action_payment|default:'Payment'}</a>
                    {/if}
                    <button type="button" class="btn btn-outline-dark btn-xs user-order-modal-trigger" data-toggle="modal" data-target="#orderModal{$wygrane[i].id}" data-order-modal-open="#orderModal{$wygrane[i].id}">
                        <i class="fa fa-search"></i> Details
                    </button>
                </td>
            </tr>
        {/section}
        </tbody>
    </table>
    </div>
    {$generator}
{/if}
