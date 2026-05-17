<?php

declare(strict_types=1);

namespace Kpay\Sdk\Laravel;

use Illuminate\Support\Facades\Facade;
use Kpay\Sdk\KpayClient;

/**
 * @method static array initPayment(array $params)
 * @method static array initWithdrawal(array $params)
 * @method static array getPayment(string $id)
 * @method static array getWithdrawal(string $id)
 * @method static bool  verifyReturnSignature(array $q)
 * @method static array awaitFinalStatus(string $id, string $kind = 'payment', ?int $maxDuration = null)
 *
 * @see KpayClient
 */
class Kpay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return KpayClient::class;
    }
}
