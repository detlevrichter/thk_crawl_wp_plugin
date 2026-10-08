<?php
/**
 * Plugin Name: Competency Slider International
 * Description: Continuing education database – choose target group, criteria and interests/skills, then show matching offers. Design based on the Penpot handoff, styled with the design tokens of the active theme. Bilingual via Polylang.
 * Version: 2.3.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: competency-slider
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/autoload.php';
\CompetencySlider\Autoloader::register();

define('COMPETENCY_SLIDER_VERSION', '2.3.1');

use CompetencySlider\Classes\Blocks;
use CompetencySlider\Classes\I18n;

/**
 * Übersetzungen laden, bevor die Blöcke registriert werden – die Titel aus
 * block.json werden dabei bereits übersetzt.
 */
add_action('init', function () {
    load_plugin_textdomain(
        'competency-slider',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}, 1);

/**
 * Kompetenz-Labels und Zielgruppen-Titel für die Polylang-String-Übersetzung
 * anmelden (läuft nur im Backend, siehe I18n::register_strings()).
 */
add_action('init', [I18n::class, 'register_strings'], 5);

/**
 * Beim Sprachwechsel die eingestellten Kriterien mitnehmen.
 */
add_filter('pll_the_language_link', [I18n::class, 'keep_query_on_language_switch'], 10, 2);

add_action('init', function () {

    // === SLIDER BLOCK ===
    $languages_path = plugin_dir_path(__FILE__) . 'languages';

    wp_register_script(
        'competency-slider-editor',
        plugins_url('slider/editor.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-i18n'],
        COMPETENCY_SLIDER_VERSION
    );

    wp_set_script_translations('competency-slider-editor', 'competency-slider', $languages_path);

    wp_register_script(
        'competency-slider-frontend',
        plugins_url('slider/frontend.js', __FILE__),
        [],
        COMPETENCY_SLIDER_VERSION,
        true
    );

    // Styles hängen am Theme-Stylesheet, damit sie danach geladen werden und
    // die Design-Tokens aus theme.json bereits gesetzt sind.
    $style_deps = wp_style_is('dk-theme', 'registered') ? ['dk-theme'] : [];

    wp_register_style(
        'competency-slider-style',
        plugins_url('slider/style.css', __FILE__),
        $style_deps,
        COMPETENCY_SLIDER_VERSION
    );

    // Die Sidebar der Ergebnisseite nutzt dieselben Slider-Klassen (.cs-slider,
    // .cs-step, .cs-block-Variablen) wie der Einstellungen-Block – deshalb haengt
    // competency-results-style zusaetzlich von competency-slider-style ab.
    wp_register_style(
        'competency-results-style',
        plugins_url('results/style.css', __FILE__),
        array_merge($style_deps, ['competency-slider-style']),
        COMPETENCY_SLIDER_VERSION
    );

    register_block_type(__DIR__ . '/slider', [
        'editor_script'   => 'competency-slider-editor',
        'script'          => 'competency-slider-frontend',
        'style'           => 'competency-slider-style',
        'render_callback' => [Blocks::class, 'competency_slider_render'],
    ]);

    // === RESULTS BLOCK ===
    wp_register_script(
        'competency-results-editor',
        plugins_url('results/editor.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-i18n'],
        COMPETENCY_SLIDER_VERSION
    );

    wp_set_script_translations('competency-results-editor', 'competency-slider', $languages_path);

    wp_register_script(
        'competency-results-frontend',
        plugins_url('results/frontend.js', __FILE__),
        [],
        COMPETENCY_SLIDER_VERSION,
        true
    );

    register_block_type(__DIR__ . '/results', [
        'editor_script'   => 'competency-results-editor',
        'script'          => 'competency-results-frontend',
        'style'           => 'competency-results-style',
        'render_callback' => [Blocks::class, 'competency_results_render'],
    ]);
});

/**
 * Daten für das Frontend-Skript. Erst hier verfügbar, weil Polylang die
 * aktuelle Sprache der Anfrage zu diesem Zeitpunkt sicher kennt.
 */
add_action('wp_enqueue_scripts', function () {
    wp_localize_script('competency-slider-frontend', 'CompetencySlider', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'lang'    => I18n::current_language(),
        'i18n'    => [
            'noSelection' => __('No criterion is selected. Use "Change criteria" to select at least one.', 'competency-slider'),
            /* translators: 1: number of selected criteria, 2: total number. */
            'summary'     => __('%1$s of %2$s criteria selected.', 'competency-slider'),
        ],
    ]);

    wp_localize_script('competency-results-frontend', 'CompetencyResults', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
}, 20);

/**
 * AJAX: Live-Vorschau der Treffer während des Einstellens.
 * Wird als Notification Bar unter den Slidern ausgegeben.
 */
add_action('wp_ajax_get_offers', 'competency_get_offers');
add_action('wp_ajax_nopriv_get_offers', 'competency_get_offers');

function competency_get_offers() {
    global $wpdb;

    // admin-ajax.php kennt die Sprache nicht sicher – das Frontend schickt sie mit.
    I18n::apply_request_language();

    // Ohne mindestens ein Kriterium lässt sich keine Auswertung bilden.
    $criteria_params = array_diff_key($_GET, ['Kategorie' => '', 'lang' => '', 'action' => '']);

    if (empty($criteria_params)) {
        wp_die('', '', ['response' => 200]);
    }

    $rows = $wpdb->get_results(
        \CompetencySlider\Classes\Offers::countSql([0.8, 0.6, 0.4, 0.2, 0])
    );

    if (empty($rows)) {
        wp_die('', '', ['response' => 200]);
    }

    $row = $rows[0];

    $buckets = [
        __('over 80%', 'competency-slider')  => (int) $row->p80,
        __('60 – 80%', 'competency-slider')  => (int) $row->p60,
        __('40 – 60%', 'competency-slider')  => (int) $row->p40,
        __('20 – 40%', 'competency-slider')  => (int) $row->p20,
        __('under 20%', 'competency-slider') => (int) $row->p0,
    ];

    $total = array_sum($buckets);

    ob_start();
    ?>
    <p class="cs-notice__title">
        <?php
        printf(
            /* translators: %s: number of matching offers. */
            esc_html(_n('%s matching offer found', '%s matching offers found', $total, 'competency-slider')),
            '<strong>' . esc_html($total) . '</strong>'
        );
        ?>
    </p>
    <p><?php esc_html_e('Match with your interests and skills:', 'competency-slider'); ?></p>
    <ul class="cs-matchlist">
        <?php foreach ($buckets as $label => $count): ?>
            <li><b><?php echo esc_html($count); ?></b> <?php echo esc_html($label); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php

    echo ob_get_clean();
    wp_die();
}

/**
 * AJAX: Ergebnisliste der Ergebnisseite live nachladen, waehrend in der
 * Sidebar an den Slidern gezogen wird (siehe results/frontend.js). Analog
 * zur Notification Bar oben, nur dass hier die tatsaechliche Kartenliste
 * ersetzt wird statt nur ein Zahlen-Preview.
 */
add_action('wp_ajax_get_results', 'competency_get_results');
add_action('wp_ajax_nopriv_get_results', 'competency_get_results');

function competency_get_results() {
    global $wpdb;

    I18n::apply_request_language();

    $results = $wpdb->get_results(\CompetencySlider\Classes\Offers::get(false));
    $count   = is_array($results) ? count($results) : 0;

    echo Blocks::render_results_panel($results, $count);
    wp_die();
}

/**
 * AJAX: Sidebar-Inhalt (Zielgruppe, Kriterien) neu laden, wenn eine andere
 * Zielgruppe gewaehlt wird - genau wie beim Verschieben der Slider soll das
 * ohne Seiten-Reload passieren (siehe results/frontend.js), damit z. B.
 * aufgeklappte Sidebar-Bereiche erhalten bleiben.
 */
add_action('wp_ajax_get_sidebar', 'competency_get_sidebar');
add_action('wp_ajax_nopriv_get_sidebar', 'competency_get_sidebar');

function competency_get_sidebar() {
    I18n::apply_request_language();

    $categorySlug = Blocks::current_category();

    echo Blocks::render_sidebar_for_category($categorySlug);
    wp_die();
}
