<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * Version
 *
 * @ORM\Table(name="version")
 * @ORM\Entity
 */
class Version
{
  /**
   * @var string
   *
   * @ORM\Column(name="version", type="string", length=255, nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $version;


}

