<?php

declare(strict_types=1);

namespace Kpay\Sdk;

/**
 * SDK KPay — PHP 8.1+ (cURL natif, aucune dépendance imposée).
 *
 * Couvre l'API marchand v1 (auth x-api-key / x-secret-key) :
 *   - initPayment / initWithdrawal (USSD ou GATEWAY)
 *   - getPayment / getWithdrawal
 *   - verifyReturnSignature (HMAC retour / webhook signé)
 *   - awaitFinalStatus : attente du statut final (hybride — à utiliser en
 *     complément de votre récepteur webhook ; s'arrête au statut terminal
 *     ou à l'échéance `maxDuration`).
 *
 * Seul `maxDuration` (secondes) est configurable pour l'attente.
 */
class KpayClient
{
    private const TERMINAL = ['COMPLETED', 'FAILED', 'CANCELLED', 'EXPIRED', 'REFUNDED'];

    private string $baseUrl;
    private string $apiKey;
    private string $secretKey;
    private ?string $gatewaySecret;
    private int $maxDuration;
    /** Cadence de sondage interne (non configurable). */
    private int $pollIntervalSec = 3;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        string $secretKey,
        ?string $gatewaySecret = null,
        int $maxDuration = 300
    ) {
        if ($baseUrl === '' || $apiKey === '' || $secretKey === '') {
            throw new KpayException('baseUrl, apiKey et secretKey sont requis');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->gatewaySecret = $gatewaySecret;
        $this->maxDuration = $maxDuration;
    }

    /** @param array<string,mixed> $params */
    public function initPayment(array $params): array
    {
        return $this->request('POST', '/v1/payments/init', $params);
    }

    /** @param array<string,mixed> $params */
    public function initWithdrawal(array $params): array
    {
        return $this->request('POST', '/v1/payments/withdraw', $params);
    }

    public function getPayment(string $id): array
    {
        return $this->request('GET', '/v1/payments/' . rawurlencode($id));
    }

    public function getWithdrawal(string $id): array
    {
        return $this->request('GET', '/v1/payments/withdraw/' . rawurlencode($id));
    }

    /**
     * Vérifie la signature HMAC d'une query de retour / d'un webhook signé.
     * Chaîne signée : `status|reference|externalId|ts` (HMAC-SHA256, gatewaySecret).
     *
     * @param array<string,mixed> $q attend status, reference, externalId?, ts, sig
     */
    public function verifyReturnSignature(array $q): bool
    {
        if ($this->gatewaySecret === null) {
            throw new KpayException('gatewaySecret non configuré');
        }
        foreach (['status', 'reference', 'ts', 'sig'] as $k) {
            if (!isset($q[$k]) || $q[$k] === '') {
                return false;
            }
        }
        $stringToSign = sprintf(
            '%s|%s|%s|%s',
            $q['status'],
            $q['reference'],
            $q['externalId'] ?? '',
            $q['ts']
        );
        $expected = hash_hmac('sha256', $stringToSign, $this->gatewaySecret);

        return hash_equals($expected, (string) $q['sig']);
    }

    /**
     * Attente du statut final (hybride côté client).
     *
     * À utiliser en complément — non en remplacement — de votre récepteur
     * webhook : sonde GET status jusqu'à un état terminal ou jusqu'à
     * `maxDuration` (secondes ; configurable, défaut = constructeur).
     *
     * @param "payment"|"withdrawal" $kind
     */
    public function awaitFinalStatus(string $id, string $kind = 'payment', ?int $maxDuration = null): array
    {
        $deadline = time() + ($maxDuration ?? $this->maxDuration);
        $fetch = fn (): array => $kind === 'withdrawal'
            ? $this->getWithdrawal($id)
            : $this->getPayment($id);

        $last = $fetch();
        while (!in_array($last['status'] ?? null, self::TERMINAL, true)) {
            if (time() >= $deadline) {
                return $last;
            }
            sleep($this->pollIntervalSec);
            $last = $fetch();
        }

        return $last;
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->baseUrl . '/api' . $path);
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'x-secret-key: ' . $this->secretKey,
        ];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new KpayException("KPay réseau: {$err}");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = ['raw' => $raw];
        }
        if ($status < 200 || $status >= 300) {
            $msg = $data['message'] ?? "KPay {$method} {$path} -> HTTP {$status}";
            throw new KpayException(is_string($msg) ? $msg : 'KPay error', $status, $data);
        }

        return $data;
    }
}
