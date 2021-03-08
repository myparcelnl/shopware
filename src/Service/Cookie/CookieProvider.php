<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Service\Cookie;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class CookieProvider implements CookieProviderInterface {

    /**
     * @var CookieProviderInterface
     */
    private $original;

    public function __construct(CookieProviderInterface $cookieProvider)
    {
        $this->original = $cookieProvider;
    }

    public function getCookieGroups($getcookies = null): array
    {
        $cookies = $this->original->getCookieGroups();

        if($getcookies !== null) {
            print_r('Dit is een if case');
            return $getcookies;
        }
        foreach ($cookies as &$cookie) {
            if (!\is_array($cookie)) {
                continue;
            }

            if (!$this->isRequiredCookieGroup($cookie)) {
                continue;
            }

            if (!\array_key_exists('entries', $cookie)) {
                continue;
            }

            $key = array_search('myparcel-cookie-key', array_column($cookie['entries'], 'cookie'));
            if ($key !== false) {
                continue;
            }

            $cookie['entries'][] = [
                'snippet_name' => 'cookie.myparcel.name',
                'cookie' => 'myparcel-cookie-key',
                'expiration' => 1,
                'value' => 'empty'
            ];

        }
        return $cookies;
    }

    public function isRequiredCookieGroup(array $cookie): bool
    {
        return (\array_key_exists('isRequired', $cookie) && $cookie['isRequired'] === true)
            && (\array_key_exists('snippet_name', $cookie) && $cookie['snippet_name'] === 'cookie.groupRequired');
    }
}
