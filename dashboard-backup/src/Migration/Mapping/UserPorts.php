<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserPorts
 *
 * @ORM\Table(name="user_ports", indexes={@ORM\Index(name="index1", columns={"proxy_ip"}), @ORM\Index(name="ports_by_userid", columns={"user_id"}), @ORM\Index(name="type", columns={"type"}), @ORM\Index(name="COUNTRY_CATEGORY", columns={"country", "category"}), @ORM\Index(name="SERVER_ID", columns={"server_id", "server_id_alt", "category"}), @ORM\Index(name="USER_COUNTRY_CATEGORY", columns={"user_type", "user_id", "country", "category"}), @ORM\Index(name="CATEGORY", columns={"category", "user_id"})})
 * @ORM\Entity
 */
class UserPorts
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
  private $userId = '0';

  /**
   * @var integer
   *
   * @ORM\Column(name="region_id", type="integer", nullable=true)
   */
  private $regionId = '0';

  /**
   * @var integer
   *
   * @ORM\Column(name="server_id", type="integer", nullable=false)
   */
  private $serverId = '0';

  /**
   * @var integer
   *
   * @ORM\Column(name="server_id_alt", type="integer", nullable=true)
   */
  private $serverIdAlt;

  /**
   * @var integer
   *
   * @ORM\Column(name="port", type="integer", nullable=false)
   */
  private $port = '0';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="time_assigned", type="datetime", nullable=false)
   */
  private $timeAssigned = '0000-00-00 00:00:00';

  /**
   * @var integer
   *
   * @ORM\Column(name="rotation_time", type="integer", nullable=false)
   */
  private $rotationTime = '0';

  /**
   * @var integer
   *
   * @ORM\Column(name="proxy_ip", type="bigint", nullable=false)
   */
  private $proxyIp = '0';

  /**
   * @var integer
   *
   * @ORM\Column(name="previous_proxy_ip", type="integer", nullable=true)
   */
  private $previousProxyIp = '0';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="last_rotated", type="datetime", nullable=true)
   */
  private $lastRotated;

  /**
   * @var string
   *
   * @ORM\Column(name="type", type="string", length=50, nullable=true)
   */
  private $type = 'standard';

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
   * @var boolean
   *
   * @ORM\Column(name="pending_replace", type="boolean", nullable=true)
   */
  private $pendingReplace = '0';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="time_updated", type="datetime", nullable=true)
   */
  private $timeUpdated = 'CURRENT_TIMESTAMP';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="rs_charge_date", type="datetime", nullable=true)
   */
  private $rsChargeDate;

  /**
   * @var boolean
   *
   * @ORM\Column(name="rs_active", type="boolean", nullable=true)
   */
  private $rsActive;


}

