<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Command;

use MyParcel\Shopware\Pdk\PdkInitializer;
use MyParcelNL\Pdk\App\Account\Contract\PdkAccountRepositoryInterface;
use MyParcelNL\Pdk\Base\Config;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Contract\PdkSettingsRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows what the plugin stored for the MyParcel account. Never prints the key.
 */
#[AsCommand(name: 'myparcel:account:show', description: 'Show the stored MyParcel account')]
final class AccountShowCommand extends Command
{
    public function __construct(private readonly PdkInitializer $pdkInitializer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->pdkInitializer->boot();

        $io       = new SymfonyStyle($input, $output);
        $settings = Pdk::get(PdkSettingsRepositoryInterface::class)->all()->account;

        // The PDK defaults apiKeyValid to true, so without a key that would read as a
        // contradictory "valid: yes"; show "n/a" instead until a key is set.
        $apiKeyValid = $settings->apiKey ? ($settings->apiKeyValid ? 'yes' : 'no') : 'n/a';

        $io->definitionList(
            ['Environment' => $settings->environment ?? Config::ENVIRONMENT_PRODUCTION],
            ['API key set' => $settings->apiKey ? 'yes' : 'no'],
            ['API key valid' => $apiKeyValid]
        );

        $account = Pdk::get(PdkAccountRepositoryInterface::class)->getAccount();

        if (null === $account) {
            $io->warning('No account stored. Run myparcel:account:update first.');

            return self::SUCCESS;
        }

        AccountOutput::write($io, $account);

        return self::SUCCESS;
    }
}
