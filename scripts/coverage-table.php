<?php

/**
 * Coverage per file, sorted by what is missing.
 *
 * The HTML report is for reading; the summary is three numbers. This is the one that
 * answers "where is the work" and "did that file reach 100%", which is what you want while
 * writing tests rather than after.
 *
 * Run through `npm run "Test: coverage (per file)"`, optionally narrowed to a path
 * fragment with `--file=Commands`. (`--only` is npm's own flag and never reaches here.)
 */

$clover = $argv[1] ?? 'build/clover.xml';
$filter = $argv[2] ?? '';

if (!file_exists($clover)) {
    fwrite(STDERR, "No coverage at {$clover}.\n");
    exit(1);
}

$xml = simplexml_load_file($clover);
$rows = [];
$total = ['covered' => 0, 'statements' => 0];
$untouched = 0;

foreach ($xml->xpath('//file') as $file) {
    $path = str_replace(getcwd() . '/app/', '', (string) $file['name']);
    $metrics = $file->metrics;
    $statements = (int) $metrics['statements'];

    if ($statements === 0) {
        continue;
    }

    $covered = (int) $metrics['coveredstatements'];
    $total['covered'] += $covered;
    $total['statements'] += $statements;

    if ($filter !== '' && !str_contains($path, $filter)) {
        continue;
    }

    if ($covered === 0) {
        $untouched++;
    }

    $rows[] = ['missing' => $statements - $covered, 'covered' => $covered, 'statements' => $statements, 'path' => $path];
}

usort($rows, fn ($a, $b) => $b['missing'] <=> $a['missing'] ?: strcmp($a['path'], $b['path']));

printf("%-58s %9s %6s\n", 'File', 'Covered', '');
foreach ($rows as $row) {
    printf(
        "%-58s %4d/%-4d %5.0f%% %s\n",
        $row['path'],
        $row['covered'],
        $row['statements'],
        100 * $row['covered'] / $row['statements'],
        $row['missing'] === 0 ? 'ok' : ($row['covered'] === 0 ? '  never touched' : '')
    );
}

printf(
    "\n%d files shown, %d never touched. Whole app: %d/%d (%.1f%%)\n",
    count($rows),
    $untouched,
    $total['covered'],
    $total['statements'],
    100 * $total['covered'] / $total['statements']
);
