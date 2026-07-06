<?php

/**
 * @file
 * Dev tool: checks drupress_editor hook registration and alter order.
 */

declare(strict_types=1);

$mh = \Drupal::moduleHandler();
print 'drupress_editor form_node_form_alter registered: ' . var_export($mh->hasImplementations('form_node_form_alter', ['drupress_editor']), TRUE) . "\n";
print 'gutenberg form_node_form_alter registered: ' . var_export($mh->hasImplementations('form_node_form_alter', ['gutenberg']), TRUE) . "\n";
print 'drupress_editor module_implements_alter registered: ' . var_export(function_exists('drupress_editor_form_node_form_alter'), TRUE) . " (function exists)\n";
print 'gutenberg helper exists: ' . var_export(function_exists('_gutenberg_is_gutenberg_enabled'), TRUE) . "\n";
