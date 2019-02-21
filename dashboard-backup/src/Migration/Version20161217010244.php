<?php

namespace Migration;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161217010244 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `proxy_users` ADD `whmcs_id` INT DEFAULT NULL AFTER `email`;');
        $this->addSql('ALTER TABLE `proxy_users` ADD UNIQUE(`whmcs_id`);');
        $this->addSql('CREATE TABLE `whmcs_products` (
              `id` int(11) NOT NULL,
              `country` varchar(2) COLLATE utf8_bin NOT NULL,
              `category` varchar(50) COLLATE utf8_bin NOT NULL,
              `product_id` int(11) NOT NULL,
              `configuration_id` int(11) DEFAULT NULL,
              `min` int(11) NOT NULL,
              `price` decimal(4,2) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
        $this->addSql('ALTER TABLE `whmcs_products` ADD PRIMARY KEY(`id`)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `proxy_users` DROP `whmcs_id`');
        $this->addSql('DROP TABLE `whmcs_products`');
    }
}
