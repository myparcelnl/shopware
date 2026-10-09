<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order;

use MyParcel\Shopware\Pdk\Language\LanguageIdResolverInterface;
use MyParcel\Shopware\Pdk\Language\LocaleLanguageChain;
use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads the states of the order state machine. StateMachineRegistry is not
 * used: it caches per state machine, not per language.
 */
final class ShopwareOrderStatusProvider implements OrderStatusProviderInterface
{
    /**
     * @param  EntityRepository<StateMachineStateCollection> $stateRepository
     */
    public function __construct(
        private readonly EntityRepository $stateRepository,
        private readonly RequestStack $requestStack,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly LanguageIdResolverInterface $languageIdResolver
    ) {
    }

    public function all(): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('stateMachine.technicalName', OrderStates::STATE_MACHINE))
            ->addSorting(new FieldSorting('technicalName'));

        $statuses = [];

        foreach ($this->stateRepository->search($criteria, $this->createContext())->getEntities() as $state) {
            $name = $state->getTranslation('name');

            $statuses[$state->getTechnicalName()] = is_string($name) ? $name : $state->getTechnicalName();
        }

        return $statuses;
    }

    /**
     * A system source, because a role with only myparcel:access cannot read
     * state_machine_state. The names follow the language of the admin user,
     * with the language chain of the request as fallback.
     */
    private function createContext(): Context
    {
        $context = $this->requestStack->getMainRequest()?->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if (!$context instanceof Context) {
            return Context::createCLIContext();
        }

        $languageChain = LocaleLanguageChain::build(
            $context->getLanguageIdChain(),
            $this->localeResolver->getLocale(),
            $this->languageIdResolver
        );

        return new Context(new SystemSource(), [], $context->getCurrencyId(), $languageChain);
    }
}
