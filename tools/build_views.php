<?php

/**
 * @file
 * Dev tool: derives the Drupress list-table views from core's content view.
 *
 * Run inside the dev site: drush php:script tools/build_views.php (from the
 * mounted repo path). Regenerates
 * modules/drupress/modules/drupress_content/config/install/views.view.drupress_{posts,pages}.yml.
 */

declare(strict_types=1);

use Drupal\Core\Serialization\Yaml;

$core_view_path = DRUPAL_ROOT . '/core/modules/node/config/optional/views.view.content.yml';
$out_dir = '/mnt/c/dev/drupress-drupal-module-suite/modules/drupress/modules/drupress_content/config/install';

$base = Yaml::decode(file_get_contents($core_view_path));

/**
 * Builds one Drupress list-table view from the core content view.
 */
$build = static function (array $view, string $kind) use ($out_dir): void {
  $is_posts = $kind === 'post';
  $id = $is_posts ? 'drupress_posts' : 'drupress_pages';

  $view['id'] = $id;
  $view['label'] = $is_posts ? 'Drupress: Posts' : 'Drupress: Pages';
  $view['description'] = $is_posts
    ? 'WordPress-style All Posts list table.'
    : 'WordPress-style All Pages list table.';
  $view['tag'] = 'drupress';
  $view['module'] = 'views';

  $display =& $view['display'];
  $options =& $display['default']['display_options'];

  $options['title'] = $is_posts ? 'Posts' : 'Pages';

  // --- Fields -------------------------------------------------------------
  $fields = $options['fields'];
  // The single-bundle views need no "Content type" column, and the
  // operations dropbutton is replaced by WP row actions under the title.
  unset($fields['type'], $fields['operations']);

  // Row actions render inside the Title column (see style columns below).
  $fields['drupress_row_actions'] = [
    'id' => 'drupress_row_actions',
    'table' => 'node_field_data',
    'field' => 'drupress_row_actions',
    'plugin_id' => 'drupress_row_actions',
    'label' => '',
    'exclude' => FALSE,
    'element_class' => '',
    'element_default_classes' => TRUE,
    'empty' => '',
    'hide_empty' => FALSE,
    'empty_zero' => FALSE,
    'hide_alter_empty' => TRUE,
  ];

  if ($is_posts) {
    foreach (['field_category' => 'Categories', 'field_tags' => 'Tags'] as $field_name => $label) {
      $fields[$field_name] = [
        'id' => $field_name,
        'table' => 'node__' . $field_name,
        'field' => $field_name,
        'plugin_id' => 'field',
        'label' => $label,
        'exclude' => FALSE,
        'element_class' => '',
        'element_default_classes' => TRUE,
        'empty' => '',
        'hide_empty' => FALSE,
        'empty_zero' => FALSE,
        'hide_alter_empty' => TRUE,
        'type' => 'entity_reference_label',
        'settings' => ['link' => TRUE],
        'group_column' => 'target_id',
        'delta_limit' => 0,
        'delta_offset' => 0,
        'delta_reversed' => FALSE,
        'delta_first_last' => FALSE,
        'multi_type' => 'separator',
        'separator' => ', ',
      ];
    }
  }

  // Column order: checkbox, title (+actions), author, cats/tags, status, date.
  $order = $is_posts
    ? ['node_bulk_form', 'title', 'drupress_row_actions', 'name', 'field_category', 'field_tags', 'status', 'changed']
    : ['node_bulk_form', 'title', 'drupress_row_actions', 'name', 'status', 'changed'];
  $ordered = [];
  foreach ($order as $key) {
    if (isset($fields[$key])) {
      $ordered[$key] = $fields[$key];
    }
  }
  $options['fields'] = $ordered;

  // --- Filters ------------------------------------------------------------
  $filters = $options['filters'];
  // The bundle comes from the Drupress mapping, resolved at query time.
  unset($filters['type'], $filters['langcode']);
  $filters['drupress_mapped_bundle'] = [
    'id' => 'drupress_mapped_bundle',
    'table' => 'node_field_data',
    'field' => 'drupress_mapped_bundle',
    'plugin_id' => 'drupress_mapped_bundle',
    'group' => 1,
    'exposed' => FALSE,
    'drupress_mapping_key' => $kind,
  ];
  // WordPress calls the unpublished state "Draft"; the status tabs (below)
  // replace core's dropdown as the primary UI, but the grouped filter itself
  // still drives `?status=` filtering, so its group titles are kept in sync.
  $filters['status']['group_info']['group_items'][2]['title'] = 'Draft';
  $options['filters'] = $filters;

  // --- Header: WP-style All | Published | Draft status tabs. --------------
  $options['header']['drupress_status_tabs'] = [
    'id' => 'drupress_status_tabs',
    'table' => 'node_field_data',
    'field' => 'drupress_status_tabs',
    'plugin_id' => 'drupress_status_tabs',
    'empty' => TRUE,
    'drupress_mapping_key' => $kind,
  ];

  // --- Style: merge row actions into the Title column ----------------------
  $options['style']['options']['columns']['drupress_row_actions'] = 'title';

  // --- Page display ---------------------------------------------------------
  $display['page_1']['display_options']['path'] = $is_posts
    ? 'admin/drupress/posts'
    : 'admin/drupress/pages';
  // Core's content view page overrides the menu tab; ours must not.
  unset($display['page_1']['display_options']['menu']);

  // --- Dependencies ----------------------------------------------------------
  $modules = ['drupress', 'drupress_content', 'node', 'user'];
  $view['dependencies'] = ['module' => $modules];
  if ($is_posts) {
    // Views field handlers for configurable fields require their storage.
    // Documented facade note: deleting these field storages deletes this
    // view (module reinstall restores it); content is never affected.
    $view['dependencies']['config'] = [
      'field.storage.node.field_category',
      'field.storage.node.field_tags',
    ];
  }

  file_put_contents("$out_dir/views.view.$id.yml", Yaml::encode($view));
  print "wrote views.view.$id.yml\n";
};

$build($base, 'post');
$build($base, 'page');
