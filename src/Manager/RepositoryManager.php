<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Manager;

use FOS\ElasticaBundle\Finder\FinderInterface;
use FOS\ElasticaBundle\Repository;
use RuntimeException;

/**
 * @author Richard Miller <info@limethinking.co.uk>
 *
 * Allows retrieval of basic or custom repository for mapped Doctrine
 * entities/documents
 */
class RepositoryManager implements RepositoryManagerInterface
{
    /**
     * @var array
     */
    private $indexes;

    /**
     * @var array
     */
    private $repositories;

    public function __construct()
    {
        $this->indexes = [];
        $this->repositories = [];
    }

    public function addIndex($indexName, FinderInterface $finder, $repositoryName = null)
    {
        $this->indexes[$indexName] = [
            'finder' => $finder,
            'repositoryName' => $repositoryName,
        ];
    }

    /**
     * Return repository for entity.
     *
     * Returns custom repository if one specified otherwise
     * returns a basic repository.
     *
     * @param string $indexName
     *
     * @return Repository
     */
    public function getRepository($indexName)
    {
        if (isset($this->repositories[$indexName])) {
            return $this->repositories[$indexName];
        }

        if (!isset($this->indexes[$indexName])) {
            throw new RuntimeException(sprintf('No search finder configured for %s', $indexName));
        }

        $repository = $this->createRepository($indexName);
        $this->repositories[$indexName] = $repository;

        return $repository;
    }

    /**
     * @param $indexName
     *
     * @return string
     */
    protected function getRepositoryName($indexName)
    {
        if (isset($this->indexes[$indexName]['repositoryName'])) {
            return $this->indexes[$indexName]['repositoryName'];
        }

        return 'FOS\ElasticaBundle\Repository';
    }

    /**
     * @param $indexName
     *
     * @return mixed
     */
    private function createRepository($indexName)
    {
        if (!class_exists($repositoryName = $this->getRepositoryName($indexName))) {
            throw new RuntimeException(sprintf('%s repository for %s does not exist', $repositoryName, $indexName));
        }

        return new $repositoryName($this->indexes[$indexName]['finder']);
    }
}
