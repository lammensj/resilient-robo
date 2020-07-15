<?php

declare(strict_types=1);

namespace Resilient\Core\Plugins\RoboPlugin;

use Resilient\Core\RoboPlugin\RoboPluginDownloaderInterface;

/**
 * Class PhpPackagesRoboPlugin.
 */
class PhpPackagesRoboPlugin extends AbstractRoboPlugin implements RoboPluginDownloaderInterface
{

    /**
     * {@inheritdoc}
     */
    public function download(): array
    {
        $tasks = [];

        $sourceList = sprintf(
            '%s/assets/%s/composer.extra.json',
            $this->configFactory->get('assets_path'),
            $this->configFactory->get('project_type')
        );
        $destinList = sprintf('%s/composer.extra.json', $this->configFactory->get('project_root'));

        if (
            !file_exists($destinList)
            && file_exists($sourceList)
        ) {
            $tasks[] = $this->taskFilesystemStack()
                ->copy($sourceList, $destinList);
            $tasks[] = $this->taskComposerUpdate()
                ->workingDir($this->configFactory->get('project_root'))
                ->option('lock');
        }

        return $tasks;
    }
}
