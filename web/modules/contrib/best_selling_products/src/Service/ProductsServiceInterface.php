<?php

namespace Drupal\best_selling_products\Service;

/**
 * Interface ProductsServiceInterface.
 */
interface ProductsServiceInterface {

  /**
   * Best selling products.
   *
   * @param int $number_of_products
   *   Number of products.
   *
   * @param string $bundle
   *   Bundle of the products to show.
   *
   * @param bool $strict_sequences
   *   Strict sequences products.
   *
   * @return array
   *   List of entities.
   */
  public function bestSellingProducts($number_of_products, $bundle, $strict_sequences);

}
