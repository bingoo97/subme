	<div class="content-box">
			<h1><a href="orders"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> Add new</h1>
			{if $providers}
			<form action="" method="post" class="form-horizontal" onsubmit="this.dataset.submitting='1'; document.getElementById('add_product').disabled = true;" data-choice-label="{$t.order_add_selection_label|default:'Your choice:'}" data-price-label="{$t.order_add_purchase_price_label|default:'Purchase price:'}">
                     <input type="hidden" name="_csrf" value="{$csrf_token|default:''}" />
                     <input type="hidden" name="order_add_token" value="{$order_add_token|default:''}" />
                     <input type="hidden" name="add_product" value="1" />

					<div class="row g-3">
									 <div class="col-auto">
										 <img src="/img/offer.png" alt="Offer" width="45" height="45">
									 </div>
									 <div class="col">
										 <p class="text-muted mb-0" style="max-width: 400px;">
                                             {if $order_catalog_product_type|default:'subscription' eq 'mixed'}
                                                 {$t.order_add_provider_description_mixed|default:'Select the provider and then choose any available subscription or credits product for this account.'}
                                             {elseif $order_catalog_product_type|default:'subscription' eq 'credits'}
                                                 {$t.order_add_provider_description_credits|default:'Select the provider for the credits order. Each provider can have different credit packages and pricing.'}
                                             {else}
                                                 {$t.order_add_provider_description|default:'Select the service provider for your subscription. Each provider offers different packages and pricing options.'}
                                             {/if}
                                         </p>
									 </div>
					</div>

					 <div class="form-group">
							 <div class="col-lg-6">
								 <label class="form-label mt-3" for="id_provider">{$t.order_add_provider_label|default:'Select provider'}:</label>

								 <select class="form-control mb-2 strong" id="id_provider" onchange="check_product(this);">
												<option value="0" id="select_input">- Select -</option>
											{section name=i loop=$providers}
												<option value="{$providers[i].id}"{if $providers|@count == 1} selected{/if}>{$providers[i].name}</option>
											{/section}
								 </select>
							 </div>
					 </div>
					 <div id="select_product"></div>
                     <div class="form-group">
							 <div class="col-lg-6">
								 <p class="mt-4">{$t.orders_note_label|default:'Provide additional information below'}</p>
								 <textarea class="form-control" name="note" rows="5" placeholder="{$t.orders_note_placeholder|default:'e.g. provide MAC or application name ...'}"></textarea>
								 <p class="fs-2 border-red text-center strong mt-3" id="selected_product_price_note" style="display:none;"></p>
							 </div>
					 </div>
                     <hr />
					 <div class="form-group">
						<div class="col-lg-6">
						   <button type="button" onclick="history.back();" class="btn btn-default btn-lg">
                              <i class="fa fa-angle-double-left" aria-hidden="true"></i> Back 
                           </button>
						   <button type="submit" class="btn btn-dark btn-lg" id="add_product" disabled>
							  {$t.order_add_continue|default:'Przejdź dalej'} <i class="fa fa-angle-double-right" aria-hidden="true"></i>
						   </button>
                           
						</div>
					 </div>   
			</form> 
			{else}
			<p>No products available.</p>
			{/if}
 	</div>
    
