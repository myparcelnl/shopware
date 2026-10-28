<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use Closure;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

/**
 * An entity repository without a database. search() and searchIds() return what
 * the closure gives for the criteria and the context; writes are recorded, not
 * applied. Shopware's own StaticEntityRepository needs ramsey/uuid for its write
 * results, which the test autoloader does not load.
 */
final class FakeEntityRepository extends EntityRepository
{
    /** @var list<Criteria> */
    public array $criteria = [];

    /** @var list<Context> */
    public array $contexts = [];

    /** @var list<array<string, mixed>> */
    public array $upserts = [];

    /** @var list<array<string, mixed>> */
    public array $updates = [];

    /** @var list<array<string, mixed>> */
    public array $deletes = [];

    /**
     * @param  Closure(Criteria, Context): EntityCollection<Entity> $search
     */
    public function __construct(private readonly Closure $search)
    {
    }

    /**
     * A repository that holds these entities. A search with ids returns the ones
     * with those ids, a search without ids returns all. Filters are ignored.
     */
    public static function with(Entity ...$entities): self
    {
        return new self(static fn (Criteria $criteria): EntityCollection => new EntityCollection(array_values(array_filter(
            $entities,
            static fn (Entity $entity): bool => [] === $criteria->getIds()
                || in_array($entity->getUniqueIdentifier(), $criteria->getIds(), true)
        ))));
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $entities = $this->find($criteria, $context);

        return new EntitySearchResult(
            $entities->first()?->getApiAlias() ?? 'fake',
            $entities->count(),
            $entities,
            null,
            $criteria,
            $context
        );
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $rows = [];

        foreach ($this->find($criteria, $context) as $entity) {
            $rows[] = ['primaryKey' => $entity->getUniqueIdentifier(), 'data' => []];
        }

        return new IdSearchResult(count($rows), $rows, $criteria, $context);
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        array_push($this->upserts, ...array_values($data));

        return self::written($context);
    }

    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        array_push($this->updates, ...array_values($data));

        return self::written($context);
    }

    public function delete(array $ids, Context $context): EntityWrittenContainerEvent
    {
        array_push($this->deletes, ...array_values($ids));

        return self::written($context);
    }

    /**
     * @return EntityCollection<Entity>
     */
    private function find(Criteria $criteria, Context $context): EntityCollection
    {
        $this->criteria[] = $criteria;
        $this->contexts[] = $context;

        return ($this->search)($criteria, $context);
    }

    private static function written(Context $context): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }
}
