<div id="homepage" class="home_login">
    {if $settings.page_logo}
    <div class="logo col-md-12 center">
        <a href="/" title="Home">
            <img src="{$settings.page_logo}" class="img-responsive" alt="{$settings.page_name|default:$t.brand_fallback}" />
        </a>
    </div>
    {else}
    <div class="logo col-md-12 center">
        <a href="/" title="Home">
            <img src="/img/logo.svg" class="img-responsive" alt="{$settings.page_name|default:$t.brand_fallback}" />
        </a>
    </div>
    {/if}

    <div class="logo-name col-md-12 center">
        <h1>{$reseller.name|default:$settings.site_name|default:$t.brand_fallback}</h1>
    </div>

    <div class="home_buttons home_login__buttons">
        <div class="col-sm-12">
            <a href="/login" title="{$t.login}">
                <div class="one_box">
                    <i class="fa fa-sign-in" aria-hidden="true"></i>
                    <p class="title">{$t.login}</p>
                </div>
            </a>
        </div>
        {if $settings.active_register == 1}
        <div class="col-sm-12">
            <a href="/register" title="{$t.register}">
                <div class="one_box">
                    <i class="fa fa-id-card-o" aria-hidden="true"></i>
                    <p class="title">{$t.register}</p>
                </div>
            </a>
        </div>
        {/if}
        {if $settings.contact_form_enabled == 1}
        <div class="col-sm-12">
            <a href="/contact" title="{$t.support}">
                <div class="one_box">
                    <i class="fa fa-envelope-o" aria-hidden="true"></i>
                    <p class="title">{$t.support}</p>
                </div>
            </a>
        </div>
        {/if}
    </div>
</div>
{if $homepage_service_overview_enabled|default:false}
<section class="homepage-service-overview">
    <div class="homepage-service-overview__card">
        <div class="homepage-service-overview__eyebrow">Poznaj możliwości platformy</div>
        <h2>Jak działa ten serwis i dlaczego cały proces jest prostszy niż w klasycznej sprzedaży prowadzonej przez wiadomości prywatne</h2>
        <p>Ta aplikacja została przygotowana po to, aby osoba zainteresowana usługą mogła przejść cały proces w uporządkowany sposób: od pierwszego kontaktu, przez rejestrację, wybór oferty i płatność, aż po późniejsze zarządzanie własnym kontem. Dla nowego użytkownika najważniejsze jest to, że nie musi znać zaplecza technicznego systemu ani rozumieć wszystkich skrótów używanych przez operatorów. Interfejs prowadzi użytkownika po gotowych sekcjach, a każda z nich odpowiada za konkretną część pracy. Dzięki temu zamiast zbierać informacje w wielu rozmowach, plikach i wiadomościach e-mail, wszystko trafia do jednego panelu, który porządkuje statusy, płatności, instrukcje i historię działań. To duża przewaga nad ręcznym modelem obsługi, w którym łatwo zgubić szczegóły zamówienia albo nie pamiętać, na jakim etapie znajduje się dana sprawa.</p>
        <p>Z perspektywy potencjalnego klienta największą wartością jest przejrzystość procesu zakupu. Po założeniu konta użytkownik widzi, jakie ma możliwości, gdzie przejść do zamówień, w jaki sposób opłacić usługę i gdzie wrócić do aktywnej płatności, jeśli nie została dokończona od razu. To ogranicza stres i zmniejsza liczbę pytań typu „co dalej?”, bo panel sam dzieli drogę na logiczne kroki. W praktyce oznacza to szybszą decyzję zakupową, mniejszą liczbę pomyłek przy płatnościach oraz łatwiejsze wdrożenie osoby, która wcześniej nie korzystała z podobnych systemów. Nawet jeśli użytkownik nie jest techniczny, nadal może poruszać się po serwisie intuicyjnie, bo zamiast skomplikowanej administracji dostaje zestaw prostych ekranów opisujących konkretne zadania.</p>
        <p>Drugim mocnym filarem jest obsługa zamówień i płatności. Serwis pozwala oddzielić to, co jest ofertą, od tego, co jest już realnym zamówieniem użytkownika. Klient może sprawdzić dostępne pakiety, uruchomić nowy proces zakupu, wznowić płatność oczekującą albo wrócić do aktywnej subskrypcji i przejść do jej odnowienia. W systemie mogą działać równolegle różne metody płatności, na przykład kryptowaluty oraz przelew bankowy, a każda z nich ma własne instrukcje i własny mechanizm statusów. To ważne, bo nie każdy użytkownik płaci w ten sam sposób. Jedna osoba woli szybkie rozwiązanie krypto, inna potrzebuje klasycznego przelewu, a panel potrafi utrzymać porządek w obu scenariuszach bez mieszania danych i bez konieczności ręcznego tłumaczenia każdego etapu przez support.</p>
        <div class="homepage-service-overview__list-block">
            <h3>Najważniejsze funkcje z punktu widzenia klienta</h3>
            <ul>
                <li>Rejestracja i logowanie do własnego panelu, w którym wszystkie sprawy są przypisane do jednego konta użytkownika.</li>
                <li>Lista zamówień z czytelnymi statusami, dzięki której łatwo sprawdzić, co jest aktywne, co wygasa i co nadal czeka na płatność.</li>
                <li>Płatności krypto i przelewy bankowe z osobnymi instrukcjami, kwotami oraz czasem ważności aktywnego żądania płatności.</li>
                <li>Historia działań i komunikatów, która pomaga wrócić do wcześniejszych decyzji bez szukania informacji poza systemem.</li>
                <li>Sekcja aplikacji i instrukcji, która prowadzi użytkownika od zakupu do realnego uruchomienia usługi na własnym urządzeniu.</li>
                <li>Ustawienia konta i zmiana hasła, czyli podstawowe narzędzia do utrzymania bezpieczeństwa oraz wygody korzystania z systemu.</li>
                <li>Kontakt z obsługą oraz live chat, które skracają czas reakcji, gdy klient potrzebuje pomocy po zakupie lub przed płatnością.</li>
            </ul>
        </div>
        <p>Warto zwrócić uwagę, że ten panel nie kończy się na samym sprzedaniu pakietu. Dobrze zaprojektowany serwis musi poradzić sobie również z tym, co dzieje się później: z odnowieniami, pytaniami klienta, zmianami statusów, problemami przy konfiguracji i potrzebą szybkiego przypomnienia instrukcji. Tutaj właśnie widać potencjał platformy jako narzędzia do długoterminowej obsługi relacji z użytkownikiem. Zamiast traktować klienta jednorazowo, system prowadzi jego konto dalej, przechowuje historię, porządkuje komunikację i ułatwia kolejne zakupy. To sprawia, że serwis może działać nie tylko jako prosta strona sprzedażowa, ale też jako pełne środowisko do utrzymania klienta i zmniejszania obciążenia ręcznej obsługi.</p>
        <p>Dla właściciela projektu albo osoby rozwijającej ofertę istotna jest z kolei skalowalność. Kiedy liczba klientów rośnie, ręczne pilnowanie tego, kto zapłacił, kto czeka na aktywację i kto pytał o pomoc, szybko staje się chaotyczne. Ten system porządkuje te obszary poprzez podział na role, statusy, przypisania płatności i osobne widoki administracyjne. Nawet jeśli użytkownik końcowy widzi prosty panel, pod spodem działa logika, która pilnuje aktywnych requestów płatniczych, danych kont, historii zmian oraz komunikacji. To właśnie taki fundament daje potencjał do dalszego rozwoju: można rozbudowywać ofertę, dodawać nowe okresy subskrypcji, kolejne instrukcje, nowe kanały kontaktu czy następne metody płatności bez konieczności przebudowywania całego doświadczenia od zera.</p>
        <div class="homepage-service-overview__list-block">
            <h3>Dlaczego ten model ma duży potencjał biznesowy i operacyjny</h3>
            <ul>
                <li>Porządkuje obsługę klienta w jednym miejscu, więc ogranicza chaos związany z pracą na kilku komunikatorach jednocześnie.</li>
                <li>Skraca drogę od wejścia na stronę do zakupu, bo użytkownik ma jasny podział na rejestrację, zamówienia, płatność i instrukcje.</li>
                <li>Zmniejsza liczbę błędów w płatnościach dzięki konkretnym kwotom, przypisanym adresom i prostym komunikatom o statusie.</li>
                <li>Ułatwia utrzymanie klienta po zakupie, bo panel nie kończy relacji na sprzedaży, tylko wspiera również aktywację i dalszą obsługę.</li>
                <li>Daje przestrzeń do rozbudowy o dodatkowe produkty, okresy subskrypcji, program poleceń, kolejne integracje i nowe ścieżki automatyzacji.</li>
                <li>Sprawia, że nawet niewielki zespół może obsługiwać większą liczbę użytkowników bez proporcjonalnego zwiększania pracy ręcznej.</li>
            </ul>
        </div>
        <p>Z punktu widzenia osoby, która dopiero rozważa skorzystanie z aplikacji, najważniejsze jest to, że serwis nie wymaga skoków między wieloma narzędziami. Można zacząć od rejestracji, potem przejść do oferty, wrócić do płatności, skorzystać z instrukcji, a później śledzić historię i status usługi we własnym koncie. To daje poczucie kontroli nad całym procesem. Użytkownik nie musi pamiętać, gdzie został wysłany link, jaki adres portfela był aktualny ani czy support już odpowiedział. W dobrze utrzymanym panelu większość tych informacji jest dostępna dokładnie tam, gdzie użytkownik naturalnie ich szuka.</p>
        <p>Podsumowując: to rozwiązanie ma sens zarówno jako praktyczny panel klienta, jak i jako baza pod rozwój bardziej dojrzałego systemu sprzedaży i obsługi. Dla końcowego odbiorcy liczy się prostota, czytelność i możliwość samodzielnego wykonywania podstawowych działań. Dla operatora liczy się kontrola, porządek i możliwość skalowania procesu bez ciągłego gaszenia pożarów. Właśnie połączenie tych dwóch perspektyw sprawia, że taki serwis ma realny potencjał: może jednocześnie poprawiać doświadczenie klienta i usprawniać codzienną pracę po stronie administracyjnej. Jeżeli ktoś szuka aplikacji, która nie jest tylko „ładną stroną”, ale realnym narzędziem do prowadzenia procesu od wejścia użytkownika do długofalowej obsługi, to właśnie w takim kierunku rozwija się ta platforma.</p>
    </div>
</section>
{/if}
<div class="clr"></div>
<div id="home-footer">
    <div class="lang">
        <span class="lang-label">{$t.guest_choose_language}:</span>
        <div class="guest-locale-switch">
            {foreach from=$supported_locales item=locale}
                <a href="/?lang={$locale.code}" class="{if $current_locale == $locale.code}active{/if}" title="{$locale.native_label}">
                    {$locale.code|upper}
                </a>
            {/foreach}
        </div>
    </div>
</div>

<div class="ip_content">
    <p class="desc">{$t.guest_language_note}</p>
    <p class="desc">Copyright &copy; 2026. {$t.copyright}</p>
</div>
