<?php declare(strict_types=1);

namespace ContactUs\Job;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Job\AbstractJob;
use ZipArchive;

class ZipResources extends AbstractJob
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');

        /**
         * @var \Omeka\Api\Manager $api
         */
        $services = $this->getServiceLocator();

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('contactus/zip/job_' . $this->job->getId());

        $this->logger = $services->get('Omeka\Logger');
        $this->logger->addProcessor($referenceIdProcessor);
        $api = $services->get('Omeka\ApiManager');

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $ids = $this->getArg('id');
        $zipFilename = $this->getArg('filename');
        $baseDir = $this->getArg('baseDir');
        $type = $this->getArg('type');

        if (empty($ids)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err('No resource id set.'); // @translate
            return;
        }
        if (empty($zipFilename)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err('No filename set.'); // @translate
            return;
        }
        if (empty($baseDir)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err('No basepath set.'); // @translate
            return;
        }
        if (empty($type)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err('No image type set.'); // @translate
            return;
        }

        if (!class_exists('ZipArchive', false)) {
            $this->logger()->err('The php extension "php-zip" must be installed.'); // @translate
            return;
        }

        // Check if resources have files.
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        $ids = array_unique(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err('Resource id is invalid.'); // @translate
            return;
        }

        $items = $api->search('items', ['id' => $ids])->getContent();
        $media = $api->search('media', ['id' => $ids])->getContent();
        $resources = array_merge($items, $media);
        if (!$resources) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            $this->logger->err('No valid resource.'); // @translate
            return;
        }

        $mediaData = [];
        foreach ($resources as $resource) {
            if ($resource instanceof MediaRepresentation) {
                $medias = [$resource];
            } else {
                $medias = $resource->media();
            }
            /** @var \Omeka\Api\Representation\MediaRepresentation $media */
            foreach ($medias as $media) {
                if (($type === 'original' && $media->hasOriginal())
                    || ($type !== 'original' && $media->hasThumbnails())
                ) {
                    $filename = $media->filename();
                    $filepath = $basePath . '/' . $type . '/' . $filename;
                    if (!file_exists($filepath)) {
                        continue;
                    }
                    $mediaType = $media->mediaType();
                    $mediaId = $media->id();
                    $mainType = strtok($mediaType, '/');
                    $extension = $media->extension();
                    $mediaData[$mediaId] = [
                        'id' => $mediaId,
                        'source' => $media->source() ?: $media->filename(),
                        'filename' => $media->filename(),
                        'filepath' => $filepath,
                        'mediatype' => $mediaType,
                        'maintype' => $mainType,
                        'extension' => $extension,
                        'size' => $media->size(),
                    ];
                }
            }
        }
        if (!count($mediaData)) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            if ($type === 'original') {
                $this->logger->err('No file attached to selected items.'); // @translate
            } else {
                $this->logger->err(
                    'No derivative file of type {type} attached to selected items.', // @translate
                    ['type' => $type]
                );
            }
            return;
        }

        $zipFilepath = $basePath . '/' . $baseDir . '/' . $zipFilename;
        if (file_exists($zipFilepath)) {
            @unlink($zipFilepath);
        }

        $result = $this->prepareDerivativeZip('zip', $zipFilepath, $mediaData);
        if (!$result) {
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            return;
        }

        // Use the direct uri because the message is not known here.
        $zipUri = $this->getBaseUri() . '/' . $baseDir . '/' . $zipFilename;
        $this->logger->notice(
            'Zip created successfully and available directly at {url}.', // @translate
            ['url' => $zipUri]
        );
    }

    /**
     * @see \ContactUs\Job\ZipResources
     * @see \DerivativeMedia\Mvc\Controller\Plugin\CreateDerivative
     */
    protected function prepareDerivativeZip(string $type, string $filepath, array $mediaData): ?bool
    {
        $services = $this->getServiceLocator();

        if (!class_exists('ZipArchive', false)) {
            $this->logger->err('The php extension "php-zip" must be installed.'); // @translate
            return false;
        }

        if (!$this->ensureDirectory(dirname($filepath))) {
            $this->logger->err('Unable to create directory in "/files/".'); // @translate
            return false;
        }

        // ZipArchive::OVERWRITE is available only in php 8.
        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE) !== true) {
            $this->logger->err('Unable to create the zip file.'); // @translate
            return false;
        }

        $url = $services->get('ViewHelperManager')->get('url');

        // Here, the site may not be available, so can't store item site url.
        $comment = $services->get('Omeka\Settings')->get('installation_title') . ' [' . $url('top', [], ['force_canonical' => true]) . ']';
        $zip->setArchiveComment($comment);

        // Store files: they are all already compressed (image, video, pdf...),
        // except some txt, xml and old docs.
        $index = 0;
        $filenames = [];
        foreach ($mediaData as $file) {
            $zip->addFile($file['filepath']);
            // Light and quick compress text and xml.
            if ($file['maintype'] === 'text'
                || $file['mediatype'] === 'application/json'
                || substr($file['mediatype'], -5) === '+json'
                || $file['mediatype'] === 'application/xml'
                || substr($file['mediatype'], -4) === '+xml'
            ) {
                $zip->setCompressionIndex($index, ZipArchive::CM_DEFLATE, 9);
            } else {
                $zip->setCompressionIndex($index, ZipArchive::CM_STORE);
            }

            // Use the source name, but check and rename for unique filename,
            // taking care of extension.
            $basepath = pathinfo($file['source'], PATHINFO_FILENAME);
            $extension = pathinfo($file['source'], PATHINFO_EXTENSION);
            $i = 0;
            do {
                $sourceBase = $basepath . ($i ? '.' . $i : '') . (strlen($extension) ? '.' . $extension : '');
                ++$i;
            } while (in_array($sourceBase, $filenames));
            $filenames[] = $sourceBase;
            $zip->renameName($file['filepath'], $sourceBase);
            ++$index;
        }

        // Only available before close before php 8.
        $status = $zip->getStatusString();

        $result = $zip->close();

        if (!$result) {
            $this->logger->err(
                'An issue occurred during the creation of the zip file: {error}.', // @translate
                ['error' => $status]
            );
            return false;
        }

        if (!file_exists($filepath)) {
            $this->logger->err(
                'An issue occurred and the zip file was not created: {error}.', // @translate
                ['error' => $status]
            );
            return false;
        }

        return true;
    }

    protected function ensureDirectory(string $dirpath): bool
    {
        if (file_exists($dirpath)) {
            return true;
        }
        return mkdir($dirpath, 0775, true);
    }

    /**
     * @todo To get the base uri is useless now, since base uri is passed as job argument.
     */
    protected function getBaseUri(): string
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $baseUri = $config['file_store']['local']['base_uri'];
        if (!$baseUri) {
            $helpers = $services->get('ViewHelperManager');
            $serverUrlHelper = $helpers->get('ServerUrl');
            $basePathHelper = $helpers->get('basePath');
            $baseUri = $serverUrlHelper($basePathHelper('files'));
            if ($baseUri === 'http:///files' || $baseUri === 'https:///files') {
                $t = $services->get('MvcTranslator');
                throw new \Omeka\Mvc\Exception\RuntimeException(
                    sprintf(
                        $t->translate('The base uri is not set (key [file_store][local][base_uri]) in the config file of Omeka "config/local.config.php". It must be set for now (key [file_store][local][base_uri]) in order to process background jobs.'), //@translate
                        $baseUri
                    )
                );
            }
        }
        return $baseUri;
    }
}
