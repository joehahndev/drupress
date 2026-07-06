<?php

declare(strict_types=1);

namespace Drupal\drupress_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * WordPress-style Trash and Restore actions.
 *
 * Honest facade: "Trash" unpublishes and "Restore" republishes through the
 * standard node API. Nothing is copied or moved, no Drupress-owned storage
 * exists, and the node history is ordinary revision history.
 */
final class TrashController extends ControllerBase {

  /**
   * Unpublishes a node ("Move to Trash" in WordPress terms).
   */
  public function trash(NodeInterface $node, Request $request): RedirectResponse {
    $node->setUnpublished();
    $node->save();
    $this->messenger()->addStatus($this->t('"@title" moved to Trash (unpublished). It stays listed under the Trash filter and can be restored at any time.', [
      '@title' => $node->label(),
    ]));
    return $this->redirectBack($request);
  }

  /**
   * Republishes a node ("Restore" in WordPress terms).
   */
  public function restore(NodeInterface $node, Request $request): RedirectResponse {
    $node->setPublished();
    $node->save();
    $this->messenger()->addStatus($this->t('"@title" restored (published).', [
      '@title' => $node->label(),
    ]));
    return $this->redirectBack($request);
  }

  /**
   * Redirects to the destination or the content overview.
   */
  protected function redirectBack(Request $request): RedirectResponse {
    $destination = $request->query->get('destination');
    if (is_string($destination) && str_starts_with($destination, '/')) {
      return new RedirectResponse($destination);
    }
    return $this->redirect('system.admin_content');
  }

}
