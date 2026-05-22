# Automatic Balance Calculations

**Learn how to use balancing transfers to let the ledger calculate transfer amounts automatically, eliminating race conditions and simplifying your code.**

## The Problem: Calculating Amounts is Harder Than It Looks

Imagine you're closing a customer's account. They have some balance remaining—maybe £73.42. You need to transfer the entire balance to a settlement account.

Your first instinct might be:

```php
// ❌ Don't do this!
$account = $accounts->ofId($customerId)->one();
$balance = $account->balance->creditsPosted->value
          - $account->balance->debitsPosted->value;

$ledger->execute(
    CreateTransfer::with(
        debitAccountId: $customerId,
        creditAccountId: $settlementId,
        amount: $balance,  // Using the balance we just read
        // ...
    ),
);
```

This looks reasonable, but it has a **critical flaw**: **race conditions**.

### The Race Condition Problem

Between reading the balance and executing the transfer, the balance might change:

**Timeline:**
1. **Thread A** reads balance: £73.42
2. **Thread B** adds £10 fee: balance is now £83.42
3. **Thread A** transfers £73.42 (based on stale data)
4. **Result**: £10 is left in the account! ❌

Or worse:

1. **Thread A** reads balance: £73.42
2. **Thread B** deducts £20: balance is now £53.42
3. **Thread A** tries to transfer £73.42
4. **Result**: Insufficient funds error! ❌

In a production system with multiple processes, background jobs, webhooks, and concurrent requests, these race conditions are inevitable.

> [!WARNING]
> **The read-then-write anti-pattern**
>
> This pattern is dangerous in any concurrent system:
>
> ```php
> // 1. Read state
> $value = $this->readSomething();
>
> // 2. Calculate based on that state
> $newValue = $this->calculate($value);
>
> // 3. Write based on the calculation
> $this->writeSomething($newValue);
> ```
>
> Between steps 1 and 3, the state might change. Your calculation becomes stale.
>
> This is called a **check-then-act** race condition, and it's one of the most common concurrency bugs.

## The Solution: Balancing Transfers

Castor Ledgering provides **balancing transfer flags** that tell the ledger: "Calculate the amount for me, using the current balance at the moment of execution."

There are two flags:

1. **`BALANCING_DEBIT`**: Calculates the amount needed to zero out the debit account
2. **`BALANCING_CREDIT`**: Calculates the amount needed to zero out the credit account

When you use these flags:
- Set `amount: 0` (it's ignored anyway)
- The ledger calculates the amount atomically
- No race conditions possible

### How It Works

```php
use Castor\Ledgering\TransferFlags;

$ledger->execute(
    CreateTransfer::with(
        id: $transferId,
        debitAccountId: $customerId,
        creditAccountId: $settlementId,
        amount: 0,  // Ignored! The ledger calculates this
        ledger: 1,
        code: 99,  // Account closure
        flags: TransferFlags::BALANCING_DEBIT,
    ),
);
```

The ledger:
1. **Locks** the accounts
2. **Reads** the current balance of the debit account
3. **Calculates**: `amount = credits_posted - debits_posted`
4. **Executes** the transfer with that amount
5. **Unlocks** the accounts

All atomically. No race conditions.

> [!NOTE]
> **Why set amount to 0?**
>
> The `amount` parameter is required by the API, but it's ignored when you use balancing flags. Convention is to set it to `0` to make it clear you're not using it.
>
> Some developers prefer to set it to the *expected* amount for documentation purposes:
>
> ```php
> amount: 7342,  // Expected: £73.42 (but ledger will calculate actual)
> ```
>
> Either way, the ledger ignores it and calculates the actual amount.

## Understanding the Two Flags

### BALANCING_DEBIT: Zero Out the Debit Account

**Formula**: `amount = debit_account.credits_posted - debit_account.debits_posted`

This calculates the amount needed to bring the **debit account** to zero balance.

**Use when**: You want to drain the debit account completely.

**Example**: Closing an account, sweeping funds, settling a balance.

### BALANCING_CREDIT: Zero Out the Credit Account

**Formula**: `amount = credit_account.debits_posted - credit_account.credits_posted`

This calculates the amount needed to bring the **credit account** to zero balance.

**Use when**: You want to pay off the credit account completely.

**Example**: Settling a loan, paying off a debt, clearing an obligation.

> [!TIP]
> **Which flag to use?**
>
> Ask yourself: "Which account do I want to zero out?"
>
> - **Debit account** → `BALANCING_DEBIT`
> - **Credit account** → `BALANCING_CREDIT`


**What happened?**

The ledger looked at the customer account:
- Credits posted: £150
- Debits posted: £76.58
- **Calculated amount**: £150 - £76.58 = £73.42

It then executed a transfer for £73.42, which:
- Debited the customer account: debits = £76.58 + £73.42 = £150
- Credited the settlement account: credits = £73.42

The customer account now has:
- Credits: £150
- Debits: £150
- **Net balance**: £0 ✓

Perfect! The account is closed, and we didn't have to calculate the amount ourselves.

### Why This is Better

Compare the two approaches:

**Manual calculation** (race condition):
```php
// ❌ Stale data possible
$balance = $this->getBalance($customerId);
$ledger->execute(
    CreateTransfer::with(
        amount: $balance,  // Might be wrong!
        // ...
    ),
);
```

**Balancing transfer** (atomic):
```php
// ✓ Always correct
$ledger->execute(
    CreateTransfer::with(
        amount: 0,
        flags: TransferFlags::BALANCING_DEBIT,
        // ...
    ),
);
```

The balancing transfer is:
- **Simpler**: No balance calculation needed
- **Safer**: No race conditions
- **Atomic**: Calculation and execution happen together
- **Self-documenting**: The intent is clear from the flag

## Example 2: Paying Off a Loan

### The Scenario

A customer wants to pay off their entire loan. The exact amount owed might have changed due to interest or fees accruing. You need to transfer exactly what's owed.

### The Implementation

```php
use Castor\Ledgering\AccountFlags;

// Create accounts
$loanAccountId = Identifier::fromHex('44444444444444444444444444444444');
$customerCashId = Identifier::fromHex('55555555555555555555555555555555');

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

// Loan was disbursed: debits = £1,000
// Customer has repaid: credits = £400
// Outstanding balance = £1,000 - £400 = £600
```

Now the customer wants to pay off the loan completely:

```php
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('66666666666666666666666666666666'),
        debitAccountId: $customerCashId,
        creditAccountId: $loanAccountId,
        amount: 0,  // Ledger will calculate
        ledger: 1,
        code: 11,  // Loan settlement
        flags: TransferFlags::BALANCING_CREDIT,
    ),
);

// The ledger calculates: amount = £1,000 - £400 = £600
// After transfer: loan account has zero net balance (fully repaid)
```

**What happened?**

The ledger looked at the loan account (the **credit** account in this transfer):
- Debits posted: £1,000
- Credits posted: £400
- **Calculated amount**: £1,000 - £400 = £600

It then executed a transfer for £600, which:
- Debited the customer cash account: debits = £600
- Credited the loan account: credits = £400 + £600 = £1,000

The loan account now has:
- Debits: £1,000
- Credits: £1,000
- **Net balance**: £0 ✓

The loan is fully paid off!

> [!NOTE]
> **Why BALANCING_CREDIT here?**
>
> We used `BALANCING_CREDIT` because we want to zero out the **credit account** (the loan account).
>
> In this transfer:
> - Debit account: customer cash
> - Credit account: loan account ← This is what we want to zero out
>
> So we use `BALANCING_CREDIT`.

## Example 3: Account Sweeps

### The Scenario

You have a checking account and a savings account. You want to:
1. Keep a minimum balance of £100 in checking
2. Sweep any excess to savings

This is common in banking apps that automatically move excess funds to high-yield savings.

### The Implementation

```php
$checkingId = Identifier::fromHex('77777777777777777777777777777777');
$savingsId = Identifier::fromHex('88888888888888888888888888888888');
$tempId = Identifier::fromHex('99999999999999999999999999999999');

// Create accounts
$ledger->execute(
    CreateAccount::with(id: $checkingId, ledger: 1, code: 100),
    CreateAccount::with(id: $savingsId, ledger: 1, code: 101),
    CreateAccount::with(id: $tempId, ledger: 1, code: 999),  // Temporary account
);

// Checking account has £350
// We want to keep £100, sweep £250 to savings
```

Here's the clever part—we use a temporary account:

```php
// Step 1: Move minimum balance to temp account
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
        debitAccountId: $checkingId,
        creditAccountId: $tempId,
        amount: 10000,  // £100 minimum balance
        ledger: 1,
        code: 1,
    ),
);

// Step 2: Sweep remaining balance to savings (balancing transfer!)
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
        debitAccountId: $checkingId,
        creditAccountId: $savingsId,
        amount: 0,  // Ledger calculates: £350 - £100 = £250
        ledger: 1,
        code: 2,
        flags: TransferFlags::BALANCING_DEBIT,
    ),
);

// Step 3: Restore minimum balance to checking
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('cccccccccccccccccccccccccccccccc'),
        debitAccountId: $tempId,
        creditAccountId: $checkingId,
        amount: 10000,  // £100
        ledger: 1,
        code: 3,
    ),
);
```

**Result:**
- Checking: £100 (minimum balance maintained)
- Savings: £250 (excess swept)
- Temp: £0 (back to zero)

The balancing transfer in step 2 automatically calculated the excess amount (£250) without us having to read the balance and do math.

> [!TIP]
> **Why use a temporary account?**
>
> We can't just "sweep everything except £100" in one transfer. The ledger doesn't support that directly.
>
> But we can:
> 1. Move £100 out (to temp)
> 2. Sweep everything remaining (balancing transfer)
> 3. Move £100 back
>
> This pattern is useful for complex balance manipulations.



## Combining with Pending Transfers

Balancing transfers can be combined with the `PENDING` flag for two-phase workflows.

### The Scenario

You want to reserve the entire balance of an account, but not commit it yet. Maybe you're waiting for external confirmation.

### The Implementation

```php
use Castor\Ledgering\TransferFlags;

// Create a pending balancing transfer
$pendingId = Identifier::fromHex('dddddddddddddddddddddddddddddddd');

$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $customerId,
        creditAccountId: $settlementId,
        amount: 0,  // Will be calculated
        ledger: 1,
        code: 99,
        flags: TransferFlags::PENDING | TransferFlags::BALANCING_DEBIT,
    ),
);

// The amount is calculated and reserved in pending balances
// Customer account: debits_pending = £73.42
// The account is effectively frozen (can't spend the pending amount)
```

Later, when you're ready to commit:

```php
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'),
        debitAccountId: $customerId,  // Not actually used
        creditAccountId: $settlementId,  // Not actually used
        amount: 0,  // Not used for POST_PENDING
        ledger: 1,
        code: 99,
        pendingId: $pendingId,
        flags: TransferFlags::POST_PENDING,
    ),
);

// Pending amount moves to posted
// Customer account: debits_posted = £73.42, debits_pending = £0
```

Or if you need to cancel:

```php
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('ffffffffffffffffffffffffffffffff'),
        debitAccountId: $customerId,
        creditAccountId: $settlementId,
        amount: 0,
        ledger: 1,
        code: 99,
        pendingId: $pendingId,
        flags: TransferFlags::VOID_PENDING,
    ),
);

// Pending amount is released
// Customer account: debits_pending = £0 (back to normal)
```

> [!NOTE]
> **When to use pending balancing transfers**
>
> This pattern is useful when:
> - You need to "lock" an account's entire balance
> - You're waiting for external confirmation (payment gateway, bank transfer)
> - You want to reserve funds but might need to cancel
>
> The balancing calculation happens when you create the pending transfer, not when you post it.

## Payment Waterfalls: The Killer Feature

This is where balancing transfers really shine. In the [Loan Management guide](loan-management.md), we use them to implement **payment waterfalls**.

### The Problem

When a customer makes a loan payment, you need to allocate it in priority order:
1. Fees (highest priority)
2. Interest
3. Principal
4. Overpayment (lowest priority)

Each bucket should get as much as possible, up to what's owed, before moving to the next.

### The Naive Approach (Don't Do This)

```php
// ❌ Race conditions everywhere!
$feesOwed = $this->getFeesOwed();
$interestOwed = $this->getInterestOwed();
$principalOwed = $this->getPrincipalOwed();

$toFees = min($paymentAmount, $feesOwed);
$remaining = $paymentAmount - $toFees;

$toInterest = min($remaining, $interestOwed);
$remaining -= $toInterest;

$toPrincipal = min($remaining, $principalOwed);
$remaining -= $toPrincipal;

$toOverpayment = $remaining;

// Now create 4 transfers with these amounts...
```

This has multiple race conditions:
- Fees might change between reading and writing
- Interest might accrue
- Another payment might come in
- Balances become stale

### The Balancing Approach (Do This)

```php
// ✓ Atomic, no race conditions
$ledger->execute(
    // 1. Receive payment into control account
    CreateTransfer::with(
        id: $paymentId,
        debitAccountId: $customerCashId,
        creditAccountId: $controlAccountId,
        amount: $paymentAmount,
        ledger: 1,
        code: 40,
    ),

    // 2. Allocate to fees (priority 1)
    CreateTransfer::with(
        id: $feesTransferId,
        debitAccountId: $controlAccountId,
        creditAccountId: $feesId,
        amount: 0,  // Calculated!
        ledger: 1,
        code: 41,
        flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
    ),

    // 3. Allocate to interest (priority 2)
    CreateTransfer::with(
        id: $interestTransferId,
        debitAccountId: $controlAccountId,
        creditAccountId: $interestId,
        amount: 0,  // Calculated!
        ledger: 1,
        code: 42,
        flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
    ),

    // 4. Allocate to principal (priority 3)
    CreateTransfer::with(
        id: $principalTransferId,
        debitAccountId: $controlAccountId,
        creditAccountId: $principalId,
        amount: 0,  // Calculated!
        ledger: 1,
        code: 43,
        flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
    ),

    // 5. Allocate to overpayment (catch-all)
    CreateTransfer::with(
        id: $overpaymentTransferId,
        debitAccountId: $controlAccountId,
        creditAccountId: $overpaymentId,
        amount: 0,  // Calculated!
        ledger: 1,
        code: 44,
        flags: TransferFlags::BALANCING_DEBIT,
    ),
);
```

**What happens:**

1. Payment goes into control account: control = £200
2. Fees transfer: takes min(control balance, fees owed) → £25
3. Interest transfer: takes min(remaining control, interest owed) → £33.70
4. Principal transfer: takes min(remaining control, principal owed) → £141.30
5. Overpayment transfer: takes whatever's left → £0

All atomic. All calculated by the ledger. No race conditions.

> [!TIP]
> **Using both BALANCING_DEBIT and BALANCING_CREDIT**
>
> When you combine both flags:
>
> ```php
> flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT
> ```
>
> The ledger calculates:
>
> ```
> amount = min(
>     debit_account.credits - debit_account.debits,   // Available to spend
>     credit_account.debits - credit_account.credits  // Amount owed
> )
> ```
>
> This is perfect for waterfalls: "Transfer as much as possible, but don't exceed either account's balance."


## Important Considerations

### 1. Amount Parameter is Ignored

When using balancing flags, the `amount` parameter is completely ignored:

```php
CreateTransfer::with(
    amount: 999999,  // This is ignored!
    flags: TransferFlags::BALANCING_DEBIT,
    // ...
)
```

Convention is to set it to `0`, but you could set it to anything. The ledger will calculate the actual amount.

### 2. Calculation Happens at Execution Time

The amount is calculated when the transfer is executed, not when you create the command:

```php
$transfer = CreateTransfer::with(
    amount: 0,
    flags: TransferFlags::BALANCING_DEBIT,
    // ...
);

// Amount is NOT calculated here

$ledger->execute($transfer);  // Amount is calculated HERE
```

This is crucial for atomicity—the calculation uses the current balance at the moment of execution.

### 3. Zero Balance Result

Balancing transfers bring the target account to **zero net balance**:

- `BALANCING_DEBIT`: Zeros out the debit account
- `BALANCING_CREDIT`: Zeros out the credit account

If you want to leave a specific balance, you'll need a different approach (like the account sweep example with a temporary account).

### 4. Cannot Combine with POST/VOID

You cannot use balancing flags with `POST_PENDING` or `VOID_PENDING`:

```php
// ❌ This will fail
CreateTransfer::with(
    pendingId: $pendingId,
    flags: TransferFlags::POST_PENDING | TransferFlags::BALANCING_DEBIT,
    // ...
)
```

Why? Because `POST_PENDING` uses the amount from the original pending transfer. There's nothing to balance.

However, you CAN use balancing flags when creating the pending transfer:

```php
// ✓ This works
CreateTransfer::with(
    flags: TransferFlags::PENDING | TransferFlags::BALANCING_DEBIT,
    // ...
)
```

### 5. Positive Amounts Only

If the calculated amount would be negative or zero, the behavior depends on the implementation:

```php
// Account has: credits = £50, debits = £100
// Net balance = -£50 (negative)

// Trying to balance this:
CreateTransfer::with(
    debitAccountId: $accountId,  // Has negative balance
    creditAccountId: $otherId,
    amount: 0,
    flags: TransferFlags::BALANCING_DEBIT,
    // ...
)

// Calculated amount = £50 - £100 = -£50
// This might fail or create a zero-amount transfer
```

Make sure the account you're balancing has a positive net balance in the expected direction.

### 6. Both Accounts Must Exist

This seems obvious, but both accounts must exist before executing a balancing transfer. The ledger needs to read their balances.

## Common Use Cases Summary

| Use Case | Flag(s) | Why |
|----------|---------|-----|
| **Account closure** | `BALANCING_DEBIT` | Drain the account being closed |
| **Loan payoff** | `BALANCING_CREDIT` | Pay exactly what's owed |
| **Debt settlement** | `BALANCING_CREDIT` | Clear the debt account |
| **Account sweeps** | `BALANCING_DEBIT` | Move excess funds |
| **Payment waterfalls** | Both | Allocate payment across multiple buckets |
| **Escrow release** | `BALANCING_DEBIT` | Release all escrowed funds |
| **Refunds** | `BALANCING_CREDIT` | Refund exactly what was charged |

## Key Takeaways

1. **Balancing transfers eliminate race conditions** by calculating amounts atomically at execution time.

2. **`BALANCING_DEBIT` zeros out the debit account**, `BALANCING_CREDIT` zeros out the credit account.

3. **Set `amount: 0`** when using balancing flags—it's ignored anyway.

4. **Combine both flags** for waterfalls: transfers as much as possible without exceeding either account's balance.

5. **Can combine with `PENDING`** for two-phase workflows, but not with `POST_PENDING` or `VOID_PENDING`.

6. **Calculation happens at execution time**, using current balances, ensuring atomicity.

7. **Simpler and safer** than reading balances and calculating in application code.

## When NOT to Use Balancing Transfers

Balancing transfers aren't always the right choice:

**Don't use them when:**
- You know the exact amount upfront (e.g., fixed payment amount)
- The amount is predetermined by business logic (e.g., amortization schedule)
- You need to leave a specific balance (not zero)
- You're transferring between accounts with unrelated balances

**Use regular transfers instead:**
```php
CreateTransfer::with(
    amount: 5000,  // Fixed amount: £50.00
    // No balancing flags
    // ...
)
```

Balancing transfers are powerful, but they're a tool for specific scenarios. Use them when you need to:
- Transfer an entire balance
- Allocate funds across multiple accounts
- Avoid race conditions from reading balances

## What's Next?

Now that you understand automatic balance calculations, you might want to learn about:

- **[Two-Phase Payments](two-phase-payments.md)**: Implement pre-authorizations, escrow, and reservations with pending transfers
- **[Preventing Overdrafts and Overpayments](preventing-overdrafts-and-overpayments.md)**: Use account flags to enforce balance constraints
- **[Loan Management](loan-management.md)**: See balancing transfers in action in a complete loan system with payment waterfalls

## See Also

- [Domain Model](../domain-model.md#transfer-flags) - Complete reference for transfer flags
- [Working with the Library](../working-with-the-library.md) - Integration patterns and best practices




