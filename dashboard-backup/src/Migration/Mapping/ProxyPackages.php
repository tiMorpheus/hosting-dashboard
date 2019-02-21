<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxyPackages
 *
 * @ORM\Table(name="proxy_packages", indexes={@ORM\Index(name="type", columns={"type"})})
 * @ORM\Entity
 */
class ProxyPackages
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
   * @ORM\Column(name="name", type="string", length=100, nullable=true)
   */
  private $name;

  /**
   * @var string
   *
   * @ORM\Column(name="price", type="decimal", precision=7, scale=2, nullable=true)
   */
  private $price;

  /**
   * @var integer
   *
   * @ORM\Column(name="ports", type="integer", nullable=true)
   */
  private $ports = '0';

  /**
   * @var integer
   *
   * @ORM\Column(name="threads", type="integer", nullable=true)
   */
  private $threads = '10';

  /**
   * @var string
   *
   * @ORM\Column(name="country", type="string", length=4, nullable=true)
   */
  private $country = 'us';

  /**
   * @var string
   *
   * @ORM\Column(name="category", type="string", length=50, nullable=true)
   */
  private $category = 'rotate';

  /**
   * @var string
   *
   * @ORM\Column(name="type", type="string", length=50, nullable=true)
   */
  private $type = 'standard';


}

