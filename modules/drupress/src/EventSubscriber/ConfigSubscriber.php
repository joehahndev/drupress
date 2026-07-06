<?php

declare(strict_types=1);

namespace Drupal\drupress\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to Drupress mapping changes.
 *
 * Menu links (drupress_menu deriver) and list views resolve the WordPress
 * content model through drupress.settings at build/query time, so a mapping
 * change must rebuild menu link plugins and invalidate render caches to take
 * effect immediately.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'onConfigSave'];
  }

  /**
   * Rebuilds mapping-derived plugins when drupress.settings is saved.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() !== 'drupress.settings') {
      return;
    }
    $this->menuLinkManager->rebuild();
    $this->cacheTagsInvalidator->invalidateTags(['config:drupress.settings']);
  }

}
