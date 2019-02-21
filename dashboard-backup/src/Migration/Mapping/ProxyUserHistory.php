<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxyUserHistory
 *
 * @ORM\Table(name="proxy_user_history", indexes={@ORM\Index(name="USER_TYPE_USER", columns={"user_type", "user_id", "proxy_id"})})
 * @ORM\Entity
 */
class ProxyUserHistory
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
   * @ORM\Column(name="user_type", type="string", length=2, nullable=false)
   */
  private $userType;

  /**
   * @var integer
   *
   * @ORM\Column(name="user_id", type="integer", nullable=false)
   */
  private $userId;

  /**
   * @var integer
   *
   * @ORM\Column(name="proxy_id", type="integer", nullable=false)
   */
  private $proxyId;

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="created", type="datetime", nullable=false)
   */
  private $created = 'CURRENT_TIMESTAMP';


}

