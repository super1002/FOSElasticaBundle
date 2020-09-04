<?php

/**
 * This file is part of the FOSElasticaBundle project.
 *
 * (c) Tim Nagel <tim@nagel.com.au>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Configuration\Source;

use FOS\ElasticaBundle\Configuration\IndexTemplateConfig;

/**
 * Returns index and type configuration from the container.
 */
class TemplateContainerSource implements SourceInterface
{
    /**
     * The internal container representation of information.
     *
     * @var array
     */
    private $configArray;

    public function __construct(array $configArray)
    {
        $this->configArray = $configArray;
    }

    /**
     * Should return all configuration available from the data source.
     *
     * @return IndexTemplateConfig[]
     */
    public function getConfiguration()
    {
        $indexes = array();
        foreach ($this->configArray as $config) {
            $index = new IndexTemplateConfig($config);

            $indexes[$config['name']] = $index;
        }

        return $indexes;
    }
}
