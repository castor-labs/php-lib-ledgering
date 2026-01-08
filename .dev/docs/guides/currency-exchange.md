# Currency Exchange

**Learn how to exchange money between different currencies using liquidity accounts.**

## The Problem

You have a customer with USD who wants to buy something priced in EUR. How do you handle this?

You might think: "Just create a transfer from the USD account to the EUR account!" But there's a problem:

**Transfers can only happen between accounts on the same ledger.**

Remember, the ledger code represents the currency. You can't transfer directly from ledger 1 (USD) to ledger 2 (EUR). It's like trying to pour dollars into a euro-shaped hole—it doesn't fit.

So how do you exchange currencies? The answer: **liquidity accounts**.

## The Solution: Liquidity Accounts

A **liquidity account** is a special account that represents your available inventory of a currency. Think of it like a currency exchange booth at the airport—they have a stock of different currencies they can trade.

Here's the pattern:

1. **Customer pays in USD** → Transfer from customer's USD account to your USD liquidity account
2. **You pay customer in EUR** → Transfer from your EUR liquidity account to customer's EUR account

Two transfers, two ledgers, one exchange.

## The Four Accounts

Every currency exchange involves exactly four accounts:

```
┌─────────────────────┐         ┌─────────────────────┐
│   Customer USD      │         │   Customer EUR      │
│   (Ledger 1)        │         │   (Ledger 2)        │
│   Balance: $100     │         │   Balance: €0       │
└─────────────────────┘         └─────────────────────┘
          │                                ▲
          │ Transfer 1                     │ Transfer 2
          │ $100                           │ €85
          ▼                                │
┌─────────────────────┐         ┌─────────────────────┐
│   Liquidity USD     │         │   Liquidity EUR     │
│   (Ledger 1)        │         │   (Ledger 2)        │
│   Your inventory    │         │   Your inventory    │
└─────────────────────┘         └─────────────────────┘
```

1. **Customer's source account** (USD) - Where they're paying from
2. **Your liquidity account** (USD) - Your USD inventory
3. **Your liquidity account** (EUR) - Your EUR inventory
4. **Customer's destination account** (EUR) - Where they're receiving

## Example: Converting $100 to EUR

Let's say:
- Exchange rate: 1 USD = 0.85 EUR
- Customer wants to convert $100

Here's the code:

```php
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Amount;
use Castor\Ledgering\TransferFlags;

// Account IDs
$customerUsdAccountId = Identifier::fromHex('1111...');
$liquidityUsdAccountId = Identifier::fromHex('2222...');
$liquidityEurAccountId = Identifier::fromHex('3333...');
$customerEurAccountId = Identifier::fromHex('4444...');

// Transfer 1: Customer pays $100 USD
$transfer1 = CreateTransfer::with(
    id: Identifier::fromHex('aaaa...'),
    debitAccountId: $customerUsdAccountId,
    creditAccountId: $liquidityUsdAccountId,
    amount: Amount::of(10000),  // $100.00
    ledger: 1,  // USD
    code: 10,   // Currency exchange
);

// Transfer 2: Customer receives €85 EUR
$transfer2 = CreateTransfer::with(
    id: Identifier::fromHex('bbbb...'),
    debitAccountId: $liquidityEurAccountId,
    creditAccountId: $customerEurAccountId,
    amount: Amount::of(8500),  // €85.00
    ledger: 2,  // EUR
    code: 10,   // Currency exchange
);

// Execute both transfers atomically
$ledger->execute($transfer1, $transfer2);
```

After this:
- Customer's USD account: $100 → $0
- Your USD liquidity: +$100
- Your EUR liquidity: -€85
- Customer's EUR account: €0 → €85

## Linking the Transfers

You should link these two transfers together so you can track them as a single exchange operation:

```php
// Generate a unique exchange ID
$exchangeId = Identifier::fromHex(md5('exchange-' . $orderId));

$transfer1 = CreateTransfer::with(
    // ... other fields
    externalIdPrimary: $exchangeId,  // Link to exchange
    externalIdSecondary: Identifier::fromHex(md5($customerId)),
);

$transfer2 = CreateTransfer::with(
    // ... other fields
    externalIdPrimary: $exchangeId,  // Same exchange ID!
    externalIdSecondary: Identifier::fromHex(md5($customerId)),
);
```

Now you can query both transfers by the exchange ID:

```php
// Find all transfers for this exchange
$exchangeTransfers = $transfers
    ->ofExternalIdPrimary($exchangeId)
    ->toList();

// Should return 2 transfers
assert(count($exchangeTransfers) === 2);
```

## Calculating Exchange Rates

The ledger doesn't calculate exchange rates for you—you need to do that in your application code:

```php
function convertCurrency(
    int $amountCents,
    float $exchangeRate
): int {
    // Convert to target currency
    $converted = $amountCents * $exchangeRate;

    // Round to nearest cent
    return (int) round($converted);

> [!TIP]
> **Where do exchange rates come from?**
>
> You typically get exchange rates from:
> - External APIs (e.g., exchangerate-api.com, fixer.io)
> - Your own pricing (you set the rate and take a spread)
> - Real-time market data feeds
>
> Store the rate you used in the transfer's external fields so you can audit it later.

## Managing Liquidity

Your liquidity accounts are your **inventory** of each currency. You need to manage them carefully.

### Monitoring Liquidity

Always know how much of each currency you have:

```php
// Check USD liquidity
$usdLiquidity = $accounts->ofId($liquidityUsdAccountId)->one();
$availableUsd = $usdLiquidity->balance->creditsPosted->value
              - $usdLiquidity->balance->debitsPosted->value;

echo "Available USD: $" . ($availableUsd / 100) . "\n";

// Check EUR liquidity
$eurLiquidity = $accounts->ofId($liquidityEurAccountId)->one();
$availableEur = $eurLiquidity->balance->creditsPosted->value
              - $eurLiquidity->balance->debitsPosted->value;

echo "Available EUR: €" . ($availableEur / 100) . "\n";
```

### Preventing Overdrafts

Use the `DEBITS_MUST_NOT_EXCEED_CREDITS` flag on liquidity accounts to prevent running out:

```php
CreateAccount::with(
    id: $liquidityEurAccountId,
    ledger: 2,  // EUR
    code: 900,  // Liquidity account
    flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,  // Can't overdraft!
);
```

Now if you try to exchange more EUR than you have, the transfer will fail:

```php
// You only have €100 in liquidity
// Customer tries to exchange $200 → €170

try {
    $ledger->execute($transfer1, $transfer2);
} catch (ConstraintViolation $e) {
    if ($e->getCode() === ErrorCode::InsufficientFunds->value) {
        // Not enough EUR liquidity!
        echo "Sorry, we don't have enough EUR in stock.\n";
    }
}
```

### Rebalancing Liquidity

Over time, your liquidity accounts will become imbalanced. You'll accumulate too much of one currency and run low on another.

You need to **rebalance** by:
1. Buying more of the currency you're low on
2. Selling excess currency you've accumulated

This happens outside the ledger (with banks, exchanges, etc.), but you record it in the ledger:

```php
// You bought €10,000 from your bank for $11,500
// Record the incoming EUR
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('cccc...'),
        debitAccountId: $bankEurAccountId,      // Bank's EUR account
        creditAccountId: $liquidityEurAccountId, // Your EUR liquidity
        amount: Amount::of(1000000),  // €10,000
        ledger: 2,  // EUR
        code: 20,   // Liquidity rebalancing
    ),
);

// Record the outgoing USD
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('dddd...'),
        debitAccountId: $liquidityUsdAccountId,  // Your USD liquidity
        creditAccountId: $bankUsdAccountId,      // Bank's USD account
        amount: Amount::of(1150000),  // $11,500
        ledger: 1,  // USD
        code: 20,   // Liquidity rebalancing
    ),
);
```

## Two-Phase Currency Exchange

What if you want to **reserve** the exchange rate before finalizing it? Use pending transfers!

### Step 1: Reserve Both Currencies

```php
// Reserve $100 USD from customer
$pendingTransfer1 = CreateTransfer::with(
    id: Identifier::fromHex('aaaa...'),
    debitAccountId: $customerUsdAccountId,
    creditAccountId: $liquidityUsdAccountId,
    amount: Amount::of(10000),
    ledger: 1,
    code: 10,
    flags: TransferFlags::PENDING,  // Reserve!
    timeout: Duration::ofMinutes(15),  // 15-minute hold
);

// Reserve €85 EUR for customer
$pendingTransfer2 = CreateTransfer::with(
    id: Identifier::fromHex('bbbb...'),
    debitAccountId: $liquidityEurAccountId,
    creditAccountId: $customerEurAccountId,
    amount: Amount::of(8500),
    ledger: 2,
    code: 10,
    flags: TransferFlags::PENDING,  // Reserve!
    timeout: Duration::ofMinutes(15),
);

$ledger->execute($pendingTransfer1, $pendingTransfer2);
```

Now:
- Customer's USD is reserved (can't spend it elsewhere)
- Your EUR is reserved (can't sell it to someone else)
- Exchange rate is locked in for 15 minutes

### Step 2: Customer Confirms

```php
// Customer confirms the exchange
$postTransfer1 = CreateTransfer::with(
    id: Identifier::fromHex('eeee...'),
    flags: TransferFlags::POST_PENDING,
    pendingId: Identifier::fromHex('aaaa...'),  // Reference pending transfer
);

$postTransfer2 = CreateTransfer::with(
    id: Identifier::fromHex('ffff...'),
    flags: TransferFlags::POST_PENDING,
    pendingId: Identifier::fromHex('bbbb...'),
);

$ledger->execute($postTransfer1, $postTransfer2);
```

The exchange is now final!

### Step 2 (Alternative): Customer Cancels

```php
// Customer cancels the exchange
$voidTransfer1 = CreateTransfer::with(
    id: Identifier::fromHex('eeee...'),
    flags: TransferFlags::VOID_PENDING,
    pendingId: Identifier::fromHex('aaaa...'),
);

$voidTransfer2 = CreateTransfer::with(
    id: Identifier::fromHex('ffff...'),
    flags: TransferFlags::VOID_PENDING,
    pendingId: Identifier::fromHex('bbbb...'),
);

$ledger->execute($voidTransfer1, $voidTransfer2);
```

Everything is released. No exchange happened.

## Complete Example: Currency Exchange Service

Here's a complete service that handles currency exchange:

```php
final class CurrencyExchangeService
{
    public function __construct(
        private Ledger $ledger,
        private AccountReader $accounts,
        private ExchangeRateProvider $rateProvider,
    ) {}

    public function exchange(
        Identifier $customerId,
        int $amountCents,
        int $sourceLedger,
        int $targetLedger,
    ): ExchangeResult {
        // Get exchange rate
        $rate = $this->rateProvider->getRate($sourceLedger, $targetLedger);
        $targetAmount = (int) round($amountCents * $rate);

        // Find customer accounts
        $sourceAccount = $this->accounts
            ->ofExternalIdPrimary($customerId)
            ->ofLedger($sourceLedger)
            ->one();

        $targetAccount = $this->accounts
            ->ofExternalIdPrimary($customerId)
            ->ofLedger($targetLedger)
            ->one();

        // Find liquidity accounts
        $sourceLiquidity = $this->accounts
            ->ofCode(900)  // Liquidity account code
            ->ofLedger($sourceLedger)
            ->one();

        $targetLiquidity = $this->accounts
            ->ofCode(900)
            ->ofLedger($targetLedger)
            ->one();

        // Generate IDs
        $exchangeId = Identifier::fromHex(bin2hex(random_bytes(16)));
        $transfer1Id = Identifier::fromHex(bin2hex(random_bytes(16)));
        $transfer2Id = Identifier::fromHex(bin2hex(random_bytes(16)));

        // Create transfers
        $transfer1 = CreateTransfer::with(
            id: $transfer1Id,
            debitAccountId: $sourceAccount->id,
            creditAccountId: $sourceLiquidity->id,
            amount: Amount::of($amountCents),
            ledger: $sourceLedger,
            code: 10,  // Exchange
            externalIdPrimary: $exchangeId,
        );

        $transfer2 = CreateTransfer::with(
            id: $transfer2Id,
            debitAccountId: $targetLiquidity->id,
            creditAccountId: $targetAccount->id,
            amount: Amount::of($targetAmount),
            ledger: $targetLedger,
            code: 10,  // Exchange
            externalIdPrimary: $exchangeId,
        );

        // Execute atomically
        try {
            $this->ledger->execute($transfer1, $transfer2);

            return new ExchangeResult(
                exchangeId: $exchangeId,
                sourceAmount: $amountCents,
                targetAmount: $targetAmount,
                rate: $rate,
            );
        } catch (ConstraintViolation $e) {
            if ($e->getCode() === ErrorCode::InsufficientFunds->value) {
                throw new InsufficientLiquidityException(
                    "Not enough liquidity for this exchange"
                );
            }
            throw $e;
        }
    }
}
```

Usage:

```php
$service = new CurrencyExchangeService($ledger, $accounts, $rateProvider);

try {
    $result = $service->exchange(
        customerId: Identifier::fromHex(md5('customer-123')),
        amountCents: 10000,      // $100.00
        sourceLedger: 1,         // USD
        targetLedger: 2,         // EUR
    );

    echo "Exchanged ${$result->sourceAmount / 100} ";
    echo "for €{$result->targetAmount / 100} ";
    echo "at rate {$result->rate}\n";

} catch (InsufficientLiquidityException $e) {
    echo "Exchange failed: {$e->getMessage()}\n";
}
```

## Key Takeaways

Let's recap what we learned:

1. **You can't transfer directly between ledgers.** Transfers only work within the same ledger (same currency).

2. **Liquidity accounts are your currency inventory.** They represent how much of each currency you have available to exchange.

3. **Every exchange involves four accounts:**
   - Customer's source account (e.g., USD)
   - Your source liquidity account (e.g., USD)
   - Your target liquidity account (e.g., EUR)
   - Customer's target account (e.g., EUR)

4. **Two transfers, executed atomically.** Both must succeed or both must fail. No partial exchanges.

5. **Link the transfers together** using external IDs so you can track them as a single exchange operation.

6. **Protect your liquidity** using the `DEBITS_MUST_NOT_EXCEED_CREDITS` flag to prevent running out of a currency.

7. **Monitor and rebalance** your liquidity accounts regularly to ensure you have enough of each currency.

8. **Use pending transfers** to lock in exchange rates before finalizing the exchange.

## Common Pitfalls

### ❌ Don't: Try to transfer between different ledgers

```php
// This will fail!
CreateTransfer::with(
    debitAccountId: $usdAccountId,   // Ledger 1
    creditAccountId: $eurAccountId,  // Ledger 2
    ledger: 1,  // Which ledger is this?!
);
```

### ✓ Do: Use two separate transfers

```php
// Transfer 1: USD → USD liquidity
CreateTransfer::with(
    debitAccountId: $usdAccountId,
    creditAccountId: $usdLiquidityId,
    ledger: 1,  // USD
);

// Transfer 2: EUR liquidity → EUR
CreateTransfer::with(
    debitAccountId: $eurLiquidityId,
    creditAccountId: $eurAccountId,
    ledger: 2,  // EUR
);
```

### ❌ Don't: Forget to link the transfers

Without linking, you can't tell which transfers belong to the same exchange.

### ✓ Do: Use external IDs to link them

```php
$exchangeId = Identifier::fromHex(bin2hex(random_bytes(16)));

// Both transfers get the same external ID
CreateTransfer::with(
    // ...
    externalIdPrimary: $exchangeId,
);
```

### ❌ Don't: Forget to protect liquidity accounts

Without constraints, you could accidentally exchange more currency than you have.

### ✓ Do: Use account flags

```php
CreateAccount::with(
    // ...
    flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
);
```

## What's Next?

Now you know how to handle currency exchange! Here are some related topics:

- **[Two-Phase Payments](two-phase-payments.md)** - Learn more about pending transfers
- **[Preventing Overdrafts and Overpayments](preventing-overdrafts-and-overpayments.md)** - Understand account flags in depth
- **[Working with the Library](../working-with-the-library.md)** - Learn about querying and error handling
- **[Domain Model](../domain-model.md)** - Complete reference for all entities

## Real-World Applications

Currency exchange is useful for:

- **Multi-currency wallets** - Let users hold and exchange different currencies
- **International payments** - Accept payments in one currency, pay out in another
- **Forex trading platforms** - Track currency trades and positions
- **Travel apps** - Convert between local and home currency
- **Crypto exchanges** - Exchange between different cryptocurrencies (each crypto is a different ledger)
- **Loyalty points** - Exchange points for cash or vice versa (points are just another "currency")


