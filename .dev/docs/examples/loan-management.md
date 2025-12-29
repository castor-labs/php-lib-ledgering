# Loan Management System

This comprehensive example demonstrates how to implement a complete loan management system using Castor Ledgering. We'll cover:

- Loan disbursement
- Principal and interest tracking
- Regular repayments
- Missed payments and late fees
- Early payoff
- Loan closure

## Overview

A loan management system requires tracking multiple components:

1. **Principal Account**: Tracks the original loan amount owed
2. **Interest Account**: Tracks accrued interest
3. **Fee Account**: Tracks late fees and other charges
4. **Customer Cash Account**: Customer's available funds
5. **Bank Account**: Bank's funds for disbursement and collection

## Account Structure

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\AccountFlags;

// Account codes
const CUSTOMER_CASH = 100;
const LOAN_PRINCIPAL = 300;
const LOAN_INTEREST = 301;
const LOAN_FEES = 302;
const BANK_CASH = 400;

// Ledger code
const USD = 1;

// Create customer's cash account
$customerCashId = Identifier::fromHex('11111111111111111111111111111111');

// Create loan accounts (one set per loan)
$loanPrincipalId = Identifier::fromHex('22222222222222222222222222222222');
$loanInterestId = Identifier::fromHex('33333333333333333333333333333333');
$loanFeesId = Identifier::fromHex('44444444444444444444444444444444');

// Create bank account
$bankCashId = Identifier::fromHex('55555555555555555555555555555555');

$ledger->execute(
    // Customer cash account
    CreateAccount::with(
        id: $customerCashId,
        ledger: USD,
        code: CUSTOMER_CASH,
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
    // Loan principal account (tracks principal owed)
    CreateAccount::with(
        id: $loanPrincipalId,
        ledger: USD,
        code: LOAN_PRINCIPAL,
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS | AccountFlags::HISTORY,
    ),
    // Loan interest account (tracks interest owed)
    CreateAccount::with(
        id: $loanInterestId,
        ledger: USD,
        code: LOAN_INTEREST,
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS | AccountFlags::HISTORY,
    ),
    // Loan fees account (tracks fees owed)
    CreateAccount::with(
        id: $loanFeesId,
        ledger: USD,
        code: LOAN_FEES,
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
    ),
    // Bank cash account
    CreateAccount::with(
        id: $bankCashId,
        ledger: USD,
        code: BANK_CASH,
    ),
);
```

### Why This Structure?

- **Separate Principal and Interest**: Allows tracking each component independently
- **CREDITS_MUST_NOT_EXCEED_DEBITS**: Prevents overpayment on loan accounts
- **HISTORY flag**: Enables balance tracking for principal and interest over time
- **DEBITS_MUST_NOT_EXCEED_CREDITS**: Prevents customer from overdrawing cash account

## Loan Disbursement

When a loan is approved, disburse funds to the customer.

```php
use Castor\Ledgering\CreateTransfer;

// Disburse $10,000 loan
$loanAmount = 1000000;  // $10,000.00

$ledger->execute(
    // Debit loan principal (customer owes this)
    CreateTransfer::with(
        id: Identifier::fromHex('66666666666666666666666666666666'),
        debitAccountId: $loanPrincipalId,
        creditAccountId: $customerCashId,
        amount: $loanAmount,
        ledger: USD,
        code: 10,  // Loan disbursement
    ),
    // Credit customer cash (customer receives funds)
    // This is handled by the same transfer above
);

// Alternative: Disburse directly to bank account if customer wants funds elsewhere
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('77777777777777777777777777777777'),
        debitAccountId: $loanPrincipalId,
        creditAccountId: $bankCashId,  // Funds go to external account
        amount: $loanAmount,
        ledger: USD,
        code: 10,
    ),
);
```

### Result

- Loan Principal Account: debits = $10,000, credits = $0 (owes $10,000)
- Customer Cash Account: credits = $10,000, debits = $0 (has $10,000)

## Accruing Interest

Interest accrues over time and is added to the interest account.

```php
// Accrue monthly interest (e.g., 1% per month on outstanding principal)
// Calculate: $10,000 * 0.01 = $100

$interestAmount = 10000;  // $100.00

$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('88888888888888888888888888888888'),
        debitAccountId: $loanInterestId,  // Interest owed increases
        creditAccountId: $bankCashId,     // Bank earns interest
        amount: $interestAmount,
        ledger: USD,
        code: 20,  // Interest accrual
    ),
);
```

### Result

- Loan Fees Account: debits = $25, credits = $25 (fully paid)
- Loan Interest Account: debits = $200, credits = $200 (fully paid)
- Loan Principal Account: debits = $10,000, credits = $775 (owes $9,225)

## Early Payoff

Customer wants to pay off the entire loan early. Use balancing transfers to calculate exact amounts.

```php
use Castor\Ledgering\TransferFlags;

// Customer wants to pay off entire loan
// Current balances:
// - Fees owed: $0
// - Interest owed: $50 (some accrued)
// - Principal owed: $9,225

// Pay off interest using balancing transfer
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('ffffffffffffffffffffffffffffffff'),
        debitAccountId: $customerCashId,
        creditAccountId: $loanInterestId,
        amount: 0,  // Calculated automatically
        ledger: USD,
        code: 21,
        flags: TransferFlags::BALANCING_CREDIT,  // Pay exact interest owed
    ),
);

// Pay off principal using balancing transfer
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('00000000000000000000000000000001'),
        debitAccountId: $customerCashId,
        creditAccountId: $loanPrincipalId,
        amount: 0,  // Calculated automatically
        ledger: USD,
        code: 22,
        flags: TransferFlags::BALANCING_CREDIT,  // Pay exact principal owed
    ),
);
```

### Result

- All loan accounts have zero net balance (fully paid off)
- Customer Cash Account: debited by exact payoff amount

## Closing the Loan

Once fully paid, close the loan accounts to prevent further activity.

```php
// First, verify all balances are zero
$principal = $accounts->ofId($loanPrincipalId)->one();
$interest = $accounts->ofId($loanInterestId)->one();
$fees = $accounts->ofId($loanFeesId)->one();

$principalBalance = $principal->balance->debitsPosted->value - $principal->balance->creditsPosted->value;
$interestBalance = $interest->balance->debitsPosted->value - $interest->balance->creditsPosted->value;
$feesBalance = $fees->balance->debitsPosted->value - $fees->balance->creditsPosted->value;

if ($principalBalance === 0 && $interestBalance === 0 && $feesBalance === 0) {
    // Mark accounts as closed (requires updating account flags)
    // Note: The library doesn't support updating account flags after creation
    // In practice, you would track loan status in your application database
    echo "Loan fully paid and closed\n";
}
```

## Querying Loan Status

### Current Balance Owed

```php
// Get current balances
$principal = $accounts->ofId($loanPrincipalId)->one();
$interest = $accounts->ofId($loanInterestId)->one();
$fees = $accounts->ofId($loanFeesId)->one();

// Calculate amounts owed (debits - credits)
$principalOwed = $principal->balance->debitsPosted->value - $principal->balance->creditsPosted->value;
$interestOwed = $interest->balance->debitsPosted->value - $interest->balance->creditsPosted->value;
$feesOwed = $fees->balance->debitsPosted->value - $fees->balance->creditsPosted->value;

$totalOwed = $principalOwed + $interestOwed + $feesOwed;

echo "Principal owed: $" . ($principalOwed / 100) . "\n";
echo "Interest owed: $" . ($interestOwed / 100) . "\n";
echo "Fees owed: $" . ($feesOwed / 100) . "\n";
echo "Total owed: $" . ($totalOwed / 100) . "\n";
```

### Payment History

```php
// Get all payments made on principal
$principalPayments = $transfers
    ->ofCreditAccount($loanPrincipalId)
    ->toList();

foreach ($principalPayments as $payment) {
    echo "Payment of $" . ($payment->amount->value / 100) .
         " on " . date('Y-m-d', $payment->timestamp->seconds) . "\n";
}
```

### Balance History

```php
// Get principal balance history (requires HISTORY flag)
$balanceHistory = $accountBalances
    ->ofAccountId($loanPrincipalId)
    ->toList();

foreach ($balanceHistory as $snapshot) {
    $owed = $snapshot->balance->debitsPosted->value - $snapshot->balance->creditsPosted->value;
    echo "Balance on " . date('Y-m-d', $snapshot->timestamp->seconds) .
         ": $" . ($owed / 100) . "\n";
}
```

## Complete Example

Here's a complete working example:

```php
<?php

use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\InMemory\AccountBalanceCollection;
use Castor\Ledgering\Storage\InMemory\AccountCollection;
use Castor\Ledgering\Storage\InMemory\TransferCollection;
use Castor\Ledgering\AccountFlags;
use Castor\Ledgering\TransferFlags;

// Initialize ledger
$accounts = new AccountCollection();
$transfers = new TransferCollection();
$accountBalances = new AccountBalanceCollection();

$ledger = new StandardLedger($accounts, $transfers, $accountBalances);

// Account IDs
$customerCashId = Identifier::fromHex('11111111111111111111111111111111');
$loanPrincipalId = Identifier::fromHex('22222222222222222222222222222222');
$loanInterestId = Identifier::fromHex('33333333333333333333333333333333');
$loanFeesId = Identifier::fromHex('44444444444444444444444444444444');
$bankCashId = Identifier::fromHex('55555555555555555555555555555555');

// Create accounts
$ledger->execute(
    CreateAccount::with(
        id: $customerCashId,
        ledger: 1,
        code: 100,
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
    CreateAccount::with(
        id: $loanPrincipalId,
        ledger: 1,
        code: 300,
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS | AccountFlags::HISTORY,
    ),
    CreateAccount::with(
        id: $loanInterestId,
        ledger: 1,
        code: 301,
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS | AccountFlags::HISTORY,
    ),
    CreateAccount::with(
        id: $loanFeesId,
        ledger: 1,
        code: 302,
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
    ),
    CreateAccount::with(
        id: $bankCashId,
        ledger: 1,
        code: 400,
    ),
);

// Disburse loan
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('66666666666666666666666666666666'),
        debitAccountId: $loanPrincipalId,
        creditAccountId: $customerCashId,
        amount: 1000000,  // $10,000
        ledger: 1,
        code: 10,
    ),
);

// Accrue interest
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('88888888888888888888888888888888'),
        debitAccountId: $loanInterestId,
        creditAccountId: $bankCashId,
        amount: 10000,  // $100
        ledger: 1,
        code: 20,
    ),
);

// Make payment
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('99999999999999999999999999999999'),
        debitAccountId: $customerCashId,
        creditAccountId: $loanInterestId,
        amount: 10000,  // $100 to interest
        ledger: 1,
        code: 21,
    ),
    CreateTransfer::with(
        id: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
        debitAccountId: $customerCashId,
        creditAccountId: $loanPrincipalId,
        amount: 40000,  // $400 to principal
        ledger: 1,
        code: 22,
    ),
);

// Check balances
$principal = $accounts->ofId($loanPrincipalId)->one();
$principalOwed = $principal->balance->debitsPosted->value - $principal->balance->creditsPosted->value;

echo "Principal owed: $" . ($principalOwed / 100) . "\n";  // $9,600
```

## Key Takeaways

1. **Separate Accounts**: Use separate accounts for principal, interest, and fees
2. **Account Flags**: Use `CREDITS_MUST_NOT_EXCEED_DEBITS` to prevent overpayment
3. **Payment Order**: Apply payments in order: fees → interest → principal
4. **Balancing Transfers**: Use for early payoff to calculate exact amounts
5. **History Tracking**: Enable `HISTORY` flag for audit trails
6. **External IDs**: Link loan accounts to your application's loan records

## See Also

- [Balance Conditional Transfers](../cookbook/balance-conditional-transfers.md) - Prevent overpayment
- [Balance Invariant Transfers](../cookbook/balance-invariant-transfers.md) - Early payoff calculations
- [Domain Model](../domain-model.md) - Understanding accounts and transfers


