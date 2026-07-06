<?php

/**
 * @file
 * Dev tool: reports where the WP-model fields render in a saved edit-form
 * HTML file. Usage: php check_edit_form.php /tmp/edit.html.
 */

declare(strict_types=1);

$html = file_get_contents($argv[1]);
$doc = new DOMDocument();
@$doc->loadHTML($html);
$xp = new DOMXPath($doc);

foreach (['field-category', 'field-tags', 'field-featured-image'] as $f) {
  $nodes = $xp->query("//*[contains(@class, 'field--name-$f')]");
  if (!$nodes->length) {
    print "$f: NOT FOUND\n";
    continue;
  }
  $el = $nodes->item(0);
  $loc = 'top-level';
  for ($p = $el->parentNode; $p instanceof DOMElement; $p = $p->parentNode) {
    $sel = $p->getAttribute('data-drupal-selector');
    if ($sel === 'edit-metabox-fields') {
      $loc = 'TRAY';
      break;
    }
    if ($sel === 'edit-additional-fields') {
      $loc = 'SIDEBAR (additional_fields)';
      break;
    }
  }
  print "$f: $loc\n";
}
