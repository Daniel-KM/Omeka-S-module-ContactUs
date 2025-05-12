<?php declare(strict_types=1);

namespace ContactUs\Service\Form;

use ContactUs\Form\ContactUsForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContactUsFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ContactUsForm(null, $options ?? []);
        $form->setEventManager($services->get('EventManager'));
        return $form;
    }
}
