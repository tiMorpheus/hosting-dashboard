<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ResellerUsers
 *
 * @ORM\Table(name="reseller_users")
 * @ORM\Entity
 */
class ResellerUsers
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
   * @ORM\Column(name="reseller_id", type="integer", nullable=false)
   */
  private $resellerId;

  /**
   * @var string
   *
   * @ORM\Column(name="username", type="string", length=255, nullable=true)
   */
  private $username;

  /**
   * @var string
   *
   * @ORM\Column(name="auth_type", type="string", length=2, nullable=true)
   */
  private $authType;

  /**
   * @var boolean
   *
   * @ORM\Column(name="rotate_30", type="boolean", nullable=true)
   */
  private $rotate30 = '0';

  /**
   * @var boolean
   *
   * @ORM\Column(name="rotate_ever", type="boolean", nullable=true)
   */
  private $rotateEver = '0';

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="rotate_limit", type="datetime", nullable=true)
   */
  private $rotateLimit;

  /**
   * @var string
   *
   * @ORM\Column(name="rotation_type", type="string", length=5, nullable=false)
   */
  private $rotationType = 'HTTP';

  /**
   * @var string
   *
   * @ORM\Column(name="api_key", type="string", length=50, nullable=true)
   */
  private $apiKey;

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="created", type="datetime", nullable=true)
   */
  private $created;

  /**
   * @var \DateTime
   *
   * @ORM\Column(name="updated", type="datetime", nullable=false)
   */
  private $updated = 'CURRENT_TIMESTAMP';


}

