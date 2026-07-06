<?php

declare(strict_types=1);

namespace Drupal\drupress\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupress\DrupressStructureInstaller;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the Drupress content model mapping.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The structure installer.
   */
  protected DrupressStructureInstaller $structureInstaller;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->structureInstaller = $container->get('drupress.structure_installer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupress_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['drupress.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('drupress.settings');

    $form['mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Content model mapping'),
      '#open' => TRUE,
      '#description' => $this->t('Drupress maps WordPress concepts onto the structures this site already has. When a mapped item does not exist, the corresponding Drupress UI is hidden — nothing breaks and no data is ever created or removed by changing these values.'),
    ];
    $form['mapping']['post_node_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"Posts" node type'),
      '#default_value' => $config->get('post_node_type'),
      '#description' => $this->t('Machine name of the node type presented as WordPress Posts (default: article).'),
    ];
    $form['mapping']['page_node_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"Pages" node type'),
      '#default_value' => $config->get('page_node_type'),
      '#description' => $this->t('Machine name of the node type presented as WordPress Pages (default: page).'),
    ];
    $form['mapping']['category_vocabulary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"Categories" vocabulary'),
      '#default_value' => $config->get('category_vocabulary'),
    ];
    $form['mapping']['tag_vocabulary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"Tags" vocabulary'),
      '#default_value' => $config->get('tag_vocabulary'),
    ];
    $form['mapping']['featured_image_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"Featured image" field'),
      '#default_value' => $config->get('featured_image_field'),
      '#description' => $this->t('Machine name of a media or image field on the Posts node type.'),
    ];

    $form['behavior'] = [
      '#type' => 'details',
      '#title' => $this->t('Behavior'),
      '#open' => TRUE,
    ];
    $form['behavior']['login_redirect_dashboard'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect to the dashboard after login'),
      '#default_value' => $config->get('login_redirect_dashboard'),
      '#description' => $this->t('Applies only while the Drupress Dashboard sub-module is enabled.'),
    ];

    $form['structures'] = [
      '#type' => 'details',
      '#title' => $this->t('Content structures'),
      '#open' => TRUE,
      '#description' => $this->t('Create the mapped vocabularies and fields as site-owned configuration, for sites installed without the Drupress recipe. Only missing items are created; existing content is never touched, and everything created here survives a later Drupress uninstall.'),
    ];
    $form['structures']['create_structures'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create missing structures'),
      '#submit' => ['::createStructures'],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler: creates any missing mapped structures.
   */
  public function createStructures(array &$form, FormStateInterface $form_state): void {
    $created = $this->structureInstaller->createMissing();
    if ($created === []) {
      $this->messenger()->addStatus($this->t('All mapped structures already exist. Nothing to create.'));
      return;
    }
    foreach ($created as $message) {
      $this->messenger()->addStatus($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('drupress.settings')
      ->set('post_node_type', $form_state->getValue('post_node_type'))
      ->set('page_node_type', $form_state->getValue('page_node_type'))
      ->set('category_vocabulary', $form_state->getValue('category_vocabulary'))
      ->set('tag_vocabulary', $form_state->getValue('tag_vocabulary'))
      ->set('featured_image_field', $form_state->getValue('featured_image_field'))
      ->set('login_redirect_dashboard', (bool) $form_state->getValue('login_redirect_dashboard'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
