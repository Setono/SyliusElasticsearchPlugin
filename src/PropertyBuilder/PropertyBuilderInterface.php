<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use FOS\ElasticaBundle\Event\TransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface PropertyBuilderInterface extends EventSubscriberInterface
{
    /**
     * @param TransformEvent $event
     */
    public function consumeEvent(TransformEvent $event): void;

    /**
     * @param TransformEvent $event
     * @param string         $class
     * @param callable       $callback
     */
    public function buildProperty(TransformEvent $event, string $class, callable $callback): void;
}
