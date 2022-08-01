<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use FOS\ElasticaBundle\Provider\IndexableInterface;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Product\Model\ProductAttributeValue;

class ObjectChangeListener implements EventSubscriber
{
    /** @var string */
    private $modelClass;

    /** @var PersisterRegistry */
    private $persisterRegistry;

    /** @var IndexableInterface */
    private $indexable;

    /** @var array */
    private $options;

    public function __construct(array $options, PersisterRegistry $persisterRegistry, IndexableInterface $indexable)
    {
        $this->options = $options;
        $this->modelClass = $options['model_class'];
        $this->persisterRegistry = $persisterRegistry;
        $this->indexable = $indexable;
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

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
        ];
    }

    /**
     * @param object $object
     *
     * @return object
     */
    private function getParentModel($object)
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

    /**
     * @param string             $eventName
     * @param LifecycleEventArgs $args
     */
    private function sendProductUpdateEvent($eventName, $args): void
    {
        $index = $this->options['index_name'];
        $object = $this->getParentModel($args->getObject());
        $persister = $this->persisterRegistry->getPersister($index);
        switch ($eventName) {
            case Events::postUpdate:
                if (!$object->isEnabled()) {
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
