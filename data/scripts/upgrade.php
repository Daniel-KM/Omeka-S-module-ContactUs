<?php declare(strict_types=1);

namespace ContactUs;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\View\Helper\Url $url
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $services->get('ViewHelperManager')->get('url');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.55')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.55'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

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
    try {
        foreach (explode(";\n", $sqls) as $sql) {
            $connection->executeStatement($sql);
        }
    } catch (\Exception $e) {
        // Already installed.
    }
}

if (version_compare($oldVersion, '3.3.8.4', '<')) {
    $settings->delete('contactus_html');
    $siteSettings = $services->get('Omeka\Settings\Site');
    $ids = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    foreach ($ids as $id) {
        $siteSettings->setTargetId($id);
        $siteSettings->set('contactus_notify_body', $config['contactus']['site_settings']['contactus_notify_body']);
        $siteSettings->set('contactus_notify_subject', $siteSettings->get('contactus_subject'));
        $siteSettings->delete('contactus_subject');
    }

    // Just to hide the data. Will be removed when the page will be resaved.
    $sql = <<<'SQL'
UPDATE site_page_block
SET
    data = REPLACE(data, '"notify_recipients":', '"_old_notify_recipients":')
WHERE layout = "contactUs";
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.8.5', '<')) {
    $message = new PsrMessage(
        'A checkbox for consent has been added to the user form. You may update the default label in site settings' // @translate
    );
    $messenger->addNotice($message);

    $siteSettings = $services->get('Omeka\Settings\Site');
    $ids = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    foreach ($ids as $id) {
        $siteSettings->setTargetId($id);
        $siteSettings->delete('contactus_newsletter');
        $siteSettings->delete('contactus_newsletter_label');
        $siteSettings->delete('contactus_attach_file');
        $siteSettings->set('contactus_consent_label', $config['contactus']['site_settings']['contactus_consent_label']);
    }

    $sql = <<<'SQL'
UPDATE site_page_block
SET
    data = REPLACE(
        data,
        '"confirmation_enabled":',
        '"consent_label":"I allow the site owner to store my name and my email to answer to this message.","confirmation_enabled":'
    )
WHERE layout = "contactUs";
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.8.7', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `contact_message`
    DROP FOREIGN KEY FK_2C9211FE7E3C61F9;
ALTER TABLE `contact_message`
    CHANGE `owner_id` `owner_id` INT DEFAULT NULL,
    CHANGE `resource_id` `resource_id` INT DEFAULT NULL,
    CHANGE `site_id` `site_id` INT DEFAULT NULL,
    CHANGE `name` `name` VARCHAR(190) DEFAULT NULL,
    CHANGE `media_type` `media_type` VARCHAR(190) DEFAULT NULL,
    CHANGE `storage_id` `storage_id` VARCHAR(190) DEFAULT NULL,
    CHANGE `extension` `extension` VARCHAR(190) DEFAULT NULL,
    CHANGE `request_url` `request_url` VARCHAR(1024) DEFAULT NULL COLLATE `latin1_bin`,
    CHANGE `user_agent` `user_agent` VARCHAR(1024) DEFAULT NULL,
    CHANGE `newsletter` `newsletter` TINYINT(1) DEFAULT NULL;
ALTER TABLE `contact_message`
    ADD CONSTRAINT FK_2C9211FE7E3C61F9 FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.3.8.8', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `contact_message`
    ADD `to_author` TINYINT(1) DEFAULT '0' NOT NULL AFTER `is_spam`,
    CHANGE `owner_id` `owner_id` INT DEFAULT NULL,
    CHANGE `resource_id` `resource_id` INT DEFAULT NULL,
    CHANGE `site_id` `site_id` INT DEFAULT NULL,
    CHANGE `name` `name` VARCHAR(190) DEFAULT NULL,
    CHANGE `media_type` `media_type` VARCHAR(190) DEFAULT NULL,
    CHANGE `storage_id` `storage_id` VARCHAR(190) DEFAULT NULL,
    CHANGE `extension` `extension` VARCHAR(190) DEFAULT NULL,
    CHANGE `request_url` `request_url` VARCHAR(1024) DEFAULT NULL COLLATE `latin1_bin`,
    CHANGE `user_agent` `user_agent` VARCHAR(1024) DEFAULT NULL,
    CHANGE `newsletter` `newsletter` TINYINT(1) DEFAULT NULL;
SQL;
    $connection->executeStatement($sql);

    $settings->set('contactus_to_author_subject', $config['contactus']['site_settings']['contactus_to_author_subject']);
    $settings->set('contactus_to_author_body', $config['contactus']['site_settings']['contactus_to_author_body']);

    $message = new PsrMessage(
        'It’s now possible to set a specific message when contacting author.' // @translate
    );
    $messenger->addSuccess($message);
    $message = new PsrMessage(
        'It’s now possible to contact authors of a resource via the view helper contactUs().' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.3.8.11', '<')) {
    $sql = <<<'SQL'
UPDATE `contact_message`
SET `resource_id` = SUBSTRING_INDEX(`request_url`, '/', -1)
WHERE `resource_id` IS NULL
    AND `request_url` IS NOT NULL 
    AND SUBSTRING_INDEX(`request_url`, '/', -1) REGEXP '^[0-9]+$'
;
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.8.13', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `contact_message`
    ADD `fields` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)' AFTER `body`
;
SQL;
    $connection->executeStatement($sql);

    $message = new PsrMessage(
        'It’s now possible to append specific fields to the form.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It’s now possible to add a contact form in item/show for themes supporting resource blocks.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.10', '<')) {
    $message = new PsrMessage(
        'It’s now possible to add a contact form in item/browse and to send a list of resource ids (need a line in theme).' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.11', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `contact_message`
    ADD `modified` DATETIME DEFAULT NULL AFTER `created`
;
SQL;
    $connection->executeStatement($sql);

    // Set modified for all old messages.
    $sql = <<<'SQL'
UPDATE `contact_message`
SET `modified` = `created`
WHERE `is_read` IS NOT NULL
    OR `is_spam` IS NOT NULL
;
SQL;
    $connection->executeStatement($sql);

    $settings->set('contactus_create_zip', $settings->get('contactus_zip') ?: '');
    $settings->delete('contactus_zip');
    $settings->set('contactus_delete_zip', 30);

    $message = new PsrMessage(
        'It’s now possible to prepare a zip file of asked files to send to a visitor via a link. See {link}settings{link_end}.', // @translate
        [
            'link' => sprintf('<a href="%s">', $url('admin/default', ['controller' => 'setting'], ['fragment' => 'contact'])),
            'link_end' => '</a>',
        ]
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}
