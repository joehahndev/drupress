<?php

/**
 * @file
 * Dev tool: creates sample posts, pages, and terms for manual testing.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

$term_ids = [];
foreach (['News' => 'category', 'Tutorials' => 'category', 'drupal' => 'tags', 'wordpress' => 'tags'] as $name => $vid) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $name, 'vid' => $vid]);
  $term = $existing ? reset($existing) : Term::create(['name' => $name, 'vid' => $vid]);
  $term->save();
  $term_ids[$name] = $term->id();
}

$posts = [
  ['Hello world!', 1, ['News'], ['wordpress']],
  ['Getting started with Drupress', 1, ['Tutorials'], ['drupal', 'wordpress']],
  ['A draft post about nothing', 0, ['News'], []],
];
foreach ($posts as [$title, $status, $cats, $tags]) {
  Node::create([
    'type' => 'article',
    'title' => $title,
    'status' => $status,
    'body' => ['value' => '<p>Sample body for ' . $title . '</p>', 'format' => 'basic_html'],
    'field_category' => array_map(fn ($n) => $term_ids[$n], $cats),
    'field_tags' => array_map(fn ($n) => $term_ids[$n], $tags),
  ])->save();
}

foreach ([['About us', 1], ['Contact', 1], ['Unpublished landing page', 0]] as [$title, $status]) {
  Node::create([
    'type' => 'page',
    'title' => $title,
    'status' => $status,
    'body' => ['value' => '<p>Sample body for ' . $title . '</p>', 'format' => 'basic_html'],
  ])->save();
}

print "Created " . count($posts) . " posts and 3 pages.\n";
