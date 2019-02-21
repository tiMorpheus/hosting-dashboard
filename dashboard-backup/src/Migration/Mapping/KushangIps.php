<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * KushangIps
 *
 * @ORM\Table(name="kushang_ips", uniqueConstraints={@ORM\UniqueConstraint(name="ip_UNIQUE", columns={"ip"})})
 * @ORM\Entity
 */
class KushangIps
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
   * @ORM\Column(name="ip", type="string", length=16, nullable=true)
   */
  private $ip;


}

