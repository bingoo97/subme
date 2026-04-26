<div id="wybrana_aukcja">
{if $wybrana_aukcja}
       <div class="col-sm-12 bg-info"> 
         <div class="auction_content">
         	<div class="auction_header">
               <h2><i class="fa fa-youtube-play" aria-hidden="true"></i> {$wybrana_aukcja.nazwa}</h2>
               <p>- Dostęp do treści w serwisie - <span class="blue">"Read More / Paid Access to content Website"</p>
            </div>
            <div class="col-lg-12 item_top_grey">
                            <div class="col-md-3 info_price"> 
                                <span class="cena_aukcji">
                                   <span class="small visible-xs">Cena brutto</span> {$wybrana_aukcja.cena} <span>{$ustawienia.nazwa_waluty}</span>
                                </span>
                               
                            </div>
                            <div class="col-md-4 price"> 
                                <p class="strong hidden-xs">cena brutto</p>
                                <p class="hidden-xs">zapłać przez <span>Paypal</span></p>
                                <p class="hidden-xs">lub <span>kryptowalutami</span></p>
                            </div>
            </div>
            <div class="col-md-5 buy_now">
            		<h2 style="font-size: 22px; margin-bottom:15px;">Wybierz Pakiet:</h2>
                                 <form action="index.php?site=orders" method="POST" class="form-horizontal">
                                 {if $uprawnienia.zalogowany} 
                                 <p>Zamówienie zostanie dodane do Twojego konta</p>
                                 	<div class="form-group">
                                        <select class="form-control" name="typ_pliku">
                                            <option value="0">- File .m3u</option>
                                            <option value="1" >- File .mag</option>
                                            <option value="2">- File .e2</option>
                                        </select>
                                    </div>
                            	
                                	<div class="form-group">
                                   		<input type="hidden" name="id_pakietu" value="{$wybrana_aukcja.id}" />
                                        <button type="submit" name="wybierz_pakiet" class="btn btn-success btn-lg">
                                           Subskrybuj <i class="fa fa-angle-double-right" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                {/if}	
                                </form>
								<div class="crypto-bar">
									<a href="payments_crypto.html" title="Crypto">
										<i class="fa fa-btc" aria-hidden="true"></i> Zapłać -15% <i class="fa fa-angle-double-right" aria-hidden="true"></i>
									</a>
								</div>
								<br />
								<p class="yellow">*** Kliknij subskrybuj a następnie napisz do nas aby utworzyć płatność w kryptowalutach.</p>
           </div>
		   <div class="clr"></div>
		   <hr/>
           <div class="row">
               <div class="col-md-12" style="margin-top: 15px;">
               	   <h4><a href="products.html" class="strong yellow"><i class="fa fa-chevron-circle-right" aria-hidden="true"></i>
 Pozostałe Pakiety Bingoo</a></h4>
               </div>
           </div>
         </div>
       </div>
{else}
	<h3><i class="fa fa-ban" aria-hidden="true"></i> Wystąpił błąd</h3>
	<p>Przepraszamy, wybrana oferta nie istnieje lub została anulowana.</p>
	<br />
	<br />
{/if}
</div>