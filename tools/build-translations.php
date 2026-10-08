<?php
/**
 * Erzeugt die deutschen Übersetzungsdateien (.po, .mo, .l10n.php) sowie die
 * JSON-Dateien für die Editor-Skripte.
 *
 * Einmalig ausführen:  php tools/build-translations.php
 * Danach kann die Datei gelöscht werden – sie gehört nicht zum Auslieferstand.
 */

if (PHP_SAPI !== 'cli') {
    exit("Nur ueber die Kommandozeile ausfuehrbar.
");
}

const CTX = "\x04"; // Trenner zwischen msgctxt und msgid
const EOT = "\x00"; // Trenner zwischen Singular und Plural

$locale       = 'de_DE';
$language     = 'de';
$plural_forms = 'nplurals=2; plural=n != 1;';
$domain       = 'competency-slider';

/**
 * [ msgid, msgid_plural|null, msgctxt|null, [Übersetzungen], Fundstelle ]
 */
$entries = [
    // --- Plugin-Header ---
    ['Competency Slider Block', null, null, ['Competency Slider Block'], 'competency-slider.php'],

    // --- block.json ---
    ['Competency Slider', null, null, ['Kompetenz-Slider'], 'slider/block.json'],
    ['Choose a target group, select criteria and set interests and skills.', null, null,
        ['Zielgruppe wählen, Kriterien auswählen und Interessen sowie Fähigkeiten einstellen.'], 'slider/block.json'],
    ['Competency Results', null, null, ['Kompetenz-Ergebnisse'], 'results/block.json'],
    ['Personalised continuing education catalogue – shows offers matching the selected criteria.', null, null,
        ['Personalisierter Weiterbildungskatalog – zeigt Angebote passend zu den gewählten Kriterien.'], 'results/block.json'],

    // --- Slider-Block ---
    ['Decrease %s', null, null, ['%s verringern'], 'includes/Classes/Blocks.php'],
    ['Increase %s', null, null, ['%s erhöhen'], 'includes/Classes/Blocks.php'],
    ['No criteria have been defined for this target group yet.', null, null,
        ['Für diese Zielgruppe sind noch keine Kriterien hinterlegt.'], 'includes/Classes/Blocks.php'],
    ['Criteria selection', null, null, ['Kriterienauswahl'], 'includes/Classes/Blocks.php'],
    ['Which interests or skills appeal to you, or which do you already have?', null, null,
        ['Welche Interessen oder Fähigkeiten interessieren Sie oder können Sie bereits?'], 'includes/Classes/Blocks.php'],
    ['%1$s of %2$s criteria selected.', null, null,
        ['%1$s von %2$s Kriterien ausgewählt.'], 'includes/Classes/Blocks.php'],
    ['Adjust interests & skills', null, null, ['Interessen & Fähigkeiten anpassen'], 'includes/Classes/Blocks.php'],
    ['View personalised catalogue', null, null, ['Personalisierten Katalog anschauen'], 'includes/Classes/Blocks.php'],
    ['Interests and skills', null, null, ['Interessen und Fähigkeiten'], 'includes/Classes/Blocks.php'],
    ['Language', null, null, ['Sprache'], 'includes/Classes/Blocks.php'],
    ['Topic areas', null, null, ['Themenfelder'], 'includes/Classes/Blocks.php'],
    ['Detailed settings', null, null, ['Detaileinstellungen'], 'includes/Classes/Blocks.php'],
    ['Apply filters', null, null, ['Filter anwenden'], 'includes/Classes/Blocks.php'],
    ['Clear filters', null, null, ['Filter zurücksetzen'], 'includes/Classes/Blocks.php'],
    ['German', null, null, ['Deutsch'], 'includes/Classes/Blocks.php'],
    ['English', null, null, ['Englisch'], 'includes/Classes/Blocks.php'],
    ['%s offer found', '%s offers found', null,
        ['%s Angebot gefunden', '%s Angebote gefunden'], 'includes/Classes/Blocks.php'],
    ['No offers match the selected languages.', null, null,
        ['Keine Angebote in den gewählten Sprachen gefunden.'], 'includes/Classes/Blocks.php'],
    ['Adjust your interests and skills.', null, null,
        ['Passen Sie Ihre Interessen und Fähigkeiten an.'], 'includes/Classes/Blocks.php'],
    ['Selected criteria', null, null, ['Ausgewählte Kriterien'], 'includes/Classes/Blocks.php'],
    ['of', null, 'step counter: 01 of 12', ['von'], 'includes/Classes/Blocks.php'],
    ['Back', null, null, ['Zurück'], 'includes/Classes/Blocks.php'],
    ['Next', null, null, ['Weiter'], 'includes/Classes/Blocks.php'],
    ['Include this criterion', null, null, ['Kriterium berücksichtigen'], 'includes/Classes/Blocks.php'],
    ['Interest', null, null, ['Interesse'], 'includes/Classes/Blocks.php'],
    ['Skill', null, null, ['Fähigkeit'], 'includes/Classes/Blocks.php'],
    ['Change criteria', null, null, ['Kriterien ändern'], 'includes/Classes/Blocks.php'],
    ['Show my catalogue', null, null, ['Individuellen Katalog anzeigen'], 'includes/Classes/Blocks.php'],
    ['Target group', null, null, ['Zielgruppenauswahl'], 'includes/Classes/Blocks.php'],
    ['Select the target group you belong to:', null, null,
        ['Wählen Sie aus, zu welcher Zielgruppe Sie gehören:'], 'includes/Classes/Blocks.php'],
    ['All target groups', null, null, ['Alle Zielgruppen'], 'includes/Classes/Blocks.php'],

    // --- Ergebnis-Block ---
    ['No interests and skills have been submitted yet.', null, null,
        ['Es wurden noch keine Interessen und Fähigkeiten übergeben.'], 'includes/Classes/Blocks.php'],
    ['Set interests & skills', null, null, ['Interessen & Fähigkeiten angeben'], 'includes/Classes/Blocks.php'],
    ['%s offer matches your profile.', '%s offers match your profile.', null,
        ['%s Angebot passt zu Ihrem Profil.', '%s Angebote passen zu Ihrem Profil.'], 'includes/Classes/Blocks.php'],
    ['You are on the right track – would you like to refine your search?', null, null,
        ['Sie sind auf dem besten Weg – möchten Sie die Suche noch verfeinern?'], 'includes/Classes/Blocks.php'],
    ['You can adjust your filters again at any time.', null, null,
        ['Sie haben jederzeit die Möglichkeit, Ihre Filter nochmal zu bearbeiten.'], 'includes/Classes/Blocks.php'],
    ['Adjust criteria', null, null, ['Kriterien anpassen'], 'includes/Classes/Blocks.php'],
    ['No offers were found for this selection. Adjust your criteria to see more results.', null, null,
        ['Zu dieser Auswahl wurden keine Angebote gefunden. Passen Sie Ihre Kriterien an, um mehr Ergebnisse zu sehen.'],
        'includes/Classes/Blocks.php'],
    ['Match: %s%%', null, null, ['Übereinstimmung: %s%%'], 'includes/Classes/Blocks.php'],
    ['Provider:', null, null, ['Anbieter:'], 'includes/Classes/Blocks.php'],
    ['Go to offer', null, null, ['Zum Angebot'], 'includes/Classes/Blocks.php'],

    // --- AJAX / Notification Bar ---
    ['No criterion is selected. Use "Change criteria" to select at least one.', null, null,
        ['Es ist kein Kriterium ausgewählt. Wählen Sie über „Kriterien ändern“ mindestens ein Kriterium aus.'],
        'competency-slider.php'],
    ['over 80%', null, null, ['über 80 %'], 'competency-slider.php'],
    ['60 – 80%', null, null, ['60 – 80 %'], 'competency-slider.php'],
    ['40 – 60%', null, null, ['40 – 60 %'], 'competency-slider.php'],
    ['20 – 40%', null, null, ['20 – 40 %'], 'competency-slider.php'],
    ['under 20%', null, null, ['unter 20 %'], 'competency-slider.php'],
    ['%s matching offer found', '%s matching offers found', null,
        ['%s passendes Angebot gefunden', '%s passende Angebote gefunden'], 'competency-slider.php'],
    ['Match with your interests and skills:', null, null,
        ['Übereinstimmung mit Ihren Interessen und Fähigkeiten:'], 'competency-slider.php'],

    // --- Editor-Skripte (auch als JSON, siehe unten) ---
    ['Target group, criteria selection and the interest/skill sliders are rendered on the front end.', null, null,
        ['Zielgruppe, Kriterienauswahl und die Regler für Interesse und Fähigkeit werden im Frontend ausgegeben.'],
        'slider/editor.js'],
    ['Personalised continuing education catalogue', null, null,
        ['Personalisierter Weiterbildungskatalog'], 'results/editor.js'],
    ['The offer cards are rendered on the front end from the URL parameters.', null, null,
        ['Die Angebotskacheln werden im Frontend anhand der URL-Parameter erzeugt.'], 'results/editor.js'],
];

/** Strings, die zusätzlich als JSON für ein Editor-Skript gebraucht werden. */
$script_strings = [
    'competency-slider-editor' => [
        'Interests and skills',
        'Target group, criteria selection and the interest/skill sliders are rendered on the front end.',
    ],
    'competency-results-editor' => [
        'Personalised continuing education catalogue',
        'The offer cards are rendered on the front end from the URL parameters.',
    ],
];

$dir = dirname(__DIR__) . '/languages';

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// ---------------------------------------------------------------- Hilfsfunktionen

function po_quote($string)
{
    $string = str_replace(["\\", "\"", "\n", "\t"], ["\\\\", "\\\"", "\\n", "\\t"], $string);
    return '"' . $string . '"';
}

/** Schlüssel im MO-/PHP-Format: msgctxt \x04 msgid \x00 msgid_plural */
function mo_key($msgid, $plural, $context)
{
    $key = ($context !== null ? $context . CTX : '') . $msgid;

    if ($plural !== null) {
        $key .= EOT . $plural;
    }

    return $key;
}

// ---------------------------------------------------------------------- PO-Datei

$header = "Project-Id-Version: Competency Slider Block 2.1\n"
    . "Report-Msgid-Bugs-To: \n"
    . "POT-Creation-Date: " . gmdate('Y-m-d H:iO') . "\n"
    . "PO-Revision-Date: " . gmdate('Y-m-d H:iO') . "\n"
    . "Last-Translator: \n"
    . "Language-Team: Deutsch\n"
    . "Language: {$locale}\n"
    . "MIME-Version: 1.0\n"
    . "Content-Type: text/plain; charset=UTF-8\n"
    . "Content-Transfer-Encoding: 8bit\n"
    . "Plural-Forms: {$plural_forms}\n"
    . "X-Domain: {$domain}\n";

$po = "# German translation for the Competency Slider Block plugin.\n"
    . "# Source strings are English; the German site uses this file.\n"
    . "msgid \"\"\nmsgstr \"\"\n";

foreach (explode("\n", rtrim($header, "\n")) as $line) {
    $po .= po_quote($line . "\n") . "\n";
}

$po .= "\n";

foreach ($entries as [$msgid, $plural, $context, $translations, $reference]) {
    $po .= "#: {$reference}\n";

    if ($context !== null) {
        $po .= 'msgctxt ' . po_quote($context) . "\n";
    }

    $po .= 'msgid ' . po_quote($msgid) . "\n";

    if ($plural !== null) {
        $po .= 'msgid_plural ' . po_quote($plural) . "\n";
        $po .= 'msgstr[0] ' . po_quote($translations[0]) . "\n";
        $po .= 'msgstr[1] ' . po_quote($translations[1]) . "\n";
    } else {
        $po .= 'msgstr ' . po_quote($translations[0]) . "\n";
    }

    $po .= "\n";
}

file_put_contents("{$dir}/{$domain}-{$locale}.po", $po);

// ---------------------------------------------------------------------- MO-Datei

$table = ['' => $header];

foreach ($entries as [$msgid, $plural, $context, $translations, $reference]) {
    $table[mo_key($msgid, $plural, $context)] = implode(EOT, $translations);
}

ksort($table, SORT_STRING);

$count       = count($table);
$originals   = '';
$targets     = '';
$origTable   = '';
$transTable  = '';
$origOffset  = 28 + 16 * $count;
$transOffset = $origOffset;

foreach ($table as $key => $value) {
    $transOffset += strlen($key) + 1;
}

foreach ($table as $key => $value) {
    $origTable .= pack('VV', strlen($key), $origOffset + strlen($originals));
    $originals .= $key . "\0";

    $transTable .= pack('VV', strlen($value), $transOffset + strlen($targets));
    $targets    .= $value . "\0";
}

$mo = pack('V*', 0x950412de, 0, $count, 28, 28 + 8 * $count, 0, 28 + 16 * $count)
    . $origTable . $transTable . $originals . $targets;

file_put_contents("{$dir}/{$domain}-{$locale}.mo", $mo);

// ------------------------------------------------------------------ l10n.php

$messages = [];

foreach ($entries as [$msgid, $plural, $context, $translations, $reference]) {
    $messages[mo_key($msgid, $plural, $context)] = implode(EOT, $translations);
}

$php = "<?php\nreturn " . var_export([
    'x-generator'   => 'competency-slider/build-translations.php',
    'plural-forms'  => $plural_forms,
    'language'      => $language,
    'project-id-version' => 'Competency Slider Block',
    'messages'      => $messages,
], true) . ";\n";

file_put_contents("{$dir}/{$domain}-{$locale}.l10n.php", $php);

// --------------------------------------------------------- JSON für Editor-JS

$lookup = [];

foreach ($entries as [$msgid, $plural, $context, $translations, $reference]) {
    if ($plural === null && $context === null) {
        $lookup[$msgid] = $translations;
    }
}

foreach ($script_strings as $handle => $strings) {
    $locale_data = [
        '' => [
            'domain'       => 'messages',
            'lang'         => $language,
            'plural-forms' => $plural_forms,
        ],
    ];

    foreach ($strings as $string) {
        if (isset($lookup[$string])) {
            $locale_data[$string] = $lookup[$string];
        }
    }

    $json = [
        'translation-revision-date' => gmdate('Y-m-d H:i:sO'),
        'generator'                 => 'competency-slider/build-translations.php',
        'domain'                    => 'messages',
        'locale_data'               => ['messages' => $locale_data],
    ];

    file_put_contents(
        "{$dir}/{$domain}-{$locale}-{$handle}.json",
        json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
}

printf("%d Eintraege geschrieben nach %s\n", count($entries), $dir);

foreach (glob("{$dir}/*") as $file) {
    printf("  %8d  %s\n", filesize($file), basename($file));
}
