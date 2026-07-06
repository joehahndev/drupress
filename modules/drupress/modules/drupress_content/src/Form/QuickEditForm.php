<?php

declare(strict_types=1);

namespace Drupal\drupress_content\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * WordPress-style Quick Edit: title, status, and date in a modal.
 *
 * A v1 subset of WordPress' inline Quick Edit (which also covers slug,
 * author, categories, comments, etc.). Everything here goes through the
 * standard node API — no separate storage, no facade compromise.
 */
final class QuickEditForm extends FormBase {

  /**
   * The node being edited, set once buildForm() resolves it.
   */
  protected ?NodeInterface $node = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->setRedirectDestination($container->get('redirect.destination'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupress_quick_edit';
  }

  /**
   * {@inheritdoc}
   *
   * The {node} route parameter is upcast to a NodeInterface and passed here
   * by name, per Drupal's form-route parameter binding.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node === NULL) {
      throw new NotFoundHttpException();
    }
    $this->node = $node;
    $form_state->set('node_id', $node->id());

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $node->label(),
      '#required' => TRUE,
    ];
    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => date('Y-m-d', (int) $node->getCreatedTime()),
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Published'),
      '#default_value' => $node->isPublished(),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $node = $this->node;
    if ($node === NULL) {
      return;
    }
    $node->setTitle((string) $form_state->getValue('title'));
    if ($form_state->getValue('status')) {
      $node->setPublished();
    }
    else {
      $node->setUnpublished();
    }

    $date = $form_state->getValue('date');
    if (is_string($date) && $date !== '') {
      $time_of_day = date('H:i:s', (int) $node->getCreatedTime());
      $timestamp = strtotime("$date $time_of_day");
      if ($timestamp !== FALSE) {
        $node->setCreatedTime($timestamp);
      }
    }

    $node->save();
    $this->messenger()->addStatus($this->t('"@title" updated.', ['@title' => $node->label()]));

    $form_state->setRedirectUrl(Url::fromUserInput($this->getRedirectDestination()->get()));
  }

  /**
   * AJAX submit callback: closes the modal and reloads the list table.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new OpenModalDialogCommand($this->t('Quick Edit'), $form, ['width' => 500]));
      return $response;
    }
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new RedirectCommand($this->getRedirectDestination()->get()));
    return $response;
  }

}
