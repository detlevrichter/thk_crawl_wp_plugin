# Competency Slider – WordPress-Plugin für die Weiterbildungsdatenbank

WordPress-Plugin, mit dem Nutzende ihr **Interesse** und ihre **Fähigkeit** für verschiedene Kompetenzen über Regler einstellen und einen nach Übereinstimmung sortierten Katalog passender Weiterbildungsangebote erhalten.

Es ist im Rahmen des Projekts **Digitalkompetenz.nrw** an der **TH Köln** entstanden und das Frontend zum Crawler [thk_crawl](https://github.com/detlevrichter/thk_crawl): Der Crawler sammelt Angebote von Anbieter-Webseiten und bewertet sie per LLM nach Kompetenzfacetten, das Plugin stellt diese Daten in WordPress dar.

> Dieser Branch (`master`) enthält die einfache, deutschsprachige Version (Plugin-Version **1.1**). Die erweiterte Version mit Zielgruppenauswahl, Mehrsprachigkeit (Polylang) und Ergebnisfilter liegt im Branch [`digitalkompetenz`](https://github.com/detlevrichter/thk_crawl_wp_plugin/tree/digitalkompetenz).

## Funktionen

- **Block „Competency Slider“:** Tabelle mit einer Zeile je Kompetenz. Jede Zeile hat eine Checkbox (Kriterium berücksichtigen oder nicht) sowie zwei Regler (1–100): **Interesse** und **Fähigkeit**.
- **Live-Vorschau** per AJAX: Unter den Reglern steht, wie viele Angebote zu über 80 %, 60–80 %, 40–60 %, 20–40 % und unter 20 % passen, sowie die Gesamtzahl.
- **Block „Competency Results“:** Liste der Angebote mit Titel, Beschreibung, Übereinstimmung in %, Anbieter und Link zum Angebot (neuer Tab), sortiert nach Übereinstimmung.
- **Zielgruppen:** Über den URL-Parameter `Kategorie` werden die Kriterien der jeweiligen Zielgruppe angezeigt. Ist sie unbekannt oder fehlt, gilt `Default`.
- **Teilbare Links:** Alle Einstellungen stehen als URL-Parameter in der Adresse.

## Voraussetzungen

- WordPress mit Block-Editor und PHP 7.4 oder neuer
- Die Datenbanktabellen des Crawlers, erreichbar aus WordPress (siehe [Datenbasis](#datenbasis))
- Ein bereits durchgeführter Crawl mit [thk_crawl](https://github.com/detlevrichter/thk_crawl)

Das Frontend-Skript nutzt das in WordPress mitgelieferte jQuery.

## Installation

1. Den Ordner dieses Repositories in `wp-content/plugins/` legen (per `git clone` oder als hochgeladenes Archiv).
2. Im WordPress-Backend unter **Plugins** *Competency Slider Block* aktivieren.
3. Crawler-Daten bereitstellen (siehe [Datenbasis](#datenbasis)).
4. Zwei Seiten anlegen:
   - eine Seite mit dem Block **Competency Slider** (Einstellungen)
   - eine Seite mit dem Block **Competency Results** (Ergebnisse) unter dem Pfad **`/results/`**

Der Pfad der Ergebnisseite ist im Code fest auf `/results/` eingestellt (`includes/Classes/Blocks.php`). Die Ergebnisseite übernimmt außerdem die Parameter an Links, die auf `/einstellung/` zeigen. Die Einstellungsseite sollte deshalb unter diesem Pfad liegen, wenn dieser Rücksprung gewünscht ist.

## Datenbasis

Das Plugin legt keine Tabellen an und greift ausschließlich **lesend** auf die Daten des Crawlers zu. Alle Tabellen werden mit dem WordPress-Tabellenpräfix angesprochen.

| Tabelle | Verwendete Spalten | Zweck |
|---|---|---|
| `<präfix>offers` | `id`, `title`, `description`, `provider`, `url`, `level` | Weiterbildungsangebote |
| `<präfix>offer_competencies` | `offer_id`, `competency`, `score` | Bewertung je Angebot und Kompetenz (0–1) |
| `<präfix>competency_types` | `slug`, `label`, `type` | Kompetenzen. Als Regler erscheinen Einträge mit `type = 'float'` (außer `level`). |
| `<präfix>categories` | `slug` | Zielgruppen. `Default` ist die Standardzielgruppe. |
| `<präfix>category_competency_type` | `category_slug`, `competency_type_slug` | Zuordnung von Kompetenzen zu Zielgruppen |

`<präfix>` ist das WordPress-Tabellenpräfix (`$table_prefix` in der `wp-config.php`, meist `wp_`).

### Anbindung über Views (empfohlen)

Die Crawler-Tabellen müssen nicht kopiert werden: Es genügt, in der WordPress-Datenbank **pro Tabelle einen View** anzulegen, der auf die Crawler-Tabelle zeigt. Das Plugin sieht so stets aktuelle Daten, und der Crawler bleibt die einzige Datenquelle.

Platzhalter: `crawl_db` = Datenbank des Crawlers, `wordpress_db` = Datenbank von WordPress, `wp_` = Tabellenpräfix. Beide Datenbanken müssen auf demselben MySQL-/MariaDB-Server liegen.

```sql
USE wordpress_db;

CREATE OR REPLACE VIEW wp_offers                   AS SELECT * FROM crawl_db.offers;
CREATE OR REPLACE VIEW wp_offer_competencies       AS SELECT * FROM crawl_db.offer_competencies;
CREATE OR REPLACE VIEW wp_competency_types         AS SELECT * FROM crawl_db.competency_types;
CREATE OR REPLACE VIEW wp_categories               AS SELECT * FROM crawl_db.categories;
CREATE OR REPLACE VIEW wp_category_competency_type AS SELECT * FROM crawl_db.category_competency_type;
```

Hinweise:

- Der WordPress-Datenbankbenutzer braucht `SELECT` auf die Views **und** auf die zugrunde liegenden Crawler-Tabellen, z. B. `GRANT SELECT ON crawl_db.* TO 'wp_user'@'localhost';`.
- Weicht ein Spaltenname ab, kann der View ihn umbenennen (`SELECT crawler_spalte AS title, … FROM …`), sodass das Plugin unverändert bleibt.

## Nutzung

1. Auf der Einstellungsseite mit den Checkboxen festlegen, welche Kompetenzen berücksichtigt werden, und je Kompetenz Interesse und Fähigkeit einstellen. Die Vorschau unter der Tabelle aktualisiert sich bei jeder Änderung.
2. Über „Ergebnisse anzeigen“ zur Ergebnisseite wechseln.

### URL-Parameter

| Parameter | Bedeutung |
|---|---|
| `Kategorie=<slug>` | Zielgruppe (ungültige Werte fallen auf `Default` zurück) |
| `<kompetenz-slug>=1..100` | Interesse an der Kompetenz |
| `<kompetenz-slug>_level=1..100` | eigene Fähigkeit bei dieser Kompetenz |

Abgewählte Kompetenzen fehlen in der URL und gehen nicht in die Berechnung ein. Beispiel: `/results/?Kategorie=Default&kommunikation=75&kommunikation_level=50` (die Slugs hängen von `competency_types` ab). Ohne Parameter zeigt die Ergebnisseite „Keine Filterparameter übergeben.“

## Berechnung der Übereinstimmung

Je Angebot wird `match_total` (0–1) berechnet:

- **Interesse:** gewichteter Mittelwert der Angebotsbewertungen über die gewählten Kompetenzen, gewichtet mit dem eingestellten Interesse:
  `match_interest = Σ(Bewertung × Interesse) / Σ Interesse`
- **Fähigkeit:** `match_skill = 1 − |Ø eigene Fähigkeit − Angebotsniveau|` (ohne Angabe: 0,5; Angebotsniveau = `offers.level`)
- **Gesamt:** `match_total = 0,75 × match_interest + 0,25 × match_skill`
- Sortierung nach `match_total` absteigend.

Die Gewichtung (75 % / 25 %) ist in `includes/Classes/Offers.php` festgelegt.

## Projektstruktur

```
competency-slider.php            Plugin-Einstieg: Blöcke, Skripte, AJAX-Handler
includes/
  autoload.php                   Autoloader (Namespace CompetencySlider)
  Classes/Blocks.php             Rendering beider Blöcke
  Classes/Offers.php             SQL für Ranking und Zähl-Buckets
slider/                          Block "Competency Slider" (block.json, editor.js, frontend.js)
results/                         Block "Competency Results" (block.json, editor.js)
LICENSE                          MIT-Lizenz
```

Die AJAX-Action `get_offers` (über `admin-ajax.php`, auch für nicht angemeldete Besucher) liefert die Live-Vorschau.

## Verwandte Projekte

- **Crawler:** [detlevrichter/thk_crawl](https://github.com/detlevrichter/thk_crawl) – sammelt und bewertet die Weiterbildungsangebote, auf denen dieses Plugin aufbaut.

## Lizenz

[MIT](LICENSE) © 2026 Detlev Richter / TH Köln
