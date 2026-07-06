<?php

declare(strict_types=1);

namespace Drupal\drupress_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\drupress\DrupressMapping;
use Drupal\drupress_dashboard\Form\QuickDraftForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The WordPress-style dashboard: At a Glance, Activity, Quick Draft.
 *
 * All numbers come from standard entity queries at request time; the
 * dashboard owns no storage and no configuration (facade principle).
 */
final class DashboardController extends ControllerBase {

  public function __construct(
    protected DrupressMapping $mapping,
    protected DateFormatterInterface $dateFormatter,
    protected ModuleHandlerInterface $extensionModuleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('drupress.mapping'),
      $container->get('date.formatter'),
      $container->get('module_handler'),
    );
  }

  /**
   * Builds the dashboard.
   */
  public function build(): array {
    $widgets = [
      'at_a_glance' => [
        '#theme' => 'drupress_dashboard_widget',
        '#title' => $this->t('At a Glance'),
        '#content' => $this->glance(),
        '#weight' => 0,
      ],
      'activity' => [
        '#theme' => 'drupress_dashboard_widget',
        '#title' => $this->t('Activity'),
        '#content' => $this->activity(),
        '#weight' => 10,
      ],
      'quick_draft' => [
        '#theme' => 'drupress_dashboard_widget',
        '#title' => $this->t('Quick Draft'),
        '#content' => $this->quickDraft(),
        '#weight' => 20,
      ],
    ];

    // Let other modules add or alter widgets (simple alter, not a plugin
    // system — WordPress' dashboard is effectively a fixed set too).
    $this->extensionModuleHandler->alter('drupress_dashboard_widgets', $widgets);

    return [
      '#theme' => 'drupress_dashboard',
      '#widgets' => $widgets,
      '#attached' => ['library' => ['drupress_admin/dashboard']],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node_list', 'comment_list', 'config:drupress.settings'],
      ],
    ];
  }

  /**
   * Builds the "At a Glance" widget: content counts, WordPress-style.
   */
  protected function glance(): array {
    $items = [];

    foreach ([
      'post' => [$this->mapping->postType(), 'Post', 'Posts'],
      'page' => [$this->mapping->pageType(), 'Page', 'Pages'],
    ] as [$bundle, $singular, $plural]) {
      if ($bundle === NULL) {
        continue;
      }
      $count = $this->nodeCount($bundle, TRUE);
      $items[] = [
        '#type' => 'link',
        '#title' => $this->formatPlural($count, "1 $singular", "@count $plural"),
        '#url' => Url::fromRoute('system.admin_content', [], ['query' => ['type' => $bundle, 'status' => '1']]),
      ];
    }

    if ($this->extensionModuleHandler->moduleExists('comment')) {
      $count = $this->entityTypeManager()->getStorage('comment')->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->count()
        ->execute();
      $items[] = [
        '#type' => 'link',
        '#title' => $this->formatPlural((int) $count, '1 Comment', '@count Comments'),
        '#url' => Url::fromRoute('comment.admin'),
      ];
    }

    $drafts = 0;
    foreach ([$this->mapping->postType(), $this->mapping->pageType()] as $bundle) {
      if ($bundle !== NULL) {
        $drafts += $this->nodeCount($bundle, FALSE);
      }
    }

    return [
      'counts' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['drupress-glance-list']],
      ],
      'drafts' => [
        '#markup' => '<p class="drupress-glance-drafts">' . $this->formatPlural($drafts, '1 item in Trash/Drafts (unpublished)', '@count items in Trash/Drafts (unpublished)') . '</p>',
      ],
    ];
  }

  /**
   * Builds the "Activity" widget: recently updated content.
   */
  protected function activity(): array {
    $bundles = array_filter([$this->mapping->postType(), $this->mapping->pageType()]);
    if ($bundles === []) {
      return ['#markup' => '<p>' . $this->t('No content model is mapped yet.') . '</p>'];
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundles, 'IN')
      ->sort('changed', 'DESC')
      ->range(0, 5)
      ->execute();

    $items = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $items[] = [
        'link' => [
          '#type' => 'link',
          '#title' => $node->label(),
          '#url' => $node->toUrl('edit-form'),
        ],
        'meta' => [
          '#markup' => '<span class="drupress-activity-meta">' . $this->dateFormatter->format((int) $node->getChangedTime(), 'short')
          . ($node->isPublished() ? '' : ' · ' . $this->t('Draft')) . '</span>',
        ],
      ];
    }

    if ($items === []) {
      return ['#markup' => '<p>' . $this->t('Nothing yet. Write your first post!') . '</p>'];
    }

    return [
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['drupress-activity-list']],
      ],
    ];
  }

  /**
   * Builds the "Quick Draft" widget: draft form plus the user's latest drafts.
   */
  protected function quickDraft(): array {
    $build = [];
    $post_type = $this->mapping->postType();
    if ($post_type === NULL) {
      return ['#markup' => '<p>' . $this->t('No "Posts" node type is mapped.') . '</p>'];
    }

    $access = $this->entityTypeManager()->getAccessControlHandler('node')->createAccess($post_type);
    if (!$access) {
      return ['#markup' => '<p>' . $this->t('You do not have permission to create posts.') . '</p>'];
    }

    $build['form'] = $this->formBuilder()->getForm(QuickDraftForm::class);

    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $post_type)
      ->condition('uid', $this->currentUser()->id())
      ->condition('status', 0)
      ->sort('created', 'DESC')
      ->range(0, 3)
      ->execute();
    if ($ids !== []) {
      $items = [];
      foreach ($storage->loadMultiple($ids) as $node) {
        $items[] = [
          '#type' => 'link',
          '#title' => $node->label(),
          '#url' => $node->toUrl('edit-form'),
        ];
      }
      $build['drafts_title'] = ['#markup' => '<h4>' . $this->t('Your Recent Drafts') . '</h4>'];
      $build['drafts'] = [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['drupress-recent-drafts']],
      ];
    }

    return $build;
  }

  /**
   * Counts nodes of a bundle by status.
   */
  protected function nodeCount(string $bundle, bool $published): int {
    return (int) $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', $published ? 1 : 0)
      ->count()
      ->execute();
  }

}
