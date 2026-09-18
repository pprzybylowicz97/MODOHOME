# MODOhome Katalog Produktów

Prosty katalog produktów sklepu stacjonarnego MODOhome dla WordPressa. Służy wyłącznie
do prezentacji produktów, zdjęć i cen — **bez WooCommerce, bez koszyka, bez płatności
i bez stanów magazynowych**.

- **Wersja:** 1.0.0
- **Wymaga WordPressa:** 6.1 lub nowszego
- **Wymaga PHP:** 8.1 lub nowszego
- **Licencja:** GPL-2.0-or-later

---

## Co potrafi

- Typ wpisu **Produkty** (`modohome_product`) i hierarchiczna taksonomia
  **Kategorie produktów** (`modohome_product_category`).
- Formularz frontendowy do dodawania produktów **z telefonu** — jedno zdjęcie z aparatu,
  nazwa, cena, gotowe.
- Nowoczesny katalog na stronie: filtry, wyszukiwarka, sortowanie, „Pokaż więcej”
  i okno modalne ze szczegółami.
- Osobna rola **Pracownik katalogu** z własnymi uprawnieniami — pracownik widzi
  i edytuje wyłącznie swoje produkty.
- Panel ustawień: kolory, kolumny, proporcje zdjęć, elementy karty, waluta, limity zdjęć.
- Import i eksport CSV oraz log aktywności dla administratora.

---

## Instalacja

1. Spakuj katalog `modohome-katalog-produktow` do pliku ZIP (albo użyj gotowego ZIP-a
   dołączonego do repozytorium).
2. W panelu WordPressa przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**.
3. Wybierz plik ZIP i kliknij **Zainstaluj teraz**, a następnie **Włącz wtyczkę**.

Podczas aktywacji wtyczka tworzy rolę „Pracownik katalogu”, tabelę logu aktywności
i zapisuje ustawienia domyślne.

### Aktualizacja

Wgraj nowszy ZIP przez ten sam ekran. Produkty, kategorie, zdjęcia i ustawienia zostają
nienaruszone.

### Odinstalowanie

Usunięcie wtyczki **nie kasuje** produktów ani zdjęć. Jeżeli chcesz, żeby dane zniknęły
razem z wtyczką, włącz wcześniej opcję **Ustawienia katalogu → Dane i zaawansowane →
Usuwanie danych**. Zdjęcia zawsze zostają w bibliotece mediów — mogą być używane gdzie indziej.

---

## Pierwsze kroki

1. **Produkty → Kategorie** — dodaj kategorie i podkategorie. Pole „Kolejność”
   decyduje o pozycji na liście filtrów (niższa liczba = wyżej).
2. **Produkty → Ustawienia katalogu** — ustaw walutę, kolory i liczbę kolumn.
3. Utwórz stronę katalogu i wstaw `[modohome_catalog]`.
4. Utwórz stronę dla pracowników i wstaw `[modohome_product_form]`
   oraz `[modohome_my_products]`.
5. **Użytkownicy → Dodaj nowego** — załóż konta z rolą **Pracownik katalogu**.

---

## Shortcode’y

### `[modohome_catalog]` — katalog produktów

| Atrybut | Wartości | Domyślnie | Opis |
|---|---|---|---|
| `category` | slug lub ID kategorii | — | Pokazuje jedną kategorię (z podkategoriami). |
| `categories` | lista po przecinku | — | Pokazuje kilka kategorii. |
| `limit` | liczba | `0` (bez limitu) | Maksymalna liczba produktów do pokazania. |
| `columns` | `1`–`6` | z ustawień | Liczba kolumn na komputerze. |
| `show_filters` | `yes` / `no` | z ustawień | Filtry kategorii i sortowanie. |
| `show_search` | `yes` / `no` | z ustawień | Pole wyszukiwania. |
| `orderby` | `date`, `price_asc`, `price_desc`, `menu_order` | z ustawień | Sortowanie początkowe. |
| `show_sold` | `show` / `hide` | z ustawień | Produkty sprzedane. |
| `heading` | tekst | z ustawień | Nagłówek nad katalogiem. |

Przykłady:

```
[modohome_catalog]
[modohome_catalog category="krzesla"]
[modohome_catalog categories="krzesla,meble,elektronika"]
[modohome_catalog limit="12"]
[modohome_catalog columns="4"]
[modohome_catalog show_filters="yes"]
[modohome_catalog show_search="yes"]
```

### `[modohome_product_form]` — dodawanie produktu z telefonu

Widoczny wyłącznie dla zalogowanych użytkowników z uprawnieniem
`edit_modohome_products`. Zawiera duży przycisk „Dodaj zdjęcie lub zrób zdjęcie”
(korzysta z aparatu telefonu), podgląd zdjęcia, pola nazwy, ceny, ceny poprzedniej,
kategorii, opisu, etykiety i dostępności.

Po zapisaniu — bez przeładowania strony — pojawia się potwierdzenie z miniaturą, nazwą
i ceną oraz przycisk „Dodaj kolejny produkt”.

Atrybut `heading` zmienia nagłówek formularza.

### `[modohome_my_products]` — panel pracownika

Lista własnych produktów w mobilnym układzie. Pracownik może edytować nazwę, cenę, opis
i zdjęcie, zmienić status, oznaczyć produkt jako sprzedany, zduplikować go lub przenieść
do kosza. Atrybut `limit` (domyślnie `20`) ustala liczbę pokazywanych produktów.

---

## Dane produktu

| Pole | Wymagane | Gdzie |
|---|---|---|
| Nazwa | tak | Tytuł wpisu |
| Zdjęcie główne | tak | Obrazek wyróżniający |
| Galeria dodatkowa | nie | Panel „Galeria zdjęć” |
| Cena | tak | Panel „Dane produktu” |
| Cena poprzednia | nie | Panel „Dane produktu” |
| Krótki opis | nie | Panel „Dane produktu” |
| Kategoria | tak | Panel „Kategorie produktów” |
| Etykieta | nie | Nowość, Promocja, Ostatnia sztuka, Bestseller, Dostępny od ręki |
| Dostępność | tak | Dostępny, Zarezerwowany, Sprzedany, Ukryty |
| Data dodania | automatycznie | Data wpisu |
| Autor | automatycznie | Autor wpisu |
| Kolejność | nie | Panel „Atrybuty” → Kolejność |

Cena zapisywana jest jako liczba, a wyświetlana z walutą z ustawień (domyślnie `zł`).
Produkty ze statusem **Ukryty** nigdy nie pojawiają się w katalogu publicznym.

---

## Uprawnienia

Rola **Pracownik katalogu** (`modohome_catalog_worker`) **może**: zalogować się, korzystać
z formularza frontendowego, dodawać produkty, przesyłać zdjęcia, edytować i usuwać
**wyłącznie własne** produkty oraz zmieniać ich status.

**Nie może**: edytować stron i wpisów, instalować wtyczek, zmieniać ustawień WordPressa,
zarządzać użytkownikami, dotykać produktów innych pracowników ani — domyślnie — tworzyć
nowych kategorii.

Wtyczka używa własnych uprawnień, nie standardowej roli autora:

```
edit_modohome_products, edit_others_modohome_products, publish_modohome_products,
delete_modohome_products, delete_others_modohome_products, edit_published_modohome_products,
delete_published_modohome_products, read_private_modohome_products, …
manage_modohome_catalog          — ustawienia, import/eksport, log
assign_modohome_product_categories — przypisywanie kategorii
manage_modohome_product_categories — tworzenie kategorii (opcjonalne dla pracowników)
```

Administrator ma pełną kontrolę. Redaktor zarządza wszystkimi produktami, ale nie ma
dostępu do ustawień wtyczki.

Dwa ustawienia sterują pracą zespołu:

- **Publikacja produktów pracowników** — włączona (domyślnie) publikuje od razu; wyłączona
  kieruje produkty do zatwierdzenia (status „Oczekujące”).
- **Kategorie przez pracowników** — pozwala pracownikom tworzyć nowe kategorie.

---

## Lista produktów w panelu

Kolumny: miniatura, nazwa, cena, cena poprzednia, kategoria, etykieta, dostępność, autor,
data dodania.

Dostępne są: filtrowanie po kategorii, dostępności i autorze, wyszukiwanie po nazwie,
sortowanie po cenie i dacie, akcje wierszowe **Oznacz jako sprzedany** i **Duplikuj produkt**,
a także akcje zbiorcze: zmiana dostępności i duplikowanie. Zbiorcze przypisanie kategorii
zrobisz przez **Edycja grupowa** WordPressa.

---

## Ustawienia katalogu

**Produkty → Ustawienia katalogu**, cztery sekcje:

- **Wygląd** — kolor główny, tekstu, tła, kart i etykiet; liczba kolumn na komputerze,
  tablecie i telefonie; proporcje zdjęć; sposób przycinania; minimalna wysokość kart;
  promień zaokrąglenia; odstępy; cień kart; nagłówek i tekst nad katalogiem.
- **Elementy karty** — kategoria, opis, cena poprzednia, etykiety, status dostępności,
  data dodania, „Zobacz szczegóły”, oznaczenie „Dodano dzisiaj”.
- **Działanie katalogu** — produkty na stronę, domyślne sortowanie, produkty sprzedane,
  tryb szczegółów (modal / podstrona / brak), podstrony produktów, wyszukiwarka, filtry,
  „Pokaż więcej”, licznik produktów, publikacja bez zatwierdzania, kategorie dla pracowników,
  maksymalny rozmiar i wymiar zdjęcia, automatyczne ukrycie po X dniach, waluta i jej pozycja.
- **Dane i zaawansowane** — log aktywności, usuwanie danych przy deinstalacji, własny CSS.

Przycisk **Przywróć ustawienia domyślne** kasuje wszystkie zmiany (z potwierdzeniem).

Pole **Własny CSS** zapisuje wyłącznie administrator; treść jest sanityzowana i ładowana
tylko tam, gdzie działa katalog.

---

## Zdjęcia

- Dozwolone formaty: **JPG, PNG, WebP**; typ sprawdzany po zawartości pliku, nie po nazwie.
- Zdjęcia większe niż ustawiony wymiar (domyślnie **1600 px**) WordPress przeskalowuje,
  zachowując proporcje.
- Miniatury generuje WordPress; orientacja zdjęć z telefonu jest korygowana na podstawie
  danych EXIF.
- Pliki trafiają wyłącznie do **biblioteki mediów** — wtyczka niczego nie zapisuje we
  własnym katalogu.
- Zbyt duży lub nieprawidłowy plik kończy się czytelnym komunikatem, bez tworzenia
  pustego produktu.

---

## Import i eksport CSV

**Produkty → Import i eksport**. Plik w UTF-8, separator **średnik**, kolumny:

```
nazwa; opis; cena; cena_poprzednia; kategoria; status; etykieta; autor; data; zdjecie
```

Wymagane przy imporcie: `nazwa`, `cena`, `kategoria`. Kilka kategorii rozdziel znakiem `|`.
Nieistniejące kategorie zostaną utworzone. Kolumna `zdjecie` przyjmuje adres URL obrazka
**już obecnego w bibliotece mediów**.

Produkty o nazwie już istniejącej w katalogu są domyślnie pomijane, a ich liczba pokazana
w podsumowaniu. Zaznacz „Dodaj mimo istniejącego produktu o tej samej nazwie”, żeby to wyłączyć.

---

## Log aktywności

**Produkty → Log aktywności** (tylko administrator). Zapisuje: kto dodał produkt, kto zmienił
cenę, kto zmienił status i kiedy produkt został usunięty. Nie przechowuje adresów IP ani
innych danych osobowych — tylko identyfikator użytkownika, produkt i rodzaj zdarzenia.
Log można wyłączyć w ustawieniach i wyczyścić jednym przyciskiem.

---

## Struktura plików

```
modohome-katalog-produktow/
├── modohome-katalog-produktow.php   Plik główny, autoloader, stałe
├── uninstall.php                    Deinstalacja (usuwa dane tylko na życzenie)
├── README.md
├── includes/
│   ├── class-plugin.php             Spina moduły, ładuje szablony
│   ├── class-activator.php          Aktywacja: role, tabela logu, cron
│   ├── class-settings.php           Ustawienia i sanityzacja
│   ├── class-roles.php              Rola pracownika i uprawnienia
│   ├── class-post-type.php          Typ wpisu Produkty
│   ├── class-taxonomy.php           Kategorie produktów
│   ├── class-product.php            Pola produktu, formatowanie
│   ├── class-product-actions.php    Status, kosz, duplikat, zmiana ceny
│   ├── class-query.php              Zapytania katalogu
│   ├── class-image-handler.php      Przesyłanie i walidacja zdjęć
│   ├── class-activity-log.php       Log aktywności
│   ├── class-shortcodes.php         Rejestracja shortcode’ów
│   ├── class-ajax.php               Punkty końcowe AJAX
│   ├── class-assets.php             Warunkowe ładowanie CSS/JS
│   └── class-cron.php               Automatyczne ukrywanie produktów
├── admin/
│   ├── class-admin.php              Spina ekrany panelu
│   ├── class-metaboxes.php          Panel danych produktu i galeria
│   ├── class-columns.php            Kolumny, filtry, akcje listy
│   ├── class-settings-page.php      Ustawienia katalogu
│   ├── class-tools-page.php         Import i eksport CSV
│   └── class-log-page.php           Log aktywności
├── public/
│   ├── class-catalog.php            Renderowanie katalogu
│   ├── class-product-form.php       Formularz dodawania
│   └── class-my-products.php        Panel pracownika
├── templates/                       Szablony do nadpisania w motywie
│   ├── catalog.php
│   ├── product-card.php
│   ├── product-modal.php
│   ├── product-form.php
│   └── my-products.php
├── assets/
│   ├── css/  catalog.css · form.css · admin.css
│   └── js/   catalog.js · form.js · admin.js
└── languages/
    └── modohome-katalog-produktow.pot
```

### Nadpisywanie szablonów w motywie

Skopiuj wybrany plik z `templates/` do katalogu motywu:

```
twoj-motyw/modohome-katalog-produktow/product-card.php
```

Wtyczka użyje wersji z motywu, więc aktualizacja nie nadpisze Twoich zmian.

---

## Bezpieczeństwo

- Nonce w każdym formularzu i każdym żądaniu AJAX.
- Sprawdzanie uprawnień przed **każdą** operacją zapisu — także po stronie serwera,
  niezależnie od tego, co pokazuje interfejs.
- Sanityzacja danych wejściowych i escaping przy wyświetlaniu.
- Ograniczone typy plików, weryfikacja rozmiaru i faktycznej zawartości obrazka.
- Brak możliwości dodania produktu bez zalogowania.
- Pracownik nie edytuje cudzych produktów — blokada zarówno w `map_meta_cap`,
  jak i w każdym punkcie końcowym.
- Brak bezpośredniego dostępu do plików PHP (`ABSPATH`, pliki `index.php`).
- Wszystkie funkcje, klasy, opcje i hooki mają prefiks `modohome_catalog_`;
  kod korzysta z przestrzeni nazw `MODOhome\Catalog`.
- Klasy CSS mają prefiks `modohome-catalog-`, więc nie nadpisują stylów motywu.

---

## Wydajność

- CSS i JavaScript ładują się **tylko** na podstronach z shortcode’em wtyczki
  i na ekranach wtyczki w panelu.
- Front działa bez jQuery — czysty JavaScript.
- Liczniki produktów w kategoriach są cache’owane na 5 minut.
- Wtyczka nie wymaga WooCommerce ani żadnego zewnętrznego frameworka.

---

## Rozwiązywanie problemów

**Katalog jest pusty, choć produkty istnieją.** Sprawdź, czy produkty są opublikowane
i czy nie mają statusu „Ukryty”. Jeśli filtr wskazuje kategorię, upewnij się, że slug
w shortcode’zie jest poprawny.

**Formularz pokazuje „Twoje konto nie ma uprawnień”.** Użytkownik musi mieć rolę
„Pracownik katalogu” (albo wyższą) — rola nadaje `edit_modohome_products` i `upload_files`.

**Zdjęcie z telefonu nie przechodzi.** Zwiększ „Maksymalny rozmiar zdjęcia” w ustawieniach.
Limit i tak nie przekroczy limitu serwera (`upload_max_filesize`).

**Produkty pracowników nie pojawiają się w katalogu.** Sprawdź ustawienie „Publikacja
produktów pracowników” — przy wyłączonym czekają na zatwierdzenie w **Produkty → Wszystkie**
ze statusem „Oczekujące”.

**Zmiana ustawienia podstron produktu nic nie dała.** Wejdź w **Ustawienia → Bezpośrednie
odnośniki** i zapisz, żeby odświeżyć reguły przepisywania.
