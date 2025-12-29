# Balance Invariant Transfers

## Problem

You need to transfer an amount that brings an account to a specific balance (zero or another target). Common scenarios:

- Closing an account by transferring its entire balance
- Settling a debt by paying exactly what's owed
- Rebalancing accounts without calculating the exact amount in application code

## Solution

Use **balancing transfer flags** to automatically calculate the transfer amount based on the account's current balance:

- **BALANCING_DEBIT**: Calculates amount to zero out the debit account's balance
- **BALANCING_CREDIT**: Calculates amount to zero out the credit account's balance

The ledger automatically determines the correct amount at execution time.

## Example 1: Closing an Account

### Problem

A customer is closing their account. You need to transfer their entire remaining balance to another account.

### Solution

Use `BALANCING_DEBIT` to transfer the entire balance from the account being closed.

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\TransferFlags;

// Create accounts
$customerAccountId = Identifier::fromHex('11111111111111111111111111111111');
$settlementAccountId = Identifier::fromHex('22222222222222222222222222222222');

$ledger->execute(
    CreateAccount::with(
        id: $customerAccountId,
        ledger: 1,
        code: 100,
    ),
    CreateAccount::with(
        id: $settlementAccountId,
        ledger: 1,
        code: 900,  // Settlement account
    ),
);

// Customer has some balance (e.g., from previous deposits/withdrawals)
// Let's say: credits = $150, debits = $75
// Net balance = $75

// Close account by transferring entire balance
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('33333333333333333333333333333333'),
        debitAccountId: $customerAccountId,
        creditAccountId: $settlementAccountId,
        amount: 0,  // Amount is ignored - will be calculated
        ledger: 1,
        code: 99,  // Account closure
        flags: TransferFlags::BALANCING_DEBIT,
    ),
);

// The ledger automatically calculates: amount = credits - debits = $75
// After transfer: customer account has zero net balance
```

### How It Works

For `BALANCING_DEBIT`:
- Calculated amount = `debit_account.credits_posted - debit_account.debits_posted`
- This brings the debit account's net balance to zero
- The `amount` parameter in the command is ignored

## Example 2: Settling a Loan

### Problem

A customer wants to pay off their entire loan balance. The exact amount owed may have changed due to interest or fees.

### Solution

Use `BALANCING_CREDIT` to pay exactly what's owed.

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\TransferFlags;
use Castor\Ledgering\AccountFlags;

// Create accounts
$loanAccountId = Identifier::fromHex('11111111111111111111111111111111');
$customerCashId = Identifier::fromHex('22222222222222222222222222222222');

$ledger->execute(
    CreateAccount::with(
        id: $loanAccountId,
        ledger: 1,
        code: 300,  // Loan account
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
    ),
    CreateAccount::with(
        id: $customerCashId,
        ledger: 1,
        code: 100,
    ),
);

// Loan was disbursed: debits = $1000
// Customer has repaid: credits = $400
// Outstanding balance = $600

// Settle the entire loan
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('33333333333333333333333333333333'),
        debitAccountId: $customerCashId,
        creditAccountId: $loanAccountId,
        amount: 0,  // Amount is ignored - will be calculated
        ledger: 1,
        code: 11,  // Loan settlement
        flags: TransferFlags::BALANCING_CREDIT,
    ),
);

// The ledger automatically calculates: amount = debits - credits = $600
// After transfer: loan account has zero net balance (fully repaid)
```

### How It Works

For `BALANCING_CREDIT`:
- Calculated amount = `credit_account.debits_posted - credit_account.credits_posted`
- This brings the credit account's net balance to zero
- The `amount` parameter in the command is ignored

## Example 3: Combining with Pending Transfers

Balancing transfers can be combined with the `PENDING` flag for two-phase workflows.

```php
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\TransferFlags;

// Create a pending balancing transfer
$pendingId = Identifier::fromHex('44444444444444444444444444444444');

$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $customerAccountId,
        creditAccountId: $settlementAccountId,
        amount: 0,  // Will be calculated
        ledger: 1,
        code: 99,
        flags: TransferFlags::PENDING | TransferFlags::BALANCING_DEBIT,
    ),
);

// The amount is calculated and reserved in pending balances
// Later, post the pending transfer to finalize

$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('55555555555555555555555555555555'),
        debitAccountId: $customerAccountId,
        creditAccountId: $settlementAccountId,
        amount: 0,  // Not used for POST_PENDING
        ledger: 1,
        code: 99,
        pendingId: $pendingId,
        flags: TransferFlags::POST_PENDING,
    ),
);
```

## Example 4: Rebalancing Between Accounts

### Problem

You have two accounts that should maintain a specific relationship, and you need to transfer funds to restore balance.

### Solution

Use balancing transfers to automatically calculate the rebalancing amount.

```php
// Scenario: Sweep excess funds from checking to savings
// Keep checking at minimum balance, move rest to savings

$checkingId = Identifier::fromHex('11111111111111111111111111111111');
$savingsId = Identifier::fromHex('22222222222222222222222222222222');
$minimumBalanceAccountId = Identifier::fromHex('33333333333333333333333333333333');

// First, transfer minimum balance to a temporary account
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('44444444444444444444444444444444'),
        debitAccountId: $checkingId,
        creditAccountId: $minimumBalanceAccountId,
        amount: 10000,  // $100 minimum balance
        ledger: 1,
        code: 1,
    ),
);

// Then sweep remaining balance to savings
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('55555555555555555555555555555555'),
        debitAccountId: $checkingId,
        creditAccountId: $savingsId,
        amount: 0,  // Calculated automatically
        ledger: 1,
        code: 2,
        flags: TransferFlags::BALANCING_DEBIT,
    ),
);

// Finally, restore minimum balance to checking
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('66666666666666666666666666666666'),
        debitAccountId: $minimumBalanceAccountId,
        creditAccountId: $checkingId,
        amount: 10000,
        ledger: 1,
        code: 3,
    ),
);
```

## Considerations

1. **Amount Parameter Ignored**: When using balancing flags, the `amount` parameter is ignored
2. **Calculation Time**: Amount is calculated at execution time based on current account balances
3. **Zero Balance Result**: Balancing transfers bring the target account to zero net balance
4. **Pending Compatibility**: Can be combined with `PENDING` flag for two-phase workflows
5. **Cannot Combine with POST/VOID**: Balancing flags cannot be used with `POST_PENDING` or `VOID_PENDING`
6. **Account Must Exist**: Both accounts must exist before executing a balancing transfer
7. **Positive Amounts Only**: If the calculated amount would be negative or zero, the transfer may fail

## Common Use Cases

- **Account Closure**: Transfer entire balance when closing an account
- **Loan Settlement**: Pay off exact remaining loan balance
- **Debt Settlement**: Clear outstanding balances
- **Account Sweeps**: Move excess funds between accounts
- **Rebalancing**: Restore target balances across multiple accounts

## See Also

- [Domain Model](../domain-model.md#transfer-flags) - Complete list of transfer flags
- [Pending Transfers](pending-transfers.md) - Two-phase transfer workflows
- [Balance Conditional Transfers](balance-conditional-transfers.md) - Enforce balance constraints

