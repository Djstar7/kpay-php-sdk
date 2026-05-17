<?php

declare(strict_types=1);

namespace Kpay\Sdk;

class KpayException extends \RuntimeException
{
    public ?int $httpStatus;

    /** @var mixed */
    public $body;

    /**
     * @param mixed $body
     */
    public function __construct(string $message, ?int $httpStatus = null, $body = null)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->body = $body;
    }
}
