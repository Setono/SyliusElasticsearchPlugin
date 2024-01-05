<?php

declare(strict_types=1);

namespace Setono\SyliusElasticsearchPlugin\PropertyBuilder;

use FOS\ElasticaBundle\Event\PreTransformEvent;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * This class is copied and altered from the BitBagCommerce/SyliusElasticsearchPlugin repo.
 */
abstract class AbstractBuilder implements PropertyBuilderInterface
{
    private ChannelRepositoryInterface $channelRepository;

    /** @var array<string, ChannelInterface> */
    private array $channelCache = [];

    public function __construct(ChannelRepositoryInterface $channelRepository)
    {
        $this->channelRepository = $channelRepository;
    }

    protected function getChannel(string $channelCode): ?ChannelInterface
    {
        if (!isset($this->channelCache[$channelCode])) {
            $channel = $this->channelRepository->findOneByCode($channelCode);
            Assert::notNull($channel);

            $this->channelCache[$channelCode] = $channel;
        }

        return $this->channelCache[$channelCode];
    }

    public function buildProperty(PreTransformEvent $event, string $class, callable $callback): void
    {
        $model = $event->getObject();

        if (!$model instanceof $class) {
            return;
        }

        $document = $event->getDocument();

        $callback($model, $document);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreTransformEvent::class => 'consumeEvent',
        ];
    }
}
