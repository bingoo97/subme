<div class="content-box referrals-page">
    <h1><a href="/"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$t.referrals_title}</h1>

    <div class="alert alert-dismissible alert-info">
       <button type="button" class="close" data-dismiss="alert">x</button>
       <i class="fa fa-info-circle" aria-hidden="true"></i> {$t.referrals_notice}
    </div>

    <div class="user-order-modal user-order-modal--static">
        <div class="user-order-modal__section">
            <h3>{$t.referrals_link_title}</h3>
            <p class="user-order-modal__access-copy">{$t.referrals_link_help}</p>
            <div class="auth-group">
                <input type="text" class="form-control auth-input" value="{$referral_link}" readonly onclick="this.select();">
            </div>
        </div>
        <div class="user-order-modal__section">
            <div class="row">
                <div class="col-sm-6">
                    <p><strong>{$t.referrals_total_label}</strong> {$referrals_total|default:0}</p>
                </div>
                <div class="col-sm-6">
                    <p><strong>{$t.referrals_converted_label}</strong> {$referrals_converted_total|default:0}</p>
                </div>
            </div>
        </div>
    </div>

{if $refy}
    <div class="table-responsive">
        <table class="table table-bordered table-responsive center">
            <thead class="strong">
                <tr>
                    <td class="hidden-xs"></td>
                    <td class="text-left">{$t.referrals_name}</td>
                    <td class="hidden-xs">{$t.referrals_date}</td>
                    <td>{$t.referrals_purchases}</td>
                    <td>{$t.referrals_status}</td>
                </tr>
            </thead>
            <tbody>
            {section name=i loop=$refy}
                <tr>
                    <td class="hidden-xs">{$smarty.section.i.index+1}.</td>
                    <td class="text-left">{$refy[i].email}</td>
                    <td class="hidden-xs">{$refy[i].registered_at|default:'-'}</td>
                    <td>{$refy[i].paid_orders_count|default:0}</td>
                    <td class="col-sm-3">
                        <span class="referrals-status referrals-status--{$refy[i].status_class|default:'warning'}">{$refy[i].status_label}</span>
                    </td>
                </tr>
            {/section}
            </tbody>
        </table>
    </div>
{else}
    <p>{$t.referrals_empty}</p>
{/if}
</div>
