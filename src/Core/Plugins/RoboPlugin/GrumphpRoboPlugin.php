<?php

declare(strict_types=1);

namespace Resilient\Core\Plugins\RoboPlugin;

use Resilient\Core\RoboPlugin\RoboPluginDownloaderInterface;

/**
 * Class GrumphpRoboPlugin.
 */
class GrumphpRoboPlugin extends AbstractRoboPlugin implements RoboPluginDownloaderInterface
{

    /**
     * {@inheritdoc}
     */
    public function download(): array
    {
        $tasks = [];

        $grumphpDir = sprintf(
            '%s/assets/%s/grumphp',
            $this->configFactory->get('setup_path'),
            $this->configFactory->get('project_type')
        );
        if (file_exists($grumphpDir)) {
            $tasks[] = $this->taskFilesystemStack()
                ->mirror(
                    $grumphpDir,
                    $this->configFactory->get('project_root'),
                    null,
                    ['override' => true]
                );
            $tasks[] = $this->taskReplaceInFile(
                sprintf('%s/grumphp.yml', $this->configFactory->get('project_root'))
            )
                ->from('[jira_code]')
                ->to($this->configFactory->get('jira_code'));
            $tasks[] = $this->taskExec(
                sprintf('%s/vendor/bin/grumphp git:deinit', $this->configFactory->get('project_root'))
            );
            $tasks[] = $this->taskExec(
                sprintf('%s/vendor/bin/grumphp git:init', $this->configFactory->get('project_root'))
            );
        }

        return $tasks;
    }
}
