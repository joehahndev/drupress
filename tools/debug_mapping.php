<?php

/**
 * @file
 * Dev tool: exercises the DrupressMapping field helpers.
 */

declare(strict_types=1);

/** @var \Drupal\drupress\DrupressMapping $m */
$m = \Drupal::service('drupress.mapping');
print 'postType: ' . var_export($m->postType(), TRUE) . "\n";
print 'categoryVocabulary: ' . var_export($m->categoryVocabulary(), TRUE) . "\n";
print 'tagVocabulary: ' . var_export($m->tagVocabulary(), TRUE) . "\n";
print 'categoryField(article): ' . var_export($m->categoryField('article'), TRUE) . "\n";
print 'tagField(article): ' . var_export($m->tagField('article'), TRUE) . "\n";
print 'featuredImageField: ' . var_export($m->featuredImageField(), TRUE) . "\n";

foreach (\Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'article') as $name => $def) {
  if ($def->getType() === 'entity_reference' && !$def->getFieldStorageDefinition()->isBaseField()) {
    print "field $name: target_type=" . var_export($def->getSetting('target_type'), TRUE)
      . ' handler_settings=' . json_encode($def->getSetting('handler_settings')) . "\n";
  }
}
