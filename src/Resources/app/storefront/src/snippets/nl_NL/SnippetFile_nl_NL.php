<?php declare(strict_types=1);

namespace Kiener\KienerMyParcel\Resources\app\storefront\src\snippets\nl_NL;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_nl_NL implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'myparcel.nl-NL';
    }

    public function getPath(): string
    {
        return __DIR__ . '/myparcel.nl-NL.json';
    }

    public function getIso(): string
    {
        return 'nl-NL';
    }

    public function getAuthor(): string
    {
        return 'Shopware Services';
    }

    public function isBase(): bool
    {
        return false;
    }
}
