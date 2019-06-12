<?php

declare(strict_types=1);

namespace Resilient\Robo\Plugin\Commands;

use Robo\Tasks;
use Symfony\Component\Finder\Finder;

/**
 * Class ScaffoldCommands.
 */
class ScaffoldCommands extends Tasks
{

    /**
     * Prepares the local files.
     *
     * @command resilient:scaffold
     */
    public function scaffold()
    {
        $finder = Finder::create();
        $finder
            ->files()
            ->in(getcwd())
            ->ignoreDotFiles(false)
            ->exclude(['assets', 'vendor', 'htdocs', 'docroot', 'web', 'app'])
            ->name(['example.*', 'default.*']);

        $commands = $this->collectionBuilder();
        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $commands->addTask(
                $this->taskFilesystemStack()
                    ->copy(
                        $file->getPathname(),
                        preg_replace(
                            '/((example|default)\.)/',
                            '',
                            $file->getPathname()
                        )
                    )
            );
        }

        return $commands;
    }
}
