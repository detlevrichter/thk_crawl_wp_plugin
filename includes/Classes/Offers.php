<?php
namespace CompetencySlider\Classes;

defined('ABSPATH') || exit;

class Offers{

    public static function get(){
        global $wpdb;
        /** ###############  category_id    ############### */
        $category_slug = 'Default';

        $percentInteresse = 0.75;
        $percentSkill = 0.25;

        if (empty($_GET)) {
            return '<p>Keine Filterparameter übergeben.</p>';
        }
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

        /*
        SUM(
            CASE competency
                WHEN 'KI' THEN score * 0.2
                WHEN 'Bearbeitung'  THEN score * 0.4
                ELSE 0
            END
        )
        */
        foreach ($_GET as $key => $value) {
            if (!isset($allowed_keys[$key])) {
               continue;
            }


            if(stristr($key,'_level') === false){
                $conditions[] = "WHEN '{$key}' THEN score * " . intval($value) / 100;
                $values[]     = intval($value) / 100;

            }else{
                $levels[] = intval($value) / 100;
            }
        }
        $scoreSumme = array_sum($values);
        /*
        Match_Interesst = (Offer.Kompetenz_1 * Interesst.Kompetenz_1 
                            + Offer.Kompetenz_2 * Interesst.Kompetenz_2 
                            + Offer.Kompetenz_3 * Interesst.Kompetenz_13)
          / (Interesst.Kompetenz_1 + Interesst.Kompetenz_2 + Interesst.Kompetenz_3)
        */
        $matchCompetencieSql  = "SUM( CASE competency \n" . join("\n", $conditions) . " END) / {$scoreSumme} as match_interest "; 

        $competencieSql = "SELECT offer_id, {$matchCompetencieSql} FROM  {$wpdb->prefix}offer_competencies as ok 
                            INNER JOIN {$wpdb->prefix}category_competency_type as c2c ON c2c.competency_type_slug = ok.competency 
                            WHERE category_slug = '{$category_slug}' 
                            GROUP BY offer_id
                            ";
 
        $levels = array_filter($levels);
        if(count($levels)) {
            $averageLevel = array_sum($levels)/count($levels);
        }else{
            $averageLevel = 0.5;
        }
       // Match_Skill = (1 - ABS(Offer.Level - Skills))
       $skillSql = "SELECT *, 
                    (1 - ABS(of.level - {$averageLevel})) as match_skill ,
                    match_interest * {$percentInteresse} + (1 - ABS(of.level - {$averageLevel}))  * {$percentSkill} as match_total
                    FROM {$wpdb->prefix}offers as of
                    INNER JOIN ({$competencieSql}) as ok ON of.id = ok.offer_id
                    INNER JOIN offer_dates as od on of.id = od.offer_id
                    WHERE of.title != \"\"
                    ORDER BY match_total desc
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
