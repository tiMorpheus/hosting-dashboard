<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxyRegions
 *
 * @ORM\Table(name="proxy_regions")
 * @ORM\Entity
 */
class ProxyRegions
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
   * @ORM\Column(name="country", type="string", length=4, nullable=true)
   */
  private $country = 'us';

  /**
   * @var string
   *
   * @ORM\Column(name="region", type="string", length=255, nullable=false)
   */
  private $region;

  /**
   * @var string
   *
   * @ORM\Column(name="state", type="string", length=45, nullable=true)
   */
  private $state;


}

