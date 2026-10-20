{if $payment_test_mode_notice_enabled|default:false}
<aside class="customer-test-notice" role="note" aria-label="{$t.payment_test_mode_notice_title|escape:'html'}">
    <span class="customer-test-notice__icon" aria-hidden="true"><i class="fa fa-flask"></i></span>
    <div><strong>{$t.payment_test_mode_notice_title|escape:'html'}</strong><p>{$t.payment_test_mode_notice_text|escape:'html'}</p></div>
</aside>
{/if}
