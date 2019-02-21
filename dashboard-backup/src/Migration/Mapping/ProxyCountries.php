<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProxyCountries
 *
 * @ORM\Table(name="proxy_countries", uniqueConstraints={@ORM\UniqueConstraint(name="code_UNIQUE", columns={"code"}), @ORM\UniqueConstraint(name="name_UNIQUE", columns={"name"})})
 * @ORM\Entity
 */
class ProxyCountries
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
   * @ORM\Column(name="code", type="string", length=2, nullable=false)
   */
  private $code;

  /**
   * @var string
   *
   * @ORM\Column(name="name", type="string", length=255, nullable=false)
   */
  private $name;


}

