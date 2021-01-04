<?php declare(strict_types=1);

namespace ContactUs;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$settings = $services->get('Omeka\Settings');
// $config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
// $entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
// $space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.3.8', '<')) {
    $settings->delete('contactus_html');
    $siteSettings = $services->get('Omeka\Settings\Site');
    $ids = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    foreach ($ids as $id) {
        $siteSettings->setTargetId($id);
        $siteSettings->delete('contactus_html');
    }
}

if (version_compare($oldVersion, '3.3.8.1', '<')) {
    $sqls = <<<'SQL'
CREATE TABLE `contact_message` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `owner_id` INT DEFAULT NULL,
    `resource_id` INT DEFAULT NULL,
    `site_id` INT DEFAULT NULL,
    `email` VARCHAR(190) NOT NULL,
    `name` VARCHAR(190) DEFAULT NULL,
    `subject` LONGTEXT DEFAULT NULL,
    `body` LONGTEXT NOT NULL,
    `source` LONGTEXT DEFAULT NULL,
    `media_type` VARCHAR(190) DEFAULT NULL,
    `storage_id` VARCHAR(190) DEFAULT NULL,
    `extension` VARCHAR(255) DEFAULT NULL,
    `request_url` VARCHAR(1024) DEFAULT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0 NOT NULL,
    `is_spam` TINYINT(1) DEFAULT 0 NOT NULL,
    `newsletter` TINYINT(1) DEFAULT NULL,
    `created` DATETIME NOT NULL,
    UNIQUE INDEX UNIQ_2C9211FE5CC5DB90 (`storage_id`),
    INDEX IDX_2C9211FE7E3C61F9 (`owner_id`),
    INDEX IDX_2C9211FE89329D25 (`resource_id`),
    INDEX IDX_2C9211FEF6BD1646 (`site_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE `contact_message` ADD CONSTRAINT FK_2C9211FE7E3C61F9 FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
ALTER TABLE `contact_message` ADD CONSTRAINT FK_2C9211FE89329D25 FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE SET NULL;
ALTER TABLE `contact_message` ADD CONSTRAINT FK_2C9211FEF6BD1646 FOREIGN KEY (`site_id`) REFERENCES `site` (`id`) ON DELETE SET NULL;
SQL;
    foreach (explode(";\n", $sqls) as $sql) {
        $connection->exec($sql);
    }
}
