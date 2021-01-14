<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use FOS\ElasticaBundle\Event\TransformEvent;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
abstract class AbstractBuilder implements PropertyBuilderInterface
{
    /** @var ChannelRepositoryInterface */
    private $channelRepository;

    /** @var array */
    private $channelCache = [];

    public function __construct(ChannelRepositoryInterface $channelRepository)
    {
        $this->channelRepository = $channelRepository;
    }

    protected function getChannel(string $channelCode): ?ChannelInterface
    {
        if (!isset($this->channelCache[$channelCode])) {
            $this->channelCache[$channelCode] = $this->channelRepository->findOneBy(['code' => $channelCode]);
        }

        return $this->channelCache[$channelCode];
    }

    /**
     * {@inheritdoc}
     */
    public function buildProperty(TransformEvent $event, string $supportedModelClass, callable $callback): void
    {
        $model = $event->getObject();

        if (!$model instanceof $supportedModelClass) {
            return;
        }

        $document = $event->getDocument();

        $callback($model, $document);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TransformEvent::PRE_TRANSFORM => 'consumeEvent',
        ];
    }
}
