{if $user.id == 2}
{if $wybrana_nagroda.test == 0 && $wybrana_nagroda.dostawca == 0}
<!-- Modal -->
<div id="modal_payments" class="modal fade" role="dialog">
  <div class="modal-dialog"> 
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-credit-card" aria-hidden="true"></i> Forma płatności {$wybrana_nagroda.package_discount} x</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row center">
           <div id="payments">
                <h1 class="text-left">Jak zapłacić za Pakiet?</h1>
                <p class="text-left">Wybierz rodzaj płatności za wybrany Pakiet Bingoo.</p>
                <p class="text-left">Od teraz możesz zapłacić za swój Pakiet Bingoo używając kryptowalut.</p>
                <p class="text-left">W przypadku płatności kryptowalutami otrzymasz rabat <span class="strong lead yellow">-10%</span></p>
                <hr />
                <ul class="nav nav-tabs"> 
                  <li class="box_input col-xs-6 col-sm-3 col-md-3 col-lg-3 active">
                  	<a data-toggle="tab" href="#paypal">
                    	<img src="/img/crypto/pp.png" class="img-responsive" alt="img" /> Paypal
                    </a>
                  </li>
                  {if $crypto}
                  {section name=i loop=$crypto}
                  <li class="box_input col-xs-6 col-sm-3 col-md-3 col-lg-3"> 
                  	<a data-toggle="tab" href="#crypto_{$crypto[i].id}">
                    	<span class="discount">taniej -10%</span>
                    	<img src="{$crypto[i].logo_url}" class="img-responsive" alt="img" /> {$crypto[i].name}
                    </a>
                  </li>
                  {/section}
                  {/if}
                </ul>
                <hr />
                
                <div class="tab-content">
                  <!-- Paypal -->
                  <div id="paypal" class="tab-pane fade in active">
                      <div class="form-group text-left" id="payment_form">
                            	<h2>
                                	<i class="fa fa-paypal" aria-hidden="true"></i> Zapłać przez Paypal
                                </h2>
                                <p class="strong">Po dokonaniu płatności prosimy sprawdzić SPAM w przypadku braku potwierdzenia e-mail w skrzynce odbiorczej.</p>
                                
        						<p class="strong">Czas realizacji zamówienia może trwać od kilku minut do kilku godzin - potwierdzenie otrzymasz na adres e-mail.<br />Prosimy o cierpliwość każde zamówienie jest realizowane tego samego dnia.</p>
                                <p class="strong" style="color:red;">[UWAGA] Po dokonaniu płatności wróć na naszą stronę klikając przycisk "Powrót do strony sprzedawcy"</p> 
                                    <div class="paypal_button">
                                            {$wybrana_nagroda.kod_paypal}
                                            <i class="fa fa-arrow-left" aria-hidden="true"></i>
                                    </div>
                        </div>
                  </div>
                  {if $crypto}
                  {section name=i loop=$crypto}
                  <div id="crypto_{$crypto[i].id}" class="tab-pane fade">
                    <div class="row">
                        <div class="col-sm-6">
                            <img src="{$crypto[i].qr_url}" class="img-responsive" alt="img" />
                            <p class="qr_code">{$crypto[i].adress_code} <!--<a href="#" title="copy"><i class="fa fa-copy" aria-hidden="true"></i></a>--></p>
                            <p class="small">(Aby dokonać płatności zeskanuj QR Code)</p>
                        </div>
                        <div class="col-sm-6 text-left crypto_desc">
                            <h3><img src="{$crypto[i].logo_url}" class="img-responsive icon" alt="img" /> {$crypto[i].name} ({$crypto[i].symbol}) <span>-10%</span></h3>
                            <p>Aby zapłacić w {$crypto[i].name} musisz posiadać wymaganą liczbę <strong class="yellow">({$crypto[i].symbol})</strong> na swoim crypto-portfelu:</p>
                            <ul>
                            	<li>Włącz portfel walutowy np. <strong class="yellow">Bitpanda, Crypto.com</strong></li>
                                <li>Wybierz w portfelu walutę: <strong class="yellow">{$crypto[i].name} ({$crypto[i].symbol})</strong></li>
                                <li>Kliknij: <strong class="yellow">Wyślij / Send</strong></li>
                                <li>Wybierz kwotę do wysłania i zatwierdź transakcję</li>
                            </ul>
                            <p class="package_name">{$wybrana_nagroda.nazwa} <span class="new_price">{$wybrana_nagroda.discount_price} {$ustawienia.nazwa_waluty}</span></p>
                            <p class="price"><span class="text">Cena zakupu ({$crypto[i].symbol}):</span><span class="ammount_price">{$crypto[i].ammount} <img src="{$crypto[i].logo_url}" class="img-responsive icon" alt="img" /><span class="ammount_desc">(wyślij podaną wyżej kwotę {$crypto[i].symbol})</span></span><span class="token_price">1 {$crypto[i].symbol} = {$crypto[i].rate} €</span><span class="desc"><span>taniej o  -{$wybrana_nagroda.package_discount} {$ustawienia.nazwa_waluty}</span></span></p>
                            <a href="#" class="btn btn-success btn-block border-radius" title=""> 
                            	<i class="fa fa-paper-plane-o" aria-hidden="true"></i> Dokonaj płatności
                            </a>
                        </div>
                    </div>
                  </div>
                  {/section}
                  {/if}
                </div>
                <div id="modal_faq">
               	    <h3>Zapłać kryptowalutami w serwisie Bitpanda <span>FAQ</span></h3>
                    <img src="/img/crypto/bitpanda.png" class="img-responsive crypto" alt="img" />
                    <br />
                    <p class="text-jusify">Jest wiele dostępnych aplikacji na których możesz kupić dowolne kryptowaluty. Ze względu na łatwość użytkowania polecamy darmowy potrfel <span class="strong yellow">Bitpanda.com</span> gdzie w łatwy sposób można kupić kryptowaluty płacąć kartą kredytową lub zwykłym przelewem bankowym. Wpłata wygląda podobnie jak w serwisie Paypal a pieniądze są na koncie Bitpanda w ciągu kilku minut. Depozyt możesz również w każdej chwili wymienić na EURO lub inną walutę i wypłacić powrotnie do banku lub skorzystać z darmwej karty płatniczej VISA którą możesz płacić w internecie oraz w każdym sklepie obsługującym płatności VISA.</p>
                    <br />
                    <p class="yellow strong">- Dramowa rejestracja i brak ukrytych opłat w Bitpanda.com</p>
                    <p class="yellow strong">- Możliwość płatności kartą Mastercard, VISA lub przelewem</p>
                    <p class="yellow strong">- Twój depozyt może się zwiększyć jeśli zakupiona ktyptowaluta zwiększy wartość</p>
                    <p class="yellow strong">- Darmowa aplikacja Bitpanda - kliknij poniżej:</p>
                    <hr />
                    <div class="download_apps">
                        <a href="https://bitpanda.page.link/?link=https://www.bitpanda.com/user/register&apn=com.bitpanda.bitpanda&ofl=https://play.google.com/store/apps/details?id=com.bitpanda.bitpanda&ifl=https://apps.apple.com/at/app/bitpanda/id1449018960?l=en&ibi=com.bitpanda.bitpanda" target="_blank" title="download">
                            <img src="/img/android-download.png" class="img-responsive" alt="img" />
                        </a>
                        <a href="https://bitpanda.page.link/?link=https://www.bitpanda.com/user/register&apn=com.bitpanda.bitpanda&ofl=https://apps.apple.com/at/app/bitpanda/id1449018960?l=en&ifl=https://apps.apple.com/at/app/bitpanda/id1449018960?l=en&ibi=com.bitpanda.bitpanda" target="_blank" title="download"> 
                            <img src="/img/ios-download.png" class="img-responsive" alt="img" />
                        </a>
                    </div>
                </div>
        </div>
      </div>
    </div>
  </div>
</div>
{/if}
{/if}