<?php

declare(strict_types=1);

namespace Drupal\Tests\drupress\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the DrupressMapping service, including graceful degradation.
 *
 * @group drupress
 * @coversDefaultClass \Drupal\drupress\DrupressMapping
 */
#[RunTestsInSeparateProcesses]
class DrupressMappingTest extends KernelTestBase {

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
    $this->installConfig(['node', 'taxonomy', 'drupress']);
  }

  /**
   * Unmapped structures degrade to NULL rather than failing.
   *
   * @covers ::postType
   * @covers ::categoryVocabulary
   * @covers ::featuredImageField
   */
  public function testGracefulDegradationWhenNothingExists(): void {
    /** @var \Drupal\drupress\DrupressMapping $mapping */
    $mapping = $this->container->get('drupress.mapping');
    // The default settings name article/category/etc., but nothing exists yet.
    $this->assertNull($mapping->postType());
    $this->assertNull($mapping->categoryVocabulary());
    $this->assertNull($mapping->featuredImageField());
    $this->assertNull($mapping->categoryField('article'));
  }

  /**
   * Mapping resolves once the named structures exist.
   *
   * @covers ::postType
   * @covers ::categoryVocabulary
   * @covers ::categoryField
   */
  public function testResolvesExistingStructures(): void {
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
      'settings' => ['handler_settings' => ['target_bundles' => ['category' => 'category']]],
    ])->save();

    /** @var \Drupal\drupress\DrupressMapping $mapping */
    $mapping = $this->container->get('drupress.mapping');
    $this->assertSame('article', $mapping->postType());
    $this->assertSame('category', $mapping->categoryVocabulary());
    $this->assertSame('field_category', $mapping->categoryField('article'));
    // A vocabulary with no referencing field resolves to no field.
    $this->assertNull($mapping->tagField('article'));
  }

}
