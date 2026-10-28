<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Command;

use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\Facade\Pdk;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints an order the way the PDK sees it. The output contains personal data: do
 * not paste it anywhere outside the development environment.
 */
#[AsCommand(name: 'myparcel:order:show', description: 'Show an order as the MyParcel PDK sees it')]
final class OrderShowCommand extends Command
{
    /**
     * @param  EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly EntityRepository $orderRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('orderNumber', InputArgument::REQUIRED, 'The Shopware order number');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $orderNumber = (string) $input->getArgument('orderNumber');
        $criteria    = (new Criteria())->addFilter(new EqualsFilter('orderNumber', $orderNumber))->setLimit(1);
        $orderId     = $this->orderRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        if (null === $orderId) {
            $io->error(sprintf('There is no order with number %s.', $orderNumber));

            return self::FAILURE;
        }

        $this->pdkInitializer->boot();

        $order = Pdk::get(PdkOrderRepositoryInterface::class)->find($orderId);

        $output->writeln((string) json_encode($order?->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
