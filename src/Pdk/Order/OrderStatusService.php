<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Order;

use MyParcel\Shopware\Pdk\Http\RequestContext;
use MyParcel\Shopware\Pdk\Language\LocaleResolverInterface;
use MyParcel\Shopware\Pdk\Placeholder\NotImplementedException;
use MyParcelNL\Pdk\App\Order\Contract\OrderStatusServiceInterface;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The order settings use these states as the options of their status selects.
 * StateMachineRegistry is not used: it caches per state machine, not per
 * language.
 *
 * @TODO INT-2151: cover this class with an integration test in a Shopware installation.
 */
final class OrderStatusService implements OrderStatusServiceInterface
{
    /**
     * @param  EntityRepository<StateMachineStateCollection> $stateRepository
     * @param  EntityRepository<LanguageCollection>          $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $stateRepository,
        private readonly EntityRepository $languageRepository,
        private readonly RequestStack $requestStack,
        private readonly LocaleResolverInterface $localeResolver
    ) {
    }

    /**
     * List the order states, sorted by technical name, with their names in the
     * language of the admin user.
     *
     * @return array<string, string> technical name => translated name
     */
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
     * @param  array<int|string> $orderIds
     *
     * @TODO INT-1962: change the order state with a state machine transition.
     */
    public function updateStatus(array $orderIds, string $status): void
    {
        throw NotImplementedException::forContract(OrderStatusServiceInterface::class);
    }

    /**
     * The admin context holds the content language. The names follow the
     * language of the admin user, with the language chain of the request as
     * fallback.
     */
    private function createContext(): Context
    {
        $context = RequestContext::fromRequestStack($this->requestStack);

        if (null === $context) {
            return Context::createCLIContext();
        }

        $languageId = $this->findLanguageId($context);

        if (null === $languageId) {
            return $context;
        }

        return new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            array_values(array_unique([$languageId, ...$context->getLanguageIdChain()])),
            $context->getVersionId(),
            $context->getCurrencyFactor(),
            $context->considerInheritance(),
            $context->getTaxState(),
            $context->getRounding()
        );
    }

    /**
     * The oldest shop language with the locale of the admin user, so the
     * result is the same when two languages share a locale.
     */
    private function findLanguageId(Context $context): ?string
    {
        $locale = $this->localeResolver->getLocale();

        if (null === $locale) {
            return null;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('locale.code', $locale))
            ->addSorting(new FieldSorting('createdAt'), new FieldSorting('id'))
            ->setLimit(1);

        return $this->languageRepository->searchIds($criteria, $context)->firstId();
    }
}
