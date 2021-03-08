<?php

namespace Kiener\KienerMyParcel\Service;

use Psr\Log\LoggerInterface;

/**
 * Class BaseService
 */
class BaseService
{
    public const FIELD_DEBUG_RESPONSE = 'response';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * BaseController constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
