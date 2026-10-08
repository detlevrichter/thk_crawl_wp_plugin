<?php
namespace CompetencySlider\Classes;

defined('ABSPATH') || exit;

/**
 * Server Side Rendering der beiden Blöcke.
 *
 * Die Struktur folgt dem Penpot-Handoff "Weiterbildungsdatenbank Design"
 * und dem User-Flow (user-flow-weiterbildungskatalog.png):
 *
 *   Zielgruppenauswahl -> Kriterienauswahl -> Interessen & Fähigkeiten anpassen
 *   -> Personalisierter Weiterbildungskatalog (Ergebnisse)
 *
 * Die ersten drei Schritte liegen auf der Einstellungsseite und werden als
 * zwei umschaltbare Ansichten innerhalb des Slider-Blocks ausgegeben.
 *
 * Alle Oberflächentexte stehen englisch im Quelltext und werden über die
 * Textdomain competency-slider übersetzt. Labels aus den eigenen Tabellen
 * laufen über die Polylang-Zeichenkettenübersetzung (siehe I18n).
 */
class Blocks
{
    /** Schrittweite der Slider (Tick-Marken im Design: alle 10 %). */
    const STEP = 25;

    /** Voreinstellung, wenn kein Wert in der URL steht. */
    const DEFAULT_VALUE = 50;

    /**
     * SVG-Icons aus dem Design (Icons / Plus, Icons / Remove, Chevrons, Haken).
     */
    private static function icon($name)
    {
        $icons = [
            'plus'     => '<path d="M11 13H6a1 1 0 0 1 0-2h5V6a1 1 0 0 1 2 0v5h5a1 1 0 0 1 0 2h-5v5a1 1 0 0 1-2 0z"/>',
            'minus'    => '<path d="M6 13a1 1 0 0 1 0-2h12a1 1 0 0 1 0 2z"/>',
            'check'    => '<path d="M9.55 17.05 4.9 12.4a1 1 0 0 1 1.4-1.4l3.25 3.24 8.15-8.15a1 1 0 1 1 1.4 1.42z"/>',
            'left'     => '<path d="M14.7 17.3 9.4 12l5.3-5.3a1 1 0 0 0-1.4-1.4l-6 6a1 1 0 0 0 0 1.4l6 6a1 1 0 0 0 1.4-1.4"/>',
            'right'    => '<path d="M9.3 6.7 14.6 12l-5.3 5.3a1 1 0 0 0 1.4 1.4l6-6a1 1 0 0 0 0-1.4l-6-6a1 1 0 0 0-1.4 1.4"/>',
            'external' => '<path d="M5 21a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6a1 1 0 0 1 0 2H5v14h14v-6a1 1 0 0 1 2 0v6a2 2 0 0 1-2 2zm5.7-7.3a1 1 0 0 1 0-1.4L17.6 5H15a1 1 0 0 1 0-2h5a1 1 0 0 1 1 1v5a1 1 0 0 1-2 0V6.4l-6.9 7.3a1 1 0 0 1-1.4 0"/>',
            'chevron'  => '<path d="M12 15.5 5.9 9.4a1 1 0 0 1 1.4-1.4L12 12.7l4.7-4.7a1 1 0 0 1 1.4 1.4z"/>',
        ];

        if (!isset($icons[$name])) {
            return '';
        }

        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
    }

    /**
     * Sucht die veröffentlichte Seite, die einen bestimmten Block enthält –
     * bevorzugt in der aktuellen Sprache.
     *
     * Reihenfolge: Seite mit passender Polylang-Sprache, sonst die von Polylang
     * verknüpfte Übersetzung, sonst die erste gefundene Seite.
     */
    private static function block_page_url($block_name, $fallback_path)
    {
        static $cache = [];

        $language = I18n::current_language();
        $key      = $block_name . '|' . $language;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        global $wpdb;

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                  WHERE post_type = 'page'
                    AND post_status = 'publish'
                    AND post_content LIKE %s
               ORDER BY ID ASC",
                '%' . $wpdb->esc_like('wp:' . $block_name) . '%'
            )
        );

        $page_id = 0;

        if ($ids) {
            if (function_exists('pll_get_post_language')) {
                foreach ($ids as $id) {
                    if (pll_get_post_language((int) $id) === $language) {
                        $page_id = (int) $id;
                        break;
                    }
                }
            }

            if (!$page_id && function_exists('pll_get_post')) {
                $translated = pll_get_post((int) $ids[0], $language);

                if ($translated) {
                    $page_id = (int) $translated;
                }
            }

            if (!$page_id) {
                $page_id = (int) $ids[0];
            }
        }

        $cache[$key] = $page_id
            ? get_permalink($page_id)
            : trailingslashit(I18n::home_url()) . ltrim($fallback_path, '/');

        return $cache[$key];
    }

    private static function settings_url()
    {
        return self::block_page_url('competency/slider', 'einstellungen/');
    }

    private static function results_url()
    {
        return self::block_page_url('competency/results', 'results/');
    }

    /**
     * Aktuelle Zielgruppe aus der URL, gegen die Kategorien-Tabelle geprüft.
     * Public, weil auch der AJAX-Handler competency_get_sidebar() (siehe
     * competency-slider.php) sie beim Wechsel der Zielgruppe braucht.
     */
    public static function current_category()
    {
        global $wpdb;

        if (!isset($_GET['Kategorie'])) {
            return 'Default';
        }

        $requested = sanitize_text_field(wp_unslash($_GET['Kategorie']));

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT slug FROM {$wpdb->prefix}categories WHERE slug = %s LIMIT 1",
                $requested
            )
        );

        return $exists ? $exists : 'Default';
    }

    /**
     * Zielgruppen für die Auswahl – vollständig aus wp_categories.
     *
     * Die Liste ist bewusst nicht gefiltert: Kommt eine Zielgruppe in der
     * Tabelle dazu oder fällt weg, ändert sich die Auswahl mit, ohne dass am
     * Plugin etwas anzupassen wäre. Die Reihenfolge folgt der Tabelle (id),
     * lediglich 'Default' steht als Sammel-Eintrag vorn.
     */
    private static function categories()
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT c.slug, c.title
               FROM {$wpdb->prefix}categories AS c
              WHERE c.slug <> ''
           ORDER BY (c.slug = 'Default') DESC, c.id ASC"
        );
    }

    private static function category_label($category)
    {
        return $category->slug === 'Default'
            ? __('All target groups', 'competency-slider')
            : I18n::translate($category->title);
    }

    /**
     * Wert aus der URL auf 0-100 begrenzen und auf die Schrittweite runden.
     */
    private static function value_from_request($key)
    {
        if (!isset($_GET[$key])) {
            return self::DEFAULT_VALUE;
        }

        $value = (int) round(intval($_GET[$key]) / self::STEP) * self::STEP;

        return max(0, min(100, $value));
    }

    /**
     * Anzeige auf der 5er-Skala: 0/25/50/75/100 -> 1/2/3/4/5.
     * Uebergeben wird weiterhin der Wert 0-100.
     */
    private static function scale_value($value)
    {
        return (int) round($value / self::STEP) + 1;
    }

    /**
     * Eine Slider-Zeile: Label | Wert | Minus | Track | Plus.
     */
    private static function render_slider($slug, $label, $value, $input_id, $disabled = false)
    {
        ?>
        <div class="cs-slider">
            <label class="cs-slider__label" for="<?php echo esc_attr($input_id); ?>">
                <?php echo esc_html($label); ?>
            </label>

            <output class="cs-slider__value" for="<?php echo esc_attr($input_id); ?>">
                <?php echo esc_html(self::scale_value($value)); ?>
            </output>

            <button
                type="button"
                class="cs-step cs-slider__minus"
                data-step="-<?php echo esc_attr(self::STEP); ?>"
                data-controls="<?php echo esc_attr($input_id); ?>"
                <?php disabled($disabled); ?>
            >
                <?php echo self::icon('minus'); ?>
                <span class="cs-visually-hidden">
                    <?php
                    /* translators: %s: name of the slider, e.g. Interest. */
                    printf(esc_html__('Decrease %s', 'competency-slider'), esc_html($label));
                    ?>
                </span>
            </button>

            <div class="cs-slider__control" style="--cs-fill: <?php echo esc_attr($value); ?>%">
                <input
                    type="range"
                    id="<?php echo esc_attr($input_id); ?>"
                    name="<?php echo esc_attr($slug); ?>"
                    class="cs-slider__input competency-range"
                    min="0"
                    max="100"
                    step="<?php echo esc_attr(self::STEP); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    aria-valuetext="<?php echo esc_attr(self::scale_value($value)); ?>"
                    data-slug="<?php echo esc_attr($slug); ?>"
                    <?php disabled($disabled); ?>
                >
            </div>

            <button
                type="button"
                class="cs-step cs-slider__plus"
                data-step="<?php echo esc_attr(self::STEP); ?>"
                data-controls="<?php echo esc_attr($input_id); ?>"
                <?php disabled($disabled); ?>
            >
                <?php echo self::icon('plus'); ?>
                <span class="cs-visually-hidden">
                    <?php
                    /* translators: %s: name of the slider, e.g. Interest. */
                    printf(esc_html__('Increase %s', 'competency-slider'), esc_html($label));
                    ?>
                </span>
            </button>
        </div>
        <?php
    }

    /**
     * Kriterien einer Zielgruppe: Slug/Label aus wp_competency_types, dazu
     * Auswahlzustand und aktuelle Werte aus der URL. Wird sowohl vom
     * Einstellungen-Block (competency_slider_render) als auch von der
     * Sidebar auf der Ergebnisseite verwendet.
     */
    private static function criteria_for_category($categorySlug)
    {
        global $wpdb;

        $table          = $wpdb->prefix . 'competency_types';
        $tableZuordnung = $wpdb->prefix . 'category_competency_type';

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

        // Nicht jede Zielgruppe hat eigene Zuordnungen (Demo-Daten wie
        // "Verwaltung"/"Studierende"): dann die Kriterien der Default-
        // Zielgruppe zeigen, statt die Slider komplett auszublenden. Offers::get()
        // faellt beim Matching auf dieselbe Zielgruppe zurueck.
        if (!$rows && $categorySlug !== 'Default') {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT c.slug, c.label
                     FROM {$table} AS c
                     INNER JOIN {$tableZuordnung} AS cct
                        ON c.slug = cct.competency_type_slug
                     WHERE c.type = 'float'
                       AND c.slug != 'level'
                       AND cct.category_slug = %s",
                    'Default'
                )
            );
        }

        // Nur die fuer die Kriterien relevanten GET-Parameter zaehlen - ein
        // AJAX-Aufruf (get_sidebar/get_results) traegt zusaetzlich 'action'
        // und 'lang' im $_GET, was die "nichts ausgewaehlt"-Erkennung sonst
        // faelschlich kippen wuerde.
        $relevant_get = array_diff_key($_GET, ['Kategorie' => '', 'action' => '', 'lang' => '']);

        $criteria = [];

        foreach ($rows as $row) {
            $level_slug = $row->slug . '_level';

            $criteria[] = [
                'slug'     => $row->slug,
                'label'    => I18n::translate($row->label),
                'level'    => $level_slug,
                'interest' => self::value_from_request($row->slug),
                'skill'    => self::value_from_request($level_slug),
                'active'   => empty($relevant_get)
                    || isset($relevant_get[$row->slug])
                    || isset($relevant_get[$level_slug]),
            ];
        }

        return $criteria;
    }

    /**
     * Block "Interessen und Fähigkeiten anpassen".
     */
    public static function competency_slider_render()
    {
        $categorySlug = self::current_category();
        $criteria     = self::criteria_for_category($categorySlug);
        $categories   = self::categories();

        if (!$criteria) {
            ob_start();
            ?>
            <section class="cs-block">
                <div class="cs-block__inner">
                    <?php self::render_audience($categories, $categorySlug); ?>
                    <p class="cs-empty"><?php esc_html_e('No criteria have been defined for this target group yet.', 'competency-slider'); ?></p>
                </div>
            </section>
            <?php
            return ob_get_clean();
        }

        $total    = count($criteria);
        $selected = count(array_filter(wp_list_pluck($criteria, 'active')));

        ob_start();
        ?>
        <section
            class="cs-block competency-slider-block"
            data-category="<?php echo esc_attr($categorySlug); ?>"
            data-results-url="<?php echo esc_url(self::results_url()); ?>"
            data-lang="<?php echo esc_attr(I18n::current_language()); ?>"
        >
            <div class="cs-block__inner">

                <?php self::render_audience($categories, $categorySlug); ?>

                <!-- Schritt: Kriterienauswahl -->
                <div class="cs-view cs-view--criteria" data-view="criteria" hidden>
                    <div class="cs-section__head">
                        <h2 class="cs-heading wp-block-heading"><?php esc_html_e('Criteria selection', 'competency-slider'); ?></h2>
                        <p class="cs-lead"><?php esc_html_e('Which interests or skills appeal to you, or which do you already have?', 'competency-slider'); ?></p>
                    </div>

                    <ul class="cs-criteria">
                        <?php foreach ($criteria as $criterion): ?>
                            <li>
                                <button
                                    type="button"
                                    class="cs-criterion"
                                    data-slug="<?php echo esc_attr($criterion['slug']); ?>"
                                    aria-pressed="<?php echo $criterion['active'] ? 'true' : 'false'; ?>"
                                >
                                    <span class="cs-criterion__check"><?php echo self::icon('check'); ?></span>
                                    <span class="cs-criterion__label"><?php echo esc_html($criterion['label']); ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="cs-criteria__summary" data-role="selection-summary">
                        <?php
                        printf(
                            /* translators: 1: number of selected criteria, 2: total number. */
                            esc_html__('%1$s of %2$s criteria selected.', 'competency-slider'),
                            '<strong>' . esc_html($selected) . '</strong>',
                            esc_html($total)
                        );
                        ?>
                    </p>

                    <div class="cs-actions">
                        <button type="button" class="cs-btn" data-action="show-adjust">
                            <?php esc_html_e('Adjust interests & skills', 'competency-slider'); ?>
                        </button>
                        <a class="cs-btn cs-btn--secondary results-link" href="<?php echo esc_url(self::results_url()); ?>">
                            <?php esc_html_e('View personalised catalogue', 'competency-slider'); ?>
                        </a>
                    </div>
                </div>

                <!-- Schritt: Interessen und Fähigkeiten anpassen -->
                <div class="cs-view cs-view--adjust" data-view="adjust">
                    <div class="cs-section__head">
                        <h2 class="cs-heading wp-block-heading"><?php esc_html_e('Interests and skills', 'competency-slider'); ?></h2>
                        <p class="cs-lead"><?php esc_html_e('Adjust your interests and skills.', 'competency-slider'); ?></p>
                    </div>

                    <div class="cs-tabs" role="tablist" aria-label="<?php esc_attr_e('Selected criteria', 'competency-slider'); ?>">
                        <?php foreach ($criteria as $index => $criterion): ?>
                            <button
                                type="button"
                                class="cs-tab"
                                role="tab"
                                id="cs-tab-<?php echo esc_attr($criterion['slug']); ?>"
                                aria-controls="cs-panel-<?php echo esc_attr($criterion['slug']); ?>"
                                aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
                                data-slug="<?php echo esc_attr($criterion['slug']); ?>"
                                <?php echo $criterion['active'] ? '' : 'hidden'; ?>
                            >
                                <?php echo esc_html($criterion['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="cs-stepper">
                        <p class="cs-stepper__count">
                            <strong data-role="index">01</strong>
                            <?php echo esc_html(_x('of', 'step counter: 01 of 12', 'competency-slider')); ?>
                            <span data-role="total"><?php echo esc_html(str_pad((string) $selected, 2, '0', STR_PAD_LEFT)); ?></span>
                        </p>

                        <div class="cs-stepper__nav">
                            <button type="button" class="cs-btn cs-btn--nav" data-nav="prev">
                                <?php echo self::icon('left'); ?>
                                <?php esc_html_e('Back', 'competency-slider'); ?>
                            </button>
                            <button type="button" class="cs-btn cs-btn--nav" data-nav="next">
                                <?php esc_html_e('Next', 'competency-slider'); ?>
                                <?php echo self::icon('right'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="cs-panels">
                        <?php foreach ($criteria as $index => $criterion): ?>
                            <section
                                class="cs-panel competency-row<?php echo $criterion['active'] ? '' : ' is-disabled'; ?>"
                                id="cs-panel-<?php echo esc_attr($criterion['slug']); ?>"
                                role="tabpanel"
                                aria-labelledby="cs-tab-<?php echo esc_attr($criterion['slug']); ?>"
                                data-slug="<?php echo esc_attr($criterion['slug']); ?>"
                                <?php echo 0 === $index ? '' : 'hidden'; ?>
                            >
                                <h3 class="cs-panel__title"><?php echo esc_html($criterion['label']); ?></h3>

                                <label class="cs-panel__toggle">
                                    <input
                                        type="checkbox"
                                        class="row-toggle"
                                        data-slug="<?php echo esc_attr($criterion['slug']); ?>"
                                        <?php checked($criterion['active']); ?>
                                    >
                                    <span><?php esc_html_e('Include this criterion', 'competency-slider'); ?></span>
                                </label>

                                <div class="cs-panel__sliders">
                                    <?php
                                    self::render_slider(
                                        $criterion['slug'],
                                        __('Interest', 'competency-slider'),
                                        $criterion['interest'],
                                        'cs-' . $criterion['slug'] . '-interest'
                                    );

                                    self::render_slider(
                                        $criterion['level'],
                                        __('Skill', 'competency-slider'),
                                        $criterion['skill'],
                                        'cs-' . $criterion['slug'] . '-skill'
                                    );
                                    ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>

                    <div class="cs-notice competency-offers" data-role="offers" aria-live="polite"></div>

                    <div class="cs-actions">
                        <button type="button" class="cs-btn cs-btn--secondary" data-action="show-criteria">
                            <?php esc_html_e('Change criteria', 'competency-slider'); ?>
                        </button>
                        <a class="cs-btn results-link" href="<?php echo esc_url(self::results_url()); ?>">
                            <?php esc_html_e('Show my catalogue', 'competency-slider'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Zielgruppenauswahl (nur wenn mehr als eine Zielgruppe Kriterien hat).
     */
    private static function render_audience($categories, $currentSlug)
    {
        if (count($categories) < 2) {
            return;
        }
        ?>
        <div class="cs-audience">
            <div class="cs-section__head">
                <h2 class="cs-heading wp-block-heading"><?php esc_html_e('Target group', 'competency-slider'); ?></h2>
                <p class="cs-lead"><?php esc_html_e('Select the target group you belong to:', 'competency-slider'); ?></p>
            </div>

            <div class="cs-audience__options">
                <?php foreach ($categories as $category): ?>
                    <?php $is_current = ($category->slug === $currentSlug); ?>
                    <a
                        class="cs-audience__option<?php echo $is_current ? ' is-active' : ''; ?>"
                        href="<?php echo esc_url(add_query_arg('Kategorie', $category->slug, self::settings_url())); ?>"
                        <?php echo $is_current ? 'aria-current="true"' : ''; ?>
                    >
                        <?php echo esc_html(self::category_label($category)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Sidebar der Ergebnisseite: buendelt die Funktionen der Einstellungen-
     * Seite (Zielgruppe, Interessen & Faehigkeiten) plus einen Sprachfilter.
     *
     * Zielgruppe und Kriterien loesen beim Aendern jeweils einen AJAX-Reload
     * aus (siehe results/frontend.js, Handler in competency-slider.php) -
     * deshalb gibt diese Methode ihr Markup selbst zurueck (statt nur direkt
     * auszugeben): so laesst sie sich unveraendert sowohl beim normalen
     * Seitenaufruf als auch fuer die AJAX-Antwort verwenden. Der Sprachfilter
     * dagegen liest nur das data-language-Attribut der bereits geladenen
     * Kacheln aus und kommt ohne Datenbank-Abfrage aus.
     */
    public static function render_sidebar($categories, $categorySlug, $criteria)
    {
        ob_start();
        ?>
        <div class="cs-sidebar__inner">

            <?php if (count($categories) > 1): ?>
                <details class="cs-sidebar__section">
                    <summary class="cs-sidebar__title">
                        <?php esc_html_e('Target group', 'competency-slider'); ?>
                        <?php echo self::icon('chevron'); ?>
                    </summary>
                    <div class="cs-sidebar__audience">
                        <?php foreach ($categories as $category): ?>
                            <?php $is_current = ($category->slug === $categorySlug); ?>
                            <a
                                class="cs-sidebar__pill<?php echo $is_current ? ' is-active' : ''; ?>"
                                href="<?php echo esc_url(add_query_arg('Kategorie', $category->slug, self::results_url())); ?>"
                                <?php echo $is_current ? 'aria-current="true"' : ''; ?>
                            >
                                <?php echo esc_html(self::category_label($category)); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($criteria): ?>
                <details class="cs-sidebar__section">
                    <summary class="cs-sidebar__title">
                        <?php esc_html_e('Topic areas', 'competency-slider'); ?>
                        <?php echo self::icon('chevron'); ?>
                    </summary>
                    <form class="cs-sidebar__form" method="get" action="<?php echo esc_url(self::results_url()); ?>">
                        <input type="hidden" name="Kategorie" value="<?php echo esc_attr($categorySlug); ?>">

                        <?php foreach ($criteria as $criterion): ?>
                            <div class="cs-sidebar__criterion">
                                <label class="cs-panel__toggle">
                                    <input
                                        type="checkbox"
                                        class="row-toggle"
                                        data-slug="<?php echo esc_attr($criterion['slug']); ?>"
                                        <?php checked($criterion['active']); ?>
                                    >
                                    <span><?php echo esc_html($criterion['label']); ?></span>
                                </label>

                                <div
                                    class="cs-sidebar__sliders"
                                    data-slug="<?php echo esc_attr($criterion['slug']); ?>"
                                    <?php echo $criterion['active'] ? '' : 'hidden'; ?>
                                >
                                    <?php
                                    self::render_slider(
                                        $criterion['slug'],
                                        __('Interest', 'competency-slider'),
                                        $criterion['interest'],
                                        'cs-r-' . $criterion['slug'] . '-interest',
                                        !$criterion['active']
                                    );

                                    self::render_slider(
                                        $criterion['level'],
                                        __('Skill', 'competency-slider'),
                                        $criterion['skill'],
                                        'cs-r-' . $criterion['slug'] . '-skill',
                                        !$criterion['active']
                                    );
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="cs-sidebar__actions">
                            <button type="submit" class="cs-btn cs-btn--compact">
                                <?php esc_html_e('Apply filters', 'competency-slider'); ?>
                            </button>
                            <a class="cs-btn cs-btn--secondary cs-btn--compact" href="<?php echo esc_url(self::results_url()); ?>">
                                <?php esc_html_e('Clear filters', 'competency-slider'); ?>
                            </a>
                        </div>
                    </form>
                </details>
            <?php endif; ?>

            <details class="cs-sidebar__section" data-role="language-filter">
                <summary class="cs-sidebar__title">
                    <?php esc_html_e('Language', 'competency-slider'); ?>
                    <?php echo self::icon('chevron'); ?>
                </summary>
                <label class="cs-sidebar__checkbox">
                    <input type="checkbox" data-language-filter="Deutsch" checked>
                    <span><?php esc_html_e('German', 'competency-slider'); ?></span>
                </label>
                <label class="cs-sidebar__checkbox">
                    <input type="checkbox" data-language-filter="Englisch" checked>
                    <span><?php esc_html_e('English', 'competency-slider'); ?></span>
                </label>
            </details>

            <div class="cs-sidebar__section cs-sidebar__section--link">
                <?php /* Aktuelle Auswahl aus der URL mitnehmen, damit die Einstellungsseite denselben Stand zeigt. */ ?>
                <a
                    class="cs-sidebar__title cs-sidebar__settings-link"
                    href="<?php echo esc_url(add_query_arg('Kategorie', $categorySlug, self::settings_url())); ?>"
                    data-keep-query="1"
                >
                    <?php esc_html_e('Detailed settings', 'competency-slider'); ?>
                </a>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Bequemer Einstiegspunkt fuer den AJAX-Handler competency_get_sidebar():
     * Zielgruppe rein, fertiges Sidebar-Markup raus, inklusive Ermittlung der
     * dafuer gueltigen Kategorien und Kriterien.
     */
    public static function render_sidebar_for_category($categorySlug)
    {
        return self::render_sidebar(
            self::categories(),
            $categorySlug,
            self::criteria_for_category($categorySlug)
        );
    }

    /**
     * Kopfzeile (Trefferzahl) + Kartenliste der Ergebnisseite. Ausgelagert,
     * weil dasselbe Fragment auch die AJAX-Antwort ist, mit der die Sidebar
     * beim Verschieben der Slider die Ergebnisse live nachlaedt (siehe
     * competency_get_results() in competency-slider.php und
     * results/frontend.js) - identisch zum Prinzip des Einstellungen-Blocks,
     * der beim Slidern schon die URL per History-API aktualisiert.
     */
    public static function render_results_panel($results, $count)
    {
        ob_start();
        ?>
        <div class="cs-results__head">
            <p class="cs-results__count" data-role="results-count">
                <?php
                printf(
                    /* translators: %s: number of offers. */
                    esc_html(_n('%s offer found', '%s offers found', $count, 'competency-slider')),
                    '<strong data-role="count-number">' . esc_html($count) . '</strong>'
                );
                ?>
            </p>
        </div>

        <?php if (!$count): ?>
            <p class="cs-empty"><?php esc_html_e('No offers were found for this selection. Adjust your criteria to see more results.', 'competency-slider'); ?></p>
        <?php else: ?>
            <ul class="cs-cards" data-role="cards">
                <?php foreach ($results as $result): ?>
                    <?php
                    $has_match = isset($result->match_total) && $result->match_total !== null;
                    $percent   = $has_match ? (int) round(max(0, min(1, (float) $result->match_total)) * 100) : null;

                    if (!$has_match) {
                        $match_class = '';
                    } elseif ($percent >= 80) {
                        $match_class = 'cs-match--high';
                    } elseif ($percent >= 40) {
                        $match_class = 'cs-match--medium';
                    } else {
                        $match_class = 'cs-match--low';
                    }

                    $language = !empty($result->offer_language) ? $result->offer_language : '';
                    ?>
                    <li data-language="<?php echo esc_attr($language); ?>">
                        <article class="cs-card competency-result-card">
                            <?php if ($has_match): ?>
                                <p class="cs-match <?php echo esc_attr($match_class); ?>">
                                    <?php
                                    printf(
                                        /* translators: %s: match in percent. */
                                        esc_html__('Match: %s%%', 'competency-slider'),
                                        esc_html($percent)
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>

                            <h3 class="cs-card__title competency-result-title">
                                <?php echo esc_html($result->title); ?>
                            </h3>

                            <?php if (!empty($result->description)): ?>
                                <p class="cs-card__description">
                                    <?php echo esc_html(wp_strip_all_tags($result->description)); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($result->provider)): ?>
                                <p class="cs-card__meta">
                                    <b><?php esc_html_e('Provider:', 'competency-slider'); ?></b>
                                    <?php echo esc_html($result->provider); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($result->url)): ?>
                                <div class="cs-card__foot">
                                    <a
                                        class="cs-btn cs-btn--compact"
                                        href="<?php echo esc_url($result->url); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <?php esc_html_e('Go to offer', 'competency-slider'); ?>
                                        <?php echo self::icon('external'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="cs-empty" data-role="no-results" hidden>
                <?php esc_html_e('No offers match the selected languages.', 'competency-slider'); ?>
            </p>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Block "Personalisierter Weiterbildungskatalog" (Ergebnisliste).
     *
     * Ohne Kriterien in der URL (weder Kategorie noch Interessen/Faehigkeiten)
     * werden alle aktiven Angebote gezeigt statt einer leeren Seite. Die
     * Einstellungen-Funktionen (Zielgruppe, Slider) sitzen als Sidebar links
     * neben der Liste, siehe render_sidebar().
     */
    public static function competency_results_render()
    {
        global $wpdb;

        $categorySlug = self::current_category();
        $criteria     = self::criteria_for_category($categorySlug);
        $categories   = self::categories();

        $results = $wpdb->get_results(Offers::get(false));
        $count   = is_array($results) ? count($results) : 0;

        ob_start();
        ?>
        <div class="cs-results-layout competency-results" data-lang="<?php echo esc_attr(I18n::current_language()); ?>">

            <aside class="cs-sidebar cs-block" aria-label="<?php esc_attr_e('Filter', 'competency-slider'); ?>">
                <?php echo self::render_sidebar($categories, $categorySlug, $criteria); ?>
            </aside>

            <div class="cs-results" data-role="results-panel">
                <?php echo self::render_results_panel($results, $count); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
