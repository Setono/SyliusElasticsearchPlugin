<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMInvalidArgumentException;
use Elastica\Exception\ResponseException;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

class ProductListener
{
    public function __construct(
        private readonly ObjectPersisterInterface $persister,
        private readonly bool $enabled,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function handlePostCreate(ResourceControllerEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        /** @var ProductInterface|null $product */
        $product = $event->getSubject();
        Assert::isInstanceOf($product, ProductInterface::class);

        // Refresh the entity to be sure that any insertion made without updating directly the object is taken in account
        try {
            $this->entityManager->refresh($product);
        } catch (ORMInvalidArgumentException $exception) {
        }

        foreach ($product->getVariants() as $child) {
            /** @var ProductVariantInterface $child */
            if ($child->getOnHand() > 0) {
                $this->persister->insertOne($product);

                return;
            }
        }
    }

    public function handlePostUpdate(ResourceControllerEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        /** @var ProductInterface|null $product */
        $product = $event->getSubject();
        Assert::isInstanceOf($product, ProductInterface::class);

        // Refresh the entity to be sure that any insertion made without updating directly the object is taken in account
        try {
            $this->entityManager->refresh($product);
        } catch (ORMInvalidArgumentException $exception) {
        }

        foreach ($product->getVariants() as $child) {
            /** @var ProductVariantInterface $child */
            if ($child->getOnHand() > 0) {
                $this->persister->replaceOne($product);

                return;
            }
        }
    }

    public function handlePostDelete(ResourceControllerEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        /** @var ProductInterface|null $product */
        $product = $event->getSubject();
        Assert::isInstanceOf($product, ProductInterface::class);

        // Do not delete products that have no ID since they do not exist in ES
        if (null === $product->getId()) {
            return;
        }

        try {
            $this->persister->deleteOne($product);
        } catch (ResponseException $exception) {
            // Errors can occur during deletion if event is thrown or listener is read multiple times.
            // Just ignore this kind of exception
        }
    }
}
