# Subme / Reseller Panel

To repo zawiera panel do sprzedazy subskrypcji, platnych dostepow i uslug cyfrowych.

Projekt ma obecnie fundament systemu resellerowo-subskrypcyjnego, ale jego kierunek produktowy jest szerszy: platforma do monetyzacji wiedzy, materialow, nagran, webinarow, konsultacji i zamknietych tresci online.

## Czym jest ten produkt

To nie jest tylko sklep ani tylko prosty panel klienta. To system operacyjny dla osoby lub firmy, ktora chce sprzedawac dostep do czegos zamknietego:

- subskrypcji
- materialow szkoleniowych
- nagran wideo
- platnych webinarow
- kursow online
- konsultacji
- pakietow edukacyjnych
- uslug cyklicznych

Najprostszy model dzialania:

```text
produkt -> platnosc -> dostep -> wygasniecie / odnowienie
```

Klient kupuje dostep, a admin zarzadza produktami, klientami, zamowieniami, platnosciami, tresciami i komunikacja.

## Dla kogo

System nie powinien byc ograniczany do jednej branzy.

Moze byc sprzedawany i wdrazany dla:

- trenerow personalnych
- dietetykow
- fizjoterapeutow
- szkoleniowcow
- nauczycieli i korepetytorow
- tworcow kursow online
- konsultantow
- organizatorow platnych webinarow
- ekspertow od marketingu, IT, finansow lub prawa
- malych firm edukacyjnych

Technicznie produkt powinien pozostac uniwersalny. Sprzedazowo mozna testowac konkretne segmenty rynku.

## Co system juz obsluguje

Aktualna aplikacja ma juz wiele elementow potrzebnych do takiego produktu:

- konta klientow
- konta resellerow
- osobny panel admina
- logowanie i reset hasla
- produkty i subskrypcje
- zamowienia
- saldo uzytkownika
- platnosci krypto
- przelewy bankowe
- reczna akceptacje platnosci
- statusy zamowien
- powiadomienia administracyjne
- messenger i live chat
- newsy, FAQ, strony statyczne i szablony email
- ustawienia serwisu i mechanizmy utrzymania

## Kierunek rozwoju

Najwazniejszy kierunek to rozwiniecie panelu w uniwersalna platforme do sprzedazy platnych dostepow.

Po MVP warto rozwijac:

- biblioteke materialow wideo i plikow
- dostepy do materialow wedlug zakupionego pakietu
- automatyczne wygasanie dostepow
- przypomnienia przed koncem subskrypcji
- linki czasowe lub jednorazowe do transmisji
- integracje z platformami webinarowymi
- raporty sprzedazy i aktywnosci klientow
- wariant white-label
- integracje platnosci online, np. karta, PayPal, Stripe, Przelewy24, PayU, Tpay i BLIK

Na tym etapie nowe bramki platnosci sa kierunkiem produktowym, a nie wdrozona funkcja w kodzie.

## Model biznesowy

Produkt moze byc oferowany jako:

- gotowy panel na abonament
- wdrozenie white-label pod marke klienta
- system dla jednej firmy z konfiguracja pod jej oferte
- narzedzie dla agencji, ktora wdraza takie panele swoim klientom

Najwieksza wartosc biznesowa: klient nie musi budowac wlasnego systemu od zera. Dostaje gotowy panel do sprzedazy dostepu do wiedzy, uslug lub materialow.

## Dokumentacja

Najwazniejsze pliki:

- `docs/README.md` - mapa dokumentacji i zasady techniczne
- `docs/FUNCTIONALITY.md` - aktualna funkcjonalnosc aplikacji
- `docs/PRODUCT_DIRECTION_PAID_CONTENT_PLATFORM.md` - kierunek produktowy i zalozenia biznesowe
- `docs/NEW_SERVER_CHECKLIST.md` - checklist przenoszenia na nowy serwer
- `docs/LOCAL_DOCKER.md` - lokalne uruchomienie w Dockerze

## Aktualny model techniczny

Aktualny kierunek techniczny:

- jedna glowna instancja panelu
- jedna baza danych dla panelu
- osobny landing page bez SQL
- panel najlepiej wdrazany jako osobny webroot / vhost

Szczegoly techniczne, deploy i zasady zmian bazy sa opisane w `docs/README.md`.
