<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webmozart\Assert\Assert;

class ProductListener
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

    public function handlePostCreate(ResourceControllerEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        /** @var ProductInterface $product */
        $product = $event->getSubject();
        Assert::isInstanceOf($product, ProductInterface::class);

        // Refresh the entity to be sure that any insertion made without updating directly the object is taken in account
        $this->entityManager->refresh($product);

        foreach ($product->getVariants() as $child) {
            /** @var ProductVariantInterface $child */
            if (!$child->isTracked() || $child->getOnHand() > 0) {
                $this->persister->insertOne($product);

                return;
            }
        }
    }
}
