<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Command;

use MyParcel\Shopware\Pdk\Account\AccountUpdater;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Account\Contract\PdkAccountRepositoryInterface;
use MyParcelNL\Pdk\Base\Config;
use MyParcelNL\Pdk\Facade\Pdk;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sets the MyParcel API key until the admin settings screen exists. The key is
 * checked when it is saved, so there is no separate "test connection" step.
 */
#[AsCommand(name: 'myparcel:account:update', description: 'Set the MyParcel API key and fetch the account')]
final class AccountUpdateCommand extends Command
{
    public function __construct(private readonly PdkInitializer $pdkInitializer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('api-key', InputArgument::REQUIRED, 'The MyParcel API key')
            ->addOption('acceptance', null, InputOption::VALUE_NONE, 'Use the acceptance API instead of production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->pdkInitializer->boot();

        $io          = new SymfonyStyle($input, $output);
        $environment = $input->getOption('acceptance')
            ? Config::ENVIRONMENT_ACCEPTANCE
            : Config::ENVIRONMENT_PRODUCTION;

        try {
            Pdk::get(AccountUpdater::class)->update((string) $input->getArgument('api-key'), $environment);
        } catch (\Throwable $exception) {
            $io->error(sprintf('The API key was not accepted: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        $account = Pdk::get(PdkAccountRepositoryInterface::class)->getAccount();

        if (null === $account) {
            $io->error('The API key was accepted, but no account was stored.');

            return self::FAILURE;
        }

        $io->success(sprintf('Account updated on %s.', $environment));
        AccountOutput::write($io, $account);

        return self::SUCCESS;
    }
}
