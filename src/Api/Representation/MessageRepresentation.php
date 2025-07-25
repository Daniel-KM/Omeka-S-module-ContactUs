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
        $getDateTimeJsonLd = function (?\DateTime $dateTime): ?array {
            return $dateTime
                ? [
                    '@value' => $dateTime->format('c'),
                    '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
                ]
                : null;
        };

        $owner = $this->owner();

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
            $linked['o:resource'] = $resource->getReference()->jsonSerialize();
        }
        $site = $this->site();
        if ($site) {
            $linked['o:site'] = $site->getReference()->jsonSerialize();
        }

        $newsletter = $this->newsletter();
        $newsletter = $newsletter === null
            ? []
            : ['o-module-contact:newsletter' => (bool) $newsletter];

        return [
            'o:id' => $this->id(),
            'o:owner' => $owner ? $owner->getReference()->jsonSerialize() : null,
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
            'o:created' => $getDateTimeJsonLd($this->resource->getCreated()),
            'o:modified' => $getDateTimeJsonLd($this->resource->getModified()),
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

    public function hasZip(): bool
    {
        $filepath = $this->zipFilepath();
        return file_exists($filepath) && is_readable($filepath) && !is_dir($filepath);
    }

    public function zipFilename(): string
    {
        return $this->resource->getId() . '.' . $this->token() . '.zip';
    }

    public function zipFilepath(): string
    {
        // TODO Use Omeka storage.
        $config = $this->getServiceLocator()->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return $basePath . '/contactus/' . $this->zipFilename();
    }

    public function zipUrl(): string
    {
        $url = $this->getViewHelper('Url');
        return $url('contact-us', ['action' => 'zip', 'id' => $this->resource->getId() . '.' . $this->token()], ['force_canonical' => true]);
    }

    public function created(): DateTime
    {
        return $this->resource->getCreated();
    }

    public function modified(): ?DateTime
    {
        return $this->resource->getModified();
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

    /**
     * Get all resource ids (main resource and field "id").
     */
    public function resourceIds(): array
    {
        $result = $this->resource();
        $result = $result ? [$result->id()] : [];
        $fields = $this->fields();
        $fields = $fields && !empty($fields['id']) ? (is_array($fields['id']) ? $fields['id'] : [$fields['id']]) : [];
        return array_values(array_unique(array_merge($result, $fields)));
    }

    public function token(): ?string
    {
        $string = $this->id() . '/' . $this->email() . '/' . $this->ip() . '/' . $this->userAgent() . '/' . $this->created()->format('Y-m-d H:i:s');
        return substr(str_replace(['+', '/', '='], '', base64_encode(hash('sha256', $string))), 0, 12);
    }
}
