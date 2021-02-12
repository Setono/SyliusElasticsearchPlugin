<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMInvalidArgumentException;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Webmozart\Assert\Assert;

class TaxonListener
{
    /** @var ObjectPersisterInterface */
    private $persister;

    /** @var bool */
    private $enabled;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(
        ObjectPersisterInterface $persister,
        bool $enabled,
        EntityManagerInterface $entityManager
    ) {
        $this->persister = $persister;
        $this->enabled = $enabled;
        $this->entityManager = $entityManager;
    }

    public function handlePostCreate(ResourceControllerEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        /** @var TaxonInterface|null $taxon */
        $taxon = $event->getSubject();
        Assert::isInstanceOf($taxon, TaxonInterface::class);

        // Refresh the entity to be sure that any insertion made without
        // updating directly the object is taken in account
        try {
            $this->entityManager->refresh($taxon);
        } catch (ORMInvalidArgumentException $exception) {
        }

        $this->persister->replaceOne($taxon);
    }

    public function handlePostUpdate(ResourceControllerEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        /** @var TaxonInterface|null $taxon */
        $taxon = $event->getSubject();
        Assert::isInstanceOf($taxon, TaxonInterface::class);

        // Refresh the entity to be sure that any insertion made without
        // updating directly the object is taken in account
        try {
            $this->entityManager->refresh($taxon);
        } catch (ORMInvalidArgumentException $exception) {
        }

        $this->persister->replaceOne($taxon);

        // If taxon is toggleable - update children taxons
        // as their enabled status may be related to parent's enabled status
        if ($taxon instanceof ToggleableInterface) {
            /** @var TaxonInterface $child */
            foreach ($taxon->getChildren() as $child) {
                $this->persister->replaceOne($child);
            }
        }
    }

    public function handlePostDelete(ResourceControllerEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        /** @var TaxonInterface|null $taxon */
        $taxon = $event->getSubject();
        Assert::isInstanceOf($taxon, TaxonInterface::class);

        $this->persister->deleteOne($taxon);
    }
}
