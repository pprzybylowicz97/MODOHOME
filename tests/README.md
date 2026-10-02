# Testy wtyczki MODOhome Katalog Produktów

Testy działają na atrapach funkcji WordPressa (`wp-stubs.php`), więc nie wymagają
instalacji WordPressa ani bazy danych. Nie trafiają do paczki ZIP — ZIP pakuje
wyłącznie katalog `modohome-katalog-produktow/`.

## Uruchomienie

```bash
./run.sh
```

Wymagania: PHP 8.1+ i Node 18+.

## Co zawiera

| Plik | Zakres |
|---|---|
| `check-css-vars.php` | Strażnik regresji: zmienne CSS muszą być deklarowane w `:root`. |
| `test-plugin.php` | Autoloader, sanityzacja cen i ustawień, uprawnienia, numerowanie stron. |
| `test-render.php` | Renderowanie szablonów katalogu, karty i okna modalnego, escaping. |
| `test-optimizer.php` | Skalowanie i konwersja zdjęć do WebP na prawdziwych plikach (GD). |
| `test-csv.php` | Sprawdza plik CSV logiką importera (wymaga `../produkty-modohome.csv`). |
| `build-inline-css.php` | Generuje CSS z ustawień — wejście do testu układu w przeglądarce. |

## Testy układu w przeglądarce

```bash
./run-browser.sh
```

Dodatkowe wymagania: Python 3 z Pillow, Node 18+ i Playwright z Chromium.

| Plik | Zakres |
|---|---|
| `test-layout.mjs` | Liczba kolumn na telefonie, tablecie i komputerze; kolory, odstępy i proporcje z ustawień. |
| `test-fit.mjs` | Mierzy, ile procent zdjęcia widać i ile kafelka to puste tło, w trzech trybach kadrowania. |
| `test-modal.mjs` | Okno modalne pokazuje pionowy zrzut ekranu w całości i mieści się w ekranie. |
| `make-fixtures.py` | Obrazki testowe: 450×975, 900×675, 700×700. |
| `make-pages.py` | Buduje strony testowe z prawdziwego CSS wtyczki. |

Te testy powstały po dwóch realnych błędach:

- **1.2.1** — zmienne deklarowane na `.modohome-catalog` przesłaniały wartości
  z panelu ustawień wstrzykiwane na `:root`, więc nie działała liczba kolumn na
  telefonie, kolory ani odstępy. Pilnuje tego też statycznie `check-css-vars.php`.
- **1.3.0** — stały kadr przycinał zrzuty ekranu do 62% powierzchni, a `contain`
  zostawiał 39–44% pustego tła. Stąd tryb „Dopasuj do zdjęcia”.
