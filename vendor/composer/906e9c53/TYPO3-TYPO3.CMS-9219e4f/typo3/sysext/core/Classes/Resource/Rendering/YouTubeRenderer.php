<?php
namespace TYPO3\CMS\Core\Resource\Rendering;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperInterface;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * YouTube renderer class
 */
class YouTubeRenderer implements FileRendererInterface
{
    /**
     * @var OnlineMediaHelperInterface
     */
    protected $onlineMediaHelper;

    /**
     * Returns the priority of the renderer
     * This way it is possible to define/overrule a renderer
     * for a specific file type/context.
     * For example create a video renderer for a certain storage/driver type.
     * Should be between 1 and 100, 100 is more important than 1
     *
     * @return int
     */
    public function getPriority()
    {
        return 1;
    }

    /**
     * Check if given File(Reference) can be rendered
     *
     * @param FileInterface $file File of FileReference to render
     * @return bool
     */
    public function canRender(FileInterface $file)
    {
        return ($file->getMimeType() === 'video/youtube' || $file->getExtension() === 'youtube') && $this->getOnlineMediaHelper($file) !== false;
    }

    /**
     * Get online media helper
     *
     * @param FileInterface $file
     * @return bool|OnlineMediaHelperInterface
     */
    protected function getOnlineMediaHelper(FileInterface $file)
    {
        if ($this->onlineMediaHelper === null) {
            $orgFile = $file;
            if ($orgFile instanceof FileReference) {
                $orgFile = $orgFile->getOriginalFile();
            }
            if ($orgFile instanceof File) {
                $this->onlineMediaHelper = OnlineMediaHelperRegistry::getInstance()->getOnlineMediaHelper($orgFile);
            } else {
                $this->onlineMediaHelper = false;
            }
        }
        return $this->onlineMediaHelper;
    }

    /**
     * Render for given File(Reference) html output
     *
     * @param FileInterface $file
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param array $options
     * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
     * @return string
     */
    public function render(FileInterface $file, $width, $height, array $options = null, $usedPathsRelativeToCurrentScript = false)
    {
        // Check for an autoplay option at the file reference itself, if not overriden yet.
        if (!isset($options['autoplay']) && $file instanceof FileReference) {
            $autoplay = $file->getProperty('autoplay');
            if ($autoplay !== null) {
                $options['autoplay'] = $autoplay;
            }
        }

        if ($file instanceof FileReference) {
            $orgFile = $file->getOriginalFile();
        } else {
            $orgFile = $file;
        }

        $videoId = $this->getOnlineMediaHelper($file)->getOnlineMediaId($orgFile);

        $urlParams = ['autohide=1'];
        if (!isset($options['controls']) || !empty($options['controls'])) {
            $urlParams[] = 'controls=2';
        }
        if (!empty($options['autoplay'])) {
            $urlParams[] = 'autoplay=1';
        }
        if (!empty($options['loop'])) {
            $urlParams[] = 'loop=1&amp;playlist=' . $videoId;
        }
        if (isset($options['relatedVideos'])) {
            $urlParams[] = 'rel=' . (int)(bool)$options['relatedVideos'];
        }
        if (!isset($options['enablejsapi']) || !empty($options['enablejsapi'])) {
            $urlParams[] = 'enablejsapi=1&amp;origin=' . rawurlencode(GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST'));
        }
        $urlParams[] = 'showinfo=' . (int)!empty($options['showinfo']);

        $src = sprintf(
            'https://www.youtube%s.com/embed/%s?%s',
            !empty($options['no-cookie']) ? '-nocookie' : '',
            $videoId,
            implode('&amp;', $urlParams)
        );

        $attributes = ['allowfullscreen'];
        if ((int)$width > 0) {
            $attributes[] = 'width="' . (int)$width . '"';
        }
        if ((int)$height > 0) {
            $attributes[] = 'height="' . (int)$height . '"';
        }
        if (is_object($GLOBALS['TSFE']) && $GLOBALS['TSFE']->config['config']['doctype'] !== 'html5') {
            $attributes[] = 'frameborder="0"';
        }
        foreach (['class', 'dir', 'id', 'lang', 'style', 'title', 'accesskey', 'tabindex', 'onclick', 'poster', 'preload'] as $key) {
            if (!empty($options[$key])) {
                $attributes[] = $key . '="' . htmlspecialchars($options[$key]) . '"';
            }
        }

        return sprintf(
            '<iframe src="%s"%s></iframe>',
            $src,
            empty($attributes) ? '' : ' ' . implode(' ', $attributes)
        );
    }
}
