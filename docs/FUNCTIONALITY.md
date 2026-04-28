# Funkcjonalnosc Aplikacji

To jest aktualny opis tego, co robi aplikacja w obecnej wersji.

## 1. Typy uzytkownikow

Aplikacja obsluguje dwa glowne typy kont:

- `client`
- `reseller`

Admin ma osobny panel zarzadzania i osobne konta w `admin_users`.

## 2. Logowanie i konto usera

User moze:

- zalozyc konto
- zalogowac sie
- zresetowac haslo
- zmienic jezyk
- ustawic avatar
- wlaczyc lub wylaczyc powiadomienia email

## 3. Produkty i subskrypcje

User moze:

- dodac nowe zamowienie
- wybrac aktywnego providera i produkt
- kupic subskrypcje
- przedluzyc istniejace zamowienie z salda

Admin moze:

- dodawac i edytowac produkty
- ustawiac triale
- sterowac providerami
- ograniczac widocznosc providerow per user

## 4. Platnosci

System obsluguje:

- platnosci krypto
- przelewy bankowe
- doladowania salda

Admin moze:

- wlaczac lub wylaczac krypto oraz bank transfery
- zatwierdzac platnosci
- przenosic je do weryfikacji
- usuwac anulowane requesty

### Crypto wallet pool

Aplikacja ma pule adresow krypto.

Jesli user chce zaplacic kryptowaluta:

- system sprawdza jego przypisania
- jesli nie ma przypisanego adresu, szuka wolnego adresu dla wybranego assetu
- jesli taki adres istnieje, tworzy assignment automatycznie przy inicjacji requestu

Jesli anulowany request zostanie usuniety:

- assignment jest zwalniany
- adres wraca do `available`

## 5. Saldo usera

User moze doladowac balance przez krypto.

Admin po potwierdzeniu platnosci moze wykorzystac saldo usera do:

- aktywacji nowego zamowienia
- przedluzenia starego zamowienia

## 6. Zamowienia

Admin ma pelna obsluge zamowien:

- nowe
- oczekujace
- aktywne
- wygasle
- anulowane

W panelu admina istnieja tez szybkie powiadomienia:

- oczekujace platnosci
- oczekujace zamowienia
- nowe konta
- konczace sie subskrypcje
- przypomnienie o backupie SQL

## 7. Messenger i live chat

System ma jeden wspolny mechanizm rozmow.

Obsluguje:

- glowny live chat user <-> support
- rozmowy `1 na 1` reseller <-> reseller
- grupy resellerow
- rozmowy admin <-> admin
- grupy tworzone przez admina

Wazne cechy:

- zaproszenia do grup
- limity grup resellerow
- mute email per rozmowa
- auto-usuwanie wiadomosci per rozmowa
- usuwanie swoich wiadomosci, a w grupach tworca ma szersze uprawnienia

Pelny opis czatu:

- `docs/live-chat.md`

## 8. Tresci i komunikacja

Admin moze zarzadzac:

- newsami
- FAQ
- stronami statycznymi
- email templates
- modulem `Pomoc` / samouczka w adminie

## 9. Settings i utrzymanie

Admin moze:

- zmieniac podstawowe ustawienia serwisu
- wlaczac lub wylaczac wybrane moduly
- uruchomic maintenance recznie
- pobrac backup SQL
- wlaczyc dzienny backup CSV dla pozytywnych platnosci krypto

Opis maintenance:

- `docs/00_SYSTEM_MAINTENANCE_RUNNER.md`

## 10. Techniczny model wdrozenia

Aktualny model:

- jeden panel
- jedna baza danych
- osobny landing bez SQL
- deploy przez skrypty z `docs/`

Przeniesienie na nowy serwer:

- `docs/NEW_SERVER_CHECKLIST.md`
