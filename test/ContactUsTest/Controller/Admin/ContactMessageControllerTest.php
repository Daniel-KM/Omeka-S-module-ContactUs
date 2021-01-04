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

    public function deleteConfirmAction(): void
    {
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

    public function batchDeleteConfirmAction(): void
    {
    }

    public function batchDeleteAction(): void
    {
    }

    public function batchDeleteAllAction(): void
    {
    }

    public function batchSetReadAction(): void
    {
    }

    public function batchSetNotReadAction(): void
    {
    }

    public function batchSetSpamAction(): void
    {
    }

    public function batchSetNotSpamAction(): void
    {
    }

    public function toggleReadAction(): void
    {
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

    /**
     * @expectedException Exception
     */
    public function toggleSpamActionNotXmlHttpRequest(): void
    {
        $message = $this->createContactMessage();
        $messageId = $message->getId();
        $this->dispatch('/admin/contact-message/' . $messageId . '/toggle-spam');
    }
}
