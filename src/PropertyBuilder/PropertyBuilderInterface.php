<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use FOS\ElasticaBundle\Event\PreTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface PropertyBuilderInterface extends EventSubscriberInterface
{
    public function consumeEvent(PreTransformEvent $event): void;

    public function buildProperty(PreTransformEvent $event, string $class, callable $callback): void;
}
