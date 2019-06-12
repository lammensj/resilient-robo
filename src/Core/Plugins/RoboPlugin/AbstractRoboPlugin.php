<?php

declare(strict_types=1);

namespace Resilient\Core\Plugins\RoboPlugin;

use Resilient\Core\Config\ConfigFactoryInterface;
use Resilient\Core\RoboPlugin\RoboPluginInterface;
use Robo\Tasks;

/**
 * Class AbstractRoboPlugin.
 */
abstract class AbstractRoboPlugin extends Tasks implements RoboPluginInterface
{

    /**
     * The config factory.
     *
     * @var \Resilient\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * Drupal constructor.
     *
     * @param \Resilient\Core\Config\ConfigFactoryInterface $configFactory
     *   The config factory.
     */
    public function __construct(ConfigFactoryInterface $configFactory)
    {
        $this->configFactory = $configFactory;
    }
}
