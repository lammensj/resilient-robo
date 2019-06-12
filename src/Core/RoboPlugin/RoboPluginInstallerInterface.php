<?php

declare(strict_types=1);

namespace Resilient\Core\RoboPlugin;

/**
 * Interface RoboPluginDownloadInterface.
 */
interface RoboPluginInstallerInterface extends RoboPluginInterface
{

    /**
     * Prepares tasks for installing a framework.
     *
     * @return array
     *   Returns an array of tasks.
     */
    public function install(): array;
}
