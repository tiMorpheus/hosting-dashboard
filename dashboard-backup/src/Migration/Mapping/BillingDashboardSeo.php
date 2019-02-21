<?php

namespace Migration\Mapping;

use Doctrine\ORM\Mapping as ORM;

/**
 * BillingDashboardSeo
 *
 * @ORM\Table(name="billing_dashboard_seo")
 * @ORM\Entity
 */
class BillingDashboardSeo
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
   * @ORM\Column(name="url", type="string", length=64, nullable=false)
   */
  private $url;

  /**
   * @var string
   *
   * @ORM\Column(name="title", type="string", length=128, nullable=true)
   */
  private $title;

  /**
   * @var string
   *
   * @ORM\Column(name="description", type="text", length=16777215, nullable=true)
   */
  private $description;

  /**
   * @var string
   *
   * @ORM\Column(name="meta_keywords", type="text", length=65535, nullable=true)
   */
  private $metaKeywords;

  /**
   * @var string
   *
   * @ORM\Column(name="meta_description", type="text", length=16777215, nullable=true)
   */
  private $metaDescription;


}

