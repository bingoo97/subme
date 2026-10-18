<div class="content-box payment-wizard customer-payment-receipt">
 <div class="payment-wizard__section">
  <div class="alert alert-success" role="status">
   <strong><i class="fa fa-check-circle" aria-hidden="true"></i> {$t.payment_crypto_detected_title|default:'Payment detected'}</strong>
   <p class="mb-2"><strong>{$t.payment_crypto_received|default:'Received'}: {$detected_request.receipt_received_label|escape:'html'}</strong></p>
   {if $detected_request.receipt_underpaid|default:false}
   <p><strong>{$t.payment_crypto_requested|default:'Requested'}: {$detected_request.receipt_requested_label|escape:'html'} · {$t.payment_crypto_shortfall|default:'Shortfall'}: {$detected_request.receipt_shortfall_label|escape:'html'}</strong></p>
   <p>{$t.payment_crypto_underpaid_review|default:'The payment is lower than requested. An administrator will verify the amount and advise on the next step. Please do not send another payment until you agree the details.'}</p>
   {else}
   <p>{$t.payment_crypto_detected_help|default:'We detected your payment. It is awaiting verification by an administrator. Your balance and order will update after approval. Please do not send another payment.'}</p>
   {/if}
  </div>
 </div>
</div>
