<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxyPools
 *
 * @ORM\Table(name="proxy_pools")
 * @ORM\Entity
 */
class ProxyPools
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
   * @ORM\Column(name="name", type="string", length=45, nullable=true)
   */
  private $name;


}

