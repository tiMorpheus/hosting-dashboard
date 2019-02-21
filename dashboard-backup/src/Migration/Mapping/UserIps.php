<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserIps
 *
 * @ORM\Table(name="user_ips", uniqueConstraints={@ORM\UniqueConstraint(name="unique", columns={"user_id", "ip"})})
 * @ORM\Entity
 */
class UserIps
{
  /**
   * @var integer
   *
   * @ORM\Column(name="id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $id;

  /**
   * @var string
   *
   * @ORM\Column(name="ip", type="string", length=15, nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $ip = '';

  /**
   * @var integer
   *
   * @ORM\Column(name="user_id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $userId = '0';

  /**
   * @var string
   *
   * @ORM\Column(name="user_type", type="string", length=2, nullable=false)
   */
  private $userType = 'BL';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="date_created", type="datetime", nullable=false)
   */
  private $dateCreated = 'CURRENT_TIMESTAMP';


}

