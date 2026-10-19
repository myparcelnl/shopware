<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Command;

use MyParcel\Shopware\Pdk\Order\Contract\OrderStorageInterface;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Order\Contract\PdkOrderRepositoryInterface;
use MyParcelNL\Pdk\Facade\Pdk;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prints an order the way the PDK sees it. With --snapshot it prints the raw
 * snapshot instead, which is how the test fixtures are made. The output contains
 * personal data: do not paste it anywhere outside the development environment.
 */
#[AsCommand(name: 'myparcel:order:show', description: 'Show an order as the MyParcel PDK sees it')]
final class OrderShowCommand extends Command
{
    public function __construct(
        private readonly PdkInitializer $pdkInitializer,
        private readonly OrderStorageInterface $orderStorage,
        private readonly EntityRepository $orderRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('orderNumber', InputArgument::REQUIRED, 'The Shopware order number')
            ->addOption('snapshot', null, InputOption::VALUE_NONE, 'Print the raw snapshot instead of the PdkOrder');
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

        $data = $input->getOption('snapshot')
            ? $this->orderStorage->getSnapshot($orderId)
            : Pdk::get(PdkOrderRepositoryInterface::class)->find($orderId)?->toArray();

        $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
