# MODOHOME

Repozytorium projektu MODOhome.

## Zawartość

| Ścieżka | Opis |
|---|---|
| `modohome-katalog-produktow/` | Wtyczka WordPress „MODOhome Katalog Produktów” — kod źródłowy |
| `modohome-katalog-produktow.zip` | Gotowa paczka instalacyjna wtyczki |

## Instalacja wtyczki

1. Pobierz `modohome-katalog-produktow.zip` z tego repozytorium.
2. W panelu WordPressa: **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**.
3. Wybierz plik ZIP, kliknij **Zainstaluj teraz**, a potem **Włącz wtyczkę**.

Pełna dokumentacja — shortcode’y, uprawnienia, ustawienia, import i eksport CSV —
znajduje się w [`modohome-katalog-produktow/README.md`](modohome-katalog-produktow/README.md).

## Przebudowa paczki ZIP

Po zmianach w kodzie:

```bash
rm -f modohome-katalog-produktow.zip
zip -r modohome-katalog-produktow.zip modohome-katalog-produktow
```
