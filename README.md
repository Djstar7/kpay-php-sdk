# kpay/sdk — PHP / Laravel

SDK officiel KPay (PHP ≥ 8.1, cURL natif). Fonctionne en **PHP pur** ou
**Laravel** (auto-discovery : ServiceProvider + façade `Kpay`).

## PHP pur

```php
use Kpay\Sdk\KpayClient;

$kpay = new KpayClient(
    baseUrl: 'https://admin.kpay.site', // sans /api
    apiKey: getenv('KPAY_API_KEY'),
    secretKey: getenv('KPAY_SECRET_KEY'),
    gatewaySecret: getenv('KPAY_GATEWAY_SECRET'),
    maxDuration: 300,                    // SEUL paramètre configurable (s)
);

$res = $kpay->initPayment([
    'amount' => 5000,
    'externalId' => 'ORDER-123',
    'returnUrl' => 'https://monsite.com/retour',
    'cancelUrl' => 'https://monsite.com/annule',
]);
// → rediriger vers $res['gatewayUrl']
```

USSD : `['amount'=>5000,'phoneNumber'=>'674693625','paymentMethod'=>'MTN_MONEY']`.
Retrait : `$kpay->initWithdrawal([...])`.

## Laravel

```bash
php artisan vendor:publish --tag=kpay-config
```

`.env` :

```
KPAY_BASE_URL=https://admin.kpay.site
KPAY_API_KEY=...
KPAY_SECRET_KEY=...
KPAY_GATEWAY_SECRET=...
KPAY_MAX_DURATION=300
```

```php
use Kpay\Sdk\Laravel\Kpay;

$res = Kpay::initPayment(['amount' => 5000, 'returnUrl' => route('kpay.return')]);
return redirect()->away($res['gatewayUrl']);
```

## Hybride : webhook + attente

Le chemin principal recommandé reste le **webhook** (résolution
instantanée). En complément, à la page de retour :

```php
if (! Kpay::verifyReturnSignature($request->query())) abort(400);

$final = Kpay::awaitFinalStatus($paymentId, 'payment'); // s'arrête au
// statut terminal (COMPLETED|FAILED|CANCELLED|EXPIRED|REFUNDED) ou à
// l'échéance max_duration
if (($final['status'] ?? null) === 'COMPLETED') { /* livrer */ }
```

`awaitFinalStatus` sonde l'API jusqu'à un statut terminal ou la durée
maximale `max_duration` (seul paramètre configurable).

## Flux mode passerelle (détail)

1. `initPayment(['amount'=>..., 'externalId'=>..., 'returnUrl'=>..., 'cancelUrl'=>...])` → `$res['gatewayUrl']`
2. Rediriger le client vers `$res['gatewayUrl']` (page hébergée KPay :
   saisie nom / numéro / méthode).
3. KPay traite le paiement, puis renvoie le client vers `returnUrl` avec
   `?status&reference&externalId&ts&sig`.
4. À la page de retour : `verifyReturnSignature($request->query())`
   (HMAC-SHA256 sur `status|reference|externalId|ts` avec `gatewaySecret`),
   puis `awaitFinalStatus` en filet de sécurité. Le **webhook**
   (`callbackUrl` de l'application) reste la confirmation
   serveur-à-serveur de référence.

## Temps réel — WebSocket (conseillé en complément)

Le SDK PHP est synchrone (cURL natif) : le suivi temps réel se fait
**côté navigateur**, via socket.io, pour un retour visuel instantané sans
attendre le polling.

> Le WebSocket est un **complément UX**, pas la source de vérité : gardez
> le webhook (et `verifyReturnSignature`) comme confirmation autoritaire.

```html
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script>
  // namespace /payments — aucune authentification socket, abonnement par id
  const socket = io("https://admin.kpay.site/payments");
  socket.emit("subscribe:transaction", { transactionId: "<?= $paymentId ?>" });
  socket.on("payment:status_changed", (p) => {
    // p = { transactionId, status, amount, reference, externalId,
    //       completedAt, failedAt, failureReason, timestamp }
    if (["COMPLETED", "FAILED", "CANCELLED"].includes(p.status)) {
      socket.disconnect();
    }
  });
</script>
```

Abonnements disponibles (ack `{ event: "subscribed", data: { room } }`) :

| Émettre | Payload | Reçoit |
|---|---|---|
| `subscribe:transaction` | `{ transactionId }` | `payment:status_changed` |
| `subscribe:application` | `{ applicationId }` | `payment:status_changed` (toutes les transactions de l'app) |
| `subscribe:withdrawal` | `{ withdrawalId }` | `withdrawal:status_changed` |
