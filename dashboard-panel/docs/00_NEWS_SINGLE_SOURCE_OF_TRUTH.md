# News: Single Source Of Truth

Ten dokument definiuje jedno źródło prawdy dla modułu newsów w tym repo.

## Obowiązująca zasada

- Dla działającej aplikacji pod `http://localhost:8088` źródłem prawdy dla newsów jest tabela `news_posts` w bazie wskazanej przez `DB_NAME` kontenera `web`.
- W aktualnym lokalnym środowisku Docker kontener `reseller_web_local` używa:
  - `DB_HOST=db`
  - `DB_NAME=reseller_v2`
  - `DB_USER=marbodz_reseller`
- To oznacza, że aktywne lokalnie newsy są czytane i zapisywane z `reseller_v2.news_posts`.

## Co korzysta z tego źródła

- Admin pod `/admin/?page=news`
- Front usera pod `/news`
- Badge newsów w navbarze usera
- Badge newsów na homepage usera

## Czego nie używać jako źródła prawdy

- `resellers_news`
- `tenant_news`

Te tabele są traktowane jako warstwa legacy / kompatybilność dla starego schematu i nie mogą być uznawane za główne źródło prawdy w środowisku v2.

## Skąd wynika spójność admin + frontend

- Front usera ładuje DB przez [public_html/index.php](/Users/bodzianek/CascadeProjects/RESELLER/reseller/public_html/index.php:10), który wymaga `dashboard-panel/config/mysql.php`.
- Admin ładuje DB przez [dashboard-panel/admin/bootstrap.php](/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/admin/bootstrap.php:5), który wymaga tego samego `dashboard-panel/config/mysql.php`.
- Wniosek: admin i frontend usera korzystają z tej samej konfiguracji połączenia DB.

## Reguła czasu publikacji

- Czas publikacji newsów musi być oceniany według czasu aplikacji PHP, nie według `NOW()` z MySQL, jeśli MySQL działa w innej strefie czasowej.
- W tym projekcie kontener PHP działa obecnie w `Europe/Warsaw`, a MySQL lokalnie w `UTC`.
- Dlatego filtry widoczności newsów muszą używać czasu aplikacji (`date('Y-m-d H:i:s')`) przekazanego do SQL jako literal, żeby news opublikowany w panelu admina był widoczny zgodnie z czasem widzianym przez użytkownika i administratora.

## Reguła na przyszłość

- Każda nowa funkcja dotycząca newsów ma czytać i zapisywać `news_posts` w aktywnej bazie aplikacji.
- Nie wolno dodawać nowej logiki produkcyjnej, która zapisuje newsy tylko do `resellers_news` albo tylko do `tenant_news`.
- Jeśli kiedyś zostanie wprowadzona nowa tabela dla newsów, ten dokument trzeba zaktualizować w tym samym PR co zmiana kodu.
