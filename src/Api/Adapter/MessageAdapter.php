<?php declare(strict_types=1);

namespace ContactUs\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Laminas\Validator\EmailAddress;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Omeka\File\TempFile;
use Omeka\File\Validator;
use Omeka\Stdlib\ErrorStore;

class MessageAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'id' => 'id',
        'owner_id' => 'owner',
        'email' => 'email',
        'name' => 'name',
        'subject' => 'subject',

        'source' => 'source',
        'media_type' => 'mediaType',
        'extension' => 'extension',

        'resource_id' => 'resource',
        // 'resource_title' => 'resource',
        'item_set_id' => 'resource',
        'item_id' => 'resource',
        'media_id' => 'resource',
        'site_id' => 'site',
        'url' => 'url',

        'ip' => 'ip',
        'user_agent' => 'user_agent',
    ];

    public function getResourceName()
    {
        return 'contact_messages';
    }

    public function getRepresentationClass()
    {
        return \ContactUs\Api\Representation\MessageRepresentation::class;
    }

    public function getEntityClass()
    {
        return \ContactUs\Entity\Message::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        foreach ([
            'resource_id' => 'resource',
            'item_set_id' => 'resource',
            'item_id' => 'resource',
            'media_id' => 'resource',
            'owner_id' => 'owner',
            'site_id' => 'site',
        ] as $queryKey => $column) {
            if (isset($query[$queryKey])) {
                $ids = is_array($query[$queryKey])
                    ? $query[$queryKey]
                    : [$query[$queryKey]];
                // Unlike buildBaseQuery(), only ids are possible here for now.
                // @see \Omeka\Api\Adapter\AbstractEntityAdapter::buildBaseQuery().
                $ids = array_filter($ids, 'is_numeric');
                if (count($ids)) {
                    $joinAlias = $this->createAlias();
                    $qb
                        ->innerJoin(
                            "omeka_root.$column",
                            $joinAlias
                        )
                        ->andWhere($expr->in(
                            "$joinAlias.id",
                            $this->createNamedParameter($qb, $ids)
                        ));
                }
            }
        }

        if (isset($query['has_resource']) && strlen((string) $query['has_resource'])) {
            $joinAlias = $this->createAlias();
            $qb
                ->innerJoin(
                    'omeka_root.resource',
                    $joinAlias
                );
            if (empty($query['has_resource'])) {
                $qb
                    ->andWhere($expr->isNull('omeka_root.resource'));
            } else {
                $qb
                    ->andWhere($expr->isNotNull('omeka_root.resource'));
            }
        }

        if (isset($query['resource_type']) && strlen((string) $query['resource_type'])) {
            $mapResourceTypes = [
                'resources' => Resource::class,
                'item_sets' => ItemSet::class,
                'items' => Item::class,
                'media' => Media::class,
                'resource' => Resource::class,
                'item_set' => ItemSet::class,
                'item' => Item::class,
                // 'users' => User::class,
                // 'sites' => Site::class,
            ];
            if ($query['resource_type'] === 'resources') {
                $qb
                    ->andWhere($expr->isNotNull('omeka_root.resource'));
            } elseif (isset($mapResourceTypes[$query['resource_type']])) {
                $entityAlias = $this->createAlias();
                $qb
                    ->innerJoin(
                        $mapResourceTypes[$query['resource_type']],
                        $entityAlias,
                        WITH,
                        $expr->eq('omeka_root.resource', $entityAlias . '.id')
                );
            } else {
                $qb
                   ->andWhere('1 = 0');
            }
        }

        foreach ([
            'url' => 'url',
            'email' => 'email',
            'name' => 'name',
            'ip' => 'ip',
            'user_agent' => 'user_agent',
        ] as $queryKey => $column) {
            if (isset($query[$queryKey]) && strlen((string) $query[$queryKey])) {
                $qb
                    ->andWhere($expr->eq(
                        "omeka_root.$column",
                        $this->createNamedParameter($qb, $query[$queryKey])
                    ));
            }
        }

        if (isset($query['has_file']) && strlen((string) $query['has_file'])) {
            if (empty($query['has_file'])) {
                $qb
                    ->andWhere($expr->isNull('omeka_root.storageId'));
            } else {
                $qb
                    ->andWhere($expr->isNotNull('omeka_root.storageId'));
            }
        }

        foreach ([
            'is_read' => 'isRead',
            'is_spam' => 'isSpam',
        ] as $queryKey => $column) {
            if (isset($query[$queryKey]) && strlen((string) $query[$queryKey])) {
                $qb
                    ->andWhere($expr->eq(
                        "omeka_root.$column",
                        $this->createNamedParameter($qb, (int) !empty($query[$queryKey]))
                    ));
            }
        }
    }

    public function hydrate(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \ContactUs\Entity\Message $entity */
        $data = $request->getContent();

        // Nothing can be updated, except flags.
        if ($request->getOperation() === Request::CREATE) {
            $this->hydrateOwner($request, $entity);
            $owner = $entity->getOwner();
            if ($owner) {
                $entity->setEmail($owner->getEmail());
                $entity->setName($owner->getName());
            } else {
                $entity->setEmail($data['o:email'] ?? null);
                $entity->setName($data['o:name'] ?? null);
            }

            $subject = $data['o-module-contact:subject'] ?? null;
            if ($subject = trim((string) $subject)) {
                $entity->setSubject($subject);
            }
            $body = $data['o-module-contact:body'] ?? null;
            if ($body = trim((string) $body)) {
                $entity->setBody($body);
            }

            $this->hydrateFile($request, $entity, $errorStore);

            $resource = !empty($data['o:resource']['o:id']) && is_numeric($data['o:resource']['o:id'])
                ? $this->getAdapter('resources')->findEntity(['id' => $data['o:resource']['o:id']])
                : null;
            $entity->setResource($resource);

            $site = !empty($data['o:site']['o:id']) && is_numeric($data['o:site']['o:id'])
                ? $this->getAdapter('sites')->findEntity(['id' => $data['o:site']['o:id']])
                : null;
            $entity->setSite($site);

            // Security data are freshed.
            $entity->setRequestUrl($this->getRequestUrl());
            $entity->setIp($this->getClientIp());
            $entity->setUserAgent($this->getUserAgent());

            $entity->setCreated(new \DateTime('now'));
        }

        if ($this->shouldHydrate($request, 'o-module-contact:is_read')) {
            $entity->setIsRead(!empty($data['o-module-contact:is_read']));
        }
        if ($this->shouldHydrate($request, 'o-module-contact:is_spam')) {
            $entity->setIsSpam(!empty($data['o-module-contact:is_spam']));
        }
    }

    /**
     * File can be uploaded via file data (like Asset).
     *
     * Any type of the whitelist in the main settings can be used. The check is
     * always processed, even if the file validation is disabled, because this
     * feature can be used by public.
     *
     * @todo Add a specific whitelist check for the public (more than assets, but less than main one).
     *
     * @param \Omeka\Api\Request $request
     * @param \Omeka\Entity\EntityInterface $entity
     * @param \Omeka\Stdlib\ErrorStore $errorStore
     */
    protected function hydrateFile(
        Request $request,
        EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        $fileData = $request->getFileData();
        $services = $this->getServiceLocator();
        // In some case, the file data are sent via base64.
        if (empty($fileData['file'][0]['tmp_name'])) {
            $fileData = ['file' => $request->getValue('file', [])];
            if (empty($fileData['file'][0]['base64'])) {
                return;
            }
            $tempFile = $this->fetchBase64File($fileData['file'][0], $errorStore);
        } else {
            // Standard way (form).
            $uploader = $services->get('Omeka\File\Uploader');
            /** @var \Omeka\File\TempFile $tempFile */
            $tempFile = $uploader->upload($fileData['file'], $errorStore);
        }

        if (!$tempFile) {
            return;
        }

        // The name is required because  there is a validator.
        $sourceName = isset($fileData['file'][0]['name']) && strlen(trim($fileData['file'][0]['name']))
            ? trim($fileData['file'][0]['name'])
            : null;
        $tempFile->setSourceName($sourceName);

        $settings = $services->get('Omeka\Settings');
        $validator = new Validator(
            $settings->get('media_type_whitelist', []),
            $settings->get('extension_whitelist', []),
            false
        );

        if (!$validator->validate($tempFile, $errorStore)) {
            return;
        }

        $this->hydrateOwner($request, $entity);
        $entity->setSource($request->getValue('o:source', $sourceName));
        $entity->setMediaType($tempFile->getMediaType());
        $entity->setStorageId($tempFile->getStorageId());
        $entity->setExtension($tempFile->getExtension());

        $tempFile->store(\ContactUs\Module::STORE_PREFIX);
        $tempFile->delete();
    }

    protected function fetchBase64File(array $fileData, ErrorStore $errorStore): ?TempFile
    {
        if (empty($fileData['base64'])) {
            return null;
        }

        /** @var \Omeka\File\TempFile $tempFile */
        $tempFile = $this->getServiceLocator()->get('Omeka\File\TempFileFactory')->build();
        $result = file_put_contents($tempFile->getTempPath(), base64_decode($fileData['base64'], true));
        if ($result === false) {
            $message = 'Error loading or saving base64 file.'; // @translate
            $errorStore->addError('file', $message);
            return null;
        }

        return $tempFile;
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \ContactUs\Entity\Message $entity */

        // When the user, the resource or the site are deleted, there is no
        // validation here, so it can be checked when created or updated?
        // No, because there may be multiple updates.
        // So the name and email are prefilled with current values if exist.
        $owner = $entity->getOwner();
        if (empty($owner)) {
            $email = $entity->getEmail();
            $validator = new EmailAddress();
            if (!$validator->isValid($email)) {
                $errorStore->addValidatorMessages('o:email', $validator->getMessages());
            }
        }

        $body = $entity->getBody();
        if (trim((string) $body) === '') {
            $errorStore->addError('o-module-contact:body', 'The body cannot be empty.'); // @translate
        }

        // Security data are automatically filled, but check is done anyway.
        $requestUrl = $entity->getRequestUrl();
        if ($requestUrl && !filter_var($requestUrl, FILTER_VALIDATE_URL)) {
            $errorStore->addError('o-module-contact:request_url', 'The request url is not valid.'); // @transalte
        }
        $ip = $entity->getIp();
        if (empty($ip) || $ip === '::') {
            $errorStore->addError('o-module-contact:ip', 'The ip cannot be empty.'); // @translate
        }
    }

    public function preprocessBatchUpdate(array $data, Request $request)
    {
        $updatables = [
            'o-module-contact:is_read' => true,
            'o-module-contact:is_spam' => true,
        ];
        $rawData = $request->getContent();
        $rawData = array_intersect_key($rawData, $updatables);
        return $rawData + $data;
    }

    /**
     * Get the request url.
     *
     * @return string
     */
    protected function getRequestUrl(): string
    {
        $serverUrl = $this->getServiceLocator()->get('ViewHelperManager')->get('ServerUrl');
        $requestUrl = $serverUrl(true);
        // Don't store credential when the api is used.
        $pos = mb_strpos($requestUrl, '?');
        return $pos === false
            ? $requestUrl
            : mb_substr($requestUrl, 0, $pos);
    }

    /**
     * Get the ip of the client.
     *
     * @return string
     */
    protected function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? $ip
            : '::';
    }

    /**
     * Get the user agent.
     *
     * @return string
     */
    protected function getUserAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}
