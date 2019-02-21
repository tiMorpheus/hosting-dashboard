<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ResellerUserPackages
 *
 * @ORM\Table(name="reseller_user_packages", uniqueConstraints={@ORM\UniqueConstraint(name="UNIQUE1", columns={"reseller_user_id", "country", "category"})})
 * @ORM\Entity
 */
class ResellerUserPackages
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
   * @var integer
   *
   * @ORM\Column(name="reseller_user_id", type="integer", nullable=false)
   */
  private $resellerUserId;

  /**
   * @var string
   *
   * @ORM\Column(name="country", type="string", length=2, nullable=true)
   */
  private $country;

  /**
   * @var string
   *
   * @ORM\Column(name="category", type="string", length=50, nullable=true)
   */
  private $category;

  /**
   * @var integer
   *
   * @ORM\Column(name="count", type="integer", nullable=false)
   */
  private $count;

  /**
   * @var integer
   *
   * @ORM\Column(name="replacements", type="integer", nullable=false)
   */
  private $replacements;

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="rotation", type="datetime", nullable=true)
   */
  private $rotation;

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="created", type="datetime", nullable=false)
   */
  private $created = '0000-00-00 00:00:00';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="updated", type="datetime", nullable=false)
   */
  private $updated = 'CURRENT_TIMESTAMP';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="expiration", type="datetime", nullable=true)
   */
  private $expiration;


}

