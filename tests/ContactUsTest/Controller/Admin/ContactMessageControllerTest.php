<?php declare(strict_types=1);

namespace ContactUsTest\Controller\Admin;

use ContactUsTest\ContactUsTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * @see \ContactUs\Controller\Admin\ContactMessageController
 */
class ContactMessageControllerTest extends AbstractHttpControllerTestCase
{
    use ContactUsTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->services = $this->getApplication()->getServiceManager();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->logout();
        $this->services = null;
    }

    /** @test */
    public function browseAction(): void
    {
        $this->dispatch('/admin/contact-message');
        $this->assertResponseStatusCode(200);
    }

    /**
     * The browse page is reached through the literal parent route, so the batch
     * forms must post to their own action, not to the browse page.
     *
     * @test
     */
    public function browseActionBatchFormsPostToTheirAction(): void
    {
        $messageId = $this->createContactMessage()->getId();

        $this->dispatch('/admin/contact-message');
        $this->assertResponseStatusCode(200);
        $this->assertQuery('form#confirm-resend-selected[action$="/admin/contact-message/batch-resend"]');
        $this->assertQuery('form#confirm-resend-all[action$="/admin/contact-message/batch-resend-all"]');

        $this->getServiceLocator()->get('Omeka\ApiManager')->delete('contact_messages', $messageId);
    }

    /**
     * The spams are hidden by default, and the filter "All" of the quick search
     * shows them too, so it must not fall back to the default.
     *
     * @test
     */
    public function browseActionSpamFilter(): void
    {
        $spam = $this->createContactMessage(null, 'spam@example.org');
        $spam->setIsSpam(true);
        $this->getServiceLocator()->get('Omeka\EntityManager')->flush();
        $spamId = $spam->getId();

        $this->dispatch('/admin/contact-message');
        $this->assertResponseStatusCode(200);
        $this->assertStringNotContainsString('spam@example.org', $this->getResponse()->getContent());
        $this->assertQuery('#is_spam input[value="0"][checked], input[name="is_spam"][value="0"][checked]');

        $this->reset();
        $this->loginAdmin();
        $this->dispatch('/admin/contact-message?is_spam=any');
        $this->assertResponseStatusCode(200);
        $this->assertStringContainsString('spam@example.org', $this->getResponse()->getContent());
        $this->assertQuery('input[name="is_spam"][value="any"][checked]');

        $this->getServiceLocator()->get('Omeka\ApiManager')->delete('contact_messages', $spamId);
    }

    /** @test */
    public function browseActionSortByCreatedAsc(): void
    {
        $this->dispatch('/admin/contact-message?sort_by=created&sort_order=asc');
        $this->assertResponseStatusCode(200);
    }

    /** @test */
    public function browseActionSortByCreatedDesc(): void
    {
        $this->dispatch('/admin/contact-message?sort_by=created&sort_order=desc');
        $this->assertResponseStatusCode(200);
    }

    /** @test */
    public function browseActionSortByName(): void
    {
        $this->dispatch('/admin/contact-message?sort_by=name&sort_order=asc');
        $this->assertResponseStatusCode(200);
    }

    /** @test */
    public function browseActionSortByEmail(): void
    {
        $this->dispatch('/admin/contact-message?sort_by=email&sort_order=asc');
        $this->assertResponseStatusCode(200);
    }

    /** @test */
    public function showDetailsAction(): void
    {
        $message = $this->createContactMessage();
        $this->dispatch('/admin/contact-message/' . $message->getId() . '/show-details');
        $this->assertResponseStatusCode(200);
    }

    /** @test */
    public function showDetailsNoRecordAction(): void
    {
        $this->dispatch('/admin/contact-message/1');
        $this->assertResponseStatusCode(404);
    }

    /** @test */
    public function deleteConfirmAction(): void
    {
        $message = $this->createContactMessage();
        $this->dispatch('/admin/contact-message/' . $message->getId() . '/delete-confirm');
        $this->assertResponseStatusCode(200);
    }

    /** @test */
    public function deleteAction(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $form = $this->services->get('FormElementManager')->get(\Omeka\Form\ConfirmForm::class);
        $this->dispatch('/admin/contact-message/' . $messageId . '/delete', 'POST', [
            'submit' => true,
            'confirmform_csrf' => $form->get('confirmform_csrf')->getValue(),
        ]);
        $result = $this->getContactMessage($messageId);
        $this->assertEmpty($result);
    }

    /** @test */
    public function deleteActionNoDeletionWithoutCsrf(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch('/admin/contact-message/' . $messageId . '/delete', 'POST', [
            'submit' => true,
        ]);
        $result = $this->getContactMessage($messageId);
        $this->assertNotEmpty($result);
    }

    /** @test */
    public function deleteActionShouldBePost(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch('/admin/contact-message/' . $messageId . '/delete');
        $result = $this->getContactMessage($messageId);
        $this->assertNotEmpty($result);
    }

    /** @test */
    public function batchDeleteConfirmAction(): void
    {
        $this->dispatch('/admin/contact-message/batch-delete-confirm');
        $this->assertResponseStatusCode(200);
    }

    /** @test */
    public function batchDeleteAction(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $form = $this->services->get('FormElementManager')->get(\Omeka\Form\ConfirmForm::class);
        $this->dispatch('/admin/contact-message/batch-delete', 'POST', [
            'resource_ids' => [$messageId],
            'confirmform_csrf' => $form->get('confirmform_csrf')->getValue(),
        ]);
        $result = $this->getContactMessage($messageId);
        $this->assertEmpty($result);
    }

    /** @test */
    public function batchDeleteActionShouldBePost(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch('/admin/contact-message/batch-delete');
        $result = $this->getContactMessage($messageId);
        $this->assertNotEmpty($result);
    }

    /** @test */
    public function batchDeleteActionNoResourceIds(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $form = $this->services->get('FormElementManager')->get(\Omeka\Form\ConfirmForm::class);
        $this->dispatch('/admin/contact-message/batch-delete', 'POST', [
            'confirmform_csrf' => $form->get('confirmform_csrf')->getValue(),
        ]);
        $result = $this->getContactMessage($messageId);
        $this->assertNotEmpty($result);
    }

    public function batchDeleteAllAction(): void
    {
        $this->markTestIncomplete('Delete all is not supported by the controller.');
    }

    /** @test */
    public function batchSetReadAction(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch(
            '/admin/contact-message/batch-set-read',
            'GET',
            ['resource_ids' => [$messageId]],
            true
        );
        $this->assertResponseStatusCode(200);
        $message = $this->getContactMessage($messageId);
        $this->assertTrue($message->isRead());
    }

    /** @test */
    public function batchSetNotReadAction(): void
    {
        $message = $this->createContactMessage();
        $message->setIsRead(true);
        $this->services->get('Omeka\EntityManager')->flush();
        $messageId = $message->getId();
        $this->dispatch(
            '/admin/contact-message/batch-set-not-read',
            'GET',
            ['resource_ids' => [$messageId]],
            true
        );
        $this->assertResponseStatusCode(200);
        $message = $this->getContactMessage($messageId);
        $this->assertFalse($message->isRead());
    }

    /** @test */
    public function batchSetSpamAction(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch(
            '/admin/contact-message/batch-set-spam',
            'GET',
            ['resource_ids' => [$messageId]],
            true
        );
        $this->assertResponseStatusCode(200);
        $message = $this->getContactMessage($messageId);
        $this->assertTrue($message->isSpam());
    }

    /** @test */
    public function batchSetNotSpamAction(): void
    {
        $message = $this->createContactMessage();
        $message->setIsSpam(true);
        $this->services->get('Omeka\EntityManager')->flush();
        $messageId = $message->getId();
        $this->dispatch(
            '/admin/contact-message/batch-set-not-spam',
            'GET',
            ['resource_ids' => [$messageId]],
            true
        );
        $this->assertResponseStatusCode(200);
        $message = $this->getContactMessage($messageId);
        $this->assertFalse($message->isSpam());
    }

    /** @test */
    public function batchSetReadActionNotXmlHttpRequest(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch(
            '/admin/contact-message/batch-set-read',
            'GET',
            ['resource_ids' => [$messageId]]
        );
        $this->assertResponseStatusCode(404);
    }

    /** @test */
    public function toggleReadAction(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch('/admin/contact-message/' . $messageId . '/toggle-read', null, [], true);
        $message = $this->getContactMessage($messageId);
        $this->assertTrue($message->isRead());
    }

    /** @test */
    public function toggleReadActionNotXmlHttpRequest(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch('/admin/contact-message/' . $messageId . '/toggle-read');
        $this->assertResponseStatusCode(404);
    }

    /** @test */
    public function toggleSpamAction(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch('/admin/contact-message/' . $messageId . '/toggle-spam', null, [], true);
        $message = $this->getContactMessage($messageId);
        $this->assertTrue($message->isSpam());

        // TODO Only one dispatch by test is possible currently.
        /*
        $this->dispatch('/admin/contact-message/' . $messageId .'/toggle-spam', null, [], true);
        $message = $this->getContactMessage($messageId);
        $this->assertFalse($message->isSpam());
        */
    }

    /** @test */
    public function toggleSpamActionNotXmlHttpRequest(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch('/admin/contact-message/' . $messageId . '/toggle-spam');
        $this->assertResponseStatusCode(404);
    }
}
