<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxyUserPackages
 *
 * @ORM\Table(name="proxy_user_packages", uniqueConstraints={@ORM\UniqueConstraint(name="UNIQUE_COUNTRY", columns={"user_id", "country", "category"})})
 * @ORM\Entity
 */
class ProxyUserPackages
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
   * @ORM\Column(name="user_id", type="integer", nullable=false)
   */
  private $userId;

  /**
   * @var integer
   *
   * @ORM\Column(name="package_id", type="integer", nullable=false)
   */
  private $packageId;

  /**
   * @var array
   *
   * @ORM\Column(name="source", type="simple_array", nullable=false)
   */
  private $source = 'unknown';

  /**
   * @var string
   *
   * @ORM\Column(name="country", type="string", length=4, nullable=true)
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
   * @ORM\Column(name="replacements", type="integer", nullable=false)
   */
  private $replacements = '0';

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
  private $created = 'CURRENT_TIMESTAMP';


}

