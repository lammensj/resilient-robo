<?php

declare(strict_types=1);

namespace Resilient\Core\Plugins\RoboPlugin;

use Resilient\Core\RoboPlugin\RoboPluginDownloaderInterface;

/**
 * Class SymfonyRoboPlugin.
 */
class SymfonyRoboPlugin extends AbstractRoboPlugin implements RoboPluginDownloaderInterface
{

    protected const SF_PROJECT = 'symfony/website-skeleton';

    /**
     * {@inheritdoc}
     */
    public function download(): array
    {
        $tasks = [];

        $envFilePath = sprintf('%s/.env', $this->configFactory->get('frmwrk_path'));
        if (!file_exists($envFilePath)) {
            if (file_exists(sprintf('%s/composer.json', $this->configFactory->get('frmwrk_path')))) {
                $tasks[] = $this->taskComposerInstall()
                    ->workingDir($this->configFactory->get('frmwrk_path'));
            } else {
                $tasks[] = $this->taskComposerCreateProject()
                    ->workingDir($this->configFactory->get('project_root'))
                    ->source(self::SF_PROJECT)
                    ->target($this->configFactory->get('frmwrk_root'));
            }

            $dbUrl = sprintf(
                'DATABASE_URL=mysql://%s:%s@%s:3306/%s',
                $this->configFactory->get('database.user'),
                $this->configFactory->get('database.password'),
                $this->configFactory->get('database.host'),
                $this->configFactory->get('database.name')
            );
            $tasks[] = $this->taskReplaceInFile($envFilePath)
                ->from('DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3306/db_name')
                ->to($dbUrl);
        }

        return $tasks;
    }
}
