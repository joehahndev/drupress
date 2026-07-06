<?php

declare(strict_types=1);

namespace Drupal\drupress_dashboard\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupress\DrupressMapping;
use Drupal\filter\FilterFormatRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * WordPress' Quick Draft: title + content, saved as an unpublished post.
 *
 * Pure standard node API — the draft is an ordinary unpublished node of the
 * mapped post type, indistinguishable from one created on the full form.
 */
final class QuickDraftForm extends FormBase {

  /**
   * The Drupress mapping service.
   */
  protected DrupressMapping $mapping;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The filter format repository.
   */
  protected FilterFormatRepositoryInterface $filterFormatRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->mapping = $container->get('drupress.mapping');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->filterFormatRepository = $container->get(FilterFormatRepositoryInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupress_quick_draft';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#attributes' => ['placeholder' => $this->t('Title')],
    ];
    $form['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#rows' => 4,
      '#attributes' => ['placeholder' => $this->t("What's on your mind?")],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Draft'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $post_type = $this->mapping->postType();
    if ($post_type === NULL) {
      $this->messenger()->addError($this->t('No "Posts" node type is mapped.'));
      return;
    }
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => $post_type,
      'title' => $form_state->getValue('title'),
      'body' => [
        'value' => $form_state->getValue('content'),
        'format' => $this->filterFormatRepository->getDefaultFormat()->id(),
      ],
      'status' => 0,
    ]);
    $node->save();
    $this->messenger()->addStatus($this->t('Draft "@title" saved. <a href=":url">Edit it now</a>.', [
      '@title' => $node->label(),
      ':url' => $node->toUrl('edit-form')->toString(),
    ]));
  }

}
