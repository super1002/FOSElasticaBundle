<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Tests\Unit\Persister;

use FOS\ElasticaBundle\Message\AsyncPersistPage;
use FOS\ElasticaBundle\Persister\AsyncPagerPersister;
use FOS\ElasticaBundle\Persister\InPlacePagerPersister;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use FOS\ElasticaBundle\Persister\PagerPersisterInterface;
use FOS\ElasticaBundle\Persister\PagerPersisterRegistry;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\PagerInterface;
use FOS\ElasticaBundle\Provider\PagerProviderInterface;
use FOS\ElasticaBundle\Provider\PagerProviderRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class AsyncPagerPersisterTest extends TestCase
{
    public function testShouldImplementPagerPersisterInterface()
    {
        $reflectionClass = new \ReflectionClass(AsyncPagerPersister::class);
        $this->assertTrue($reflectionClass->implementsInterface(PagerPersisterInterface::class));
    }

    public function testInsertDispatchAsyncPersistPageObject()
    {
        $pagerPersisterRegistry = new PagerPersisterRegistry($this->createMock(ServiceLocator::class));
        $pagerProviderRegistry = $this->createMock(PagerProviderRegistry::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $sut = new AsyncPagerPersister($pagerPersisterRegistry, $pagerProviderRegistry, $messageBus);

        $messageBus->expects($this->once())->method('dispatch')->with(
            $this->callback(
                function ($message) {
                    return $message instanceof AsyncPersistPage;
                }
            )
        )->willReturn(new Envelope(new AsyncPersistPage(0, [])));

        $pager = $this->createMock(PagerInterface::class);
        $sut->insert($pager);
    }

    public function testInsertPageReturnObjectCount()
    {
        $persistersLocator = $this->createMock(ServiceLocator::class);
        $persistersLocator->expects($this->once())->method('has')->with('foo')->willReturn(true);
        $persistersLocator->expects($this->once())->method('get')->with('foo')->willReturn($this->createMock(ObjectPersisterInterface::class));

        $pagerPersistersLocator = $this->createMock(ServiceLocator::class);
        $pagerPersistersLocator->expects($this->once())->method('has')->with('in_place')->willReturn(true);
        $pagerPersistersLocator->expects($this->once())->method('get')->with('in_place')->willReturn(
            new InPlacePagerPersister(
                new PersisterRegistry($persistersLocator),
                $this->createMock(EventDispatcherInterface::class)
            )
        );

        $pagerPersisterRegistry = new PagerPersisterRegistry($pagerPersistersLocator);

        $pagerMock = $this->createMock(PagerInterface::class);
        $pagerMock->expects($this->exactly(2))->method('setMaxPerPage')->with(10);
        $pagerMock->method('setCurrentPage')->withConsecutive([1], [1], [0]);
        $pagerMock->expects($this->exactly(2))->method('getCurrentPageResults')->willReturn([]);

        $provider = $this->createMock(PagerProviderInterface::class);
        $provider->expects($this->once())->method('provide')->with([
            'first_page' => 1,
            'last_page' => 1,
            'indexName' => 'foo',
            'max_per_page' => 10,
        ])->willReturn($pagerMock);

        $pagerProviderRegistry = $this->createMock(PagerProviderRegistry::class);
        $pagerProviderRegistry->expects($this->once())->method('getProvider')->with('foo')->willReturn($provider);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $sut = new AsyncPagerPersister($pagerPersisterRegistry, $pagerProviderRegistry, $messageBus);

        $sut->insertPage(1, [
            'indexName' => 'foo',
            'max_per_page' => 10,
        ]);
    }
}
