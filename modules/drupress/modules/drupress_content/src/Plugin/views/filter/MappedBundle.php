<?php

declare(strict_types=1);

namespace Drupal\drupress_content\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\drupress\DrupressMapping;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters nodes to the bundle mapped as WordPress "Posts" or "Pages".
 *
 * Resolving the bundle through the mapping service at query time (instead of
 * a stock bundle filter) means the shipped Drupress views carry no config
 * dependency on any node type: remapping the content model retargets the
 * views instantly, and deleting a node type never cascade-deletes them.
 */
#[ViewsFilter('drupress_mapped_bundle')]
final class MappedBundle extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Drupress mapping service.
   */
  protected DrupressMapping $mapping;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->mapping = $container->get('drupress.mapping');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    // Which side of the WP model this view lists: 'post' or 'page'.
    $options['drupress_mapping_key'] = ['default' => 'post'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return $this->options['drupress_mapping_key'] === 'page'
      ? $this->t('Mapped "Pages" bundle')->render()
      : $this->t('Mapped "Posts" bundle')->render();
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $bundle = $this->options['drupress_mapping_key'] === 'page'
      ? $this->mapping->pageType()
      : $this->mapping->postType();

    $this->ensureMyTable();
    // When the mapping is unresolved the view must show nothing rather than
    // leak every node: filter on an impossible bundle.
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], "$this->tableAlias.type", $bundle ?? '', '=');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Results change when the mapping changes.
    return ['config:drupress.settings'];
  }

}
