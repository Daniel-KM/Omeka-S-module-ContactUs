<?php

namespace DoctrineProxies\__CG__\ContactUs\Entity;


/**
 * DO NOT EDIT THIS FILE - IT WAS CREATED BY DOCTRINE'S PROXY GENERATOR
 */
class Message extends \ContactUs\Entity\Message implements \Doctrine\ORM\Proxy\Proxy
{
    /**
     * @var \Closure the callback responsible for loading properties in the proxy object. This callback is called with
     *      three parameters, being respectively the proxy object to be initialized, the method that triggered the
     *      initialization process and an array of ordered parameters that were passed to that method.
     *
     * @see \Doctrine\Common\Proxy\Proxy::__setInitializer
     */
    public $__initializer__;

    /**
     * @var \Closure the callback responsible of loading properties that need to be copied in the cloned object
     *
     * @see \Doctrine\Common\Proxy\Proxy::__setCloner
     */
    public $__cloner__;

    /**
     * @var boolean flag indicating if this object was already initialized
     *
     * @see \Doctrine\Persistence\Proxy::__isInitialized
     */
    public $__isInitialized__ = false;

    /**
     * @var array<string, null> properties to be lazy loaded, indexed by property name
     */
    public static $lazyPropertiesNames = array (
);

    /**
     * @var array<string, mixed> default values of properties to be lazy loaded, with keys being the property names
     *
     * @see \Doctrine\Common\Proxy\Proxy::__getLazyProperties
     */
    public static $lazyPropertiesDefaults = array (
);



    public function __construct(?\Closure $initializer = null, ?\Closure $cloner = null)
    {

        $this->__initializer__ = $initializer;
        $this->__cloner__      = $cloner;
    }







    /**
     * 
     * @return array
     */
    public function __sleep()
    {
        if ($this->__isInitialized__) {
            return ['__isInitialized__', 'id', 'owner', 'email', 'name', 'subject', 'body', 'fields', 'source', 'mediaType', 'storageId', 'extension', 'resource', 'site', 'requestUrl', 'ip', 'userAgent', 'newsletter', 'isRead', 'isSpam', 'toAuthor', 'created', 'modified'];
        }

        return ['__isInitialized__', 'id', 'owner', 'email', 'name', 'subject', 'body', 'fields', 'source', 'mediaType', 'storageId', 'extension', 'resource', 'site', 'requestUrl', 'ip', 'userAgent', 'newsletter', 'isRead', 'isSpam', 'toAuthor', 'created', 'modified'];
    }

    /**
     * 
     */
    public function __wakeup()
    {
        if ( ! $this->__isInitialized__) {
            $this->__initializer__ = function (Message $proxy) {
                $proxy->__setInitializer(null);
                $proxy->__setCloner(null);

                $existingProperties = get_object_vars($proxy);

                foreach ($proxy::$lazyPropertiesDefaults as $property => $defaultValue) {
                    if ( ! array_key_exists($property, $existingProperties)) {
                        $proxy->$property = $defaultValue;
                    }
                }
            };

        }
    }

    /**
     * 
     */
    public function __clone()
    {
        $this->__cloner__ && $this->__cloner__->__invoke($this, '__clone', []);
    }

    /**
     * Forces initialization of the proxy
     */
    public function __load(): void
    {
        $this->__initializer__ && $this->__initializer__->__invoke($this, '__load', []);
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __isInitialized(): bool
    {
        return $this->__isInitialized__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setInitialized($initialized): void
    {
        $this->__isInitialized__ = $initialized;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setInitializer(\Closure $initializer = null): void
    {
        $this->__initializer__ = $initializer;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __getInitializer(): ?\Closure
    {
        return $this->__initializer__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     */
    public function __setCloner(\Closure $cloner = null): void
    {
        $this->__cloner__ = $cloner;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific cloning logic
     */
    public function __getCloner(): ?\Closure
    {
        return $this->__cloner__;
    }

    /**
     * {@inheritDoc}
     * @internal generated method: use only when explicitly handling proxy specific loading logic
     * @deprecated no longer in use - generated code now relies on internal components rather than generated public API
     * @static
     */
    public function __getLazyProperties(): array
    {
        return self::$lazyPropertiesDefaults;
    }

    
    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        if ($this->__isInitialized__ === false) {
            return (int)  parent::getId();
        }


        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getId', []);

        return parent::getId();
    }

    /**
     * {@inheritDoc}
     */
    public function setOwner(?\Omeka\Entity\User $owner): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setOwner', [$owner]);

        return parent::setOwner($owner);
    }

    /**
     * {@inheritDoc}
     */
    public function getOwner(): ?\Omeka\Entity\User
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getOwner', []);

        return parent::getOwner();
    }

    /**
     * {@inheritDoc}
     */
    public function setEmail(?string $email): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setEmail', [$email]);

        return parent::setEmail($email);
    }

    /**
     * {@inheritDoc}
     */
    public function getEmail(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getEmail', []);

        return parent::getEmail();
    }

    /**
     * {@inheritDoc}
     */
    public function setName(?string $name): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setName', [$name]);

        return parent::setName($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getName', []);

        return parent::getName();
    }

    /**
     * {@inheritDoc}
     */
    public function setSubject(?string $subject): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setSubject', [$subject]);

        return parent::setSubject($subject);
    }

    /**
     * {@inheritDoc}
     */
    public function getSubject(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getSubject', []);

        return parent::getSubject();
    }

    /**
     * {@inheritDoc}
     */
    public function setBody(?string $body): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setBody', [$body]);

        return parent::setBody($body);
    }

    /**
     * {@inheritDoc}
     */
    public function getBody(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getBody', []);

        return parent::getBody();
    }

    /**
     * {@inheritDoc}
     */
    public function setFields(?array $fields): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setFields', [$fields]);

        return parent::setFields($fields);
    }

    /**
     * {@inheritDoc}
     */
    public function getFields(): ?array
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getFields', []);

        return parent::getFields();
    }

    /**
     * {@inheritDoc}
     */
    public function setSource(?string $source): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setSource', [$source]);

        return parent::setSource($source);
    }

    /**
     * {@inheritDoc}
     */
    public function getSource(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getSource', []);

        return parent::getSource();
    }

    /**
     * {@inheritDoc}
     */
    public function setMediaType(?string $mediaType): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setMediaType', [$mediaType]);

        return parent::setMediaType($mediaType);
    }

    /**
     * {@inheritDoc}
     */
    public function getMediaType(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getMediaType', []);

        return parent::getMediaType();
    }

    /**
     * {@inheritDoc}
     */
    public function getFilename(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getFilename', []);

        return parent::getFilename();
    }

    /**
     * {@inheritDoc}
     */
    public function setStorageId(?string $storageId): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setStorageId', [$storageId]);

        return parent::setStorageId($storageId);
    }

    /**
     * {@inheritDoc}
     */
    public function getStorageId(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getStorageId', []);

        return parent::getStorageId();
    }

    /**
     * {@inheritDoc}
     */
    public function setExtension(?string $extension): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setExtension', [$extension]);

        return parent::setExtension($extension);
    }

    /**
     * {@inheritDoc}
     */
    public function getExtension(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getExtension', []);

        return parent::getExtension();
    }

    /**
     * {@inheritDoc}
     */
    public function setResource(?\Omeka\Entity\Resource $resource): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setResource', [$resource]);

        return parent::setResource($resource);
    }

    /**
     * {@inheritDoc}
     */
    public function getResource(): ?\Omeka\Entity\Resource
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getResource', []);

        return parent::getResource();
    }

    /**
     * {@inheritDoc}
     */
    public function setSite(?\Omeka\Entity\Site $site): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setSite', [$site]);

        return parent::setSite($site);
    }

    /**
     * {@inheritDoc}
     */
    public function getSite(): ?\Omeka\Entity\Site
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getSite', []);

        return parent::getSite();
    }

    /**
     * {@inheritDoc}
     */
    public function setRequestUrl(?string $requestUrl): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setRequestUrl', [$requestUrl]);

        return parent::setRequestUrl($requestUrl);
    }

    /**
     * {@inheritDoc}
     */
    public function getRequestUrl(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getRequestUrl', []);

        return parent::getRequestUrl();
    }

    /**
     * {@inheritDoc}
     */
    public function setIp(?string $ip): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setIp', [$ip]);

        return parent::setIp($ip);
    }

    /**
     * {@inheritDoc}
     */
    public function getIp(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getIp', []);

        return parent::getIp();
    }

    /**
     * {@inheritDoc}
     */
    public function setUserAgent(?string $userAgent): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setUserAgent', [$userAgent]);

        return parent::setUserAgent($userAgent);
    }

    /**
     * {@inheritDoc}
     */
    public function getUserAgent(): ?string
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getUserAgent', []);

        return parent::getUserAgent();
    }

    /**
     * {@inheritDoc}
     */
    public function setNewsletter(?bool $newsletter): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setNewsletter', [$newsletter]);

        return parent::setNewsletter($newsletter);
    }

    /**
     * {@inheritDoc}
     */
    public function getNewsletter(): ?bool
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getNewsletter', []);

        return parent::getNewsletter();
    }

    /**
     * {@inheritDoc}
     */
    public function setIsRead($isRead): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setIsRead', [$isRead]);

        return parent::setIsRead($isRead);
    }

    /**
     * {@inheritDoc}
     */
    public function isRead(): bool
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'isRead', []);

        return parent::isRead();
    }

    /**
     * {@inheritDoc}
     */
    public function setIsSpam($isSpam): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setIsSpam', [$isSpam]);

        return parent::setIsSpam($isSpam);
    }

    /**
     * {@inheritDoc}
     */
    public function isSpam(): bool
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'isSpam', []);

        return parent::isSpam();
    }

    /**
     * {@inheritDoc}
     */
    public function setToAuthor($toAuthor): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setToAuthor', [$toAuthor]);

        return parent::setToAuthor($toAuthor);
    }

    /**
     * {@inheritDoc}
     */
    public function isToAuthor(): bool
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'isToAuthor', []);

        return parent::isToAuthor();
    }

    /**
     * {@inheritDoc}
     */
    public function setCreated(\DateTime $created): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setCreated', [$created]);

        return parent::setCreated($created);
    }

    /**
     * {@inheritDoc}
     */
    public function getCreated(): \DateTime
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getCreated', []);

        return parent::getCreated();
    }

    /**
     * {@inheritDoc}
     */
    public function setModified(?\DateTime $modified): \ContactUs\Entity\Message
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'setModified', [$modified]);

        return parent::setModified($modified);
    }

    /**
     * {@inheritDoc}
     */
    public function getModified(): ?\DateTime
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getModified', []);

        return parent::getModified();
    }

    /**
     * {@inheritDoc}
     */
    public function getResourceId()
    {

        $this->__initializer__ && $this->__initializer__->__invoke($this, 'getResourceId', []);

        return parent::getResourceId();
    }

}
