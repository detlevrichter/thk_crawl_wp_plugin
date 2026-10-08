<?php
namespace CompetencySlider\Classes;

defined('ABSPATH') || exit;

/**
 * Mehrsprachigkeit.
 *
 * Zwei Ebenen werden unterschieden:
 *
 * 1. Oberflächentexte des Plugins – normale WordPress-Übersetzungen
 *    (Quelltext englisch, Übersetzungen unter languages/).
 * 2. Inhalte aus den eigenen Datenbanktabellen (Kompetenz-Labels,
 *    Zielgruppen-Titel) – diese sind keine Posts und können deshalb nicht von
 *    Polylang dupliziert werden. Sie werden über die String-Übersetzungen von
 *    Polylang gepflegt (Sprachen -> Zeichenkettenübersetzungen).
 *
 * Ohne Polylang bleibt alles funktionsfähig; es greift dann die Sprache aus
 * get_locale().
 */
class I18n
{
    /** Gruppenname in der Polylang-Oberfläche. */
    const STRING_GROUP = 'Competency Slider';

    /** Textdomain des Plugins. */
    const TEXT_DOMAIN = 'competency-slider';

    public static function polylang_active()
    {
        return function_exists('pll__');
    }

    /**
     * Sprach-Slug der aktuellen Anfrage, z. B. "de" oder "en".
     */
    public static function current_language()
    {
        if (function_exists('pll_current_language')) {
            $slug = pll_current_language('slug');

            if ($slug) {
                return $slug;
            }
        }

        return substr(determine_locale(), 0, 2);
    }

    /**
     * Alle eingerichteten Sprachen als [slug => locale].
     */
    public static function languages()
    {
        if (!function_exists('pll_languages_list')) {
            return [substr(determine_locale(), 0, 2) => determine_locale()];
        }

        $slugs   = pll_languages_list(['fields' => 'slug']);
        $locales = pll_languages_list(['fields' => 'locale']);

        if (!$slugs || count($slugs) !== count($locales)) {
            return [];
        }

        return array_combine($slugs, $locales);
    }

    /**
     * Registriert die Datenbank-Labels für die Polylang-Zeichenkettenübersetzung.
     * Nur im Backend nötig – dort wird die Liste aufgebaut und gepflegt.
     */
    public static function register_strings()
    {
        if (!is_admin() || !function_exists('pll_register_string')) {
            return;
        }

        global $wpdb;

        $labels = $wpdb->get_col(
            "SELECT DISTINCT label FROM {$wpdb->prefix}competency_types WHERE label <> ''"
        );

        foreach ((array) $labels as $label) {
            pll_register_string(
                'competency-' . sanitize_title($label),
                $label,
                self::STRING_GROUP,
                mb_strlen($label) > 60
            );
        }

        $titles = $wpdb->get_col(
            "SELECT DISTINCT title FROM {$wpdb->prefix}categories WHERE title <> ''"
        );

        foreach ((array) $titles as $title) {
            pll_register_string(
                'category-' . sanitize_title($title),
                $title,
                self::STRING_GROUP,
                false
            );
        }
    }

    /**
     * Übersetzt ein Label aus der Datenbank in die aktuelle Sprache.
     */
    public static function translate($text)
    {
        if ($text === null || $text === '') {
            return $text;
        }

        return self::polylang_active() ? pll__($text) : $text;
    }

    /**
     * Startseite der aktuellen Sprache – Basis für Fallback-Links.
     */
    public static function home_url()
    {
        if (function_exists('pll_home_url')) {
            $url = pll_home_url();

            if ($url) {
                return $url;
            }
        }

        return home_url('/');
    }

    /**
     * Kurssprache, nach der die Angebote gefiltert werden.
     *
     * Die Tabelle wp_offer_competencies führt pro Angebot eine Zeile
     * competency = 'Sprache' mit den deutschen Sprachbezeichnungen
     * ("Deutsch", "Englisch"). Über den Filter competency_slider_offer_language
     * lässt sich der Wert ändern oder mit '' die Filterung abschalten.
     */
    public static function offer_language()
    {
        $map = apply_filters('competency_slider_offer_language_map', [
            'de' => 'Deutsch',
            'en' => 'Englisch',
        ]);

        $language = self::current_language();
        $value    = isset($map[$language]) ? $map[$language] : '';

        return apply_filters('competency_slider_offer_language', $value, $language);
    }

    /**
     * Der Sprachumschalter verwirft standardmäßig die Query-Parameter. Auf den
     * Seiten mit unseren Blöcken stecken darin aber die eingestellten
     * Interessen und Fähigkeiten – die bleiben so beim Sprachwechsel erhalten.
     *
     * @param string $url  Ziel-URL der anderen Sprache.
     * @param string $slug Sprach-Slug.
     */
    public static function keep_query_on_language_switch($url, $slug)
    {
        unset($slug);

        if (is_admin() || !$url || empty($_GET) || !is_singular()) {
            return $url;
        }

        $post = get_post();

        if (!$post
            || (!has_block('competency/slider', $post) && !has_block('competency/results', $post))
        ) {
            return $url;
        }

        $args = array_diff_key($_GET, ['lang' => '']);

        if (empty($args)) {
            return $url;
        }

        return add_query_arg(array_map('sanitize_text_field', wp_unslash($args)), $url);
    }

    /**
     * In AJAX-Anfragen steht die Sprache nicht zwingend fest. Das Frontend
     * schickt sie deshalb mit; der Wert wird gegen die eingerichteten Sprachen
     * geprüft und nur dann übernommen.
     */
    public static function apply_request_language()
    {
        if (empty($_REQUEST['lang'])) {
            return;
        }

        $requested = sanitize_key(wp_unslash($_REQUEST['lang']));
        $languages = self::languages();

        if (!isset($languages[$requested])) {
            return;
        }

        $locale = $languages[$requested];

        // Polylang für pll__() auf die angeforderte Sprache stellen.
        if (function_exists('PLL') && PLL() && isset(PLL()->model)) {
            $language = PLL()->model->get_language($requested);

            if ($language) {
                PLL()->curlang = $language;

                if (method_exists(PLL(), 'load_strings_translations')) {
                    PLL()->load_strings_translations($locale);
                }
            }
        }

        if ($locale !== determine_locale()) {
            switch_to_locale($locale);
        }
    }
}
