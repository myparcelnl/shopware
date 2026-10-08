<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order;

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
        private readonly RequestStack $requestStack
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
     * state_machine_state. The language chain of the request keeps the names in
     * the language of the request.
     */
    private function createContext(): Context
    {
        $context = $this->requestStack->getMainRequest()?->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if (!$context instanceof Context) {
            return Context::createCLIContext();
        }

        return new Context(new SystemSource(), [], $context->getCurrencyId(), $context->getLanguageIdChain());
    }
}
