<?php

/**
 * FQL syntax highlighter CLI.
 *
 * Usage:
 *   php examples/highlight.php "SELECT id FROM json(data.json)"
 *   echo "SELECT * FROM x" | php examples/highlight.php
 *   php examples/highlight.php --html "SELECT * FROM x"   # emit HTML instead of ANSI
 *
 * When `--html` is passed (or FQL_HIGHLIGHT=html in the environment) the output is a
 * `<pre class="fql"><code>...</code></pre>` block ready to drop into a web page.
 * The companion stylesheet `examples/highlighter.css` provides a starter theme.
 */

require __DIR__ . '/../vendor/autoload.php';

use FQL\Sql\Highlighter\HighlighterKind;
use FQL\Sql\Provider as SqlProvider;

/**
 * @return array{sql: string, kind: HighlighterKind, html: bool}
 */
function parseArgs(array $argv): array
{
    $html = (getenv('FQL_HIGHLIGHT') === 'html');
    $positional = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--html') {
            $html = true;
            continue;
        }
        if ($arg === '--bash') {
            $html = false;
            continue;
        }
        $positional[] = $arg;
    }

    if ($positional === []) {
        $sql = trim((string) stream_get_contents(STDIN));
    } else {
        $sql = implode(' ', $positional);
    }

    return [
        'sql' => $sql,
        'kind' => $html ? HighlighterKind::HTML : HighlighterKind::BASH,
        'html' => $html,
    ];
}

$args = parseArgs($argv);

if ($args['sql'] === '') {
    fwrite(STDERR, "Usage: php examples/highlight.php [--html|--bash] \"<SQL>\"\n");
    fwrite(STDERR, "       echo \"<SQL>\" | php examples/highlight.php\n");
    exit(1);
}

$highlighted = SqlProvider::highlight($args['sql'], $args['kind']);

if ($args['html']) {
    echo '<pre class="fql"><code>', $highlighted, '</code></pre>', PHP_EOL;
} else {
    echo $highlighted, PHP_EOL;
}
