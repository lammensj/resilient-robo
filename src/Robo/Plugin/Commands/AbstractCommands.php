<?php

declare(strict_types=1);

namespace Resilient\Robo\Plugin\Commands;

use DI\Container;
use DI\ContainerBuilder;
use Resilient\Core\Config\ConfigFactory;
use Resilient\Core\Config\ConfigFactoryInterface;
use Resilient\Core\RoboPlugin\RoboPluginFactory;
use Resilient\Core\RoboPlugin\RoboPluginFactoryInterface;
use Robo\Tasks;
use function DI\create;
use function DI\get;

/**
 * Class AbstractCommands.
 */
abstract class AbstractCommands extends Tasks
{

    /**
     * The container.
     *
     * @var \DI\Container
     */
    protected $container;

    /**
     * The collection builder.
     *
     * @var \Robo\Collection\CollectionBuilder
     */
    protected $collectionBuilder;

    /**
     * @var \Resilient\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * @var \Resilient\Core\RoboPlugin\RoboPluginFactory
     */
    protected $roboPluginFactory;

    /**
     * Load all the configuration file.
     */
    protected function initialize(): void
    {
        $this->collectionBuilder = $this->collectionBuilder();

        $this->configureContainer();

        $this->configFactory = $this->container->get(ConfigFactoryInterface::class);
        $this->roboPluginFactory = $this->container->get(RoboPluginFactoryInterface::class);
    }

    /**
     * Configure the container.
     *
     * @throws \Exception
     */
    private function configureContainer(): void
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions(
            [
                ConfigFactoryInterface::class => create(ConfigFactory::class),
                RoboPluginFactoryInterface::class => create(RoboPluginFactory::class)
                    ->constructor(get(Container::class)),
            ]
        );
        $this->container = $builder->build();
    }
}
