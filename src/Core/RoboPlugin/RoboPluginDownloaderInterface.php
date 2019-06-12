<?php

declare(strict_types=1);

namespace Resilient\Core\RoboPlugin;

/**
 * Interface RoboPluginDownloadInterface.
 */
interface RoboPluginDownloaderInterface extends RoboPluginInterface
{

    /**
     * Prepares tasks for downloading a framework.
     *
     * @return array
     *   Returns an array of tasks.
     */
    public function download(): array;
}
