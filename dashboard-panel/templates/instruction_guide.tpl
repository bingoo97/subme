<div class="content-box info-page">
    <div class="payment-wizard__header">
        <h1><a href="/instructions"><i class="fa fa-chevron-circle-left back" aria-hidden="true"></i></a> {$instruction_guide_title}</h1>
    </div>

    {if $instruction_guide_site == 'instruction-crypto-exchange'}
        <img src="img/cex.png" class="img-responsive" alt="img" />
        <br />
        <p class="text-justify"><strong>Giełdy kryptowalutowe</strong> to platformy umożliwiające zakup, sprzedaż oraz wymianę kryptowalut na fiat (tradycyjne waluty) oraz inne kryptowaluty.</p>
        <p class="text-justify">Do najpopularniejszych giełd należą <strong>Binance, Coinbase, Crypto.com, ByBit</strong>. Każda z nich oferuje możliwość zakupu kryptowalut kartą płatniczą, przelewem bankowym lub innymi metodami płatności.</p>
        <p class="text-justify">To proste - po prostu wybierasz dowolną kryptowalutę, wpisujesz kwotę, płacisz przelewem lub przez ApplePay lub Google Pay i to wszystko!</p>
        <hr/>
        <div class="exchange-buttons mb-3">
            <a href="https://www.binance.com" class="btn btn-sm" style="background-color: #F3BA2F; color: #000;" target="_blank" title="Binance">
                <img src="img/crypto/exchanges/bnb.png" class="exchange-logo" alt="Binance" /> Binance
            </a>
            <a href="https://www.coinbase.com" class="btn btn-primary btn-sm" target="_blank" title="Coinbase">
                <img src="img/crypto/exchanges/coinbase.jpeg" class="exchange-logo" alt="Coinbase" /> Coinbase
            </a>
            <a href="https://crypto.com" class="btn btn-sm" style="background-color: #0042C2; color: #fff;" target="_blank" title="Crypto.com">
                <img src="img/crypto/exchanges/cro.png" class="exchange-logo" alt="Crypto.com" /> Crypto.com
            </a>
            <a href="https://www.bybit.com" class="btn btn-sm" style="background-color: #000; color: #F3BA2F;" target="_blank" title="ByBit">
                <img src="img/crypto/exchanges/bybit.jpeg" class="exchange-logo" alt="ByBit" /> ByBIT Exchange
            </a>
        </div>
        <br />
        <h1 class="home_title">Jak zapłacić?</h1>
        <ul style="list-style: none;padding: 5px;">
            <li>1. Załóż darmowe konto na wybranej giełdzie np. <span class="btn btn-xs btn-outline-dark strong">Binance</span> <span class="btn btn-xs btn-outline-dark strong">Coinbase</span> <span class="btn btn-xs btn-outline-dark strong">Crypto.com</span> <span class="btn btn-xs btn-outline-dark strong">ByBit</span></li>
            <li>2. Zweryfikuj swoje konto (KYC) - wgraj dokument tożsamości. Proces trwa kilka minut.</li>
            <li>3. Doładuj konto kartą płatniczą, ApplePay lub przelewem.</li>
            <li>4. Przejdź do sekcji <span class="btn btn-xs btn-outline-dark strong">Kup / Buy</span></li>
            <li>5. Wybierz kryptowalutę np. <span class="btn btn-xs btn-outline-dark strong">Bitcoin</span> <span class="btn btn-xs btn-outline-dark strong">Dogecoin</span><br />- <strong class="red">napisz do nas przed płatnością aby dokonać wyliczenia kwoty.</strong> <span class="btn btn-xs btn-danger strong"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span></li>
            <li>6. Wpisz kwotę zakupu <br/><strong class="red border-red border-radius text-justify">UWAGA! Prowizje są niewielkie, ale przy wypłacie dolicz 1-2 EUR na opłaty sieciowe.</strong></li>
            <li>7. Dokonaj zakupu i poczekaj na zatwierdzenie.</li>
            <li>8. Przejdź do sekcji <span class="btn btn-xs btn-outline-dark strong">Wypłata / Withdraw</span></li>
            <li>9. Wybierz sieć <span class="btn btn-xs btn-outline-dark strong">network</span> zgodnie z informacją od nas np. <span class="btn btn-xs btn-outline-dark strong">Bitcoin Network</span><br/><strong class="red border-red border-radius text-justify mt-2">UWAGA! Wybierz odpowiednią sieć - w przypadku wybrania złej sieci płatność zostanie odrzucona lub transfer nie dotrze do nas.</strong></li>
            <li>10. Wpisz <strong>adres portfela</strong> otrzymany od nas.</li>
            <li>11. Wpisz <strong>kwotę</strong> i zatwierdź wypłatę.</li>
            <li>12. Gratulacje! Dokonałeś płatności!</li>
        </ul>
        <p class="text-muted">*** Płatność z giełdy kryptowalutowej jest realizowana zazwyczaj w ciągu 10-60 min od chwili wysłania. Czas zależy od wybranej kryptowaluty i sieci. Poinformuj nas na Live Chat o dokonanej transakcji i podaj szczegóły transferu.</p>
        <hr/>
        <div class="alert alert-info"><i class="fa fa-info-circle" aria-hidden="true"></i> Jeśli nie jesteś pewny ile dokładnie kupić napisz do nas na LIVE CHAT a wyliczymy to za Ciebie.</div>
        <div class="alert alert-info"><i class="fa fa-info-circle" aria-hidden="true"></i> Sprawdź przed wysłaniem ile wynosi opłata transakcyjna! Ostateczne prowizje i cały koszt transakcji zobaczysz w podsumowaniu wypłaty przed wysłaniem. Jeśli masz wątpliwości to napisz do nas!</div>
        <div class="alert alert-danger"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <strong>UWAGA!</strong> W przypadku płatności z giełdy kryptowalutowej należy podać imię i nazwisko osoby do której jest wykonywany przelew w zależności od wymogów giełdy. Prosimy nie wpisywać nic na temat Subskrybcji czy opłaty za Stream np. {$settings.site_name} itp.</div>
    {elseif $instruction_guide_site == 'instruction-revolut'}
        <img src="img/rev-crypto.jpg" class="img-responsive" alt="img" />
        <br />
        <p class="text-justify">{$t.instruction_revolut_intro_1|default:'<strong>Revolut</strong> jest międzynarodowym bankiem i jedną z najpopularniejszych aplikacji płatniczych na świecie.' nofilter}</p>
        <p class="text-justify">{$t.instruction_revolut_intro_2|default:'Dostęp do kryptowalut w aplikacji jest już od kilku lat, a w ostatnim czasie aplikacja udostępniła możliwość wysyłania kryptowalut na zewnętrzne adresy i szybkie płatności kryptowalutowe między użytkownikami.'}</p>
        <hr/>
        <a href="https://revolut.com/referral/?referral-code=marcinuw1!FEB2-24-AR-L1" class="btn btn-warning btn-lg w-100 mb-3" target="_blank" title="download">
            {$t.instruction_revolut_download_button|default:'Pobierz aplikację Revolut'} <i class="fa fa-angle-double-right" aria-hidden="true"></i>
        </a>
        <br />
        <h1 class="home_title">{$t.instruction_revolut_how_to_pay_title|default:'Jak zapłacić?'}</h1>
        <ul style="list-style: none;padding: 5px;">
            <li>{$t.instruction_revolut_step1_html|default:'1. Załóż darmowe konto w Revolut<br/> - <a class="strong blue" title="" href="https://revolut.com/referral/?referral-code=marcinuw1!FEB2-24-AR-L1" target="_blank">Link do Revolut</a>' nofilter}</li>
            <li>{$t.instruction_revolut_step2_html|default:'2. Doładuj konto bankowe w Revolut za pomocą <strong>ApplePay, PayPal lub kartą VISA, Mastercard.</strong>' nofilter}</li>
            <img src="/img/crypto/revo/1.jpeg" class="img-responsive phone-img" alt="" /> 
            <li>{$t.instruction_revolut_step3_html|default:'3. Przejdź: <span class="btn btn-xs btn-outline-dark strong">Krypto</span> <i class="fa fa-caret-right" aria-hidden="true"></i>  <span class="btn btn-xs btn-outline-dark strong">Inwestuj</span>' nofilter}</li>
            <img src="/img/crypto/revo/2.jpeg" class="img-responsive phone-img" alt="" /> 
            <li>{$t.instruction_revolut_step4_html|default:'4. Wybierz kryptowalutę, którą chcesz zakupić np. <span class="btn btn-xs btn-outline-dark strong">Bitcoin</span> <span class="btn btn-xs btn-outline-dark strong">Dogecoin</span> <br />- <strong class="red">napisz do nas przed płatnością, aby dokonać wyliczenia kwoty do zakupu.</strong> <span class="btn btn-xs btn-danger strong"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' nofilter}</li>
            <img src="/img/crypto/revo/3.jpeg" class="img-responsive phone-img" alt="" />
            <li>{$t.instruction_revolut_step5_html|default:'5. Wpisz kwotę zakupu <br/><strong class="red border-red border-radius text-justify">UWAGA! Revolut pobiera prowizję w przypadku kryptowalut - należy kupić za przynajmniej 2-3 EUR więcej niż cena zamówienia. Jeśli chcesz wysyłać kryptowaluty bez prowizji operatora, przejdź na TrustWallet. Tam prowizja za przesłanie to zaledwie 0.10$.</strong>' nofilter}</li>
            <img src="/img/crypto/revo/4.jpeg" class="img-responsive phone-img" alt="" />
            <li>{$t.instruction_revolut_step6_html|default:'5. Dokonaj zakupu.' nofilter}</li>
            <li>{$t.instruction_revolut_step7_html|default:'6. Poczekaj na zatwierdzenie transakcji.' nofilter}</li>
            <li>{$t.instruction_revolut_step8_html|default:'7. Wybierz: <span class="btn btn-xs btn-outline-dark strong">Wyślij / Send</span>' nofilter}</li>
            <img src="/img/crypto/revo/5.jpeg" class="img-responsive phone-img" alt="" />
            <li>{$t.instruction_revolut_step9_html|default:'8. Wybierz: <span class="btn btn-xs btn-outline-dark strong">Dodaj adres portfela</span><br/><strong class="red border-red border-radius text-justify mt-2"> W przypadku płatności przez Revolut lub z giełdy kryptowalutowej należy podać imię i nazwisko osoby, do której jest wykonywany przelew.</strong>' nofilter}</li>
            <img src="/img/crypto/revo/6.jpeg" class="img-responsive phone-img" alt="" />
            <li>{$t.instruction_revolut_step10_html|default:'9. Wybierz: <span class="btn btn-xs btn-outline-danger strong">Portfel innej osoby <i class="fa fa-caret-right" aria-hidden="true"></i> TrustWallet</span>' nofilter}</li>
            <li>{$t.instruction_revolut_step11_html|default:'- Imię i nazwisko: <span class="btn btn-xs btn-outline-danger strong">otrzymasz od nas na Live Chat!</span>' nofilter}</li>
            <img src="/img/crypto/revo/7.jpeg" class="img-responsive phone-img" alt="" />
            <li>{$t.instruction_revolut_step12_html|default:'10. Wpisz <strong>adres portfela</strong>, który otrzymasz od nas. <span class="btn btn-xs btn-danger strong"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' nofilter}</li>
            <li>{$t.instruction_revolut_step13_html|default:'11. Wpisz <strong>nazwę portfela</strong> np. portfel zewnętrzny (opcjonalnie)<br /><strong class="red border-red border-radius">UWAGA! Prosimy nie wpisywać nic na temat subskrypcji czy opłaty za Stream lub TV itp.</strong>'|replace:'{site_name}':$settings.site_name nofilter}</li>
            <li>{$t.instruction_revolut_step14_html|default:'12. Wybierz dodany wcześniej portfel z listy dostępnych portfeli.' nofilter}</li>
            <li>{$t.instruction_revolut_step15_html|default:'13. Wpisz <strong>kwotę transakcji</strong><br />- <strong class="red">napisz do nas przed płatnością, aby dokonać wyliczeń liczby tokenów do wysłania.</strong> <span class="btn btn-xs btn-danger strong"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span>' nofilter}</li>
            <li>{$t.instruction_revolut_step16_html|default:'14. Zatwierdź klikając: <span class="btn btn-xs btn-outline-dark strong">Wyślij</span>' nofilter}</li>
            <li>{$t.instruction_revolut_step17_html|default:'15. Gratulacje! Dokonałeś płatności!' nofilter}</li>
        </ul>
        <p class="text-muted">{$t.instruction_revolut_note|default:'*** Płatność z Revolut jest realizowana zazwyczaj w ciągu 5-30 min od chwili wysłania. Poinformuj nas na Live Chat o dokonanej transakcji i podaj szczegóły transferu.'}</p>
        <hr/>
        <div class="alert alert-info"><i class="fa fa-info-circle" aria-hidden="true"></i> {$t.instruction_revolut_alert_1|default:'Jeśli nie jesteś pewny, ile dokładnie kupić, napisz do nas na LIVE CHAT, a wyliczymy to za Ciebie.'}</div>
        <div class="alert alert-info"><i class="fa fa-info-circle" aria-hidden="true"></i> {$t.instruction_revolut_alert_2|default:'Sprawdź przed wysłaniem, ile wynosi opłata transakcyjna! Ostateczne prowizje i cały koszt transakcji zobaczysz po wpisaniu kwoty oraz naszego adresu do wysyłki w podsumowaniu transakcji przed wysłaniem. Jeśli masz wątpliwości, to napisz do nas!'}</div>
    {elseif $instruction_guide_site == 'instruction-smart-iptv'}
        <img src="/img/logo_smart.png" class="img-responsive" alt="Smart IPTV" />
        <br />
        <p class="text-justify"><strong>W aplikacji Smart IPTV można wgrać listę m3u na ich stronie:</strong></p>
        <div class="border-red red">Uwaga! Przed wgraniem nowej listy usuń zawsze starą playlistę, nawet jeśli nie jest widoczna na TV</div>
        <ul style="list-style: none;padding: 5px;">
            <li>1. Przejdź do strony Moja lista IPTV: <a href="https://siptv.app/mylist/" target="_blank" class="btn btn-sm btn-outline-dark">https://siptv.app/mylist/</a></li>
            <li>2. Wprowadź MAC adres urządzenia oraz załaduj playliste</li>
            <li>3. Wybieramy kraj <span class="btn btn-xs btn-outline-dark strong">Poland</span> oraz opcje <span class="btn btn-xs btn-outline-dark strong">Logos</span> <i class="fa fa-caret-right" aria-hidden="true"></i> <span class="btn btn-xs btn-outline-dark strong">Save online</span> <i class="fa fa-caret-right" aria-hidden="true"></i> <span class="btn btn-xs btn-outline-dark strong">Detect EPG</span></li>
            <li>4. Zaznacz opcję <span class="btn btn-xs btn-outline-dark strong">save online</span> przyciśnij przycisk <span class="btn btn-xs btn-outline-dark strong">Send/Upload</span></li>
            <li>5. Wyłączamy i włączamy ponownie urządzenie</li>
        </ul>
        <br/>
        <a href="/img/upload_playlist.gif" target="_blank"><img class="img-responsive thumbnail" src="/img/upload_playlist.gif" alt=""></a>
    {elseif $instruction_guide_site == 'instruction-ott-player'}
        <img src="/img/ott/logo-ott.jpg" class="img-responsive" alt="OTT Player" />
        <br />
        <p class="text-justify"><strong>Instrukcja jak korzystać z aplikacji OTT-Player:</strong></p>
        <p class="text-justify">Aplikację można zainstalować na urządzeniach z systemem Android lub Windows.</p>
        <hr/>
        <ul style="list-style: none;padding: 5px;">
            <li>1. Włączamy aplikację i wybieramy jeden z dostępnych języków np. <span class="btn btn-xs btn-outline-dark strong">Polski</span> <i class="fa fa-caret-right" aria-hidden="true"></i> <span class="btn btn-xs btn-outline-dark strong">Manual setup</span></li>
            <li>2. Przewijamy listę z wtyczkami na sam dół i wybieramy <span class="btn btn-xs btn-outline-dark strong">New Look</span></li>
            <li>3. Wpisujemy nazwę użytkownika i hasło (dane do logowania znajdziesz przy zamówieniu)</li>
            <li>4. Klikamy <span class="btn btn-xs btn-outline-dark strong">Uruchom ponownie odtwarzacz</span> i gotowe</li>
        </ul>
        <p class="text-muted">*** OTT-Player umożliwia również korzystanie bezpośrednio z linków [m3u] od innych dostawców</p>
        <hr/>
        <img class="img-responsive" style="padding: 1px; border-radius: 10px; margin: 2px 0; border: 1px solid #3d505a;" src="/img/ott/1.png" alt="" />
        <br/>
        <img class="img-responsive" style="padding: 1px; border-radius: 10px; margin: 2px 0; border: 1px solid #3d505a;" src="/img/ott/2.png" alt="" />
        <br/>
        <img class="img-responsive" style="padding: 1px; border-radius: 10px; margin: 2px 0; border: 1px solid #3d505a;" src="/img/ott/3.png" alt="" />
    {elseif $instruction_guide_site == 'instruction-newlook'}
        <img src="/img/new_look.png" class="img-responsive" alt="NewLook" />
        <br />
        <p class="text-justify"><strong>Instrukcja jak korzystać z aplikacji NewLook 4 i NewLook 2:</strong></p>
        <p class="text-justify">Aplikację można zainstalować na urządzeniach z systemem Android.</p>
        <hr/>
        <ul style="list-style: none;padding: 5px;">
            <li>1. Zainstaluj aplikację NewLook 4 lub NewLook 2 na urządzeniu z Android</li>
            <li>2. Włącz aplikację i postępuj zgodnie z kreatorem konfiguracji</li>
            <li>3. Wpisz dane do logowania (nazwę użytkownika i hasło) które znajdziesz przy zamówieniu</li>
            <li>4. Zakończ konfigurację i gotowe</li>
        </ul>
        <p class="text-muted">*** Wystarczy zainstalować aplikację na urządzeniu z Android i postępować zgodnie z kreatorem, wpisując dane do logowania</p>
    {else}
        <img src="/img/tw2.png" class="img-responsive" alt="Trust Wallet" />
        <br />
        <p class="strong text-justify">{$t.instruction_trust_wallet_intro_1|default:'Dokonaj płatności kryptowalutami za Pakiet. Zachęcamy do pobrania darmowej aplikacji TrustWallet na telefon. Bank Revolut połączył się z TrustWallet - teraz możesz kupić kryptowaluty, płacąc z salda Revolut lub kartą przez ApplePay lub GooglePay.'}</p>
        <p class="strong">{$t.instruction_trust_wallet_intro_2|default:'Po instalacji postępuj zgodnie z instrukcjami, aby utworzyć nowy portfel, a następnie doładuj saldo konta np. kartą płatniczą.'}</p>
        <hr />
        <div class="download_apps">
            <a href="https://play.google.com/store/apps/details?id=com.wallet.crypto.trustapp&referrer=utm_source%3Dwebsite" target="_blank" title="download">
                <img src="/img/android-download.png" class="img-responsive" style="background: #000;" alt="Pobierz na Android" />
            </a>
            <a href="https://apps.apple.com/app/apple-store/id1288339409?mt=8" target="_blank" title="download">
                <img src="/img/ios-download.png" class="img-responsive" style="background: #000;" alt="Pobierz na iOS" />
            </a>
        </div>
        <hr/>
        <div class="alert alert-dismissible alert-info">
            <i class="fa fa-info-circle" aria-hidden="true"></i> {$t.instruction_trust_wallet_info_alert|default:'Aplikacja TrustWallet obsługuje płatności przez ApplePay lub GooglePay oraz płatności z Revolut bez dodatkowych weryfikacji.'}
        </div>
        <div class="instruction-content">
            <h1>{$t.instruction_trust_wallet_payment_guide_title|default:'Instrukcja płatności'}</h1>
            <p class="desc-title">1. {$t.instruction_trust_wallet_step1_title|default:'Pobierz aplikację TrustWallet'} - <a href="https://trustwallet.com/" target="_blank" class="strong" title="">{$t.instruction_trust_wallet_download|default:'Pobierz'}</a></p>
            <p class="desc-note">{$t.instruction_trust_wallet_step1_desc|default:'Aby kupić kryptowalutę potrzebujesz portfela. TrustWallet jest darmową aplikacją na telefon z Android lub iOS. Po instalacji postępuj zgodnie z instrukcjami aby utworzyć nowy portfel.'}</p>
            <p class="desc-title">2. {$t.instruction_trust_wallet_step2_title|default:'Następnie kliknij w przycisk "Środki" lub "Zasil swój portfel"'}</p>
            <img src="/img/crypto/trust/1.jpg" class="img-responsive phone-img" alt="" />
            <p class="desc-title">3. {$t.instruction_trust_wallet_step3_title|default:'Wybierz formę płatności'}</p>
            <p class="desc-note">{$t.instruction_trust_wallet_step3_desc|default:'Wybierz płatność przez ApplePay, GooglePay lub Revolut. Środki zostaną pobrane natychmiast po zatwierdzeniu.'}</p>
            <img src="/img/crypto/trust/2.jpg" class="img-responsive phone-img" alt="" />
            <p class="desc-title">4. {$t.instruction_trust_wallet_step4_title|default:'Wybierz kwotę i kryptowalutę'}</p>
            <p class="desc-note">{$t.instruction_trust_wallet_step4_desc|default:'Wybierz kryptowalutę <strong>np. Bitcoin</strong> lub <strong>Dogecoin - DOGE</strong> ma bardzo niską opłatę za wysłanie (ok. 0.10$). Wpisz kwotę za jaką chcesz kupić pakiet. Zalecamy płatności za 3-6 miesięcy aby uniknąć częstych płatności.' nofilter}</p> 
            <img src="/img/crypto/trust/3.jpg" class="img-responsive phone-img" alt="" />
            <p class="desc-title">5. {$t.instruction_trust_wallet_step5_title|default:'Wybierz operatora płatności'}</p>
            <p class="desc-note">{$t.instruction_trust_wallet_step5_desc|default:'Wybierz operatora płatności np. Revolut. Zostaniesz przekierowany do strony gdzie zalogujesz się i potwierdzisz płatność. Po dokonaniu płatności otrzymasz potwierdzenie na email. Środki pojawią się w portfelu w ciągu 5-15 minut.'}</p>
            <img src="/img/crypto/trust/4.jpg" class="img-responsive phone-img" alt="" />
            <p class="desc-title">6. {$t.instruction_trust_wallet_step6_title|default:'Zapłać za Pakiet'}</p>
            <p class="desc-note">{$t.instruction_trust_wallet_step6_desc|default:'Środki zostaną dodane do salda TrustWallet w ciągu 5-15 min.'}</p>
            <p class="desc-title">7. {$t.instruction_trust_wallet_step7_title|default:'Wyślij przelew na nasz adres portfela'}</p>
            <p class="desc-note">{$t.instruction_trust_wallet_step7_desc|default:'Kliknij na stronie głównej w zakupioną kryptowalutę np. Dogecoin a następnie gdy zobaczysz swoje saldo zakupionej kryptowaluty kliknij w przycisk "Send".'}</p> 
            <p class="desc-title">8. {$t.instruction_trust_wallet_step8_title|default:'Wprowadź dane odbiorcy'}</p>
            <p class="desc-note">{$t.instruction_trust_wallet_step8_desc|default:'Po kliknięciu w przycisk "Send" wprowadź nasze dane, na które chcesz przesłać płatność.<br/>Adres oraz kwotę płatności otrzymasz od nas - napisz do nas na LIVE CHAT. Skopiuj dane do płatności albo zeskanuj QR kod w swoim portfelu kryptowalutowym.' nofilter}</p>
            <img src="/img/crypto/trust/6.jpg" class="img-responsive phone-img" alt="" />
            <p class="desc-title">9. {$t.instruction_trust_wallet_step9_title|default:'Gratulacje! Właśnie zapłaciłeś za swój Pakiet!'}</p>
        </div>
        <hr />
        <div class="d-grid gap-2">
            <a href="https://trustwallet.com/" target="_blank" class="btn btn-dark btn-lg border-radius">
                <i class="fa fa-external-link" aria-hidden="true"></i> {$t.instruction_trust_wallet_download_button|default:'Download TrustWallet'}
            </a>

            {if $settings.support_chat_enabled == 1}
                <button type="button" class="btn btn-danger btn-lg payment-support-button" onclick="return openMessengerPanel('{$user.id}');">
                    <i class="fa fa-life-ring" aria-hidden="true"></i> {$t.instructions_contact_support|default:'Contact support'}
                </button>
            {/if}
        </div>
    {/if}

    <hr />
    <div class="d-grid gap-2">
        {if $settings.support_chat_enabled == 1 && ($instruction_guide_site == 'instruction-revolut' || $instruction_guide_site == 'instruction-crypto-exchange')}
            <button type="button" class="btn btn-danger btn-lg payment-support-button" onclick="return openMessengerPanel('{$user.id}');">
                <i class="fa fa-life-ring" aria-hidden="true"></i> {$t.instructions_contact_support|default:'Contact support'}
            </button>
        {/if}

        <a href="/instructions" class="btn btn-default btn-lg btn-back" title="{$t.back|default:'Back'}"><i class="fa fa-angle-double-left" aria-hidden="true"></i> {$t.back|default:'Back'}</a>
    </div>
</div>
