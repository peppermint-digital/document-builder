/**
 * Fragt die PHP-Seite nach ihrer Kartengeometrie.
 *
 * Der Umweg ueber einen echten PHP-Aufruf ist der Punkt: eine nachgebaute
 * Erwartung im Test wuerde nur pruefen, ob ich zweimal dasselbe gedacht habe.
 * Was hier verglichen wird, ist die Zahl, die spaeter im PDF steht.
 */
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const wurzel = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

const SKRIPT = `
require $argv[1] . '/vendor/autoload.php';

use Peppermint\\DocumentBuilder\\Data\\PageSetup;
use Peppermint\\DocumentBuilder\\Data\\SheetSetup;
use Peppermint\\DocumentBuilder\\Presets\\CardPreset;

$faelle = json_decode($argv[2], true);
$ausgabe = [];

foreach ($faelle as $name => $f) {
    $rand = (float) ($f['margin'] ?? 20.0);
    $page = new PageSetup(marginTop: $rand, marginRight: $rand, marginBottom: $rand, marginLeft: $rand);

    $sheet = new SheetSetup(
        page: $page,
        cardWidth: $f['cardWidth'],
        cardHeight: $f['cardHeight'],
        columns: $f['columns'],
        rows: $f['rows'],
        gutterX: $f['gutterX'],
        gutterY: $f['gutterY'],
    );

    $preset = new CardPreset($sheet);
    $optionen = [];
    if ($f['fontPt'] !== null)  { $optionen['card_font_pt'] = $f['fontPt']; }
    if ($f['paddingEm'] !== 0.0) { $optionen['card_padding_em'] = $f['paddingEm']; }
    if ($f['borderMm'] !== 0.0)  { $optionen['card_border_mm'] = $f['borderMm']; }

    $css = $preset->css($page, $optionen);

    // Die Zahlen aus dem erzeugten Stylesheet zurueckholen — genau die, die
    // DomPDF zu sehen bekommt.
    preg_match('/\\.db-card \\{.*?width: ([\\d.]+)mm;\\s*height: ([\\d.]+)mm;\\s*padding: ([\\d.]+)mm;\\s*border: ([\\d.]+)mm/s', $css, $m);
    preg_match('/font-size: ([\\d.]+)pt;/', $css, $f2);
    preg_match('/\\.db-card-title \\{\\s*font-size: ([\\d.]+)em/', $css, $t);

    $ausgabe[$name] = [
        'innerWidth'  => (float) $m[1],
        'innerHeight' => (float) $m[2],
        'paddingMm'   => (float) $m[3],
        'borderMm'    => (float) $m[4],
        'baseFontPt'  => (float) $f2[1],
        'titleEm'     => (float) $t[1],
        'gridWidth'   => $sheet->gridWidth(),
        'perPage'     => $sheet->perPage(),
    ];
}

echo json_encode($ausgabe);
`;

export function phpGeometrie(faelle) {
    const roh = execFileSync('php', ['-r', SKRIPT, wurzel, JSON.stringify(faelle)], {
        encoding: 'utf8',
        cwd: wurzel,
    });

    return JSON.parse(roh);
}
