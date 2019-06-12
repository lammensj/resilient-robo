<?php

declare(strict_types=1);

namespace Resilient\Core\Config;

use Resilient\Core\RoboPlugin\RoboPluginFactoryInterface;
use Robo\Robo;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Class ConfigFactory.
 */
class ConfigFactory implements ConfigFactoryInterface
{

    /**
     * The cached data.
     *
     * @var array|\Consolidation\Config\ConfigInterface|\Robo\Config
     */
    protected static $cache = [];
    /**
     * The config.
     *
     * @var \Consolidation\Config\ConfigInterface
     */
    protected $config;

    /**
     * ConfigFactory constructor.
     */
    public function __construct()
    {
        if (empty($this->config)) {
            Robo::loadConfiguration(
                [
                    getenv('ROBO_CONFIG'),
                ]
            );
            $this->config = Robo::config();
            $this->validateConfig();
            $this->generateExtraConfig();
        }
    }

    /**
     * Validate the provides configuration.
     */
    private function validateConfig()
    {
        $errorMessages = [];

        if ($this->get('VIRTUAL_HOST') === 'customize-me.docksal') {
            $errorMessages[] = '- The VIRTUAL_HOST parameter in the docksal-local.env file has not been modified.';
        }

        $projectType = $this->get('project_type');
        if (!$projectType || !array_key_exists($projectType, RoboPluginFactoryInterface::MAPPING)) {
            $errorMessages[] = sprintf(
                '- The project_type parameter in the robo.yml file is not set or doesn\'t have a supported site type (found %s).',
                $projectType
            );
        }

        $jiraCode = $this->get('jira_code');
        if ($jiraCode === 'CUSTOMIZEME') {
            $errorMessages[] = '- The jira_code parameter in the robo.yml file has not been modified.';
        }

        if (!empty($errorMessages)) {
            throw new InvalidConfigurationException(implode("\n", $errorMessages));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        $configKey = sprintf('%s.%s', $this->getConfigPrefix(), strtolower($name));
        if (!$this->config->has($configKey)) {
            $value = getenv($name);
            $this->config->set($configKey, $value);
            self::$cache[$configKey] = $value;

            return $value;
        }

        if (!empty(self::$cache[$configKey])) {
            return self::$cache[$configKey];
        }

        $value = $this->config->get($configKey);
        self::$cache[$configKey] = $value;

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigPrefix(): string
    {
        return 'command.resilient';
    }

    /**
     * Generates extra configuration.
     */
    private function generateExtraConfig(): void
    {
        $projectRoot = explode(' ', $this->get('PROJECT_ROOT'))[0];
        $this->config->set(
            sprintf('%s.project_root', $this->getConfigPrefix()),
            $projectRoot
        );
        $this->config->set(
            sprintf('%s.setup_path', $this->getConfigPrefix()),
            realpath(dirname($this->get('ROBO_CONFIG')))
        );
        $frmwrkPath = implode('/', [$projectRoot, $this->get('frmwrk_root')]);
        $frmwrkPath = array_reduce(
            explode('/', $frmwrkPath),
            function ($a, $b) {
                if ($a === 0) {
                    $a = '/';
                }
                if ($b === '' || $b === '.') {
                    return $a;
                }
                if ($b === '..') {
                    return dirname($a);
                }

                return preg_replace('/\/+/', '/', sprintf('%s/%s', $a, $b));
            },
            0
        );
        $this->config->set(
            sprintf('%s.frmwrk_path', $this->getConfigPrefix()),
            $frmwrkPath
        );
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value): ConfigFactoryInterface
    {
        $this->config->set($name, $value);
        self::$cache[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function loadMultiple(array $names): array
    {
        $list = [];
        foreach ($names as $key => $name) {
            $list[$name] = $this->get($name);
        }

        return $list;
    }
}
