<?php

namespace FOQ\ElasticaBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

/**
 * Searches a type
 */
class SearchCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('type', InputArgument::REQUIRED, 'The type to search in'),
                new InputArgument('query', InputArgument::REQUIRED, 'The text to search'),
            ))
            ->addOption('index', null, InputOption::VALUE_NONE, 'The index to search in')
            ->setName('foq:elastica:search')
            ->setDescription('Searches documents in a given type and index');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $index  = $this->container->get('foq_elastica.index_manager')->getIndex($input->getOption('index'));
        $type   = $index->getType($input->getArgument('type'));
        $query  = $input->getArgument('query');

        $resultSet = $type->search($query);

        $output->writeLn(sprintf('Found %d results', $resultSet->count()));
        foreach ($resultSet->getResults() as $result) {
            $source = $result->getSource();
            $output->writeLn(sprintf('[%0.2f] %s', $result->getScore(), var_export(reset($source), true)));
        }
    }
}
