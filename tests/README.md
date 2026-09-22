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

## Test układu w przeglądarce

`check-css-vars.php` pilnuje statycznie tego, co w 1.2.1 okazało się realnym błędem:
zmienne zadeklarowane na `.modohome-catalog` przesłaniały wartości wstrzykiwane
z panelu ustawień na `:root`, przez co liczba kolumn na telefonie, kolory i odstępy
nie działały.

Pełne sprawdzenie układu wymaga przeglądarki:

```bash
php build-inline-css.php          # zapisuje inline.css z ustawień
# następnie strona testowa + Playwright, patrz historia commita 1.2.1
```
