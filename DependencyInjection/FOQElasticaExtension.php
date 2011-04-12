<?php

namespace FOQ\ElasticaBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Config\FileLocator;

class FOQElasticaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('config.xml');

        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->process($configuration->getConfigTree(), $configs);

        if (empty ($config['default_client'])) {
            $keys = array_keys($config['clients']);
            $config['default_client'] = reset($keys);
        }

        if (empty ($config['default_index'])) {
            $keys = array_keys($config['indexes']);
            $config['default_index'] = reset($keys);
        }

        $clientsByName = $this->loadClients($config['clients'], $container);
        $indexesByName = $this->loadIndexes($config['indexes'], $container, $clientsByName, $config['default_client']);
        $this->loadIndexManager($indexesByName, $container);

        $container->setAlias('foq_elastica.client', sprintf('foq_elastica.client.%s', $config['default_client']));
        $container->setAlias('foq_elastica.index', sprintf('foq_elastica.index.%s', $config['default_index']));
    }

    /**
     * Loads the configured clients.
     *
     * @param array $config An array of clients configurations
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadClients(array $clients, ContainerBuilder $container)
    {
        $clientDefs = array();
        foreach ($clients as $name => $client) {
            $clientDefArgs = array(
                isset($client['host']) ? $client['host'] : null,
                isset($client['port']) ? $client['port'] : array(),
            );
            $clientDef = new Definition('%foq_elastica.client.class%', $clientDefArgs);
            $container->setDefinition(sprintf('foq_elastica.client.%s', $name), $clientDef);
            $clientDefs[$name] = $clientDef;
        }

        return $clientDefs;
    }

    /**
     * Loads the configured indexes.
     *
     * @param array $config An array of indexes configurations
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function loadIndexes(array $indexes, ContainerBuilder $container, array $clientsByName, $defaultClientName)
    {
        $indexDefs = array();
        foreach ($indexes as $name => $index) {
            if (isset($index['client'])) {
                $clientName = $index['client'];
                if (!isset($clientsByName[$clientName])) {
                    throw new InvalidArgumentException(sprintf('The elastica client with name "%s" is not defined', $clientName));
                }
            } else {
                $clientName = $defaultClientName;
            }
            $indexDefArgs = array($clientsByName[$clientName], $name);
            $indexDef = new Definition('%foq_elastica.index.class%', $indexDefArgs);
            $container->setDefinition(sprintf('foq_elastica.index.%s', $name), $indexDef);
            $indexDefs[$name] = $indexDef;
        }

        return $indexDefs;
    }

    /**
     * Loads the index manager
     *
     * @return null
     **/
    public function loadIndexManager(array $indexDefs, ContainerBuilder $container)
    {
        $managerDef = $container->getDefinition('foq_elastica.index_manager');
        $managerDef->setArgument(0, $indexDefs);
    }
}
