<?php

declare(strict_types=1);

use MyParcel\Shopware\Pdk\Audit\Repository\NoopAuditRepository;
use MyParcel\Shopware\Tests\Support\TestPdk;
use MyParcelNL\Pdk\Audit\Contract\PdkAuditRepositoryInterface;
use MyParcelNL\Pdk\Audit\Model\Audit;
use MyParcelNL\Pdk\Storage\MemoryCacheStorage;

beforeEach(fn () => TestPdk::boot());

it('fulfils the audit contract and never has audits', function () {
    $repository = new NoopAuditRepository(new MemoryCacheStorage());

    $repository->store(new Audit(['id' => 'a1']));

    expect($repository)->toBeInstanceOf(PdkAuditRepositoryInterface::class)
        ->and($repository->all())->toHaveCount(0);
});
