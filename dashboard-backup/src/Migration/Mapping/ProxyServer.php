<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxyServer
 *
 * @ORM\Table(name="proxy_server")
 * @ORM\Entity
 */
class ProxyServer
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
   * @ORM\Column(name="server_ip", type="string", length=16, nullable=true)
   */
  private $serverIp;

  /**
   * @var boolean
   *
   * @ORM\Column(name="active", type="boolean", nullable=true)
   */
  private $active;


}

