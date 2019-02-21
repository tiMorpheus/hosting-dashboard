<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxySource
 *
 * @ORM\Table(name="proxy_source", uniqueConstraints={@ORM\UniqueConstraint(name="ip_UNIQUE", columns={"ip"})})
 * @ORM\Entity
 */
class ProxySource
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
   * @ORM\Column(name="region_id", type="integer", nullable=false)
   */
  private $regionId;

  /**
   * @var string
   *
   * @ORM\Column(name="ip", type="string", length=16, nullable=false)
   */
  private $ip;

  /**
   * @var string
   *
   * @ORM\Column(name="name", type="string", length=45, nullable=false)
   */
  private $name;

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="created", type="datetime", nullable=true)
   */
  private $created = 'CURRENT_TIMESTAMP';


}

