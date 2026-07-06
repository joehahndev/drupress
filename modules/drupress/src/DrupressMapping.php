<?php

declare(strict_types=1);

namespace Drupal\drupress;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Resolves the WordPress-facing content model onto real Drupal structures.
 *
 * Drupress is a facade: it never owns content storage. This service maps the
 * WordPress concepts (Posts, Pages, Categories, Tags, Featured image) onto
 * whatever node types, vocabularies and fields the site actually has, as
 * configured in drupress.settings. Every getter degrades gracefully: when a
 * mapped structure does not exist on this site, the getter returns NULL and
 * callers are expected to hide the corresponding UI rather than fail.
 */
class DrupressMapping {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Gets the node type machine name used as WordPress "Posts", if it exists.
   */
  public function postType(): ?string {
    return $this->existingBundle('node_type', $this->setting('post_node_type'));
  }

  /**
   * Gets the node type machine name used as WordPress "Pages", if it exists.
   */
  public function pageType(): ?string {
    return $this->existingBundle('node_type', $this->setting('page_node_type'));
  }

  /**
   * Gets the vocabulary machine name used as "Categories", if it exists.
   */
  public function categoryVocabulary(): ?string {
    return $this->existingBundle('taxonomy_vocabulary', $this->setting('category_vocabulary'));
  }

  /**
   * Gets the vocabulary machine name used as "Tags", if it exists.
   */
  public function tagVocabulary(): ?string {
    return $this->existingBundle('taxonomy_vocabulary', $this->setting('tag_vocabulary'));
  }

  /**
   * Gets the "Featured image" field name if it exists on the post type.
   */
  public function featuredImageField(): ?string {
    $post_type = $this->postType();
    $field = $this->setting('featured_image_field');
    if ($post_type === NULL || $field === NULL) {
      return NULL;
    }
    $definitions = $this->entityTypeManager
      ->getStorage('field_config')
      ->load("node.$post_type.$field");
    return $definitions !== NULL ? $field : NULL;
  }

  /**
   * Gets the field on a bundle referencing the "Categories" vocabulary.
   */
  public function categoryField(string $bundle): ?string {
    return $this->termReferenceField($bundle, $this->categoryVocabulary());
  }

  /**
   * Gets the field on a bundle referencing the "Tags" vocabulary.
   */
  public function tagField(string $bundle): ?string {
    return $this->termReferenceField($bundle, $this->tagVocabulary());
  }

  /**
   * Finds the first term reference field on a bundle targeting a vocabulary.
   */
  protected function termReferenceField(string $bundle, ?string $vocabulary): ?string {
    if ($vocabulary === NULL || !$this->entityTypeManager->hasDefinition('node')) {
      return NULL;
    }
    foreach ($this->entityFieldManager->getFieldDefinitions('node', $bundle) as $name => $definition) {
      if ($definition->getType() !== 'entity_reference' || $definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }
      if ($definition->getSetting('target_type') !== 'taxonomy_term') {
        continue;
      }
      $handler_settings = $definition->getSetting('handler_settings') ?? [];
      if (in_array($vocabulary, $handler_settings['target_bundles'] ?? [], TRUE)) {
        return $name;
      }
    }
    return NULL;
  }

  /**
   * Whether users should land on the Drupress dashboard after login.
   */
  public function loginRedirectEnabled(): bool {
    return (bool) $this->configFactory->get('drupress.settings')->get('login_redirect_dashboard');
  }

  /**
   * Reads a string setting, normalizing empty values to NULL.
   */
  protected function setting(string $key): ?string {
    $value = $this->configFactory->get('drupress.settings')->get($key);
    return is_string($value) && $value !== '' ? $value : NULL;
  }

  /**
   * Returns the id when a config entity of the given type exists, else NULL.
   */
  protected function existingBundle(string $entity_type_id, ?string $id): ?string {
    if ($id === NULL || !$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage($entity_type_id)->load($id) !== NULL ? $id : NULL;
  }

}
