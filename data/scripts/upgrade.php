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
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $services->get('ViewHelperManager')->get('url');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

$localConfig = require dirname(__DIR__, 2) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.90')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.90'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

$this->checkPhpVersion();

if (version_compare($oldVersion, '3.3.8', '<')) {
    $settings->delete('contactus_html');
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
    } catch (\Throwable $e) {
        // Already installed.
    }
}

if (version_compare($oldVersion, '3.3.8.4', '<')) {
    $settings->delete('contactus_html');
    $ids = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    foreach ($ids as $id) {
        $siteSettings->setTargetId($id);
        $siteSettings->set('contactus_notify_body', $localConfig['contactus']['site_settings']['contactus_notify_body']);
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

    $ids = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    foreach ($ids as $id) {
        $siteSettings->setTargetId($id);
        $siteSettings->delete('contactus_newsletter');
        $siteSettings->delete('contactus_newsletter_label');
        $siteSettings->delete('contactus_attach_file');
        $siteSettings->set('contactus_consent_label', $localConfig['contactus']['site_settings']['contactus_consent_label']);
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
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $sql) {
        $connection->executeStatement($sql);
    }
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

    $settings->set('contactus_author', $localConfig['contactus']['settings']['contactus_author']);

    $ids = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    foreach ($ids as $id) {
        $siteSettings->setTargetId($id);
        $siteSettings->set('contactus_to_author_subject', $localConfig['contactus']['site_settings']['contactus_to_author_subject']);
        $siteSettings->set('contactus_to_author_body', $localConfig['contactus']['site_settings']['contactus_to_author_body']);
    }

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
            'link' => sprintf('<a href="%s">', htmlspecialchars($url('admin/default', ['controller' => 'setting'], ['fragment' => 'contact']))),
            'link_end' => '</a>',
        ]
    );
    $message->setEscapeHtml(false);
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.13', '<')) {
    $settings->set('contactus_create_zip', $settings->get('contactus_create_zip', 'original') ?: 'original');
    $message = new PsrMessage(
        'A new button allows to create a zip for any contact.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.14', '<')) {
    $ids = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    foreach ($ids as $id) {
        $siteSettings->setTargetId($id);
        $siteSettings->set('contactus_append_resource_show', $localConfig['contactus']['site_settings']['contactus_append_resource_show']);
        $siteSettings->set('contactus_append_items_browse', $localConfig['contactus']['site_settings']['contactus_append_items_browse']);
    }
    $message = new PsrMessage(
        'Two new options allow to append the contact form to resource pages. They are disabled by default, so check them if you need them.' // @translate
    );
    $messenger->addWarning($message);

    $message = new PsrMessage(
        'A new option allows to use the user email to send message. It is not recommended because many emails providers reject them as spam. Use it only if you manage your own domain.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.15', '<')) {
    $ids = $api->search('sites', [], ['initialize' => false, 'returnScalar' => 'id'])->getContent();
    foreach ($ids as $id) {
        $siteSettings->setTargetId($id);
        $siteSettings->set('contactus_confirmation_newsletter_subject', $localConfig['contactus']['site_settings']['contactus_confirmation_newsletter_subject']);
        $siteSettings->set('contactus_confirmation_newsletter_body', $localConfig['contactus']['site_settings']['contactus_confirmation_newsletter_body']);
    }

    $message = new PsrMessage(
        'A new block allows to display a form to subscribe to a newsletter.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.16', '<')) {
    /**
     * Migrate blocks of this module to new blocks of Omeka S v4.1.
     *
     * Migrate templates.
     * Replace filled settting "heading" by a specific block "Heading" or "Html".
     *
     * @var \Laminas\Log\Logger $logger
     *
     * @see \Omeka\Db\Migrations\MigrateBlockLayoutData
     */

    // It is not possible to search for templates that use heading, because it
    // is used in many places. So only in doc block.
    // Warning: heading is no more used in blocks, but still usable in the view
    // helper.

    // Check themes that use "$heading" in block
    $strings = [
        'themes/*/view/common/block-layout/contact-us*' => [
            '* @var string $heading',
            'if ($options[\'heading\'])',
        ],
        'themes/*/view/common/block-template/contact-us*' => [
            '* @var string $heading',
            'if ($options[\'heading\'])',
        ],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($strings as $path => $strings) {
        $result = $manageModuleAndResources->checkStringsInFiles($strings, $path);
        if ($result) {
            $results[] = $result;
        }
    }
    if ($results) {
        $message = new PsrMessage(
            'The option "heading" was removed from blocks Contact Us and Newsletter and replaced by a block Heading (if module Block Plus is present) or Html. Remove it in the following files before upgrade and automatic conversion: {json}', // @translate
            ['json' => json_encode($results, 448)]
        );
        $logger->err($message->getMessage(), $message->getContext());
        throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
    }

    $pageRepository = $entityManager->getRepository(\Omeka\Entity\SitePage::class);

    $viewHelpers = $services->get('ViewHelperManager');
    $escape = $viewHelpers->get('escapeHtml');
    $hasBlockPlus = $this->isModuleActive('BlockPlus');

    $pagesUpdated = [];
    $pagesUpdated2 = [];
    foreach ($pageRepository->findAll() as $page) {
        $pageSlug = $page->getSlug();
        $siteSlug = $page->getSite()->getSlug();
        $position = 0;
        foreach ($page->getBlocks() as $block) {
            $block->setPosition(++$position);
            $layout = $block->getLayout();
            if ($layout !== 'contactUs' && $layout !== 'newsletter') {
                continue;
            }
            $data = $block->getData() ?: [];
            $layoutData = $block->getLayoutData() ?? [];

            // Migrate template.
            $template = $data['template'] ?? '';
            $layoutData = $block->getLayoutData() ?? [];
            $existingTemplateName = $layoutData['template_name'] ?? null;
            $templateName = pathinfo($template, PATHINFO_FILENAME);
            $templateCheck = $layout === 'newsletter' ? 'newsletter' : 'contact-us';
            if ($templateName
                && $templateName !== $templateCheck
                && (!$existingTemplateName || $existingTemplateName === $templateCheck)
            ) {
                $layoutData['template_name'] = $templateName;
                $pagesUpdated[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['template']);

            $heading = $data['heading'] ?? '';

            // Replace setting "heading".
            if (strlen($heading)) {
                $b = new \Omeka\Entity\SitePageBlock();
                $b->setPage($page);
                $b->setPosition(++$position);
                if ($hasBlockPlus) {
                    $b->setLayout('heading');
                    $b->setData([
                        'text' => $heading,
                        'level' => 3,
                    ]);
                } else {
                    $b->setLayout('html');
                    $b->setData([
                        'html' => '<h3>' . $escape($heading) . '</h3>',
                    ]);
                }
                $entityManager->persist($b);
                $block->setPosition(++$position);
                $pagesUpdated2[$siteSlug][$pageSlug] = $pageSlug;
            }
            unset($data['heading']);

            $block->setData($data);
            $block->setLayoutData($layoutData);
        }
    }

    $entityManager->flush();

    if ($pagesUpdated) {
        $result = array_map('array_values', $pagesUpdated);
        $message = new PsrMessage(
            'The setting "template" was moved to the new block layout settings available since Omeka S v4.1. You may check pages for styles: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }

    if ($pagesUpdated2) {
        $result = array_map('array_values', $pagesUpdated2);
        $message = new PsrMessage(
            'The option "heading" was removed from blocks Contact Us and Newsletter. New block "Heading" (if module Block Plus is present) or "Html" was prepended to all blocks that had a filled heading. You may check pages for styles: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $messenger->addWarning($message);
        $logger->warn($message->getMessage(), $message->getContext());
    }

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        if (!$siteSettings->get('contactus_confirmation_message')) {
            $siteSettings->set('contactus_confirmation_message', $localConfig['contactus']['site_settings']['contactus_confirmation_message']);
        }
        if (!$siteSettings->get('contactus_confirmation_message_newsletter')) {
            $siteSettings->set('contactus_confirmation_message_newsletter', $localConfig['contactus']['site_settings']['contactus_confirmation_message_newsletter']);
        }
        if ($siteSettings->get('contactus_selection_max') === null) {
            $siteSettings->set('contactus_selection_max', 25);
        }
    }

    $message = new PsrMessage(
        'New options were added to set message after posting mail.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to unsubscribe to a newsletter.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'A new page allows the user or the visitor to see all the selected resources.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'The number of selected items can be set in site settings. It is limited to 25 by default.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.19', '<')) {
    if (!$settings->get('contactus_author')) {
        $settings->set('contactus_author', $localConfig['contactus']['settings']['contactus_author']);
    }
}

if (version_compare($oldVersion, '3.4.21', '<')) {
    $message = new PsrMessage(
        'It is now possible to define specific fields for the contact us form via the main or site settings.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.23', '<')) {
    $message = new PsrMessage(
        'New fields were added for messages.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'Existing fields for messages were clarified. You may need to check your config.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.24', '<')) {
    $list = $settings->get('contactus_notify_recipients');
    $first = $list ? reset($list) : null;
    if ($first) {
        $settings->set('contactus_sender_email', $first);
    }

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $list = $siteSettings->get('contactus_notify_recipients');
        $first = $list ? reset($list) : null;
        if ($first) {
            $siteSettings->set('contactus_sender_email', $first);
        }
    }

    $message = new PsrMessage(
        'A new field allows to set the sender email. The first email of the list of emails for notification is no more used as sender. Check it if needed.' // @translate
    );
    $messenger->addWarning($message);

    $message = new PsrMessage(
        'Warning: there might be an issue with the option "Send with user email". So check it and try to send a message. Note that to send with user email is not recommended, unless you have a good smtp server.' // @translate
    );
    $messenger->addWarning($message);
}

if (version_compare($oldVersion, '3.4.26', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `contact_message`
            CHANGE `ip` `ip` VARCHAR(45) NOT NULL COLLATE `latin1_bin` AFTER `request_url`;
        SQL;
    $connection->executeStatement($sql);

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $value = $siteSettings->get('contactus_label_selection');
        if ($value !== null) {
            $siteSettings->set('contactus_selection_label', $value);
            $siteSettings->delete('contactus_label_selection');
        }
        $value = $siteSettings->get('contactus_label_guest_link');
        if ($value !== null) {
            $siteSettings->set('contactus_selection_label_guest_link', $value);
            $siteSettings->delete('contactus_label_guest_link');
        }
    }

    $message = new PsrMessage(
        'A new site option allows to hide the guest page for selection.' // @translate
    );
    $messenger->addSuccess($message);
}

if (version_compare($oldVersion, '3.4.29', '<')) {
    // Migrate contactus_append_resource_show and
    // contactus_append_items_browse to contactus_placement.
    $resourceToPlacement = [
        'items' => 'after/items',
        'medias' => 'after/media',
        'item_sets' => 'after/item_sets',
    ];
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $append = $siteSettings->get('contactus_append_resource_show', []);
        $placements = [];
        foreach ($append as $resource) {
            if (isset($resourceToPlacement[$resource])) {
                $placements[] = $resourceToPlacement[$resource];
            }
        }
        if ($siteSettings->get('contactus_append_items_browse', false)) {
            $placements[] = 'browse/items';
        }
        $siteSettings->set('contactus_placement', $placements);
    }
}

if (version_compare($oldVersion, '3.4.30', '<')) {
    // TODO Check if theme is custom for the file view/common/contact-us.phtml.
    $message = new PsrMessage(
        'Some new anti-spam checks were added in main settings. Check them if needed.' // @translate
    );
    $messenger->addSuccess($message);

    // The following step is useless with version 3.4.31 (streamed zip).
    // Nevertheless, it is kept for a future usage.

    // Zip tokens are now HMAC-signed, so the filename changes for each existing
    // message. Rename existing zip files to the new filename so the admin zip
    // links keep working.
    // Previously sent emails still carry the old token in their URL and cannot
    // be salvaged; the list of affected message ids is reported so the admin
    // can notify users.
    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    $zipDir = $basePath . '/contactus';
    $renamedIds = [];
    $orphanIds = [];
    if (is_dir($zipDir)) {
        // Because api is not available during upgrade, compute token manually.
        // See previous version for a simpler code with api.
        $secret = (string) $settings->get('contactus_token_secret');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            $settings->set('contactus_token_secret', $secret);
        }
        $token = function (array $contactMessage) use($secret): string {
            $string = $contactMessage['id'] . '/' . $contactMessage['email'] . '/' . $contactMessage['ip'] . '/' . $contactMessage['user_agent'] . '/' . $contactMessage['created'];
            return substr(strtr(base64_encode(hash_hmac('sha256', $string, $secret, true)), ['+' => '', '/' => '', '=' => '']), 0, 12);
        };

        $m = [];
        $files = glob($zipDir . '/*.zip') ?: [];
        foreach ($files as $filepath) {
            $name = basename($filepath);
            if (!preg_match('~^(\d+)\.[A-Za-z0-9]+\.zip$~', $name, $m)) {
                continue;
            }
            $id = (int) $m[1];
            $contactMessage = $connection->executeQuery('SELECT * FROM contact_message WHERE id = :id', ['id' => $id])->fetchAssociative();
            if (!$contactMessage) {
                @unlink($filepath);
                $orphanIds[] = $id;
                continue;
            }
            $newName = $id . '.' . $token($contactMessage) . '.zip';
            if ($name === $newName) {
                continue;
            }
            $newPath = $zipDir . '/' . $newName;
            if (@rename($filepath, $newPath)) {
                $renamedIds[] = $id;
            }
        }
    }

    if ($renamedIds) {
        sort($renamedIds);
        $message = new PsrMessage(
            'The zip filenames were re-signed. Previously sent emails pointing to these zip files are now broken. Contact messages concerned: {ids}.', // @translate
            ['ids' => '#' . implode(', #', $renamedIds)]
        );
        $messenger->addWarning($message);
    }
    if ($orphanIds) {
        sort($orphanIds);
        $message = new PsrMessage(
            'Removed {count} orphan zip files (message deleted): {ids}.', // @translate
            ['count' => count($orphanIds), 'ids' => '#' . implode(', #', $orphanIds)]
        );
        $messenger->addNotice($message);
    }
}

if (version_compare($oldVersion, '3.4.31', '<')) {
    // Zips are now streamed on demand and no longer stored on disk, so the
    // "remove after some days" setting and every previously generated zip file
    // become obsolete.
    // Remove the leftover zip files (the "{id}.{token}.zip" pattern never
    // matches message attachments, which keep their storage hash name, so the
    // folder and the attachments are preserved). The zip links in already sent
    // emails keep working: the token is unchanged and the archive is rebuilt on
    // the fly.
    $settings->delete('contactus_delete_zip');

    $config = $services->get('Config');
    $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
    $zipDir = $basePath . '/contactus';
    $removed = 0;
    if (is_dir($zipDir)) {
        foreach (glob($zipDir . '/*.zip') ?: [] as $filepath) {
            if (preg_match('~^\d+\.[A-Za-z0-9]+\.zip$~', basename($filepath))
                && @unlink($filepath)
            ) {
                ++$removed;
            }
        }
    }
    if ($removed) {
        $message = new PsrMessage(
            'Removed {count} stored zip files: zips are now streamed on demand, so no file is kept and existing links still work.', // @translate
            ['count' => $removed]
        );
        $messenger->addNotice($message);
    }
}

if (version_compare($oldVersion, '3.4.32', '<')) {
    // List the four default fields (name, email, subject, message) explicitly
    // so they appear in the config editor and can be reordered or relabeled.
    // Only when the setting was not customized yet.
    if (!$settings->get('contactus_fields')) {
        $settings->set('contactus_fields', [
            'name' => 'Name', // @translate
            'from' => [
                'name' => 'from',
                'type' => \Laminas\Form\Element\Email::class,
                'options' => ['label' => 'Email'], // @translate
                'attributes' => ['required' => true],
            ],
            'subject' => 'Subject', // @translate
            'message' => [
                'name' => 'message',
                'type' => \Laminas\Form\Element\Textarea::class,
                'options' => ['label' => 'Message'], // @translate
                'attributes' => ['required' => true],
            ],
        ]);
    }

    // The system field "id" (attached resource ids) was wrongly persisted
    // inside the block "fields" data. It is injected at runtime, so remove it
    // from every contact form block, and drop the "fields" key when it becomes
    // empty.
    $rows = $connection
        ->executeQuery('SELECT id, data FROM site_page_block WHERE data LIKE \'%"fields"%\'')
        ->fetchAllAssociative();
    $updated = 0;
    foreach ($rows as $row) {
        $data = json_decode((string) $row['data'], true);
        if (!is_array($data)
            || !isset($data['fields'])
            || !is_array($data['fields'])
            || !array_key_exists('id', $data['fields'])
        ) {
            continue;
        }
        unset($data['fields']['id']);
        if (!$data['fields']) {
            unset($data['fields']);
        }
        $connection->executeStatement(
            'UPDATE site_page_block SET data = :data WHERE id = :id',
            ['data' => json_encode($data), 'id' => (int) $row['id']]
        );
        ++$updated;
    }
    if ($updated) {
        $messenger->addNotice(new PsrMessage(
            'Removed the "id" system field from {count} contact form blocks.', // @translate
            ['count' => $updated]
        ));
    }

    // The migration of 3.4.26 called get() instead of set(), so the renamed
    // selection labels were read and dropped instead of being copied: the
    // custom labels of these sites silently fell back to the defaults. Redo it
    // for the bases that already passed this version. Idempotent: the new
    // setting is filled only when it is still empty and the old one remains.
    $repaired = 0;
    $renamedSiteSettings = [
        'contactus_label_selection' => 'contactus_selection_label',
        'contactus_label_guest_link' => 'contactus_selection_label_guest_link',
    ];
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        foreach ($renamedSiteSettings as $old => $new) {
            $value = $siteSettings->get($old);
            if ($value === null) {
                continue;
            }
            if (!$siteSettings->get($new)) {
                $siteSettings->set($new, $value);
                ++$repaired;
            }
            $siteSettings->delete($old);
        }
    }
    if ($repaired) {
        $messenger->addWarning(new PsrMessage(
            'Restored {count} site labels for the selection, lost by an issue in the upgrade to version 3.4.26.', // @translate
            ['count' => $repaired]
        ));
    }

    // The same issue left the sender email empty since 3.4.24, where it should
    // have been filled with the first email of the notification list. It is not
    // set here: the site has been sending emails without it for many versions,
    // and changing the sender silently may break the spf or dkim records.
    if (!$settings->get('contactus_sender_email')) {
        $messenger->addNotice(new PsrMessage(
            'The option "Email of the sender" is empty: the no-reply or the administrator email is used. Set it in the settings if a specific sender is needed.' // @translate
        ));
    }

    // The fields of the form are now flat: a theme posting "fields[name]" still
    // works through a fallback, but the fallback is deprecated. Only warn: the
    // forms keep working, so the upgrade must not be blocked.
    $strings = [
        'themes/*/view/common/contact-us*' => ['fields['],
        'themes/*/view/common/block-layout/contact-us*' => ['fields['],
        'themes/*/view/common/block-template/contact-us*' => ['fields['],
        'themes/*/view/omeka/site/item/browse*' => ['fields[id]'],
        'themes/*/view/search/resource-list*' => ['fields[id]'],
    ];
    $manageModuleAndResources = $this->getManageModuleAndResources();
    $results = [];
    foreach ($strings as $path => $stringsToCheck) {
        $result = $manageModuleAndResources->checkStringsInFiles($stringsToCheck, $path);
        if ($result) {
            $results[] = $result;
        }
    }
    if ($results) {
        $message = new PsrMessage(
            'The fields of the contact form are now flat: use the name of the field ("phone") instead of the nested name ("fields[phone]"), and "id[]" instead of "fields[id][]". The old names still work for now. Check the following files: {json}', // @translate
            ['json' => json_encode($results, 448)]
        );
        $logger->warn($message->getMessage(), $message->getContext());
        $messenger->addWarning($message);
    }
}

$this->checkSpamGuardPresence($services);
