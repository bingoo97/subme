# Provider Delivery Rules

Ten dokument opisuje jedno źródło prawdy dla działania:

- `Obsługuje ręczne wydanie`
- `Obsługuje podmianę URL`
- checkboxa orderu `Pokaż userowi dodatkowe pole z URL`

## Główna zasada

- Provider steruje domyślnym sposobem pokazywania danych dostępowych userowi.
- Order może dodatkowo nadpisać tylko widoczność pełnego URL przez checkbox `delivery_link_visible`.

## Obsługuje ręczne wydanie

Pole w tabeli:

- `product_providers.supports_manual_delivery`

Domyślna wartość:

- `1` czyli włączone

Znaczenie:

- provider działa w trybie ręcznego wydania danych dostępowych
- admin może zapisać w orderze pełny `delivery_link`
- user nie musi widzieć pełnego URL

## Jak działa order przy ręcznym wydaniu

Jeśli provider ma:

- `supports_manual_delivery = 1`

i order ma zapisany:

- `orders.delivery_link`

to frontend usera działa tak:

1. z linku wyciąga dane logowania
2. user widzi blok `Dane do logowania`
3. pełny URL jest ukryty, jeśli admin nie zaznaczy checkboxa widoczności URL

Przykład:

- zapisany link:
  - `http://linkurl.live:8080/get.php?username=DSj89dsa&password=tLYI6dmF3D&type=m3u_plus&output=ts`
- user zobaczy:
  - `Login: DSj89dsa`
  - `Hasło: tLYI6dmF3D`

## Pokaż userowi dodatkowe pole z URL

Pole w tabeli:

- `orders.delivery_link_visible`

Znaczenie:

- decyduje tylko o tym, czy user zobaczy pełny URL
- nie decyduje o tym, czy admin może zapisać URL w orderze

Reguły:

- jeśli `delivery_link_visible = 0`:
  - przy providerze manualnym user widzi tylko login/hasło z linku
  - pełny URL jest ukryty
- jeśli `delivery_link_visible = 1`:
  - user widzi login/hasło
  - user widzi też pełny URL

Ważne:

- w adminie pole URL ma być zawsze edytowalne
- checkbox steruje tylko widocznością po stronie usera

## Obsługuje podmianę URL

Pola w tabeli `product_providers`:

- `supports_url_replacement`
- `url_replacement_from`
- `url_replacement_to`

Znaczenie:

- jeśli provider ma włączoną podmianę URL, frontend nie pokazuje userowi surowego linku z orderu
- zamiast tego renderuje link po podmianie prefixu

Przykład:

- w orderze zapisany jest:
  - `http://abc.com:8800/get.php?username=foo&password=bar`
- w providerze ustawione jest:
  - `url_replacement_from = http://abc.com:8800`
  - `url_replacement_to = http://cba.com:8800`
- user zobaczy:
  - `http://cba.com:8800/get.php?username=foo&password=bar`

## Zakres podmiany

Podmieniany jest tylko początek linku.

To znaczy:

- jeśli link zaczyna się od `url_replacement_from`
- frontend zamienia tylko ten prefix na `url_replacement_to`
- dalsza część linku zostaje bez zmian

To pozwala zmienić host lub panel providera bez edycji każdego orderu osobno.

## Gdzie to działa

Admin:

- karta edycji providera w `Admin -> Products`
- karta orderu w `Admin -> Orders`

User:

- `Orders` lista i modal szczegółów subskrypcji

## Aktualne reguły techniczne

- nowe pola providera są utrzymywane w runtime przez:
  - `dashboard-panel/bootstrap/application.php`
- zapis providera jest obsługiwany przez:
  - `dashboard-panel/admin/bootstrap.php`
- widok usera dla orderów jest budowany przez:
  - `dashboard-panel/pages/profil/orders_check.php`
  - `dashboard-panel/templates/profil/orders.tpl`

## Reguła na przyszłość

- jeśli zmieniamy interpretację `supports_manual_delivery`
- albo logikę `delivery_link_visible`
- albo sposób podmiany URL

to ten plik trzeba zaktualizować w tym samym commicie co kod.
