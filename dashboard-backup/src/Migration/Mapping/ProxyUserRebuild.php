<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxyUserRebuild
 *
 * @ORM\Table(name="proxy_user_rebuild")
 * @ORM\Entity
 */
class ProxyUserRebuild
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
   * @ORM\Column(name="user_id", type="integer", nullable=true)
   */
  private $userId;

  /**
   * @var integer
   *
   * @ORM\Column(name="server_id", type="integer", nullable=true)
   */
  private $serverId;


}

