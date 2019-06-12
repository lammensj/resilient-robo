<?php

declare(strict_types=1);

namespace Resilient\Core\RoboPlugin;

use DI\Container;
use DI\NotFoundException;
use Robo\Collection\CollectionBuilder;

/**
 * Class RoboPluginFactory.
 */
class RoboPluginFactory implements RoboPluginFactoryInterface
{

    /**
     * The container.
     *
     * @var \DI\Container
     */
    protected $container;

    /**
     * RoboPluginFactory constructor.
     *
     * @param \DI\Container $container
     *   The container.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function createInstance($name, array $configuration = []): RoboPluginInterface
    {
        if (!array_key_exists($name, self::MAPPING)) {
            throw new NotFoundException(sprintf('Robo plugin for key \'%s\' not found.', $name));
        }

        /** @var \Resilient\Core\RoboPlugin\RoboPluginInterface $instance */
        $instance = $this->container->get(self::MAPPING[$name]);
        if (!empty($configuration['collection_builder']) && $configuration['collection_builder'] instanceof CollectionBuilder) {
            $instance->setBuilder($configuration['collection_builder']);
        }

        return $instance;
    }
}
