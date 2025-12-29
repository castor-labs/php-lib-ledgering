# Balance Conditional Transfers

## Problem

You need to enforce balance constraints on accounts to prevent overdrafts, overpayments, or other invalid states. For example:

- A customer cash account should never have more debits than credits (prevent overdrafts)
- A loan account should never have more credits than debits (prevent overpayment)
- A prepaid card should only allow spending up to the loaded amount

## Solution

Use **Account Flags** to enforce balance constraints at the ledger level. The library provides two complementary flags:

1. **DEBITS_MUST_NOT_EXCEED_CREDITS**: Prevents debits from exceeding credits
2. **CREDITS_MUST_NOT_EXCEED_DEBITS**: Prevents credits from exceeding debits

These constraints are enforced automatically by the ledger when executing transfers.

## Example 1: Preventing Overdrafts (Customer Cash Account)

### Problem

A customer has a cash account. They should only be able to spend money they have deposited.

### Solution

Use the `DEBITS_MUST_NOT_EXCEED_CREDITS` flag.

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\AccountFlags;
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\ErrorCode;

// Create customer cash account with overdraft protection
$customerId = Identifier::fromHex('11111111111111111111111111111111');
$merchantId = Identifier::fromHex('22222222222222222222222222222222');

$ledger->execute(
    CreateAccount::with(
        id: $customerId,
        ledger: 1,  // USD
        code: 100,  // Customer account
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
    CreateAccount::with(
        id: $merchantId,
        ledger: 1,
        code: 200,  // Merchant account
    ),
);

// Deposit $100 into customer account
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('33333333333333333333333333333333'),
        debitAccountId: $merchantId,  // Merchant gives money
        creditAccountId: $customerId,  // Customer receives money
        amount: 10000,  // $100.00
        ledger: 1,
        code: 1,
    ),
);

// Customer can spend up to $100
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('44444444444444444444444444444444'),
        debitAccountId: $customerId,  // Customer spends
        creditAccountId: $merchantId,  // Merchant receives
        amount: 5000,  // $50.00 - OK
        ledger: 1,
        code: 2,
    ),
);

// Attempting to overdraft will fail
try {
    $ledger->execute(
        CreateTransfer::with(
            id: Identifier::fromHex('55555555555555555555555555555555'),
            debitAccountId: $customerId,
            creditAccountId: $merchantId,
            amount: 10000,  // $100.00 - Would overdraft!
            ledger: 1,
            code: 2,
        ),
    );
} catch (ConstraintViolation $e) {
    if ($e->getCode() === ErrorCode::InsufficientFunds->value) {
        echo "Insufficient funds!\n";
    }
}
```

### How It Works

The constraint enforces: `debits_posted + debits_pending ≤ credits_posted`

- After deposit: credits = 10000, debits = 0 ✓
- After $50 spend: credits = 10000, debits = 5000 ✓
- Attempting $100 spend: would make debits = 15000 > credits = 10000 ✗

## Example 2: Preventing Overpayment (Loan Account)

### Problem

A customer has a loan. They should not be able to repay more than they borrowed.

### Solution

Use the `CREDITS_MUST_NOT_EXCEED_DEBITS` flag.

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\AccountFlags;

// Create loan account with overpayment protection
$loanAccountId = Identifier::fromHex('11111111111111111111111111111111');
$customerAccountId = Identifier::fromHex('22222222222222222222222222222222');
$bankAccountId = Identifier::fromHex('33333333333333333333333333333333');

$ledger->execute(
    // Loan account (tracks what customer owes)
    CreateAccount::with(
        id: $loanAccountId,
        ledger: 1,
        code: 300,  // Loan account
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
    ),
    // Customer cash account
    CreateAccount::with(
        id: $customerAccountId,
        ledger: 1,
        code: 100,
    ),
    // Bank account
    CreateAccount::with(
        id: $bankAccountId,
        ledger: 1,
        code: 400,
    ),
);

// Disburse loan: $1000 to customer
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('44444444444444444444444444444444'),
        debitAccountId: $loanAccountId,  // Loan account debited (customer owes)
        creditAccountId: $customerAccountId,  // Customer receives cash
        amount: 100000,  // $1000.00
        ledger: 1,
        code: 10,  // Loan disbursement
    ),
);

// Customer repays $500
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('55555555555555555555555555555555'),
        debitAccountId: $customerAccountId,  // Customer pays
        creditAccountId: $loanAccountId,  // Loan account credited (debt reduced)
        amount: 50000,  // $500.00 - OK
        ledger: 1,
        code: 11,  // Loan repayment
    ),
);

// Attempting to overpay will fail
try {
    $ledger->execute(
        CreateTransfer::with(
            id: Identifier::fromHex('66666666666666666666666666666666'),
            debitAccountId: $customerAccountId,
            creditAccountId: $loanAccountId,
            amount: 100000,  // $1000.00 - Would overpay!
            ledger: 1,
            code: 11,
        ),
    );
} catch (ConstraintViolation $e) {
    echo "Cannot repay more than owed!\n";
}
```

### How It Works

The constraint enforces: `credits_posted + credits_pending ≤ debits_posted`

- After disbursement: debits = 100000, credits = 0 ✓
- After $500 repayment: debits = 100000, credits = 50000 ✓
- Attempting $1000 repayment: would make credits = 150000 > debits = 100000 ✗

## Pending Transfers and Balance Constraints

Balance constraints also apply to pending amounts:

```php
// Account with constraint
$accountId = Identifier::fromHex('11111111111111111111111111111111');

$ledger->execute(
    CreateAccount::with(
        id: $accountId,
        ledger: 1,
        code: 100,
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
);

// Deposit $100
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('22222222222222222222222222222222'),
        debitAccountId: $otherAccountId,
        creditAccountId: $accountId,
        amount: 10000,
        ledger: 1,
        code: 1,
    ),
);

// Create pending transfer for $60
$pendingId = Identifier::fromHex('33333333333333333333333333333333');
$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $accountId,
        creditAccountId: $otherAccountId,
        amount: 6000,
        ledger: 1,
        code: 2,
        flags: TransferFlags::PENDING,
    ),
);

// Now only $40 is available (100 - 60 pending)
// Attempting to spend $50 will fail even though posted balance is $100
```

## Considerations

1. **Constraint Enforcement**: Constraints are checked when transfers are executed, not when accounts are created
2. **Mutually Exclusive**: The two constraint flags cannot be combined on the same account
3. **Pending Amounts**: Constraints include pending amounts in their calculations
4. **Error Handling**: Always catch `ConstraintViolation` exceptions when constraints might be violated
5. **Use Cases**: 
   - `DEBITS_MUST_NOT_EXCEED_CREDITS`: Cash accounts, prepaid cards, gift cards
   - `CREDITS_MUST_NOT_EXCEED_DEBITS`: Loan accounts, credit lines, debt tracking

## See Also

- [Domain Model](../domain-model.md#account-flags) - Complete list of account flags
- [Pending Transfers](pending-transfers.md) - Two-phase transfer workflows
- [Balance Invariant Transfers](balance-invariant-transfers.md) - Automatic balance calculations

