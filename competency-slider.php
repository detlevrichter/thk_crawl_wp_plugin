<?php
/**
 * Plugin Name: Competency Slider Block
 * Description: Sidebar-Slider basierend auf competency_types mit AJAX-Auswertung
 * Version: 1.0
 */

use CompetencySlider\Classes\Blocks;

if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/includes/autoload.php';
\CompetencySlider\Autoloader::register();

add_action('init', function () {



    // === SLIDER BLOCK ===
    wp_register_script(
        'competency-slider-editor',
        plugins_url('slider/editor.js', __FILE__),
        ['wp-blocks', 'wp-element'],
        '1.0'
    );

    wp_register_script(
        'competency-slider-frontend',
        plugins_url('slider/frontend.js', __FILE__),
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('competency-slider-frontend', 'CompetencySlider', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);

    register_block_type(__DIR__ . '/slider', [
        'editor_script'   => 'competency-slider-editor',
        'script'          => 'competency-slider-frontend',
        'render_callback' =>  [\CompetencySlider\Classes\Blocks::class,'competency_slider_render'],
    ]);

    // === RESULTS BLOCK ===
    wp_register_script(
        'competency-results-editor',
        plugins_url('results/editor.js', __FILE__),
        ['wp-blocks', 'wp-element'],
        '1.0'
    );

    register_block_type(__DIR__ . '/results', [
        'editor_script'   => 'competency-results-editor',
        'render_callback' => [\CompetencySlider\Classes\Blocks::class,'competency_results_render'],
    ]);
});

/**
 * AJAX
 */
add_action('wp_ajax_get_offers', 'competency_get_offers');
add_action('wp_ajax_nopriv_get_offers', 'competency_get_offers');

function competency_get_offers() {
    global $wpdb;
     $sql = (new \CompetencySlider\Classes\Offers())::countSql([0.8,0.6,0.4,0.2]);
     $rows = $wpdb->get_results($sql);
     $row = $rows[0];
     $return = '';
     $return .= $row->p80 . " Ergebnisse mit einer Übereinstimmung von > 80% <br>";
     $return .= $row->p60 . " Ergebnisse mit einer Übereinstimmung zwischen 60% und 80%<br>";
     $return .= $row->p40 . " Ergebnisse mit einer Übereinstimmung zwischen 40% und 60%<br>";
     $return .= $row->p20 . " Ergebnisse mit einer Übereinstimmung zwischen 20% und 40%<br>";
    echo '<p>  '.$return.'</p>';
    wp_die();
}
