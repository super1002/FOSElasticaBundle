<?php

namespace FOS\ElasticaBundle\Command;

use Elastica\Exception\Bulk\ResponseException as BulkResponseException;
use FOS\ElasticaBundle\Event\IndexPopulateEvent;
use FOS\ElasticaBundle\Event\TypePopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Index\Resetter;
use FOS\ElasticaBundle\Persister\Event\Events;
use FOS\ElasticaBundle\Persister\Event\OnExceptionEvent;
use FOS\ElasticaBundle\Persister\Event\PostAsyncInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\Event\PostInsertObjectsEvent;
use FOS\ElasticaBundle\Persister\InPlacePagerPersister;
use FOS\ElasticaBundle\Persister\PagerPersisterInterface;
use FOS\ElasticaBundle\Persister\PagerPersisterRegistry;
use FOS\ElasticaBundle\Provider\PagerProviderRegistry;
use FOS\ElasticaBundle\Provider\ProviderRegistry;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\Question;

/**
 * Populate the search index.
 */
class PopulateCommand extends ContainerAwareCommand
{
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var IndexManager
     */
    private $indexManager;

    /**
     * @var ProgressClosureBuilder
     */
    private $progressClosureBuilder;

    /**
     * @var ProviderRegistry
     */
    private $providerRegistry;

    /**
     * @var PagerProviderRegistry
     */
    private $pagerProviderRegistry;

    /**
     * @var PagerPersisterInterface
     */
    private $pagerPersister;

    /**
     * @var Resetter
     */
    private $resetter;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('fos:elastica:populate')
            ->addOption('index', null, InputOption::VALUE_OPTIONAL, 'The index to repopulate')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'The type to repopulate')
            ->addOption('no-reset', null, InputOption::VALUE_NONE, 'Do not reset index before populating')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, '[DEPRECATED] Start indexing at offset', 0)
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Sleep time between persisting iterations (microseconds)', 0)
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, '[DEPRECATED] Index packet size (overrides provider config option)')
            ->addOption('ignore-errors', null, InputOption::VALUE_NONE, 'Do not stop on errors')
            ->addOption('no-overwrite-format', null, InputOption::VALUE_NONE, 'Prevent this command from overwriting ProgressBar\'s formats')

            ->addOption('first-page', null, InputOption::VALUE_REQUIRED, 'The pager\'s page to start population from. Including the given page.', 1)
            ->addOption('last-page', null, InputOption::VALUE_REQUIRED, 'The pager\'s page to end population on. Including the given page.', null)
            ->addOption('max-per-page', null, InputOption::VALUE_REQUIRED, 'The pager\'s page size', 100)
            ->addOption('pager-persister', null, InputOption::VALUE_REQUIRED, 'The pager persister to be used to populate the index', InPlacePagerPersister::NAME)

            ->setDescription('Populates search indexes from providers')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->dispatcher = $this->getContainer()->get('event_dispatcher');
        $this->indexManager = $this->getContainer()->get('fos_elastica.index_manager');
        $this->providerRegistry = $this->getContainer()->get('fos_elastica.provider_registry');
        $this->pagerProviderRegistry = $this->getContainer()->get('fos_elastica.pager_provider_registry');
        $this->resetter = $this->getContainer()->get('fos_elastica.resetter');
        $this->progressClosureBuilder = new ProgressClosureBuilder();

        /** @var PagerPersisterRegistry $pagerPersisterRegistry */
        $pagerPersisterRegistry = $this->getContainer()->get('fos_elastica.pager_persister_registry');
        $this->pagerPersister = $pagerPersisterRegistry->getPagerPersister($input->getOption('pager-persister'));

        if (!$input->getOption('no-overwrite-format') && class_exists('Symfony\\Component\\Console\\Helper\\ProgressBar')) {
            ProgressBar::setFormatDefinition('normal', " %current%/%max% [%bar%] %percent:3s%%\n%message%");
            ProgressBar::setFormatDefinition('verbose', " %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%\n%message%");
            ProgressBar::setFormatDefinition('very_verbose', " %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%\n%message%");
            ProgressBar::setFormatDefinition('debug', " %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%\n%message%");
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $index = $input->getOption('index');
        $type = $input->getOption('type');
        $reset = !$input->getOption('no-reset');
        $options = array(
            'ignore_errors' => $input->getOption('ignore-errors'),
            'offset' => $input->getOption('offset'),
            'sleep' => $input->getOption('sleep'),
            'first_page' => $input->getOption('first-page'),
            'max_per_page' => $input->getOption('max-per-page'),
        );
        if ($input->getOption('last-page')) {
            $options['last_page'] = $input->getOption('last-page');
        }

        if ($input->getOption('batch-size')) {
            $options['batch_size'] = (int) $input->getOption('batch-size');
        }

        if ($input->isInteractive() && $reset && $input->getOption('offset')) {
            /** @var QuestionHelper $dialog */
            $dialog = $this->getHelperSet()->get('question');
            if (!$dialog->ask($input, $output, new Question('<question>You chose to reset the index and start indexing with an offset. Do you really want to do that?</question>'))) {
                return;
            }
        }

        if (null === $index && null !== $type) {
            throw new \InvalidArgumentException('Cannot specify type option without an index.');
        }

        if (null !== $index) {
            if (null !== $type) {
                $this->populateIndexType($output, $index, $type, $reset, $options);
            } else {
                $this->populateIndex($output, $index, $reset, $options);
            }
        } else {
            $indexes = array_keys($this->indexManager->getAllIndexes());

            foreach ($indexes as $index) {
                $this->populateIndex($output, $index, $reset, $options);
            }
        }
    }

    /**
     * Recreates an index, populates its types, and refreshes the index.
     *
     * @param OutputInterface $output
     * @param string          $index
     * @param boolean         $reset
     * @param array           $options
     */
    private function populateIndex(OutputInterface $output, $index, $reset, $options)
    {
        $event = new IndexPopulateEvent($index, $reset, $options);
        $this->dispatcher->dispatch(IndexPopulateEvent::PRE_INDEX_POPULATE, $event);

        if ($event->isReset()) {
            $output->writeln(sprintf('<info>Resetting</info> <comment>%s</comment>', $index));
            $this->resetter->resetIndex($index, true);
        }

        $types = array_keys($this->providerRegistry->getIndexProviders($index));
        foreach ($types as $type) {
            $this->populateIndexType($output, $index, $type, false, $event->getOptions());
        }

        $this->dispatcher->dispatch(IndexPopulateEvent::POST_INDEX_POPULATE, $event);

        $this->refreshIndex($output, $index, $reset);
    }

    /**
     * Deletes/remaps an index type, populates it, and refreshes the index.
     *
     * @param OutputInterface $output
     * @param string          $index
     * @param string          $type
     * @param boolean         $reset
     * @param array           $options
     */
    private function populateIndexType(OutputInterface $output, $index, $type, $reset, $options)
    {
        $event = new TypePopulateEvent($index, $type, $reset, $options);
        $this->dispatcher->dispatch(TypePopulateEvent::PRE_TYPE_POPULATE, $event);

        if ($event->isReset()) {
            $output->writeln(sprintf('<info>Resetting</info> <comment>%s/%s</comment>', $index, $type));
            $this->resetter->resetIndexType($index, $type);
        }

        $offset = $options['offset'];
        $loggerClosure = $this->progressClosureBuilder->build($output, 'Populating', $index, $type, $offset);

        if ($this->getContainer()->getParameter('fos_elastica.use_v5_api') || getenv('FOS_ELASTICA_USE_V5_API')) {
            if ($loggerClosure) {
                $this->dispatcher->addListener(
                    Events::ON_EXCEPTION, 
                    function(OnExceptionEvent $event) use ($loggerClosure, $options) {
                        $loggerClosure(
                            $options['batch_size'],
                            count($event->getObjects()),
                            sprintf('<error>%s</error>', $event->getException()->getMessage())
                        );
                    }
                );

                $this->dispatcher->addListener(
                    Events::POST_INSERT_OBJECTS,
                    function(PostInsertObjectsEvent $event) use ($loggerClosure) {
                        $loggerClosure(count($event->getObjects()), $event->getPager()->getNbResults());
                    }
                );

                $this->dispatcher->addListener(
                    Events::POST_ASYNC_INSERT_OBJECTS,
                    function(PostAsyncInsertObjectsEvent $event) use ($loggerClosure) {
                        $loggerClosure($event->getObjectsCount(), $event->getPager()->getNbResults(), $event->getErrorMessage());
                    }
                );
            }
            
            if ($options['ignore_errors']) {
                $this->dispatcher->addListener(Events::ON_EXCEPTION, function(OnExceptionEvent $event) {
                    if ($event->getException() instanceof BulkResponseException) {
                        $event->setIgnore(true);
                    }
                });
            }
            
            $provider = $this->pagerProviderRegistry->getProvider($index, $type);

            $pager = $provider->provide($options);

            $options['indexName'] = $index;
            $options['typeName'] = $type;
            $options['batch_size'] = 100;
            $options['skip_indexable_check'] = false;

            $this->pagerPersister->insert($pager, $options);
        } else {
            unset(
                $options['first_page'],
                $options['last_page'],
                $options['max_per_page'],
                $options['pager_provider']
            );

            $provider = $this->providerRegistry->getProvider($index, $type);
            $provider->populate($loggerClosure, $options);
        }

        $this->dispatcher->dispatch(TypePopulateEvent::POST_TYPE_POPULATE, $event);

        $this->refreshIndex($output, $index, false);
    }

    /**
     * Refreshes an index.
     *
     * @param OutputInterface $output
     * @param string          $index
     * @param bool            $postPopulate
     */
    private function refreshIndex(OutputInterface $output, $index, $postPopulate = true)
    {
        if ($postPopulate) {
            $this->resetter->postPopulate($index);
        }

        $output->writeln(sprintf('<info>Refreshing</info> <comment>%s</comment>', $index));
        $this->indexManager->getIndex($index)->refresh();
    }
}
