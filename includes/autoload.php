<?php
namespace CompetencySlider;

defined('ABSPATH') || exit;

class Autoloader {

    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    private static function autoload($class) {

        // Nur Klassen unseres Plugins laden
        if (strpos($class, __NAMESPACE__ . '\\') !== 0) {
            return;
        }

        // Namespace entfernen
        $relative_class = str_replace(__NAMESPACE__ . '\\', '', $class);

        // Namespace zu Pfad umwandeln
        $relative_class = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);

        $file = plugin_dir_path(__FILE__) . $relative_class . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
