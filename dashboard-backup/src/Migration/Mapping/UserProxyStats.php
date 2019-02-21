<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserProxyStats
 *
 * @ORM\Table(name="user_proxy_stats", uniqueConstraints={@ORM\UniqueConstraint(name="PROXY_USER", columns={"proxy_id", "user_id"})})
 * @ORM\Entity
 */
class UserProxyStats
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
  private $userType = 'BL';

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
   * @var integer
   *
   * @ORM\Column(name="times_assigned", type="integer", nullable=false)
   */
  private $timesAssigned = '0';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="last_assigned", type="datetime", nullable=false)
   */
  private $lastAssigned = 'CURRENT_TIMESTAMP';


}

