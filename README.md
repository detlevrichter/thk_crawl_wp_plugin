# Competency Slider International – WordPress-Plugin für die Weiterbildungsdatenbank

WordPress-Plugin für eine **Weiterbildungsdatenbank**: Nutzende wählen eine Zielgruppe und Kriterien (Kompetenzen), stellen **Interesse** und **Fähigkeit** ein und erhalten einen personalisierten Katalog passender Angebote, sortiert nach Übereinstimmung. Das Plugin ist zweisprachig (Deutsch/Englisch) über Polylang und übernimmt die Gestaltung des aktiven Themes.

Es ist im Rahmen des Projekts **Digitalkompetenz.nrw** an der **TH Köln** entstanden und das Frontend zum Crawler [thk_crawl](https://github.com/detlevrichter/thk_crawl): Der Crawler sammelt Angebote von Anbieter-Webseiten und bewertet sie per LLM nach Kompetenzfacetten, das Plugin stellt diese Daten in WordPress dar.

> Diese README bezieht sich auf den Branch **`digitalkompetenz`** (Plugin-Version **2.3.1**). Der Branch `master` enthält eine frühere, einfachere Version (v1.1) ohne Zielgruppenauswahl, Mehrsprachigkeit und Ergebnisfilter.
>

## Funktionen

- **Zielgruppen** (`Kategorie`): Pro Zielgruppe werden eigene Kriterien angezeigt. Gibt es für eine Zielgruppe keine Zuordnung, werden die Kriterien von `Default` verwendet. Angebote lassen sich außerdem nach Zielgruppe der Quelle filtern.
- **Zwei-Schritt-Einstellung:**
  1. *Kriterien wählen* – Auswahl der relevanten Themen (Anzahl gewählter Kriterien wird angezeigt).
  2. *Einstellen* – je Kriterium ein Tab mit zwei Reglern, **Interesse** und **Fähigkeit** (Skala 1–5, intern 0–100 in 25er-Schritten), mit Vor-/Zurück-Navigation und Plus-/Minus-Schaltflächen.
- **Live-Vorschau** per AJAX: Eine Meldung unter den Reglern zeigt, wie viele Angebote zu über 80 %, 60–80 %, 40–60 %, 20–40 % und unter 20 % passen.
- **Ergebnisseite** mit Katalog und Seitenleiste:
  - Angebotskarten mit Titel, Beschreibung, Anbieter, **Match in %** (hoch ≥ 80 %, mittel ≥ 40 %, niedrig darunter) und Link zum Angebot (neuer Tab)
  - Seitenleiste mit Zielgruppenwahl, Themenbereichen samt Reglern, Sprachfilter (Deutsch/Englisch, im Browser) und Link zu den Detaileinstellungen
  - Aktualisierung ohne Seitenreload
- **Teilbare Links:** Alle Einstellungen stehen als URL-Parameter in der Adresse.
- **Mehrsprachig:** Oberfläche über Übersetzungsdateien (de_DE enthalten), Kriterien-Bezeichnungen und Zielgruppen-Titel aus der Datenbank über Polylang-String-Übersetzungen. Beim Sprachwechsel bleiben die gewählten Kriterien erhalten. Angebote werden auf die aktuelle Sprache gefiltert (anpassbar, siehe [Filter](#filter-für-entwickler)).
- **Barrierefreiheit:** Tabs mit ARIA-Rollen, `aria-live`-Hinweise und Wertetexte für die Regler.

## Voraussetzungen

- WordPress **6.4** oder neuer (Block-Editor), PHP **7.4** oder neuer
- Die Datenbanktabellen des Crawlers, erreichbar aus WordPress (siehe [Datenbasis](#datenbasis))
- Ein bereits durchgeführter Crawl mit [thk_crawl](https://github.com/detlevrichter/thk_crawl)
- Optional: **Polylang** für die Zweisprachigkeit. Ohne Polylang läuft das Plugin einsprachig in der Sprache der Website.
- Optional: ein Theme mit dem Stylesheet-Handle `dk-theme` und Design-Tokens in der `theme.json`. Die Plugin-Styles hängen von diesem Stylesheet ab, sofern es registriert ist.

## Installation

1. Repository in das Plugin-Verzeichnis klonen und den Branch wechseln:
   ```bash
   cd wp-content/plugins
   git clone -b digitalkompetenz https://github.com/detlevrichter/thk_crawl_wp_plugin.git
   ```
2. Im WordPress-Backend unter **Plugins** *Competency Slider International* aktivieren.
3. Crawler-Daten bereitstellen (siehe [Datenbasis](#datenbasis)).
4. Zwei Seiten anlegen:
   - eine Seite mit dem Block **Competency Slider** (Einstellungen), z. B. unter `/einstellungen/`
   - eine Seite mit dem Block **Competency Results** (Ergebnisse), z. B. unter `/results/`
5. Bei Zweisprachigkeit: Polylang einrichten, beide Seiten übersetzen und die Übersetzungen verknüpfen. Unter *Sprachen → Übersetzungen* (Gruppe „Competency Slider“) die Kriterien- und Zielgruppen-Bezeichnungen übersetzen.

Die Seiten werden **automatisch gefunden**: Das Plugin sucht veröffentlichte Seiten, die den jeweiligen Block enthalten (bei Polylang bevorzugt in der aktuellen Sprache bzw. deren Übersetzung). Nur wenn keine Seite gefunden wird, greifen die festen Pfade `einstellungen/` und `results/`.

## Datenbasis

Das Plugin legt keine Tabellen an und greift ausschließlich **lesend** auf die Daten des Crawlers zu.

| Tabelle | Verwendete Spalten | Zweck |
|---|---|---|
| `<präfix>offers` | `id`, `title`, `description`, `provider`, `url`, `level`, `crawl_list_id` | Weiterbildungsangebote |
| `<präfix>offer_competencies` | `offer_id`, `competency`, `score` | Bewertung je Angebot und Kompetenz (0–1). Die Zeile mit `competency = 'Sprache'` enthält die Kurssprache des Angebots. |
| `<präfix>competency_types` | `slug`, `label`, `type` | Kriterien. Als Regler erscheinen Einträge mit `type = 'float'` (außer `level`). |
| `<präfix>categories` | `id`, `slug`, `title` | Zielgruppen. `Default` steht für „Alle Zielgruppen“. |
| `<präfix>category_competency_type` | `category_slug`, `competency_type_slug` | Zuordnung von Kriterien zu Zielgruppen |
| `crawl_list` (**ohne** Präfix) | `id`, `master_id` | Verbindung Angebot → Quelle (Zielgruppenfilter) |
| `categories_crawl_master` (**ohne** Präfix) | `categories_slug`, `crawl_master_id` | Zuordnung Zielgruppe → Quelle |

`<präfix>` ist das WordPress-Tabellenpräfix (`$table_prefix` in der `wp-config.php`, meist `wp_`). Die beiden Tabellen für den Zielgruppenfilter werden ohne Präfix angesprochen.

Welche Tabellen und Spalten der Crawler tatsächlich erzeugt, steht in dessen README. Fehlende oder abweichend benannte Tabellen (z. B. `competency_types`, `categories`, `category_competency_type`, `categories_crawl_master`) müssen ergänzt bzw. per View angepasst werden.

### Anbindung über Views (empfohlen)

WordPress greift nur lesend auf die Daten zu. Die Crawler-Tabellen müssen daher nicht kopiert werden: Es genügt, in der WordPress-Datenbank **pro Tabelle einen View** anzulegen, der auf die Crawler-Tabelle zeigt. Das Plugin sieht so stets aktuelle Daten, und der Crawler bleibt die einzige Datenquelle.

Platzhalter: `crawl_db` = Datenbank des Crawlers, `wordpress_db` = Datenbank von WordPress, `wp_` = Tabellenpräfix. Beide Datenbanken müssen auf demselben MySQL-/MariaDB-Server liegen.

```sql
USE wordpress_db;

-- Tabellen mit WordPress-Präfix
CREATE OR REPLACE VIEW wp_offers                   AS SELECT * FROM crawl_db.offers;
CREATE OR REPLACE VIEW wp_offer_competencies       AS SELECT * FROM crawl_db.offer_competencies;
CREATE OR REPLACE VIEW wp_competency_types         AS SELECT * FROM crawl_db.competency_types;
CREATE OR REPLACE VIEW wp_categories               AS SELECT * FROM crawl_db.categories;
CREATE OR REPLACE VIEW wp_category_competency_type AS SELECT * FROM crawl_db.category_competency_type;

-- Tabellen ohne Präfix
CREATE OR REPLACE VIEW crawl_list                  AS SELECT * FROM crawl_db.crawl_list;
CREATE OR REPLACE VIEW categories_crawl_master     AS SELECT * FROM crawl_db.categories_crawl_master;
```

Hinweise:

- Der WordPress-Datenbankbenutzer braucht `SELECT` auf die Views **und** auf die zugrunde liegenden Crawler-Tabellen, z. B. `GRANT SELECT ON crawl_db.* TO 'wp_user'@'localhost';`.
- Ein View kann nicht denselben Namen wie eine Tabelle in derselben Datenbank tragen. Liegen Crawler und WordPress in einer Datenbank, müssen die Tabellen mit dem Präfix dort entsprechend anders heißen oder bereits vorhanden sein.
- Weicht ein Spaltenname ab, kann der View ihn umbenennen (`SELECT crawler_spalte AS title, … FROM …`), sodass das Plugin unverändert bleibt.

## Nutzung

1. Auf der Einstellungsseite Zielgruppe wählen (sofern mehr als eine Zielgruppe existiert) und relevante **Kriterien** auswählen.
2. Zu **Einstellen** wechseln und je Kriterium Interesse und Fähigkeit festlegen. Die Vorschau aktualisiert sich bei jeder Änderung.
3. Über den Ergebnislink zum Katalog wechseln. Dort lassen sich Zielgruppe, Regler und Sprache weiter anpassen.

### URL-Parameter

| Parameter | Bedeutung |
|---|---|
| `Kategorie=<slug>` | Zielgruppe (ungültige Werte fallen auf `Default` zurück) |
| `<kompetenz-slug>=0..100` | Interesse an der Kompetenz (gerundet auf 25er-Schritte) |
| `<kompetenz-slug>_level=0..100` | eigene Fähigkeit bei dieser Kompetenz |
| `lang=<code>` | Sprache bei AJAX-Aufrufen (wird von Polylang bzw. dem Plugin gesetzt) |

Nicht gewählte Kriterien fehlen in der URL und gehen nicht in die Berechnung ein. Beispiel: `/results/?Kategorie=Default&kommunikation=75&kommunikation_level=50` (die Slugs hängen von `competency_types` ab).

## Berechnung der Übereinstimmung

Je Angebot wird `match_total` (0–1) berechnet, ausgehend von den gewählten Kriterien:

- Für jedes Kriterium mit Interesse `I > 0` und Angebotsbewertung `K > 0,3`:
  `Teilwert = 0,75 × K × I + 0,25 × (1 − |Fähigkeit − Angebotsniveau|)`
  (Fähigkeit ohne Angabe: 0,5; Angebotsniveau = `offers.level`)
- Die Übereinstimmung des Angebots ist der **höchste Teilwert** über alle Kriterien (nicht der Durchschnitt). Angebote mit Wert 0 werden nicht angezeigt.
- Sortierung: `match_total` absteigend, danach Titel.
- Ohne gewählte Kriterien werden alle Angebote alphabetisch ohne Prozentangabe gezeigt.

Gewichtung (75 % / 25 %) und Mindestbewertung (0,3) sind in `includes/Classes/Offers.php` festgelegt.

## Filter für Entwickler

| Filter | Zweck |
|---|---|
| `competency_slider_offer_language_map` | Zuordnung Sprachkürzel → Kurssprache in der Datenbank (Standard: `de` → `Deutsch`, `en` → `Englisch`) |
| `competency_slider_offer_language` | Kurssprache überschreiben. Ein leerer Wert deaktiviert den Sprachfilter. |

## Projektstruktur

```
competency-slider.php            Plugin-Einstieg: Blöcke, Skripte, Styles, AJAX-Handler
includes/
  autoload.php                   Autoloader (Namespace CompetencySlider)
  Classes/Blocks.php             Rendering beider Blöcke, Seitenleiste, Ergebnisliste
  Classes/Offers.php             SQL für Ranking und Zähl-Buckets
  Classes/I18n.php               Polylang-Anbindung, Sprachlogik
slider/                          Block "Competency Slider" (block.json, editor.js, frontend.js, style.css)
results/                         Block "Competency Results" (block.json, editor.js, frontend.js, style.css)
languages/                       Übersetzungen (de_DE: .po, .mo, .l10n.php, Editor-JSON)
tools/build-translations.php     Erzeugt die de_DE-Übersetzungsdateien (php tools/build-translations.php)
LICENSE                          MIT-Lizenz
```

### AJAX-Endpunkte

Alle über `admin-ajax.php`, auch für nicht angemeldete Besucher:

| Action | Zweck |
|---|---|
| `get_offers` | Live-Vorschau (Anzahl je Match-Bereich) unter den Reglern |
| `get_results` | Ergebnisliste neu laden |
| `get_sidebar` | Seitenleiste bei Zielgruppenwechsel neu laden |


## Verwandte Projekte

- **Crawler:** [detlevrichter/thk_crawl](https://github.com/detlevrichter/thk_crawl) – sammelt und bewertet die Weiterbildungsangebote, auf denen dieses Plugin aufbaut.

## Lizenz

[MIT](LICENSE) © 2026 Detlev Richter / TH Köln

## Kontext

Entstanden an der **TH Köln** im Projekt **Digitalkompetenz.nrw**.
