<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel;

use Kiener\KienerMyParcel\Service\ShippingMethod\ShippingMethodService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class KienerMyParcel extends Plugin
{

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->updateCustomFields($installContext->getCurrentPluginVersion());
    }

    public function activate(ActivateContext $activateContext): void
    {
        /** @var ShippingMethodService $shippingMethodService */
        $shippingMethodService = $this->container->get(ShippingMethodService::class);

        // Install MyParcel shipping methods
        $shippingMethodService->createShippingMethods($activateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->deleteCustomFields();

    }

    private function updateCustomFields(string $to, ?string $from = null)
    {
        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        if ($this->version_between('0.1.0', $to, $from)) {
            $this->updateCustomFields_0_1_0($customFieldSetRepository);
        }
    }

    private function version_between(string $between, string $to, ?string $from = null)
    {
        return version_compare($to, $between, '>=')
            && (is_null($from) || version_compare($from, $between, '<'));
    }

    private function deleteCustomFields()
    {
        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $entityIds = $customFieldSetRepository->searchIds(
            (new Criteria())
                ->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('name', 'memo_auction_customer'),
                ]))
            , Context::createDefaultContext()
        )->getIds();

        if (count($entityIds) < 1) {
            return;
        }

        $entityIds = array_map(function ($element) {
            return ['id' => $element];
        }, array_values($entityIds));

        $customFieldSetRepository->delete(
            $entityIds,
            Context::createDefaultContext()
        );
    }

    private function updateCustomFields_0_1_0(EntityRepositoryInterface $customFieldSetRepository)
    {

        $customFieldSetRepository->upsert([
            [
                'name' => 'kiener_myparcel',
                'global' => true,
                'customFields' => [
                    [
                        'name' => 'my_parcel',
                        'type' => CustomFieldTypes::JSON,
                    ]
                ],
                'relations' => [
                    [
                        'entityName' => $this->container->get(OrderDefinition::class)->getEntityName()
                    ]
                ],
            ]
        ], Context::createDefaultContext());
    }
}
