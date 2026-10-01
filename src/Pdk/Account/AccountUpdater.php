<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Account;

use MyParcelNL\Pdk\App\Action\Backend\Account\UpdateAccountAction;
use MyParcelNL\Pdk\App\Api\Contract\PdkActionsServiceInterface;
use MyParcelNL\Pdk\App\Api\PdkEndpoint;
use MyParcelNL\Pdk\Facade\Pdk;
use MyParcelNL\Pdk\Settings\Model\AccountSettings;

/**
 * Runs the PDK's own account update for a key given outside a request.
 *
 * handle() cannot be used from the console: it reads the key from a JSON
 * request body and ends by building the admin context, which needs adapters
 * that do not exist yet. The steps in between are the ones that matter — store
 * the key, validate it against the API, pick the proposition from the account,
 * fetch carriers and subscription features — and this calls exactly those.
 */
final class AccountUpdater extends UpdateAccountAction
{
    /**
     * @throws \Throwable when the API rejects the key; the PDK has then marked it invalid
     */
    public function update(string $apiKey, string $environment): void
    {
        // updateAndSaveAccount() executes the subscription features action,
        // and the PDK only resolves actions for a known context.
        Pdk::get(PdkActionsServiceInterface::class)->setContext(PdkEndpoint::CONTEXT_BACKEND);

        $accountSettings = $this->updateAccountSettings([
            AccountSettings::API_KEY     => $apiKey,
            AccountSettings::ENVIRONMENT => $environment,
        ]);

        $this->updateAndSaveAccount($accountSettings);
    }
}
