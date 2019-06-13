<?php

declare(strict_types=1);

namespace Resilient\Core\Plugins\RoboPlugin;

use Resilient\Core\RoboPlugin\RoboPluginDownloaderInterface;
use Resilient\Core\RoboPlugin\RoboPluginInstallerInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class DrupalRoboPlugin.
 */
class DrupalRoboPlugin extends AbstractRoboPlugin implements RoboPluginDownloaderInterface, RoboPluginInstallerInterface
{

    protected const DRUPAL_PROJECT = 'lammensj/drupal-project:8.x-dev';

    /**
     * {@inheritdoc}
     */
    public function download(): array
    {
        $tasks = [];

        $defaultFolderPath = sprintf(
            '%s/%s/sites/default',
            $this->configFactory->get('frmwrk_path'),
            $this->configFactory->get('app_root')
        );

        if (!file_exists(
            sprintf('%s/settings.local.php', $defaultFolderPath)
        )) {
            if (file_exists(sprintf('%s/composer.json', $this->configFactory->get('frmwrk_path')))) {
                $tasks[] = $this->taskComposerInstall()
                    ->workingDir($this->configFactory->get('frmwrk_path'));
            } else {
                $tasks[] = $this->taskComposerCreateProject()
                    ->workingDir($this->configFactory->get('project_root'))
                    ->source(self::DRUPAL_PROJECT)
                    ->noInteraction()
                    ->target($this->configFactory->get('frmwrk_root'));
            }

            // Copy local settings files into Drupal directory.
            $source = sprintf(
                '%s/assets/drupal8/core',
                $this->configFactory->get('assets_path')
            );
            $tasks[] = $this->taskCopyDir(
                [$source => $defaultFolderPath]
            );

            // Insert database credentials.
            $localSettingsFilePath = sprintf('%s/settings.local.php', $defaultFolderPath);
            $tasks[] = $this->taskReplaceInFile($localSettingsFilePath)
                ->from('INSERT_DB_HOST')
                ->to($this->configFactory->get('database.host'));
            $tasks[] = $this->taskReplaceInFile($localSettingsFilePath)
                ->from('INSERT_DB_USER')
                ->to($this->configFactory->get('database.user'));
            $tasks[] = $this->taskReplaceInFile($localSettingsFilePath)
                ->from('INSERT_DB_PASSWORD')
                ->to($this->configFactory->get('database.password'));
            $tasks[] = $this->taskReplaceInFile($localSettingsFilePath)
                ->from('INSERT_DB_NAME')
                ->to($this->configFactory->get('database.name'));

            // Enable including local.settings.php.
            $settingsFilePath = sprintf('%s/settings.php', $defaultFolderPath);
            $tasks[] = $this->taskReplaceInFile($settingsFilePath)
                ->from('# if (file_exists')
                ->to('if (file_exists');
            $tasks[] = $this->taskReplaceInFile($settingsFilePath)
                ->from('#   include')
                ->to('  include');
            $tasks[] = $this->taskReplaceInFile($settingsFilePath)
                ->from('# }')
                ->to('}');

            // Protect the local settings files.
            $chmod = sprintf('chmod 644 %s/*local*', $defaultFolderPath);
            $tasks[] = $this->taskExec($chmod);
        }

        return $tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function install(): array
    {
        $tasks = [];

        // Include the necessary external tasks.
        $drushStackClass = '\Boedah\Robo\Task\Drush\DrushStack';

        $tasks[] = $this->task($drushStackClass)
            ->drupalRootDirectory($this->configFactory->get('frmwrk_path'))
            ->drush('sql-drop');

        $dbFiles = Finder::create()
            ->files()
            ->name('drupal8.sql*')
            ->in($this->configFactory->get('project_root'));
        if ($dbFiles->hasResults()) {
            $tasks[] = $this->task($drushStackClass)
                ->drupalRootDirectory($this->configFactory->get('frmwrk_path'))
                ->drush(sprintf('sqlq --file=%s', key(iterator_to_array($dbFiles))));
        } else {
            $task = $this->task($drushStackClass)
                ->drupalRootDirectory($this->configFactory->get('frmwrk_path'))
                ->accountName($this->configFactory->get('site.account.name'))
                ->accountMail($this->configFactory->get('site.account.email'))
                ->accountPass($this->configFactory->get('site.account.password'));

            if (file_exists(
                sprintf('%s/config/sync/core.extension.yml', $this->configFactory->get('frmwrk_path'))
            )) {
                $task->existingConfig();
            } else {
                $task
                    ->siteName($this->configFactory->get('site.name'))
                    ->siteMail($this->configFactory->get('site.email'));
                $tasks[] = $this->taskFilesystemStack()
                    ->mkdir(sprintf('%s/config/dev', $this->configFactory->get('frmwrk_path')));
            }

            $tasks[] = $task->siteInstall('resilient');
        }

        return $tasks;
    }
}
