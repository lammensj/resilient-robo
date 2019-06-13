<?php

declare(strict_types=1);

namespace Resilient\Core\Plugins\RoboPlugin;

use Resilient\Core\RoboPlugin\RoboPluginDownloaderInterface;

/**
 * Class CircleCiRoboPlugin.
 */
class CircleCiRoboPlugin extends AbstractRoboPlugin implements RoboPluginDownloaderInterface
{

    /**
     * {@inheritdoc}
     */
    public function download(): array
    {
        $tasks = [];

        $circleCiDir = sprintf(
            '%s/assets/%s/circleci',
            $this->configFactory->get('assets_path'),
            $this->configFactory->get('project_type')
        );

        if (file_exists($circleCiDir)) {
            $configFile = sprintf(
                '%s/.circleci/config.yml',
                $this->configFactory->get('project_root')
            );
            $tasks[] = $this->taskFilesystemStack()
                ->mirror(
                    $circleCiDir,
                    sprintf(
                        '%s/.circleci',
                        $this->configFactory->get('project_root')
                    )
                );

            $tasks[] = $this->taskReplaceInFile($configFile)
                ->from('[docker_image]')
                ->to($this->configFactory->get('CLI_IMAGE'));

            $tasks[] = $this->taskReplaceInFile($configFile)
                ->from('[path_to_composer_working_directory]')
                ->to($this->configFactory->get('frmwrk_root'));
            $tasks[] = $this->taskReplaceInFile($configFile)
                ->from('[path_to_composer.lock]')
                ->to(
                    sprintf(
                        '%s/composer.lock',
                        $this->configFactory->get('frmwrk_root')
                    )
                );

            if ($themeRoot = $this->configFactory->get('deploy.theme_root')) {
                $themeDirectory = sprintf(
                    '%s/%s',
                    $this->configFactory->get('frmwrk_root'),
                    $themeRoot
                );
                $tasks[] = $this->taskReplaceInFile($configFile)
                    ->from('[path_to_theme]')
                    ->to($themeDirectory);
            }

            $deployFile = sprintf(
                '%s/.circleci/deploy.sh',
                $this->configFactory->get('project_root')
            );
            $tasks[] = $this->taskReplaceInFile($deployFile)
                ->from('[repo_id]')
                ->to($this->configFactory->get('deploy.repo_id'));
        }

        return $tasks;
    }
}
