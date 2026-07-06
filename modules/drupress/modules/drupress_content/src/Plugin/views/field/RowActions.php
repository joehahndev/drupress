<?php

declare(strict_types=1);

namespace Drupal\drupress_content\Plugin\views\field;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * WordPress-style row actions: Edit | Trash/Restore | View.
 *
 * Rendered under the title cell and revealed on row hover by the
 * drupress_admin theme, like the wp-admin list tables. "Trash" is honestly
 * an unpublish (and "Restore" a republish): no data moves anywhere, the
 * node's published flag is toggled through the standard node API.
 */
#[ViewsField('drupress_row_actions')]
final class RowActions extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The redirect destination service.
   */
  protected RedirectDestinationInterface $redirectDestination;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->redirectDestination = $container->get('redirect.destination');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // No query modification: this field renders from the loaded entity.
  }

  /**
   * {@inheritdoc}
   *
   * Like core's EntityOperations views field, this returns a render array;
   * the views renderer handles it. The base signature predates render arrays.
   *
   * @return array
   *   A render array.
   */
  // @phpstan-ignore method.childReturnType, method.childReturnType
  public function render(ResultRow $values) {
    $node = $this->getEntity($values);
    if (!$node instanceof NodeInterface) {
      return [];
    }

    $destination = ['query' => $this->redirectDestination->getAsArray()];
    $links = [];

    if ($node->access('update')) {
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $node->toUrl('edit-form', $destination),
      ];
      $links[$node->isPublished() ? 'trash' : 'restore'] = [
        'title' => $node->isPublished() ? $this->t('Trash') : $this->t('Restore'),
        'url' => Url::fromRoute(
          $node->isPublished() ? 'drupress_content.trash' : 'drupress_content.restore',
          ['node' => $node->id()],
          $destination,
        ),
      ];
    }
    if ($node->access('view')) {
      $links['view'] = [
        'title' => $this->t('View'),
        'url' => $node->toUrl('canonical'),
      ];
    }
    if ($node->access('delete')) {
      $links['delete'] = [
        'title' => $this->t('Delete Permanently'),
        'url' => $node->toUrl('delete-form', $destination),
      ];
    }

    if ($links === []) {
      return [];
    }

    $items = [];
    foreach ($links as $key => $link) {
      $items[] = [
        '#type' => 'link',
        '#title' => $link['title'],
        '#url' => $link['url'],
        '#attributes' => ['class' => ['drupress-row-action', "drupress-row-action--$key"]],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['drupress-row-actions']],
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

}
