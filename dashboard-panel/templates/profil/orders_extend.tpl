    <div class="content-box">
		<h1><a href="orders"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> Extend</h1>
         {if $selected}
         <p class="strong">{$selected.name} (#{$selected.id})</p>
		 <hr/>
                   <form action="" method="post" class="form-horizontal">
                           <input type="hidden" class="form-control" name="id" value="{$selected.id}" />
                           <div class="form-group">
                                 <div class="col-lg-12">
                                     <select class="form-control price-option" name="id_pakietu"> 
                                        {section name=i loop=$products}
                                              <option value="{$products[i].id}">{$products[i].name} ({$products[i].price} {$reseller.currency_symbol})</option>  
                                        {/section}
                                     </select>
                                 </div>
                         </div>
                         <hr />
                         <div class="form-group">
                            <div class="col-lg-12">
                               <a href="order-payment-{$selected.id}" class="btn btn-success btn-lg" title="Payment">
                                  <i class="fa fa-btc" aria-hidden="true"></i> Click to Payment <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                               </a>
                            </div>
                         </div>  
                    </form>
         {else} 
            <div class="alert alert-dismissible alert-danger">
              <i class="fa fa-minus-circle"></i> No package available.
            </div>
         {/if}
 </div>