<?php

declare(strict_types=1);

namespace Drupal\drupress_content\Plugin\views\area;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\drupress\DrupressMapping;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * WordPress-style "All | Published | Draft" status tab links with counts.
 *
 * Reuses the view's own exposed "status" filter (a grouped boolean filter:
 * group 1 = Published/status=1, group 2 = Draft/status=0) so the tabs are
 * just alternate links into the same query-driven filtering — no separate
 * query logic to keep in sync with the view.
 */
#[ViewsArea('drupress_status_tabs')]
final class StatusTabs extends AreaPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Drupress mapping service.
   */
  protected DrupressMapping $mapping;

  /**
   * The current request.
   */
  protected RequestStack $requestStack;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->mapping = $container->get('drupress.mapping');
    $instance->requestStack = $container->get('request_stack');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['drupress_mapping_key'] = ['default' => 'post'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return (string) $this->t('Drupress status tabs');
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    $bundle = $this->options['drupress_mapping_key'] === 'page'
      ? $this->mapping->pageType()
      : $this->mapping->postType();
    if ($bundle === NULL) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $count = static function (int $status) use ($storage, $bundle): int {
      return (int) $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundle)
        ->condition('status', $status)
        ->count()
        ->execute();
    };

    $published = $count(1);
    $draft = $count(0);
    $all = $published + $draft;

    $request = $this->requestStack->getCurrentRequest();
    $base_query = $request?->query->all() ?? [];
    unset($base_query['page']);

    $tabs = [
      'all' => [$this->formatPlural($all, 'All (@count)', 'All (@count)'), NULL],
      'published' => [$this->formatPlural($published, 'Published (@count)', 'Published (@count)'), '1'],
      'draft' => [$this->formatPlural($draft, 'Draft (@count)', 'Draft (@count)'), '2'],
    ];

    $current = $request?->query->get('status');
    $current_key = match (TRUE) {
      $current === '1' => 'published',
      $current === '2' => 'draft',
      default => 'all',
    };

    $links = [];
    foreach ($tabs as $key => [$label, $status_value]) {
      $query = $base_query;
      if ($status_value === NULL) {
        unset($query['status']);
      }
      else {
        $query['status'] = $status_value;
      }
      $links[$key] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => Url::fromRoute('<current>', [], ['query' => $query]),
        '#attributes' => [
          'class' => $key === $current_key ? ['is-active'] : [],
        ],
      ];
    }

    return [
      '#theme' => 'drupress_status_tabs',
      '#tabs' => $links,
      '#cache' => [
        'contexts' => ['url.query_args'],
        'tags' => ['node_list'],
      ],
    ];
  }

}
