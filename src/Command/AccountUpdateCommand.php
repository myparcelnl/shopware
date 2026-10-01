<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Command;

use MyParcel\Shopware\Pdk\Account\AccountUpdater;
use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Account\Contract\PdkAccountRepositoryInterface;
use MyParcelNL\Pdk\Base\Config;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Contract\PdkSettingsRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sets the MyParcel API key until the admin settings screen exists. The key is
 * checked when it is saved, so there is no separate "test connection" step.
 *
 * The key comes from the MYPARCEL_API_KEY environment variable, so it never
 * appears on the command line or in the console log.
 */
#[AsCommand(name: 'myparcel:account:update', description: 'Set the MyParcel API key (from MYPARCEL_API_KEY) and fetch the account')]
final class AccountUpdateCommand extends Command
{
    private const API_KEY_ENV = 'MYPARCEL_API_KEY';

    public function __construct(private readonly PdkInitializer $pdkInitializer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('acceptance', null, InputOption::VALUE_NONE, 'Use the acceptance API instead of production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apiKey = getenv(self::API_KEY_ENV);

        if (false === $apiKey || '' === trim($apiKey)) {
            $io->error(sprintf('Set the %s environment variable to the MyParcel API key.', self::API_KEY_ENV));

            return self::FAILURE;
        }

        $this->pdkInitializer->boot();

        $environment = $input->getOption('acceptance')
            ? Config::ENVIRONMENT_ACCEPTANCE
            : Config::ENVIRONMENT_PRODUCTION;

        try {
            Pdk::get(AccountUpdater::class)->update($apiKey, $environment);
        } catch (\Throwable $exception) {
            $apiKeyValid = Pdk::get(PdkSettingsRepositoryInterface::class)->all()->account->apiKeyValid;

            $io->error(sprintf(
                $apiKeyValid
                    ? 'The API key is valid, but updating the account failed: %s'
                    : 'The API key was not accepted: %s',
                $exception->getMessage()
            ));

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
