<?php

declare(strict_types=1);

namespace Drupal\Tests\drupress\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the Drupress facade principle: uninstalling never harms content.
 *
 * Installs the suite, creates site-owned structures and content the way the
 * recipe and an editor would, uninstalls every Drupress module, and asserts
 * that all content, fields and field data survive intact while Drupress's own
 * presentation config is cleanly removed.
 *
 * @group drupress
 */
#[RunTestsInSeparateProcesses]
class FacadeUninstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'taxonomy',
    'views',
    'drupress',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node', 'taxonomy', 'drupress']);
  }

  /**
   * Uninstalling Drupress preserves content, fields and field data.
   */
  public function testUninstallPreservesContent(): void {
    // --- Site-owned structures (as the recipe would create them). ----------
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'category', 'name' => 'Categories'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_category',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_category',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Categories',
      'settings' => ['handler_settings' => ['target_bundles' => ['category' => 'category']]],
    ])->save();
    // The Tags field storage the drupress_posts view display depends on.
    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();

    // Enable the list-tables module now that the fields its views reference
    // exist. This mirrors the recipe order (content model, then presentation).
    $this->container->get('module_installer')->install(['drupress_content']);

    // --- Content an editor would create. -----------------------------------
    $term = Term::create(['vid' => 'category', 'name' => 'News']);
    $term->save();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Facade survivor',
      'field_category' => $term->id(),
    ]);
    $node->save();
    $node_id = (int) $node->id();
    $term_id = (int) $term->id();

    // Sanity: Drupress presentation config is present while installed.
    $this->assertNotNull($this->config('views.view.drupress_posts')->get('id'));
    $this->assertNotNull($this->config('drupress.settings')->get('post_node_type'));

    // --- Uninstall the entire Drupress suite. ------------------------------
    $this->container->get('module_installer')->uninstall([
      'drupress_content',
      'drupress',
    ]);

    // --- Content and structures survive untouched. -------------------------
    $reloaded = Node::load($node_id);
    $this->assertNotNull($reloaded, 'Node survives Drupress uninstall.');
    $this->assertSame('Facade survivor', $reloaded->label());
    $category_value = $reloaded->get('field_category')->getValue();
    $this->assertSame(
      $term_id,
      (int) ($category_value[0]['target_id'] ?? 0),
      'Field data (category reference) survives uninstall.',
    );

    $this->assertNotNull(Term::load($term_id), 'Taxonomy term survives.');
    $this->assertNotNull(
      FieldStorageConfig::loadByName('node', 'field_category'),
      'Site-owned field storage survives (never module-owned).',
    );
    $this->assertNotNull(
      FieldConfig::loadByName('node', 'article', 'field_category'),
      'Site-owned field instance survives.',
    );
    $this->assertNotNull(NodeType::load('article'), 'Node type survives.');
    $this->assertNotNull(Vocabulary::load('category'), 'Vocabulary survives.');

    // --- Drupress's own presentation config is gone. -----------------------
    $this->assertTrue(
      $this->config('views.view.drupress_posts')->isNew(),
      'Drupress view is removed on uninstall.',
    );
    $this->assertTrue(
      $this->config('drupress.settings')->isNew(),
      'Drupress settings are removed on uninstall.',
    );
  }

}
