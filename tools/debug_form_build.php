<?php

/**
 * @file
 * Dev tool: builds the node 1 edit form and inspects field placement.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;

$node = Node::load(1);
$form = \Drupal::service('entity.form_builder')->getForm($node, 'default');

print 'drupress marker: ' . var_export($form['#drupress_editor_ran'] ?? 'NOT SET', TRUE) . "\n";
print 'additional_fields exists: ' . var_export(isset($form['additional_fields']), TRUE) . "\n";
if (isset($form['additional_fields'])) {
  print 'additional_fields children: ' . implode(',', array_filter(array_keys($form['additional_fields']), fn ($k) => !str_starts_with((string) $k, '#'))) . "\n";
}
foreach (['field_category', 'field_tags', 'field_featured_image'] as $f) {
  $top = isset($form[$f]);
  $group = $top ? ($form[$f]['#group'] ?? 'none') : '-';
  print "$f: top-level=" . var_export($top, TRUE) . " group=$group\n";
}
print 'form class attr: ' . implode(' ', $form['#attributes']['class'] ?? []) . "\n";
