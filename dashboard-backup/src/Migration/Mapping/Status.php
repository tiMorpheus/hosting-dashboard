<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * Status
 *
 * @ORM\Table(name="status")
 * @ORM\Entity
 */
class Status
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
   * @ORM\Column(name="process_name", type="string", length=50, nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $processName = '';

  /**
   * @var integer
   *
   * @ORM\Column(name="running", type="integer", nullable=true)
   */
  private $running;


}

