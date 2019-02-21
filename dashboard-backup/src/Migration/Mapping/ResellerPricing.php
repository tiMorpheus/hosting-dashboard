<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ResellerPricing
 *
 * @ORM\Table(name="reseller_pricing")
 * @ORM\Entity
 */
class ResellerPricing
{
  /**
   * @var integer
   *
   * @ORM\Column(name="id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var string
   *
   * @ORM\Column(name="country", type="string", length=2, nullable=false)
   */
  private $country;

  /**
   * @var string
   *
   * @ORM\Column(name="category", type="string", length=45, nullable=false)
   */
  private $category;

  /**
   * @var integer
   *
   * @ORM\Column(name="min", type="integer", nullable=false)
   */
  private $min;

  /**
   * @var integer
   *
   * @ORM\Column(name="max", type="integer", nullable=false)
   */
  private $max;

  /**
   * @var string
   *
   * @ORM\Column(name="price", type="decimal", precision=10, scale=2, nullable=false)
   */
  private $price;


}

