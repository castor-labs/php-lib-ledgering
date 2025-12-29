# Currency Exchange

## Problem

Your application needs to exchange funds between different currencies. For example, converting 100 USD to INR, or allowing users to hold balances in multiple currencies.

## Solution

Currency exchange is implemented using **linked transfers** between accounts on different ledgers. Each ledger represents a different currency.

### Key Concepts

1. **Separate Ledgers**: Each currency has its own ledger code (e.g., 1 for USD, 2 for EUR, 3 for INR)
2. **Liquidity Provider**: An entity (bank, exchange) that facilitates the exchange
3. **Linked Transfers**: Two transfers executed atomically - if one fails, both fail
4. **Exchange Rate**: Determines the amount ratio between currencies

### Account Structure

A simple currency exchange involves four accounts:

- **Source Account (A₁)**: Customer's account in source currency (e.g., USD)
- **Source Liquidity (L₁)**: Liquidity provider's account in source currency
- **Destination Liquidity (L₂)**: Liquidity provider's account in destination currency
- **Destination Account (A₂)**: Customer's account in destination currency

## Example: USD to INR Exchange

Let's exchange 100.00 USD to INR at a rate of 1 USD = 82.42135 INR.

### Step 1: Create Accounts

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\Identifier;

// Ledger codes
const USD = 1;
const INR = 2;

// Account codes
const CUSTOMER_ACCOUNT = 100;
const LIQUIDITY_ACCOUNT = 900;

// Customer accounts
$customerUsdId = Identifier::fromHex('11111111111111111111111111111111');
$customerInrId = Identifier::fromHex('22222222222222222222222222222222');

// Liquidity provider accounts
$liquidityUsdId = Identifier::fromHex('33333333333333333333333333333333');
$liquidityInrId = Identifier::fromHex('44444444444444444444444444444444');

$ledger->execute(
    // Customer USD account
    CreateAccount::with(
        id: $customerUsdId,
        ledger: USD,
        code: CUSTOMER_ACCOUNT,
    ),
    // Customer INR account
    CreateAccount::with(
        id: $customerInrId,
        ledger: INR,
        code: CUSTOMER_ACCOUNT,
    ),
    // Liquidity USD account
    CreateAccount::with(
        id: $liquidityUsdId,
        ledger: USD,
        code: LIQUIDITY_ACCOUNT,
    ),
    // Liquidity INR account
    CreateAccount::with(
        id: $liquidityInrId,
        ledger: INR,
        code: LIQUIDITY_ACCOUNT,
    ),
);
```

### Step 2: Execute Atomic Exchange

```php
use Castor\Ledgering\CreateTransfer;

// Exchange 100.00 USD = 8242.14 INR
// Amounts in smallest units (cents/paise)
$usdAmount = 10000;  // 100.00 USD
$inrAmount = 824214; // 8242.14 INR

$ledger->execute(
    // Transfer 1: USD from customer to liquidity provider
    CreateTransfer::with(
        id: Identifier::fromHex('55555555555555555555555555555555'),
        debitAccountId: $customerUsdId,
        creditAccountId: $liquidityUsdId,
        amount: $usdAmount,
        ledger: USD,
        code: 1,  // Exchange transfer
    ),
    // Transfer 2: INR from liquidity provider to customer
    CreateTransfer::with(
        id: Identifier::fromHex('66666666666666666666666666666666'),
        debitAccountId: $liquidityInrId,
        creditAccountId: $customerInrId,
        amount: $inrAmount,
        ledger: INR,
        code: 1,  // Exchange transfer
    ),
);
```

**Important**: The transfers must be submitted in the same `execute()` call. When using `TransactionalLedger` (with database storage), both transfers are executed within a single database transaction, ensuring atomicity - both transfers succeed or both fail together.

### Step 3: Adding Exchange Fees

To charge a fee (spread), add a third linked transfer:

```php
$usdAmount = 10000;  // 100.00 USD
$feeAmount = 10;     // 0.10 USD fee
$inrAmount = 824214; // 8242.14 INR

$ledger->execute(
    // Transfer 1: USD from customer to liquidity provider
    CreateTransfer::with(
        id: Identifier::fromHex('55555555555555555555555555555555'),
        debitAccountId: $customerUsdId,
        creditAccountId: $liquidityUsdId,
        amount: $usdAmount,
        ledger: USD,
        code: 1,
    ),
    // Transfer 2: Fee from customer to liquidity provider
    CreateTransfer::with(
        id: Identifier::fromHex('77777777777777777777777777777777'),
        debitAccountId: $customerUsdId,
        creditAccountId: $liquidityUsdId,
        amount: $feeAmount,
        ledger: USD,
        code: 2,  // Fee transfer
    ),
    // Transfer 3: INR from liquidity provider to customer
    CreateTransfer::with(
        id: Identifier::fromHex('66666666666666666666666666666666'),
        debitAccountId: $liquidityInrId,
        creditAccountId: $customerInrId,
        amount: $inrAmount,
        ledger: INR,
        code: 1,
    ),
);
```

## Complete Example

```php
<?php

use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\InMemory\AccountBalanceCollection;
use Castor\Ledgering\Storage\InMemory\AccountCollection;
use Castor\Ledgering\Storage\InMemory\TransferCollection;
use Castor\Ledgering\TransferFlags;

$ledger = new StandardLedger(
    accounts: new AccountCollection(),
    transfers: new TransferCollection(),
    accountBalances: new AccountBalanceCollection(),
);

// Create accounts
$customerUsdId = Identifier::fromHex('11111111111111111111111111111111');
$customerInrId = Identifier::fromHex('22222222222222222222222222222222');
$liquidityUsdId = Identifier::fromHex('33333333333333333333333333333333');
$liquidityInrId = Identifier::fromHex('44444444444444444444444444444444');

// (Account creation code from Step 1)

// Execute exchange with fee
// (Transfer code from Step 3)
```

## Considerations

1. **Exchange Rate Precision**: Always round in favor of the liquidity provider to prevent arbitrage
2. **Atomic Execution**: Use `TransactionalLedger` to ensure all transfers in an exchange complete or none do
3. **Separate Fee Transfer**: Recording fees separately preserves the exchange rate information
4. **Ledger Isolation**: Transfers can only occur between accounts on the same ledger
5. **Balance Requirements**: Use account flags like `DEBITS_MUST_NOT_EXCEED_CREDITS` to prevent overdrafts

## See Also

- [Pending Transfers](pending-transfers.md) - Use pending transfers for exchange reservations
- [Balance Conditional Transfers](balance-conditional-transfers.md) - Prevent overdrafts during exchange

