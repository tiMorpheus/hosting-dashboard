<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxiesIpv4
 *
 * @ORM\Table(name="proxies_ipv4", uniqueConstraints={@ORM\UniqueConstraint(name="id_index", columns={"id"}), @ORM\UniqueConstraint(name="ip_port_index", columns={"ip", "port"})}, indexes={@ORM\Index(name="active_index", columns={"active", "dead"}), @ORM\Index(name="region", columns={"region_id"}), @ORM\Index(name="active_index2", columns={"active", "dead", "static", "ip"})})
 * @ORM\Entity
 */
class ProxiesIpv4
{
  /**
   * @var integer
   *
   * @ORM\Column(name="id", type="bigint", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var integer
   *
   * @ORM\Column(name="source_id", type="integer", nullable=true)
   */
  private $sourceId;

  /**
   * @var integer
   *
   * @ORM\Column(name="region_id", type="integer", nullable=true)
   */
  private $regionId = '1';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="date_added", type="datetime", nullable=true)
   */
  private $dateAdded = '0000-00-00 00:00:00';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="date_updated", type="datetime", nullable=true)
   */
  private $dateUpdated;

  /**
   * @var integer
   *
   * @ORM\Column(name="active", type="integer", nullable=false)
   */
  private $active = '1';

  /**
   * @var boolean
   *
   * @ORM\Column(name="new", type="boolean", nullable=false)
   */
  private $new = '0';

  /**
   * @var boolean
   *
   * @ORM\Column(name="pcheck", type="boolean", nullable=false)
   */
  private $pcheck = '0';

  /**
   * @var string
   *
   * @ORM\Column(name="ip", type="string", length=16, nullable=false)
   */
  private $ip;

  /**
   * @var integer
   *
   * @ORM\Column(name="port", type="integer", nullable=true)
   */
  private $port;

  /**
   * @var string
   *
   * @ORM\Column(name="mask", type="string", length=45, nullable=true)
   */
  private $mask;

  /**
   * @var integer
   *
   * @ORM\Column(name="dead", type="integer", nullable=true)
   */
  private $dead = '0';

  /**
   * @var integer
   *
   * @ORM\Column(name="dead_count", type="integer", nullable=true)
   */
  private $deadCount = '0';

  /**
   * @var integer
   *
   * @ORM\Column(name="dead_total_count", type="integer", nullable=false)
   */
  private $deadTotalCount = '0';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="dead_date", type="datetime", nullable=true)
   */
  private $deadDate;

  /**
   * @var string
   *
   * @ORM\Column(name="pools", type="string", length=255, nullable=true)
   */
  private $pools;

  /**
   * @var integer
   *
   * @ORM\Column(name="static", type="integer", nullable=true)
   */
  private $static;

  /**
   * @var boolean
   *
   * @ORM\Column(name="pristine", type="boolean", nullable=true)
   */
  private $pristine = '0';

  /**
   * @var string
   *
   * @ORM\Column(name="country", type="string", length=4, nullable=true)
   */
  private $country;

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="last_check", type="datetime", nullable=true)
   */
  private $lastCheck;

  /**
   * @var string
   *
   * @ORM\Column(name="last_error", type="string", length=255, nullable=true)
   */
  private $lastError;

  /**
   * @var string
   *
   * @ORM\Column(name="source", type="string", length=50, nullable=true)
   */
  private $source;

  /**
   * @var integer
   *
   * @ORM\Column(name="times_assigned", type="integer", nullable=false)
   */
  private $timesAssigned = '0';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="last_used", type="datetime", nullable=true)
   */
  private $lastUsed;

  /**
   * @var string
   *
   * @ORM\Column(name="block", type="string", length=50, nullable=true)
   */
  private $block;

  /**
   * @var string
   *
   * @ORM\Column(name="host", type="string", length=50, nullable=true)
   */
  private $host;

  /**
   * @var string
   *
   * @ORM\Column(name="organization", type="string", length=50, nullable=true)
   */
  private $organization;

  /**
   * @var string
   *
   * @ORM\Column(name="host_loc", type="string", length=50, nullable=true)
   */
  private $hostLoc;

  /**
   * @var string
   *
   * @ORM\Column(name="ip_country_code", type="string", length=5, nullable=true)
   */
  private $ipCountryCode;

  /**
   * @var string
   *
   * @ORM\Column(name="ip_region_code", type="string", length=5, nullable=true)
   */
  private $ipRegionCode;

  /**
   * @var string
   *
   * @ORM\Column(name="ip_city", type="string", length=50, nullable=true)
   */
  private $ipCity;

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="last_ip_check", type="datetime", nullable=true)
   */
  private $lastIpCheck;


}

