<?php

declare(strict_types=1);

namespace MyParcel\Shopware\Pdk\Language;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\Locale\LocaleException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads the language from the Shopware context of the main request: the
 * language of the admin user in the admin API, the sales channel language in
 * the storefront.
 */
final class ShopwareLocaleResolver implements LocaleResolverInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly LanguageLocaleCodeProvider $localeCodeProvider,
        private readonly Connection $connection
    ) {
    }

    public function getLocale(): ?string
    {
        $context = $this->requestStack->getMainRequest()?->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if (!$context instanceof Context) {
            return null;
        }

        $source = $context->getSource();

        if ($source instanceof AdminApiSource && null !== $source->getUserId()) {
            return $this->findUserLocale($source->getUserId()) ?? $this->findLanguageLocale($context);
        }

        return $this->findLanguageLocale($context);
    }

    /**
     * The admin context holds the content language, not the language of the
     * user. A plain query, because a repository search with this context needs
     * user:read, which a MyParcel-only role does not have.
     */
    private function findUserLocale(string $userId): ?string
    {
        $code = $this->connection->fetchOne(
            'SELECT locale.code FROM `user` INNER JOIN locale ON locale.id = `user`.locale_id WHERE `user`.id = :id',
            ['id' => Uuid::fromHexToBytes($userId)]
        );

        return is_string($code) ? $code : null;
    }

    private function findLanguageLocale(Context $context): ?string
    {
        try {
            return $this->localeCodeProvider->getLocaleForLanguageId($context->getLanguageId());
        } catch (LocaleException) {
            return null;
        }
    }
}
