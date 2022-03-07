<?php declare(strict_types=1);

namespace ContactUs\Entity;

use DateTime;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Resource;
use Omeka\Entity\Site;
use Omeka\Entity\User;

/**
 * @Entity
 * @Table(
 *     name="contact_message"
 * )
 * @HasLifecycleCallbacks
 */
class Message extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(
     *      type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * @var User
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\User"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $owner;

    /**
     * @var string
     *
     * @Column(
     *      type="string",
     *      length=190
     * )
     */
    protected $email;

    /**
     * @var string
     *
     * @Column(
     *      type="string",
     *      length=190,
     *      nullable=true
     * )
     */
    protected $name;

    /**
     * @var string
     *
     * @Column(
     *      type="text",
     *      nullable=true
     * )
     */
    protected $subject;

    /**
     * @var string
     *
     * @Column(
     *      type="text"
     * )
     */
    protected $body;

    /**
     * @var string
     *
     * @Column(
     *      type="text",
     *      nullable=true
     * )
     */
    protected $source;

    /**
     * @var string
     *
     * @Column(
     *      length=190,
     *      nullable=true
     * )
     */
    protected $mediaType;

    /**
     * @var string
     *
     * @Column(
     *      length=190,
     *      unique=true,
     *      nullable=true
     * )
     */
    protected $storageId;

    /**
     * @var string
     *
     * @Column(
     *      nullable=true
     * )
     */
    protected $extension;

    /**
     * @var Resource
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Resource"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $resource;

    /**
     * @var Site
     *
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Site"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="SET NULL"
     * )
     */
    protected $site;

    /**
     * @var string
     *
     * @Column(
     *      type="string",
     *      length=1024,
     *      nullable=true
     * )
     */
    protected $requestUrl;

    /**
     * May be ipv4 or ipv6.
     *
     * @var string
     *
     * @Column(
     *      type="string",
     *      length=45
     * )
     */
    protected $ip;

    /**
     * @var string
     *
     * @Column(
     *      type="text",
     *      length=65534,
     *      nullable=true
     * )
     */
    protected $userAgent;

    /**
     * @var bool
     *
     * @Column(
     *      type="boolean",
     *      nullable=true
     * )
     */
    protected $newsletter;

    /**
     * @var bool
     *
     * @Column(
     *      type="boolean",
     *      nullable=false,
     *      options={
     *          "default":0
     *      }
     * )
     */
    protected $isRead = false;

    /**
     * @var bool
     *
     * @Column(
     *      type="boolean",
     *      nullable=false,
     *      options={
     *          "default":0
     *      }
     * )
     */
    protected $isSpam = false;

    /**
     * @var DateTime
     *
     * @Column(
     *      type="datetime"
     * )
     */
    protected $created;

    public function getId()
    {
        return $this->id;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setMediaType(?string $mediaType): self
    {
        $this->mediaType = $mediaType;
        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->mediaType;
    }

    public function getFilename(): ?string
    {
        $filename = $this->storageId;
        if ($filename !== null && $this->extension !== null) {
            $filename .= '.' . $this->extension;
        }
        return $filename;
    }

    public function setStorageId(?string $storageId): self
    {
        $this->storageId = $storageId;
        return $this;
    }

    public function getStorageId(): ?string
    {
        return $this->storageId;
    }

    public function setExtension(?string $extension): self
    {
        $this->extension = $extension;
        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setResource(?Resource $resource): self
    {
        $this->resource = $resource;
        return $this;
    }

    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    public function setSite(?Site $site): self
    {
        $this->site = $site;
        return $this;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setRequestUrl(?string $requestUrl): self
    {
        $this->requestUrl = $requestUrl;
        return $this;
    }

    public function getRequestUrl(): ?string
    {
        return $this->requestUrl;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setNewsletter(?bool $newsletter): self
    {
        $this->newsletter = $newsletter;
        return $this;
    }

    public function getNewsletter(): ?bool
    {
        return $this->newsletter;
    }

    public function setIsRead($isRead): self
    {
        $this->isRead = (bool) $isRead;
        return $this;
    }

    public function isRead(): bool
    {
        return (bool) $this->isRead;
    }

    public function setIsSpam($isSpam): self
    {
        $this->isSpam = (bool) $isSpam;
        return $this;
    }

    public function isSpam(): bool
    {
        return (bool) $this->isSpam;
    }

    public function setCreated(DateTime $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTime
    {
        return $this->created;
    }

    /**
     * @PrePersist
     */
    public function prePersist(LifecycleEventArgs $eventArgs): void
    {
        $this->created = new DateTime('now');
    }
}
