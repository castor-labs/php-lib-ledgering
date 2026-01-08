# Preventing Overdrafts and Overpayments

**Learn how to use account flags to enforce balance constraints and prevent invalid states in your financial system.**

## The Problem: Money Can't Be Negative (Usually)

Imagine you're building a digital wallet. A customer has £50 in their account. They try to spend £100. What should happen?

In the real world, you can't spend money you don't have (unless you have a credit line). Your ledger should enforce this rule automatically, not rely on your application code to check balances before every transaction.

Similarly, if a customer owes you £100 on a loan, they shouldn't be able to "repay" £200 and create a negative debt. That excess £100 needs to go somewhere else (like an overpayment account).

**The challenge**: How do you enforce these constraints at the ledger level, so they're impossible to violate?

## The Solution: Account Flags

Castor Ledgering provides **account flags** that enforce balance constraints automatically. Think of them as database constraints, but for money.

There are two complementary flags:

1. **`DEBITS_MUST_NOT_EXCEED_CREDITS`**: Prevents spending more than you have
2. **`CREDITS_MUST_NOT_EXCEED_DEBITS`**: Prevents paying more than you owe

These aren't just validation—they're **business rules enforced at the ledger level**. When you try to violate them, the ledger throws a `ConstraintViolation` exception and the transfer doesn't happen.

> [!NOTE]
> **Why enforce at the ledger level?**
>
> You could check balances in your application code before creating transfers:
>
> ```php
> if ($account->balance < $amount) {
>     throw new Exception("Insufficient funds");
> }
> $ledger->execute($transfer);
> ```
>
> But this has a critical flaw: **race conditions**. Between checking the balance and executing the transfer, another thread might deduct money. Your check becomes stale.
>
> Account flags are enforced **atomically** during transfer execution, using the current balance at that exact moment. No race conditions possible.

## Understanding Debits and Credits (Again)

Before we dive into examples, let's refresh the mental model:

**For asset accounts** (things you own or are owed):
- **Debit** = Increase (you have more)
- **Credit** = Decrease (you have less)

**For liability accounts** (things you owe):
- **Debit** = Decrease (you owe less)
- **Credit** = Increase (you owe more)

**Net balance** = Debits - Credits

- Positive balance = Asset (you have money or are owed money)
- Negative balance = Liability (you owe money)

Now let's see how flags enforce constraints on these balances.

## Preventing Overdrafts: Customer Cash Accounts

### The Scenario

You're building a digital wallet. Customers deposit money and spend it. You need to ensure they can't spend more than they have.

### The Account Structure

A customer cash account is an **asset from the customer's perspective**:
- Credits increase their balance (deposits)
- Debits decrease their balance (spending)

You want to prevent: **debits > credits** (spending more than deposited)

### The Implementation

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\AccountFlags;
use Castor\Ledgering\Identifier;

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
        code: 200,  // Merchant account (no flags needed)
    ),
);
```

The `DEBITS_MUST_NOT_EXCEED_CREDITS` flag enforces:

```
debits_posted + debits_pending ≤ credits_posted
```

This means: "You can only debit (spend) up to the amount that's been credited (deposited)."

### Using the Account

```php
use Castor\Ledgering\CreateTransfer;

// Customer deposits £100
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('33333333333333333333333333333333'),
        debitAccountId: $merchantId,    // Merchant gives money
        creditAccountId: $customerId,   // Customer receives money
        amount: 10000,  // £100.00
        ledger: 1,
        code: 1,  // Deposit
    ),
);

// Now: customer account has credits = £100, debits = £0
// Net balance = £0 - £100 = -£100 (they have £100)
```

Wait, negative balance? Yes! Remember, from the **ledger's perspective**, the customer account is a liability (you owe them money). A credit balance means they have money.

> [!TIP]
> **Display vs Storage**
>
> When displaying balances to customers, you'd show:
>
> ```php
> $balance = $account->balance->creditsPosted->value
>          - $account->balance->debitsPosted->value;
> echo "Balance: £" . ($balance / 100);  // £100.00
> ```
>
> The sign depends on your perspective. For customer-facing displays, flip the sign to make deposits positive.

Now let's try spending:

```php
// Customer spends £50 - this works
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('44444444444444444444444444444444'),


But what if they try to overdraft?

```php
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\ErrorCode;

// Customer tries to spend £100 (but only has £50)
try {
    $ledger->execute(
        CreateTransfer::with(
            id: Identifier::fromHex('55555555555555555555555555555555'),
            debitAccountId: $customerId,
            creditAccountId: $merchantId,
            amount: 10000,  // £100.00 - Would overdraft!
            ledger: 1,
            code: 2,
        ),
    );
} catch (ConstraintViolation $e) {
    if ($e->getCode() === ErrorCode::InsufficientFunds->value) {
        echo "Insufficient funds! You have £50, but tried to spend £100.\n";
    }
}
```

The ledger rejects the transfer because it would violate the constraint:
- Current state: credits = £100, debits = £50
- After transfer: credits = £100, debits = £150
- Constraint check: £150 > £100 ❌ **REJECTED**

The transfer doesn't happen. The account balance is unchanged. Your customer can't overdraft.

### How It Works Internally

When you execute a transfer with `DEBITS_MUST_NOT_EXCEED_CREDITS`, the ledger:

1. **Locks** the accounts involved (prevents concurrent modifications)
2. **Reads** the current balances
3. **Calculates** what the new balances would be
4. **Checks** the constraint: `new_debits ≤ credits_posted`
5. **Applies** the transfer if the constraint passes
6. **Throws** `ConstraintViolation` if the constraint fails
7. **Unlocks** the accounts

This all happens atomically—either the entire operation succeeds, or none of it does.

## Preventing Overpayment: Loan Accounts

### The Scenario

You're building a loan management system. Customers borrow money and repay it. You need to ensure they can't repay more than they owe.

Why? Because overpayments create accounting headaches. If someone owes £100 and pays £200, where does the extra £100 go? It needs to be tracked separately (in an overpayment account) so you can refund it or apply it to future payments.

### The Account Structure

A loan account is an **asset from the lender's perspective**:
- Debits increase what they owe (loan disbursement, interest, fees)
- Credits decrease what they owe (repayments)

You want to prevent: **credits > debits** (repaying more than owed)

### The Implementation

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\AccountFlags;

$loanAccountId = Identifier::fromHex('11111111111111111111111111111111');
$customerCashId = Identifier::fromHex('22222222222222222222222222222222');
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
        id: $customerCashId,
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
```

The `CREDITS_MUST_NOT_EXCEED_DEBITS` flag enforces:

```
credits_posted + credits_pending ≤ debits_posted
```

This means: "You can only credit (repay) up to the amount that's been debited (borrowed)."

### Using the Account

```php
// Disburse loan: £1,000 to customer
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('44444444444444444444444444444444'),
        debitAccountId: $loanAccountId,      // Loan account debited (customer owes)
        creditAccountId: $customerCashId,    // Customer receives cash
        amount: 100000,  // £1,000.00
        ledger: 1,
        code: 10,  // Loan disbursement
    ),
);

// Now: loan account has debits = £1,000, credits = £0
// Net balance = £1,000 - £0 = £1,000 (customer owes £1,000)
```

The customer can repay up to £1,000:

```php
// Customer repays £500
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('55555555555555555555555555555555'),
        debitAccountId: $customerCashId,  // Customer pays
        creditAccountId: $loanAccountId,  // Loan account credited (debt reduced)
        amount: 50000,  // £500.00
        ledger: 1,
        code: 11,  // Loan repayment
    ),
);

// Now: debits = £1,000, credits = £500
// Net balance = £1,000 - £500 = £500 (customer owes £500)
```

But they can't overpay:

```php
// Customer tries to repay £1,000 (but only owes £500)
try {
    $ledger->execute(
        CreateTransfer::with(
            id: Identifier::fromHex('66666666666666666666666666666666'),
            debitAccountId: $customerCashId,
            creditAccountId: $loanAccountId,
            amount: 100000,  // £1,000.00 - Would overpay!
            ledger: 1,
            code: 11,
        ),
    );
} catch (ConstraintViolation $e) {
    echo "Cannot repay more than owed! You owe £500, but tried to pay £1,000.\n";
}
```

The ledger rejects the transfer:
- Current state: debits = £1,000, credits = £500
- After transfer: debits = £1,000, credits = £1,500
- Constraint check: £1,500 > £1,000 ❌ **REJECTED**

> [!TIP]
> **Handling overpayments properly**
>
> In a real loan system, you'd use a **payment waterfall** with an overpayment account:
>
> 1. Customer pays £1,000
> 2. £500 goes to the loan account (pays off the debt)
> 3. £500 goes to an overpayment account (no constraint flag)
> 4. You can refund the overpayment or apply it to future payments
>
> See the [Loan Management Guide](loan-management.md) for a complete implementation.



## Pending Transfers and Balance Constraints

Here's where things get interesting: **pending amounts count toward constraints**.

Remember pending transfers from the [Two-Phase Payments guide](two-phase-payments.md)? They're used for pre-authorizations, escrow, and reservations. When you create a pending transfer, the funds are reserved but not yet committed.

**The key insight**: Account flags include pending amounts in their constraint checks.

### Example: Pre-Authorization

```php
use Castor\Ledgering\TransferFlags;

// Customer has £100 in their account
// credits = £100, debits = £0

// Create a pending transfer for £60 (hotel pre-authorization)
$pendingId = Identifier::fromHex('77777777777777777777777777777777');

$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $customerId,
        creditAccountId: $hotelId,
        amount: 6000,  // £60.00
        ledger: 1,
        code: 10,  // Hotel pre-auth
        flags: TransferFlags::PENDING,
    ),
);

// Now: credits_posted = £100, debits_posted = £0, debits_pending = £60
// Available balance = £100 - £0 - £60 = £40
```

The customer now has only £40 available, even though their posted balance is still £100. The £60 is reserved.

If they try to spend £50:

```php
try {
    $ledger->execute(
        CreateTransfer::with(
            id: Identifier::fromHex('88888888888888888888888888888888'),
            debitAccountId: $customerId,
            creditAccountId: $merchantId,
            amount: 5000,  // £50.00
            ledger: 1,
            code: 2,
        ),
    );
} catch (ConstraintViolation $e) {
    echo "Insufficient funds! You have £40 available (£60 is pending).\n";
}
```

The constraint check is:
```
debits_posted + debits_pending + new_debit ≤ credits_posted
£0 + £60 + £50 ≤ £100
£110 ≤ £100 ❌ **REJECTED**
```

The pending amount is included in the calculation. This prevents double-spending of reserved funds.

> [!NOTE]
> **Why include pending amounts?**
>
> Imagine a hotel pre-authorizes £60, but the constraint didn't include pending amounts. The customer could then spend their full £100 elsewhere. When the hotel tries to post the pending transfer, the customer would have insufficient funds!
>
> Including pending amounts in constraints ensures that reserved funds stay reserved.

## Prepaid Cards and Gift Cards

A common use case for `DEBITS_MUST_NOT_EXCEED_CREDITS` is prepaid cards and gift cards.

### The Scenario

You sell gift cards. Customers load money onto them and spend it. The card should:
1. Only allow spending up to the loaded amount
2. Prevent overdrafts
3. Track the remaining balance

### The Implementation

```php
$giftCardId = Identifier::fromHex('99999999999999999999999999999999');
$merchantId = Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

$ledger->execute(
    CreateAccount::with(
        id: $giftCardId,
        ledger: 1,
        code: 500,  // Gift card account
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
);

// Customer loads £50 onto the gift card
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
        debitAccountId: $merchantId,
        creditAccountId: $giftCardId,
        amount: 5000,  // £50.00
        ledger: 1,
        code: 20,  // Gift card load
    ),
);

// Customer spends £30
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('cccccccccccccccccccccccccccccccc'),
        debitAccountId: $giftCardId,
        creditAccountId: $merchantId,
        amount: 3000,  // £30.00
        ledger: 1,
        code: 21,  // Gift card purchase
    ),
);

// Remaining balance: £50 - £30 = £20
// Customer can spend up to £20 more
```

The flag ensures the gift card can never go negative. No complex balance checking needed in your application code—the ledger enforces it automatically.

## Credit Lines: The Opposite Constraint

What if you want to allow overdrafts up to a limit? Like a credit card or line of credit?

You'd use a **different account structure**:

1. **Cash account**: No flags (can go negative)
2. **Credit limit account**: Tracks the credit line with `CREDITS_MUST_NOT_EXCEED_DEBITS`

```php
$cashAccountId = Identifier::fromHex('dddddddddddddddddddddddddddddddd');
$creditLimitId = Identifier::fromHex('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');

$ledger->execute(
    // Cash account (can go negative)
    CreateAccount::with(
        id: $cashAccountId,
        ledger: 1,
        code: 100,
        // No flags - can overdraft
    ),
    // Credit limit account
    CreateAccount::with(
        id: $creditLimitId,
        ledger: 1,
        code: 600,
        flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
    ),
);

// Set credit limit to £1,000
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('ffffffffffffffffffffffffffffffff'),
        debitAccountId: $creditLimitId,
        creditAccountId: $cashAccountId,
        amount: 100000,  // £1,000.00 credit limit
        ledger: 1,
        code: 30,  // Credit limit setup
    ),
);

// Now customer can spend up to £1,000 (even with £0 balance)
// Each purchase debits cash and credits credit limit
// When credit limit is fully credited, they've used their full credit line
```

This is more complex, but it shows how account flags can model different business rules.


## Important Considerations

### 1. Constraints Are Mutually Exclusive

You cannot combine both flags on the same account:

```php
// ❌ This will fail
CreateAccount::with(
    id: $accountId,
    ledger: 1,
    code: 100,
    flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS
         | AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
)
```

Why? Because they're contradictory. The first says "debits ≤ credits" and the second says "credits ≤ debits". Together, they'd mean "debits = credits" always, which would make the account useless.

### 2. Constraints Are Checked at Execution Time

The constraint is checked when you execute a transfer, not when you create the account:

```php
// Creating an account with a flag doesn't check anything yet
$ledger->execute(
    CreateAccount::with(
        id: $accountId,
        ledger: 1,
        code: 100,
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
);

// The constraint is checked here, when you try to transfer
$ledger->execute(
    CreateTransfer::with(
        debitAccountId: $accountId,  // ← Constraint checked here
        // ...
    ),
);
```

### 3. Always Handle ConstraintViolation Exceptions

When using account flags, always catch `ConstraintViolation` exceptions:

```php
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\ErrorCode;

try {
    $ledger->execute($transfer);
} catch (ConstraintViolation $e) {
    // Check the specific error code
    match ($e->getCode()) {
        ErrorCode::InsufficientFunds->value =>
            // Handle insufficient funds
            $this->notifyCustomer("Insufficient funds"),
        ErrorCode::OverpaymentNotAllowed->value =>
            // Handle overpayment attempt
            $this->redirectToOverpaymentFlow(),
        default =>
            // Handle other constraint violations
            throw $e,
    };
}
```

### 4. Constraints Include Pending Amounts

Remember: both `debits_pending` and `credits_pending` are included in constraint checks. This is by design—it prevents double-spending of reserved funds.

### 5. Use Flags for Business Rules, Not Just Validation

Account flags aren't just validation—they're **business rules encoded in your account structure**. They make invalid states impossible, not just unlikely.

This is better than application-level validation because:
- No race conditions
- Enforced atomically
- Can't be bypassed
- Self-documenting (the account structure shows the rules)

## Common Use Cases Summary

| Use Case | Flag | Why |
|----------|------|-----|
| **Digital wallets** | `DEBITS_MUST_NOT_EXCEED_CREDITS` | Prevent spending more than deposited |
| **Prepaid cards** | `DEBITS_MUST_NOT_EXCEED_CREDITS` | Prevent overdrafts |
| **Gift cards** | `DEBITS_MUST_NOT_EXCEED_CREDITS` | Limit spending to loaded amount |
| **Loan accounts** | `CREDITS_MUST_NOT_EXCEED_DEBITS` | Prevent overpayment |
| **Debt tracking** | `CREDITS_MUST_NOT_EXCEED_DEBITS` | Ensure payments don't exceed debt |
| **Escrow accounts** | `CREDITS_MUST_NOT_EXCEED_DEBITS` | Prevent releasing more than held |
| **Control accounts** | `DEBITS_MUST_NOT_EXCEED_CREDITS` | Ensure waterfall doesn't overspend |

## Key Takeaways

1. **Account flags enforce business rules at the ledger level**, preventing invalid states atomically.

2. **`DEBITS_MUST_NOT_EXCEED_CREDITS`** prevents spending more than you have—use it for cash accounts, prepaid cards, and gift cards.

3. **`CREDITS_MUST_NOT_EXCEED_DEBITS`** prevents paying more than you owe—use it for loan accounts, debt tracking, and escrow.

4. **Pending amounts are included** in constraint checks, preventing double-spending of reserved funds.

5. **Constraints are checked atomically** during transfer execution, eliminating race conditions.

6. **Always catch `ConstraintViolation` exceptions** and handle them appropriately in your application.

7. **Flags are mutually exclusive**—you can't use both on the same account.

## What's Next?

Now that you understand how to prevent overdrafts and overpayments, you might want to learn about:

- **[Automatic Balance Calculations](automatic-balance-calculations.md)**: Use balancing transfers to automatically calculate amounts based on account balances
- **[Two-Phase Payments](two-phase-payments.md)**: Implement pre-authorizations, escrow, and reservations with pending transfers
- **[Loan Management](loan-management.md)**: Build a complete loan system with principal, interest, fees, and payment waterfalls

## See Also

- [Domain Model](../domain-model.md#account-flags) - Complete reference for account flags
- [Working with the Library](../working-with-the-library.md) - Integration patterns and best practices




