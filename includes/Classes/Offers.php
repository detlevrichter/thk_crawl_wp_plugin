<?php
namespace CompetencySlider\Classes;

defined('ABSPATH') || exit;

class Offers{

    public static function get($applyLanguageFilter = true){
        global $wpdb;
        /** ###############  category_id    ############### */
        $category_slug = 'Default';

        $percentInteresse = 0.75;
        $percentSkill = 0.25;

        $table = $wpdb->prefix . 'competency_types';

        $conditions = [];
        $values     = [];
        $levels     = [];
        $allowed_keys = wp_cache_get('competency_slugs');

        if ($allowed_keys === false) {
            $allowed_keys = $wpdb->get_col("SELECT slug FROM $table");
            wp_cache_set('competency_slugs', $allowed_keys);
        }

        $allowed_keys = array_flip($allowed_keys);
        $table = $wpdb->prefix . 'categories';

        // GET-Wert holen und leicht vorvalidieren
        $slug = isset($_GET['Kategorie']) ? sanitize_key($_GET['Kategorie']) : '';

        $category_slug = null;

        if ($slug !== '') {
            $category_slug = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT slug FROM $table WHERE slug = %s",
                    $slug
                )
            );
        }

        // Optional: prüfen ob gefunden
        if ($category_slug === null) {
            $category_slug = 'Default';
        }


        // Zielgruppe fuer den Angebotsfilter merken, bevor das Matching
        // weiter unten ggf. auf 'Default' zurueckfaellt.
        $audience_slug = $category_slug;
		
		        /*
        Zielgruppenfilter: Jedes Angebot haengt ueber crawl_list (master_id)
        an einer Quelle aus crawl_master; categories_crawl_master ordnet die
        Quellen den Zielgruppen zu. 'Default' (Alle Zielgruppen) filtert nicht
        und zeigt auch Angebote, deren Quelle keiner Zielgruppe zugeordnet ist.
        Beide Tabellen tragen wie alle anderen das WordPress-Praefix.
        */
        $audienceSql = '';

        if ($audience_slug !== 'Default') {
            $audienceSql = $wpdb->prepare(
                " AND of.crawl_list_id IN (
                    SELECT cl.id
                    FROM {$wpdb->prefix}crawl_list AS cl
                    INNER JOIN {$wpdb->prefix}categories_crawl_master AS ccm ON ccm.crawl_master_id = cl.master_id
                    WHERE ccm.categories_slug = %s
                )",
                $audience_slug
            );
        }

		
        if ($category_slug !== 'Default') {
            $has_mapping = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT 1 FROM {$wpdb->prefix}category_competency_type WHERE category_slug = %s LIMIT 1",
                    $category_slug
                )
            );

            if (!$has_mapping) {
                $category_slug = 'Default';
            }
        }

        /*
        Eingaben der Person aus der URL: <slug> = Interesse, <slug>_level =
        Level im jeweiligen Kriterium (beides 0-100). Abgewaehlte Kriterien
        fehlen in der URL (ihre Regler sind dann disabled) und zaehlen nicht.
        */
        $interests    = [];
        $personLevels = [];

        foreach ($_GET as $key => $value) {
            $slug = str_replace('_level', '', $key);
            if (!isset($allowed_keys[$slug])) {
                continue;
            }

            if ($slug === $key) {
                $interests[$slug] = max(0, min(100, intval($value))) / 100;
            } else {
                $personLevels[$slug] = max(0, min(100, intval($value))) / 100;
            }
        }

        /*
        Sprachfilter: wp_offer_competencies fuehrt pro Angebot eine Zeile
        competency = 'Sprache' mit der Kurssprache. Auf der englischen Seite
        werden so nur englischsprachige Angebote gezeigt.
        Abschalten: add_filter('competency_slider_offer_language', '__return_empty_string');
        $applyLanguageFilter = false laesst stattdessen alle Sprachen durch -
        fuer die Ergebnisliste, die den Sprachfilter per data-Attribut im
        Frontend erledigt (siehe results/frontend.js). Das offer_language-Feld
        wird dafuer in jedem Fall mitgegeben, ganz ohne zweite Abfrage.
        */
        $languageJoin = "LEFT JOIN (
                SELECT offer_id AS lang_offer_id, score AS offer_language
                FROM {$wpdb->prefix}offer_competencies
                WHERE competency = 'Sprache'
            ) AS lang ON lang.lang_offer_id = of.id";

        $languageSql = '';

        if ($applyLanguageFilter) {
            $offerLanguage = I18n::offer_language();

            if ($offerLanguage !== '') {
                $languageSql = $wpdb->prepare(' AND lang.offer_language = %s', $offerLanguage);
            }
        }

        /*
        Ohne explizite Werte in der URL (erster Aufruf der Ergebnisseite):
        alle Kompetenzen der Zielgruppe mit dem Standardwert (50 %) ansetzen.
        Das entspricht genau dem Zustand, den die Sidebar in diesem Fall schon
        anzeigt (alle Kriterien aktiv, Regler auf 50) - so gibt es von Anfang
        an eine Uebereinstimmung in Prozent statt einer unbewerteten Liste.
        */
        if (empty($interests)) {
            $default_slugs = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT c.slug
                     FROM {$wpdb->prefix}competency_types AS c
                     INNER JOIN {$wpdb->prefix}category_competency_type AS cct
                        ON c.slug = cct.competency_type_slug
                     WHERE c.type = 'float' AND c.slug != 'level' AND cct.category_slug = %s",
                    $category_slug
                )
            );

            foreach ($default_slugs as $default_slug) {
                $interests[$default_slug] = 0.5;
            }
        }

        /*
        Matching (Stand 02.10.2026, "2026-10-02 Matching.xlsm", Spalte A):
        Pro Kriterium k ein Teilwert, die Uebereinstimmung ist das Maximum.

          Teilwert_k = 0,75 * K_k * I_k + 0,25 * (1 - |L_Person,k - L_Angebot|)
                       wenn K_k > 0,3 und I_k > 0, sonst 0

          K_k         Einschaetzung des Angebots im Kriterium (offer_competencies.score)
          I_k         Interesse der Person im Kriterium
          L_Person,k  Level der Person im Kriterium (ohne URL-Wert: 0,5)
          L_Angebot   Level des Angebots (offers.level)
        */
        $minScore   = 0.3;
        $conditions = [];

        foreach ($interests as $slug => $interest) {
            if ($interest <= 0) {
                continue;
            }

            $personLevel  = isset($personLevels[$slug]) ? (float) $personLevels[$slug] : 0.5;
            $interest     = (float) $interest;
            $safeSlug     = esc_sql($slug);
            $conditions[] = "WHEN '{$safeSlug}' THEN IF(ok.score > {$minScore}, ok.score * {$interest} * {$percentInteresse} + (1 - ABS({$personLevel} - o2.level)) * {$percentSkill}, 0)";
        }

        // Kein Kriterium mit Interesse > 0 (oder Zielgruppe ohne Kompetenzen):
        // keine Grundlage fuer ein Matching, alle Angebote ohne Prozentanzeige.
        if (empty($conditions)) {
            return "SELECT lang.offer_language, of.*
                    FROM {$wpdb->prefix}offers as of
                    {$languageJoin}
                    WHERE of.title != \"\" {$languageSql}{$audienceSql}
                    ORDER BY of.title ASC
                    ";
        }

        $safeCategory = esc_sql($category_slug);

        $matchSql = "SELECT ok.offer_id,
                        MAX(CASE ok.competency
                            " . join("\n                            ", $conditions) . "
                            ELSE 0
                        END) AS match_value
                     FROM {$wpdb->prefix}offer_competencies AS ok
                     INNER JOIN {$wpdb->prefix}offers AS o2 ON o2.id = ok.offer_id
                     INNER JOIN {$wpdb->prefix}category_competency_type AS c2c ON c2c.competency_type_slug = ok.competency
                     WHERE c2c.category_slug = '{$safeCategory}'
                     GROUP BY ok.offer_id";

        /*
        Angebote ohne passendes Kriterium (Uebereinstimmung 0 %) werden
        ausgeblendet; ok.match_value ist fuer sie 0 oder NULL.
        Das "SELECT *," am Anfang muss so bleiben: countSql() entfernt es per
        Regex, um die Abfrage als Unterabfrage zu zaehlen.
        */
        $skillSql = "SELECT *,
                    COALESCE(ok.match_value, 0) AS match_total
                    FROM {$wpdb->prefix}offers as of
                    LEFT JOIN ({$matchSql}) as ok ON of.id = ok.offer_id
                    {$languageJoin}
                    WHERE of.title != \"\" AND ok.match_value > 0 {$languageSql}{$audienceSql}
                    ORDER BY match_total DESC, of.title ASC
                    ";
        return $skillSql;

    }

    public static function countSql(array $prozente){
        $sqlpart = [];
        arsort($prozente);
        $i = 0;
        foreach($prozente as $prozent){
            $p = floatval($prozent);
            $n = (string)($p * 100);
            if(!$i){
                $sqlpart[] = "SUM(match_total > {$p}) AS p{$n}";
            }else{
                $sqlpart[] = "SUM(match_total > {$p} AND match_total <= {$i}) AS p{$n}";
            }
            $i = $p;
        }
        $part = join(",\n",$sqlpart);
        $sql = self::get();
        $sql = preg_replace('/\* ?,/','',$sql);
        $sqlFinal = "SELECT {$part} FROM ({$sql}) as dt";
        return $sqlFinal ;
    }


}
