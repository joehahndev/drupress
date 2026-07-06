<?php

declare(strict_types=1);

namespace Drupal\drupress_menu\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\drupress\DrupressMapping;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives the mapping-dependent WordPress sidebar links.
 *
 * Static routes (Appearance, Plugins, Users, ...) live in
 * drupress_menu.links.menu.yml. Everything whose target depends on the
 * drupress.settings mapping (Posts, Pages, Categories, Tags, Media,
 * Dashboard, Comments) is derived here so that remapping the content model
 * or toggling sub-modules keeps the sidebar correct without stale links.
 */
final class DrupressMenuLinks extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    protected DrupressMapping $mapping,
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteProviderInterface $routeProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get('drupress.mapping'),
      $container->get('module_handler'),
      $container->get('router.route_provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $links = [];

    // Dashboard: the Drupress dashboard when available, content overview
    // otherwise — there must always be a "Dashboard" as the WP anchor point.
    $dashboard_route = $this->moduleHandler->moduleExists('drupress_dashboard') && $this->routeExists('drupress_dashboard.dashboard')
      ? 'drupress_dashboard.dashboard'
      : 'system.admin_content';
    $links['dashboard'] = [
      'title' => 'Dashboard',
      'route_name' => $dashboard_route,
      'weight' => 0,
      'options' => $this->icon('dashboard'),
    ] + $base_plugin_definition;

    // Posts.
    if (($post_type = $this->mapping->postType()) !== NULL) {
      $posts_route = $this->routeExists('view.drupress_posts.page_1')
        ? 'view.drupress_posts.page_1'
        : 'system.admin_content';
      $links['posts'] = [
        'title' => 'Posts',
        'route_name' => $posts_route,
        'weight' => 5,
        'options' => $this->icon('posts'),
      ] + $base_plugin_definition;
      $links['posts.all'] = [
        'title' => 'All Posts',
        'route_name' => $posts_route,
        'parent' => 'drupress_menu.dynamic:posts',
        'weight' => 0,
      ] + $base_plugin_definition;
      $links['posts.add'] = [
        'title' => 'Add New Post',
        'route_name' => 'node.add',
        'route_parameters' => ['node_type' => $post_type],
        'parent' => 'drupress_menu.dynamic:posts',
        'weight' => 1,
      ] + $base_plugin_definition;
      if (($category_vocabulary = $this->mapping->categoryVocabulary()) !== NULL) {
        $links['posts.categories'] = [
          'title' => 'Categories',
          'route_name' => 'entity.taxonomy_vocabulary.overview_form',
          'route_parameters' => ['taxonomy_vocabulary' => $category_vocabulary],
          'parent' => 'drupress_menu.dynamic:posts',
          'weight' => 2,
        ] + $base_plugin_definition;
      }
      if (($tag_vocabulary = $this->mapping->tagVocabulary()) !== NULL) {
        $links['posts.tags'] = [
          'title' => 'Tags',
          'route_name' => 'entity.taxonomy_vocabulary.overview_form',
          'route_parameters' => ['taxonomy_vocabulary' => $tag_vocabulary],
          'parent' => 'drupress_menu.dynamic:posts',
          'weight' => 3,
        ] + $base_plugin_definition;
      }
    }

    // Media.
    if ($this->moduleHandler->moduleExists('media')) {
      $media_route = $this->routeExists('view.drupress_media.page_1')
        ? 'view.drupress_media.page_1'
        : 'entity.media.collection';
      $links['media'] = [
        'title' => 'Media',
        'route_name' => $media_route,
        'weight' => 10,
        'options' => $this->icon('media'),
      ] + $base_plugin_definition;
      $links['media.library'] = [
        'title' => 'Library',
        'route_name' => $media_route,
        'parent' => 'drupress_menu.dynamic:media',
        'weight' => 0,
      ] + $base_plugin_definition;
      if ($this->routeExists('entity.media.add_page')) {
        $links['media.add'] = [
          'title' => 'Add New Media File',
          'route_name' => 'entity.media.add_page',
          'parent' => 'drupress_menu.dynamic:media',
          'weight' => 1,
        ] + $base_plugin_definition;
      }
    }

    // Pages.
    if (($page_type = $this->mapping->pageType()) !== NULL) {
      $pages_route = $this->routeExists('view.drupress_pages.page_1')
        ? 'view.drupress_pages.page_1'
        : 'system.admin_content';
      $links['pages'] = [
        'title' => 'Pages',
        'route_name' => $pages_route,
        'weight' => 15,
        'options' => $this->icon('pages'),
      ] + $base_plugin_definition;
      $links['pages.all'] = [
        'title' => 'All Pages',
        'route_name' => $pages_route,
        'parent' => 'drupress_menu.dynamic:pages',
        'weight' => 0,
      ] + $base_plugin_definition;
      $links['pages.add'] = [
        'title' => 'Add New Page',
        'route_name' => 'node.add',
        'route_parameters' => ['node_type' => $page_type],
        'parent' => 'drupress_menu.dynamic:pages',
        'weight' => 1,
      ] + $base_plugin_definition;
    }

    // Comments.
    if ($this->moduleHandler->moduleExists('comment') && $this->routeExists('comment.admin')) {
      $links['comments'] = [
        'title' => 'Comments',
        'route_name' => 'comment.admin',
        'weight' => 20,
        'options' => $this->icon('comments'),
      ] + $base_plugin_definition;
    }

    $this->derivatives = $links;
    return $this->derivatives;
  }

  /**
   * Checks whether a route exists.
   */
  protected function routeExists(string $route_name): bool {
    return count($this->routeProvider->getRoutesByNames([$route_name])) === 1;
  }

  /**
   * Builds the URL options for a Drupress icon-pack icon.
   */
  protected function icon(string $icon_id): array {
    return [
      'icon' => [
        'pack_id' => 'drupress',
        'icon_id' => $icon_id,
      ],
    ];
  }

}
