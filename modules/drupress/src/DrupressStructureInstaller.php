<?php

declare(strict_types=1);

namespace Drupal\drupress;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Creates the site-owned structures the WordPress mapping expects.
 *
 * This is the idempotent counterpart to the recipe: it creates only what is
 * missing, as ordinary site-owned config (owned by the site, never by a
 * Drupress module), so it is safe to run on a site installed without the
 * recipe, or to re-establish structures after they were changed. Every created
 * object survives a later Drupress uninstall, exactly like recipe output.
 */
class DrupressStructureInstaller {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DrupressMapping $mapping,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Creates any missing mapped structures.
   *
   * @return string[]
   *   Human-readable messages describing what was created (empty if nothing
   *   was missing).
   */
  public function createMissing(): array {
    $settings = $this->configFactory->get('drupress.settings');
    $created = [];

    // Vocabularies for Categories and Tags.
    foreach (['category_vocabulary' => 'Categories', 'tag_vocabulary' => 'Tags'] as $key => $label) {
      $vid = $settings->get($key);
      if (is_string($vid) && $vid !== '' && $this->moduleHandler->moduleExists('taxonomy')
        && $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vid) === NULL) {
        $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create([
          'vid' => $vid,
          'name' => $label,
        ])->save();
        $created[] = (string) $this->t('Created the "@label" vocabulary (@vid).', ['@label' => $label, '@vid' => $vid]);
      }
    }

    $post_type = $this->mapping->postType();
    if ($post_type === NULL) {
      return $created;
    }

    // Category / Tag reference fields on the post type.
    $field_map = [
      $settings->get('category_vocabulary') => 'field_category',
      $settings->get('tag_vocabulary') => 'field_tags',
    ];
    foreach ($field_map as $vid => $field_name) {
      if (!is_string($vid) || $vid === '') {
        continue;
      }
      $created = array_merge($created, $this->ensureTermReferenceField($post_type, $field_name, $vid));
    }

    // Featured image field (media reference), when Media is available.
    $image_field = $settings->get('featured_image_field');
    if (is_string($image_field) && $image_field !== '' && $this->moduleHandler->moduleExists('media')) {
      $created = array_merge($created, $this->ensureMediaReferenceField($post_type, $image_field));
    }

    return $created;
  }

  /**
   * Ensures a term-reference field storage and instance exist.
   *
   * @return string[]
   *   Messages for anything created.
   */
  protected function ensureTermReferenceField(string $bundle, string $field_name, string $vid): array {
    $created = [];
    if (FieldStorageConfig::loadByName('node', $field_name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => -1,
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
      $created[] = (string) $this->t('Created the @field field storage.', ['@field' => $field_name]);
    }
    if (FieldConfig::loadByName('node', $bundle, $field_name) === NULL) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => ['target_bundles' => [$vid => $vid]],
        ],
      ])->save();
      $created[] = (string) $this->t('Added @field to the @bundle type.', [
        '@field' => $field_name,
        '@bundle' => $bundle,
      ]);
    }
    return $created;
  }

  /**
   * Ensures a media-reference field storage and instance exist.
   *
   * @return string[]
   *   Messages for anything created.
   */
  protected function ensureMediaReferenceField(string $bundle, string $field_name): array {
    $created = [];
    if (FieldStorageConfig::loadByName('node', $field_name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => 1,
        'settings' => ['target_type' => 'media'],
      ])->save();
      $created[] = (string) $this->t('Created the @field field storage.', ['@field' => $field_name]);
    }
    if (FieldConfig::loadByName('node', $bundle, $field_name) === NULL) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => 'Featured image',
        'settings' => ['handler' => 'default:media', 'handler_settings' => []],
      ])->save();
      $created[] = (string) $this->t('Added @field to the @bundle type.', [
        '@field' => $field_name,
        '@bundle' => $bundle,
      ]);
    }
    return $created;
  }

}
