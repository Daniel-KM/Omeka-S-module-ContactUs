<?php declare(strict_types=1);

namespace ContactUs\Api\Representation;

use DateTime;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\UserRepresentation;

class MessageRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'contact-message';
    }

    public function getJsonLdType()
    {
        return 'o-module-contact:Message';
    }

    public function getJsonLd()
    {
        $owner = $this->owner();
        if ($owner) {
            $owner = $owner->getReference();
        }

        $file = $this->filename();
        if ($file) {
            $fileData = [
                'o:source' => $this->source(),
                'o:media_type' => $this->mediaType(),
                'o:filename' => $file,
            ];
        } else {
            $fileData = [];
        }

        $linked = [];
        $resource = $this->resource();
        if ($resource) {
            $linked['o:resource'] = $resource->getReference();
        }
        $site = $this->site();
        if ($site) {
            $linked['o:site'] = $site->getReference();
        }

        $newsletter = $this->newsletter();
        $newsletter = is_null($newsletter)
            ? []
            : ['o-module-contact:newsletter' => (bool) $newsletter];

        $created = [
            '@value' => $this->getDateTime($this->created()),
            '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
        ];

        return [
            'o:id' => $this->id(),
            'o:owner' => $owner,
            'o:email' => $this->email(),
            'o:name' => $this->name(),
            'o-module-contact:subject' => $this->subject(),
            'o-module-contact:body' => $this->body(),
            'o-module-contact:fields' => $this->fields(),
        ]
        + $fileData
        + $linked
        + [
            'o-module-contact:request_url' => $this->requestUrl(),
            'o-module-contact:ip' => $this->ip(),
            'o-module-contact:user_agent' => $this->userAgent(),
        ]
        + $newsletter
        + [
            'o-module-contact:is_read' => $this->isRead(),
            'o-module-contact:is_spam' => $this->isSpam(),
            'o-module-contact:to_author' => $this->isToAuthor(),
            'o:created' => $created,
        ];
    }

    /**
     * Get the owner representation of the sender, if internal.
     */
    public function owner(): ?UserRepresentation
    {
        $owner = $this->resource->getOwner();
        return $owner
            ? $this->getAdapter('users')->getRepresentation($owner)
            : null;
    }

    public function email(): ?string
    {
        return $this->resource->getEmail();
    }

    public function name(): ?string
    {
        return $this->resource->getName();
    }

    public function subject(): ?string
    {
        return $this->resource->getSubject();
    }

    public function body(): ?string
    {
        return $this->resource->getBody();
    }

    public function fields(): ?array
    {
        return $this->resource->getFields();
    }

    public function source(): ?string
    {
        return $this->resource->getSource();
    }

    public function mediaType(): ?string
    {
        return $this->resource->getMediaType();
    }

    public function filename(): ?string
    {
        return $this->resource->getFilename();
    }

    /**
     * Get the resource attached to this message.
     */
    public function resource(): ?AbstractResourceEntityRepresentation
    {
        $resource = $this->resource->getResource();
        return $resource
            ? $this->getAdapter('resources')->getRepresentation($resource)
            : null;
    }

    /**
     * Get the site representation attached to this message.
     */
    public function site(): ?SiteRepresentation
    {
        $site = $this->resource->getSite();
        return $site
            ? $this->getAdapter('sites')->getRepresentation($site)
            : null;
    }

    public function requestUrl(): ?string
    {
        return $this->resource->getRequestUrl();
    }

    public function ip(): ?string
    {
        return $this->resource->getIp();
    }

    public function userAgent(): ?string
    {
        return $this->resource->getUserAgent();
    }

    public function newsletter(): ?bool
    {
        return $this->resource->getNewsletter();
    }

    public function isRead(): bool
    {
        return $this->resource->isRead();
    }

    public function isSpam(): bool
    {
        return $this->resource->isSpam();
    }

    public function isToAuthor(): bool
    {
        return $this->resource->isToAuthor();
    }

    public function created(): DateTime
    {
        return $this->resource->getCreated();
    }

    public function assetUrl(): ?string
    {
        $filename = $this->filename();
        return $filename
            ? $this->getFileUrl(\ContactUs\Module::STORE_PREFIX, $filename)
            : null;
    }

    /**
     * A message can be its own "thumbnail" when there is a file.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractRepresentation::thumbnail()
     */
    public function thumbnail(): ?self
    {
        return $this->filename() ? $this : null;
    }
}
