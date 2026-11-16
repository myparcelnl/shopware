<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Tests\Support;

use Closure;
use MyParcelNL\Pdk\App\Api\Contract\PdkActionsServiceInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the actions it was asked to run and runs none of them. $onExecute
 * runs inside each call, so a test can act while an action is in progress.
 */
final class RecordingActionsService implements PdkActionsServiceInterface
{
    /** @var list<array{action: mixed, parameters: array<string, mixed>, automatic: bool}> */
    public array $calls = [];

    public ?Closure $onExecute = null;

    public function execute($action, array $parameters = []): Response
    {
        return $this->record($action, $parameters, false);
    }

    public function executeAutomatic($action, array $parameters = []): Response
    {
        return $this->record($action, $parameters, true);
    }

    public function setContext(string $context): PdkActionsServiceInterface
    {
        return $this;
    }

    /**
     * @param  array<string, mixed> $parameters
     */
    private function record(mixed $action, array $parameters, bool $automatic): Response
    {
        $this->calls[] = ['action' => $action, 'parameters' => $parameters, 'automatic' => $automatic];

        if (null !== $this->onExecute) {
            ($this->onExecute)();
        }

        return new Response();
    }
}
