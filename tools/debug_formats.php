<?php

/**
 * @file
 * Dev tool: prints text formats and editors.
 */

declare(strict_types=1);

use Drupal\filter\Entity\FilterFormat;

print 'formats: ' . implode(',', array_keys(FilterFormat::loadMultiple())) . "\n";
print 'editors: ' . implode(',', array_keys(\Drupal::entityTypeManager()->getStorage('editor')->loadMultiple())) . "\n";
print 'block_content module: ' . (\Drupal::moduleHandler()->moduleExists('block_content') ? 'yes' : 'no') . "\n";
