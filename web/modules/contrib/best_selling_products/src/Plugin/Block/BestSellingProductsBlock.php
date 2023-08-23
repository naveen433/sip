<?php

namespace Drupal\best_selling_products\Plugin\Block;

use Drupal\best_selling_products\Service\ProductsServiceInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Best selling products block.
 *
 * @Block(
 *   id = "best_selling_products_block",
 *   admin_label = @Translation("Best selling products block"),
 *
 * )
 */
class BestSellingProductsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Default number of products.
   */
  const DEFAULT_NUMBER_OF_PRODUCTS = 5;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The products service.
   *
   * @var \Drupal\best_selling_products\Service\ProductsServiceInterface
   */
  protected $productsService;

  /**
   * Constructs a BestSellingProductsBlock class.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityDisplayRepositoryInterface $entity_display_repository,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    ProductsServiceInterface $products_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->productsService = $products_service;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'number_of_products' => self::DEFAULT_NUMBER_OF_PRODUCTS,
      'max_age' => 86400,
      'view_mode' => 'teaser',
      'bundle' => 'all',
      'strict_sequences' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('best_selling_products.products')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $number_of_products = $this->configuration['number_of_products'];
    $bundle = $this->configuration['bundle'];
    $strict_sequences = $this->configuration['strict_sequences'];

    $products = $this->productsService->bestSellingProducts($number_of_products, $bundle, $strict_sequences);

    if ($products) {
      return [
        '#theme' => 'best_selling_products',
        '#products' => $this->getRenderedProducts($products, $this->configuration['view_mode']),
        '#cache' => [
          'max-age' => $this->configuration['max_age'],
        ],
      ];
    }
    else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $view_modes = $this->entityDisplayRepository->getViewModes('commerce_product');
    $product_bundles = $this->entityTypeBundleInfo->getBundleInfo('commerce_product');

    if (!empty($view_modes)) {
      $modes = [];
      foreach ($view_modes as $key => $view_mode) {
        $modes[$key] = $view_mode['label'];
      }

      $form['view_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Select view mode'),
        '#options' => $modes,
        '#default_value' => $config['view_mode'] ?? '',
      ];
    }

    if(!empty($product_bundles)){
      $bundles = ["all" => $this->t('All')];
      foreach ($product_bundles as $key => $product_bundle) {
        $bundles[$key] = $product_bundle['label'];
      }

      $form['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Select product bundle'),
        '#options' => $bundles,
        '#default_value' => $config['bundle'] ?? '',
      ];
    }

    $form['number_of_products'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of products to show'),
      '#required' => TRUE,
      '#default_value' => $config['number_of_products'],
    ];

    $form['max_age'] = [
      '#title' => $this->t('Cache'),
      '#type' => 'select',
      '#options' => [
        '0' => 'No Caching',
        '1800' => '30 Minutes',
        '3600' => '1 Hour',
        '21600' => '6 Hours',
        '43200' => '12 Hours',
        '86400' => '1 Day',
        '172800' => '2 Days',
        '432000' => '5 Days',
        '604800' => '1 Week',
        '-1' => 'Permanent',
      ],
      '#default_value' => isset($this->configuration['max_age']) ? $this->configuration['max_age'] : '86400',
      '#description' => $this->t('Set the max age the block is allowed to be cached for.'),
    ];

    $form['strict_sequences'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strict sequences of products'),
      '#default_value' => isset($this->configuration['strict_sequences']) ? $this->configuration['strict_sequences'] : FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $values = $form_state->getValues();
    $this->configuration['number_of_products'] = $values['number_of_products'];
    $this->configuration['view_mode'] = $values['view_mode'] ?? '';
    $this->configuration['max_age'] = $values['max_age'];
    $this->configuration['bundle'] = $values['bundle'];
    $this->configuration['strict_sequences'] = $values['strict_sequences'];
  }

  /**
   * Gets random products and renders them in the selected view mode.
   *
   * @param array $products
   *   An array of product entities.
   * @param string $view_mode
   *   The view mode that is used for rendering the products.
   *
   * @return array
   *   An array of rendered products.
   */
  public function getRenderedProducts(array $products, $view_mode) {
    $view_builder = $this->entityTypeManager
      ->getViewBuilder('commerce_product');

    $index = 1;
    $result = [];
    foreach ($products as $product) {
      $classes = [
        'best-selling-product',
        'best-selling-product-' . $index,
      ];
      $key = 'best_selling_product_' . $index;

      $result[] = [
        '#prefix' => '<div class="' . implode(' ', $classes) . '">',
        '#suffix' => '</div>',

        $key => !empty($view_mode) ? $view_builder->view($product, $view_mode) : $view_builder->view($product),
      ];

      $index++;
    }

    return $result;
  }

}
