<?php

declare(strict_types=1);

namespace Resilient\Core\Plugins\RoboPlugin;

use Resilient\Core\RoboPlugin\RoboPluginDownloaderInterface;

/**
 * Class LaravelRoboPlugin.
 */
class LaravelRoboPlugin extends AbstractRoboPlugin implements RoboPluginDownloaderInterface
{

    protected const LARAVEL_PROJECT = 'laravel/laravel';
    protected const LARAVEL_BACKUP = 'spatie/laravel-backup';

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
                    ->source(self::LARAVEL_PROJECT)
                    ->target($this->configFactory->get('frmwrk_root'));
                $tasks[] = $this->taskComposerRequire()
                    ->workingDir($this->configFactory->get('frmwrk_path'))
                    ->dependency(self::LARAVEL_BACKUP);
            }

            // Insert database credentials
            $tasks[] = $this->taskReplaceInFile($envFilePath)
                ->from('DB_HOST=127.0.0.1')
                ->to(sprintf('DB_HOST=%s', $this->configFactory->get('database.host')));
            $tasks[] = $this->taskReplaceInFile($envFilePath)
                ->from('DB_USERNAME=homestead')
                ->to(sprintf('DB_USERNAME=%s', $this->configFactory->get('database.user')));
            $tasks[] = $this->taskReplaceInFile($envFilePath)
                ->from('DB_PASSWORD=secret')
                ->to(sprintf('DB_PASSWORD=%s', $this->configFactory->get('database.password')));
            $tasks[] = $this->taskReplaceInFile($envFilePath)
                ->from('DB_DATABASE=homestead')
                ->to(sprintf('DB_DATABASE=%s', $this->configFactory->get('database.name')));
        }

        return $tasks;
    }
}
