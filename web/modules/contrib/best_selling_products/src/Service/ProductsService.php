<?php

namespace Drupal\best_selling_products\Service;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mysql\Driver\Database\mysql\Connection;

/**
 * Class ProductsService.
 */
class ProductsService implements ProductsServiceInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\mysql\Driver\Database\mysql\Connectionn definition.
   *
   * @var \Drupal\mysql\Driver\Database\mysql\Connection
   */
  protected $database;

  /**
   * Constructs a new ProductService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function bestSellingProducts($number_of_products, $bundle, $strict_sequences) {
    $query = $this->database->select('commerce_order', 'co')
      ->fields('pvfd', ['product_id']);
    $count_field = $query->addExpression('COUNT(pvfd.product_id)', 'count');
    $query->leftJoin('commerce_order_item', 'coi', 'co.order_id=coi.order_id');
    $query->leftJoin('commerce_product_variation_field_data', 'pvfd', 'coi.purchased_entity=pvfd.variation_id');
    $query->condition('co.state', 'completed');
    $query->isNotNull('pvfd.product_id');
    $query->groupBy('pvfd.product_id');
    $query->orderBy($count_field, 'DESC');
    if ($strict_sequences) {
      $query->orderBy('pvfd.product_id', 'DESC');
    }
    $query->addTag('best_selling_products');
    $product_ids = $query->execute()->fetchCol();

    $products = $this->isPublishedProducts($product_ids, $number_of_products, $bundle);

    return $products;
  }

  /**
   * Get published products.
   */
  private function isPublishedProducts($product_ids, $number_of_products, $bundle) {
    $products = [];
    foreach ($product_ids as $product_id) {
      /** @var \Drupal\commerce_order\Entity\Order $order */
      $product = $this->entityTypeManager->getStorage('commerce_product')
        ->load($product_id);

      if ($product instanceof ProductInterface && $product->isPublished() && ($product->bundle() === $bundle || $bundle === 'all')) {
        $products[$product->id()] = $product;
      }
      if (count($products) == $number_of_products) {
        break;
      }
    }
    return $products;
  }

}
