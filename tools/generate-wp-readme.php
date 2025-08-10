<?php
/**
 * Advanced converter from README.md to WordPress.org readme.txt format.
 * - Dynamic stable tag from plugin header
 * - Extracts sections (Key Features, Installation, FAQ, Screenshots)
 * - Optional heading conversion inside extracted blocks
 * - Basic error handling
 * Usage: php tools/generate-wp-readme.php > .wordpress-org/readme.txt
 */
$root = dirname(__DIR__);
if (php_sapi_name() !== 'cli') { fwrite(STDERR, "Must run via CLI PHP.\n"); exit(1);} 

$readmeFile = $root . '/README.md';
if (!file_exists($readmeFile)) { fwrite(STDERR, "README.md not found\n"); exit(1);} 
$md = file_get_contents($readmeFile);
if ($md === false) { fwrite(STDERR, "Failed to read README.md\n"); exit(1);} 

// Derive plugin version (Stable tag) from main plugin file
$plugin_main = $root . '/authentic-checker.php';
$stable_tag = '0.0.0';
if (file_exists($plugin_main)) {
    $header = file_get_contents($plugin_main);
    if ($header && preg_match('/^Version:\s*(.+)$/mi', $header, $vm)) {
        $stable_tag = trim($vm[1]);
    }
}

// Extract first heading as plugin name
preg_match('/^#\s+(.+)$/m', $md, $m);
$name = $m[1] ?? 'Plugin Name';

// Short description (multi-line paragraph after H1 until blank line or next heading)
$lines = preg_split('/\r?\n/', $md);
$shortLines = [];
$foundMain = false; $collect=false;
foreach ($lines as $l) {
    if (!$foundMain && preg_match('/^#\s+/', $l)) { $foundMain = true; $collect=true; continue; }
    if (!$foundMain) continue;
    if (preg_match('/^##\s+/', $l)) break; // next section reached without description
    if (trim($l)==='') {
        if ($collect && $shortLines) break; // paragraph ended
        continue;
    }
    if ($collect) { $shortLines[] = trim($l); }
}
$short = strip_tags(implode(' ', $shortLines));
$changelogFull = '';
// Include top two changelog sections if CHANGELOG.md present
$clFile = $root.'/CHANGELOG.md';
if (file_exists($clFile)) {
    $cl = file_get_contents($clFile);
    if ($cl !== false) {
        // Match headings like # [x.y.z] - date or ## [x.y.z]
        if (preg_match_all('/^#?+\s*\[(\d+\.\d+\.\d+)\].*?$(?:\n(?:(?!^# ).*)*)/m', $cl, $matches)) {
            $sections = $matches[0];
            $top = array_slice($sections,0,2);
            $changelogFull = trim(implode("\n\n", $top));
        }
        if (!$changelogFull) { $changelogFull = "See repository for history."; }
    }
}

// Helpers
function extract_section($md, $heading) {
    $pattern = '/^##\s+'.preg_quote($heading,'/').'\s*\n(.*?)(?=^##\s+|\z)/ms';
    if (preg_match($pattern, $md, $m)) return trim($m[1]);
    return '';
}
function convert_headings_wp($text) {
    return preg_replace_callback('/^##\s+(.+)$/m', function($m){ return '= '.trim($m[1]).' ='; }, $text);
}

$features = extract_section($md,'Key Features');
$screens = extract_section($md,'Screenshots');
$install = extract_section($md,'Installation');
$faq = extract_section($md,'FAQ');

$descBlocks = [];
if ($features) $descBlocks[] = "Features:\n".$features;
if ($screens) $descBlocks[] = "Screenshots:\n".$screens;
$desc = $descBlocks ? implode("\n\n", $descBlocks) : '';
if (!$desc) { $desc = "See GitHub README for full feature list."; }

$desc = convert_headings_wp($desc);
if ($install) $install = convert_headings_wp($install);
if ($faq) $faq = convert_headings_wp($faq);

$out = [];
$out[] = "== $name ==";
$out[] = "Contributors: (add your wp.org username)";
// Dynamic tags guess (basic keyword scan)
$baseTags = ['authenticity','woocommerce','verification','product codes'];
if (stripos($md,'qr') !== false) $baseTags[]='qr';
if (stripos($md,'rest api') !== false) $baseTags[]='rest-api';
if (stripos($md,'privacy') !== false) $baseTags[]='privacy';
$out[] = 'Tags: '.implode(', ', array_unique($baseTags));
$out[] = "Requires at least: 6.0";
$out[] = "Tested up to: 6.5";
$out[] = "Stable tag: $stable_tag";
$out[] = "Requires PHP: 7.4";
$out[] = "License: GPLv2 or later";
$out[] = "License URI: https://www.gnu.org/licenses/gpl-2.0.html";
$out[] = '';
$out[] = $short ?: 'Authenticity code verification for WooCommerce.';
$out[] = '';
$out[] = '== Description ==';
$out[] = $desc;
if ($install) { $out[] = "\n== Installation =="; $out[] = $install; }
if ($faq) { $out[] = "\n== Frequently Asked Questions =="; $out[] = $faq; }
$out[] = "\n== Changelog ==";
$out[] = $changelogFull ?: 'See CHANGELOG.md for complete history.';

file_put_contents('php://stdout', implode("\n", $out) . "\n");
