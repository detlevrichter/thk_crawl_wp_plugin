<?php
namespace CompetencySlider\Classes;

defined('ABSPATH') || exit;

class Blocks{

/**
 * Server Side Render
 */
    public static function competency_slider_render() {
        global $wpdb;

        $table = $wpdb->prefix . 'competency_types';
        $tableZuordnung = $wpdb->prefix . 'category_competency_type';
        $tableCategories  = $wpdb->prefix . 'categories';

        // Standardwert
        $categorySlug = 'Default';

        // GET-Parameter prüfen
        if ( isset($_GET['Kategorie']) ) {
            $requestedSlug = sanitize_text_field(wp_unslash($_GET['Kategorie']));

            // Existiert der Slug in categories?
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT slug 
                     FROM {$tableCategories} 
                     WHERE slug = %s 
                     LIMIT 1",
                    $requestedSlug
                )
            );

            if ($exists) {
                $categorySlug = $requestedSlug;
            }else{
                $categorySlug = 'Default';
            }
        }
        $rows = $wpdb->get_results(            
            $wpdb->prepare(
                "SELECT DISTINCT c.slug, c.label
                 FROM {$table} AS c
                 INNER JOIN {$tableZuordnung} AS cct
                    ON c.slug = cct.competency_type_slug
                 WHERE c.type = 'float'
                   AND c.slug != 'level'
                   AND cct.category_slug = %s",
                $categorySlug
            )
        );

        if (!$rows) {
            return '<p>Keine Slider gefunden.</p>';
        }

        ob_start();
        ?>
        
        <div class="competency-slider-block">

            <table class="competency-table">
                <thead>
                    <tr>
                        <th>Aktiv</th>
                        <th></th>
                        <th>Interesse</th>
                        <th>Fähigkeit</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($rows as $row):

                    $interest_value = isset($_GET[$row->slug])
                        ? intval($_GET[$row->slug])
                        : 50;

                    $level_slug = $row->slug . '_level';

                    $level_value = isset($_GET[$level_slug])
                        ? intval($_GET[$level_slug])
                        : 50;
                    $is_active =
                            isset($_GET[$row->slug]) ||
                            isset($_GET[$level_slug]) ||
                            empty($_GET); // beim ersten Laden aktiv
                ?>

                    <tr class="competency-row">
                        <td>
                            <input
                                type="checkbox"
                                class="row-toggle"
                                <?php checked($is_active); ?>
                            >
                        </td>
                        <td class="competency-label">
                            <?php echo esc_html($row->label); ?>
                        </td>

                        <td>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                value="<?php echo esc_attr($interest_value); ?>"
                                class="competency-range"
                                data-slug="<?php echo esc_attr($row->slug); ?>"
                            >
                            <span class="competency-value">
                                <?php echo esc_html($interest_value); ?>
                            </span>
                        </td>

                        <td>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                value="<?php echo esc_attr($level_value); ?>"
                                class="competency-range"
                                data-slug="<?php echo esc_attr($level_slug); ?>"
                            >
                            <span class="competency-value">
                                <?php echo esc_html($level_value); ?>
                            </span>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
            <?php
                $results_url = site_url('/results/');
            ?>
            <p>&nbsp;</p>
            <p class="competency-results-link">
                <a
                    href="<?php echo esc_url($results_url); ?>"
                    class="results-link"
                >
                    <strong>Ergebnisse anzeigen</strong>
                </a>
            </p>
            <div class="competency-offers"></div>


        </div>
        <?php
        return ob_get_clean();
    }



    public static function competency_results_render() {
        global $wpdb;

        if (empty($_GET)) {
            return '<p>Keine Filterparameter übergeben.</p>';
        }

        
        $sql = (new \CompetencySlider\Classes\Offers())::get();
 
        $results = $wpdb->get_results($sql);
    

        ob_start();
      




        ?>

        <div class="competency-results wp-block-group">

            <?php foreach ($results as $result): ?>
            
                <article class="competency-result-card wp-block-group">
                    <h3 class="competency-result-title wp-block-heading">
                    <?php echo esc_html($result->title); ?>
                    </h3>

                    <p class="competency-result-description">
                    <?php echo esc_html($result->description); ?>
                    </p>

                    <ul class="competency-result-meta">
                        <li><strong>Übereinstimmung:</strong> <?php echo esc_html(round($result->match_total * 100,0)); ?>%</li>
                        <?php if($result->duration): ?>
                            <li><strong>Dauer:</strong> <?php echo esc_html($result->duration); ?></li>
                        <?php endif; ?>
                        <?php if($result->place): ?>
                            <li><strong>Ort:</strong> <?php echo esc_html($result->place); ?></li>
                        <?php endif; ?>
                        <li><a href="<?php echo esc_html($result->url); ?>" target="_blanc"><strong>zum Angebot</strong></a>   </li>
                    </ul>
                </article>





            <?php endforeach; ?>

        </div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const params = window.location.search;

    // Keine Parameter → nichts zu tun
    if (!params) return;

    document.querySelectorAll('a[href]').forEach(link => {
        try {
            const url = new URL(link.href, window.location.origin);

            // Nur Links, die auf /slider/ enden
            if (url.pathname.endsWith('/einstellung/')) {
                url.search = params;
                link.href = url.toString();
            }
        } catch (e) {
            // ungültige URLs ignorieren
        }
    });
});



</script>


        <?php
        return ob_get_clean();
    }



}
