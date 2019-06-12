<?php

declare(strict_types=1);

namespace Resilient\Core\Config;

/**
 * Interface ConfigFactoryInterface.
 */
interface ConfigFactoryInterface
{

    /**
     * Returns a configuration value for a given key.
     *
     * @param string $name
     *   The name of the configuration value.
     *
     * @return mixed
     *   A configuration value.
     */
    public function get($name);

    /**
     * Set a configuration value for a given key.
     *
     * @param string $name
     *   The name of the configuration value.
     * @param mixed $value
     *   The value of the configuration.
     *
     * @return $this
     *   Returns the called object.
     */
    public function set($name, $value): ConfigFactoryInterface;

    /**
     * Returns a list of configuration values for the given names.
     *
     * @param array $names
     *   List of names of configuration values.
     *
     * @return array
     *   List of successfully loaded configuration values, keyed by name.
     */
    public function loadMultiple(array $names): array;

    /**
     * Get the configuration prefix.
     *
     * @return string
     *   Returns the configuration prefix.
     */
    public function getConfigPrefix(): string;

}
