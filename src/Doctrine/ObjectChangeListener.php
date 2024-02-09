<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Doctrine;

use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Webmozart\Assert\Assert;

class ObjectChangeListener
{
    private string $modelClass;

    public function __construct(
        /**
         * @var array{ model_class: string, index_name: string } $options
         */
        private readonly array $options,
        private readonly PersisterRegistry $persisterRegistry,
        private readonly IndexableInterface $indexable,
    ) {
        $this->modelClass = $options['model_class'];
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        if ($this->getParentModel($args->getObject()) instanceof $this->modelClass) {
            $this->sendProductUpdateEvent(Events::postUpdate, $args);
        }
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        if ($this->getParentModel($args->getObject()) instanceof $this->modelClass) {
            $this->sendProductUpdateEvent(Events::postPersist, $args);
        }
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        if ($this->getParentModel($args->getObject()) instanceof $this->modelClass) {
            $this->sendProductUpdateEvent(Events::preRemove, $args);
        }
    }

    private function getParentModel(object $object): ?object
    {
        if ($object instanceof $this->modelClass) {
            return $object;
        }
        if ($object instanceof ProductAttributeValue) {
            return $object->getSubject();
        }
        if ($object instanceof ProductTranslation) {
            return $object->getTranslatable();
        }

        return $object;
    }

    private function sendProductUpdateEvent(string $eventName, LifecycleEventArgs $args): void
    {
        $index = $this->options['index_name'];
        $object = $this->getParentModel($args->getObject());
        Assert::notNull($object);

        $persister = $this->persisterRegistry->getPersister($index);
        switch ($eventName) {
            case Events::postUpdate:
                if ($object instanceof ToggleableInterface && !$object->isEnabled()) {
                    $persister->deleteOne($object);

                    break;
                }

                if ($persister->handlesObject($object)) {
                    if ($this->indexable->isObjectIndexable($index, $object)) {
                        $persister->replaceOne($object);
                    } else {
                        $persister->deleteOne($object);
                    }
                }

                break;
            case Events::postPersist:
                if ($persister->handlesObject($object) && $this->indexable->isObjectIndexable($index, $object)) {
                    $persister->insertOne($object);
                }

                break;
            case Events::preRemove:
                $persister->deleteOne($object);

                break;
        }
    }
}
