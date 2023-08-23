<?php

namespace Drupal\eca_commerce\Plugin\ECA\Condition;

use Drupal\commerce\ConditionManagerInterface;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide all Commerce condition plugins as ECA conditions.
 *
 * @EcaCondition(
 *   id = "eca_commerce_commerce",
 *   deriver = "Drupal\eca_commerce\Plugin\ECA\Condition\CommerceDeriver"
 * )
 */
class Commerce extends ConditionBase {

  /**
   * The commerce condition plugin manager.
   *
   * @var \Drupal\commerce\ConditionManagerInterface
   */
  protected ConditionManagerInterface $commerceConditionManager;

  /**
   * The commerce condition plugin.
   *
   * @var \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface
   */
  protected ConditionInterface $commerceConditionPlugin;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Commerce {
    /** @var \Drupal\eca_commerce\Plugin\ECA\Condition\Commerce $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->commerceConditionManager = $container->get('plugin.manager.commerce_condition');
    return $instance;
  }

  /**
   * Return the condition plugin after it has been fully configured.
   *
   * @return \Drupal\commerce\Plugin\Commerce\Condition\ConditionInterface
   *   This commerce condition plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function commerceConditionPlugin(): ConditionInterface {
    if (!isset($this->commerceConditionPlugin)) {
      /* @noinspection PhpFieldAssignmentTypeMismatchInspection */
      $this->commerceConditionPlugin = $this->commerceConditionManager->createInstance($this->pluginDefinition['commerce_plugin']);
      $this->commerceConditionPlugin->setConfiguration($this->configuration);
    }
    return $this->commerceConditionPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $result = $this->commerceConditionPlugin()->evaluate($this->getContext('entity')->getContextValue());
    return $this->negationCheck($result);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    if (!isset($this->commerceConditionManager)) {
      return parent::defaultConfiguration();
    }
    try {
      $pluginDefault = $this->commerceConditionPlugin()->defaultConfiguration();
    }
    catch (PluginException $e) {
      $pluginDefault = [];
    }
    return $pluginDefault + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    try {
      return $this->commerceConditionPlugin()->buildConfigurationForm($form, $form_state);
    }
    catch (PluginException $e) {
      // @todo Do we need to log this?
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    try {
      $this->commerceConditionPlugin()->validateConfigurationForm($form, $form_state);
    }
    catch (PluginException $e) {
      // @todo Do we need to log this?
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    try {
      /** @var \Drupal\Core\Form\SubformStateInterface $form_state */
      $this->commerceConditionPlugin()->submitConfigurationForm($form, $form_state);
    }
    catch (PluginException $e) {
      // @todo Do we need to log this?
    }
    if (!empty($this->commerceConditionPlugin()->defaultConfiguration())) {
      $this->configuration = $this->commerceConditionPlugin()->getConfiguration() + $this->configuration;
    }
  }

}
