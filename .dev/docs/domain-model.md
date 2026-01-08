# Domain Model

**A complete reference for the core entities in Castor Ledgering.**

Think of this as your field guide to the ledger. Everything in Castor Ledgering revolves around three core concepts: **Accounts** (where money lives), **Transfers** (how money moves), and **Balances** (how much money there is).

All entities are immutable—once created, they never change. This makes your financial data predictable and safe.

## Account

An **Account** is like a bucket that holds money. It could represent:
- A customer's cash balance
- A loan that's owed
- Revenue you've earned
- Inventory you're tracking

Every account tracks both **posted** (finalized) and **pending** (reserved) amounts.

### Properties

```php
final readonly class Account
{
    public Identifier $id;                    // Unique 128-bit identifier
    public Code $ledger;                      // Ledger/currency code
    public Code $code;                        // Account type code
    public AccountFlags $flags;               // Behavior flags
    public Identifier $externalIdPrimary;     // Primary external reference
    public Identifier $externalIdSecondary;   // Secondary external reference
    public Code $externalCodePrimary;         // External code reference
    public Balance $balance;                  // Current balance
    public Instant $timestamp;                // Creation/update timestamp
}
```

### Understanding the Properties

Let's break down what each property means:

- **id**: A unique 128-bit identifier for this account. Think of it like a UUID—no two accounts can have the same ID.

- **ledger**: Which currency or asset type this account holds. All transfers must happen between accounts with the same ledger code. For example:
  - `1` = USD
  - `2` = EUR
  - `3` = Bitcoin
  - `100` = Loyalty points

- **code**: Your application's account type. You define what these mean. Common examples:
  - `100` = Customer checking account
  - `200` = Merchant account
  - `300` = Loan account
  - `400` = Revenue account

- **flags**: Special behaviors for this account (see Account Flags below). These enforce business rules automatically.

- **externalId**: Links this ledger account to your application's entities. For example, if you have a `User` with ID `abc-123`, you can store that here to find the account later.

- **balance**: The current state of the account—how much has been debited, credited, and what's pending.

### Account Flags

Flags give accounts special powers. They enforce business rules automatically:

- **NONE** (0): A regular account with no special behavior.

- **DEBITS_MUST_NOT_EXCEED_CREDITS** (1): **Prevents overdrafts.**
  The account can't spend more than it has. Perfect for customer cash accounts, prepaid cards, and gift cards.
  Enforces: `debits_posted + debits_pending ≤ credits_posted`

- **CREDITS_MUST_NOT_EXCEED_DEBITS** (2): **Prevents overpayment.**
  The account can't receive more credits than it has debits. Perfect for loan accounts and debt tracking.
  Enforces: `credits_posted + credits_pending ≤ debits_posted`

- **HISTORY** (4): **Tracks balance history.**
  Creates a snapshot of the balance after every transfer. Use this when you need to generate statements or audit trails.

- **CLOSED** (8): **Marks the account as closed.**
  No new transfers are allowed. The account is frozen.

> [!NOTE]
> **Mutually exclusive flags**
>
> You can't use both `DEBITS_MUST_NOT_EXCEED_CREDITS` and `CREDITS_MUST_NOT_EXCEED_DEBITS` on the same account. Pick one based on what you're modeling.

**Learn more:** See [Preventing Overdrafts and Overpayments](guides/preventing-overdrafts-and-overpayments.md) for practical examples.

### Example

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\AccountFlags;

// Create a customer cash account with overdraft protection
$ledger->execute(
    CreateAccount::with(
        id: Identifier::fromHex('11111111111111111111111111111111'),
        ledger: 1,  // USD
        code: 100,  // Checking account
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS | AccountFlags::HISTORY,
        externalIdPrimary: Identifier::fromHex('customer-uuid-here...'),
    ),
);
```

## Transfer

A **Transfer** is how money moves between accounts. Every transfer follows the golden rule of double-entry bookkeeping:

**For every debit, there must be an equal and opposite credit.**

This means:
- Money never appears from nowhere
- Money never disappears into nowhere
- The books always balance

A transfer always involves exactly two accounts:
- **Debit account**: Where the money comes from (or what increases a debt)
- **Credit account**: Where the money goes to (or what decreases a debt)

### Properties

```php
final readonly class Transfer
{
    public Identifier $id;                    // Unique 128-bit identifier
    public Identifier $debitAccountId;        // Account to debit
    public Identifier $creditAccountId;       // Account to credit
    public Amount $amount;                    // Transfer amount
    public Identifier $pendingId;             // Reference to pending transfer
    public Code $ledger;                      // Ledger/currency code
    public Code $code;                        // Transfer type code
    public TransferFlags $flags;              // Behavior flags
    public Duration $timeout;                 // Pending transfer timeout
    public Identifier $externalIdPrimary;     // Primary external reference
    public Identifier $externalIdSecondary;   // Secondary external reference
    public Code $externalCodePrimary;         // External code reference
    public Instant $timestamp;                // Creation timestamp
}
```

### Understanding the Properties

- **id**: Unique identifier for this transfer. Like accounts, no two transfers can have the same ID.

- **debitAccountId** and **creditAccountId**: The two accounts involved in the transfer.

- **amount**: How much to transfer, in the smallest currency unit (e.g., cents for USD). Always a non-negative integer.
  Examples:
  - `1000` = $10.00
  - `50` = $0.50
  - `100000` = $1,000.00

- **ledger**: Must match both accounts' ledger codes. You can't transfer USD to a EUR account.

- **code**: Your application's transfer type. You define what these mean. Common examples:
  - `1` = Payment
  - `2` = Refund
  - `10` = Loan disbursement
  - `11` = Loan repayment

- **flags**: Special behaviors for this transfer (see Transfer Flags below). These enable powerful features like pending transfers and automatic balance calculations.

- **pendingId**: When posting or voiding a pending transfer, this references the original pending transfer's ID.

- **timeout**: For pending transfers, how long before they expire. Use `Duration::ofDays(7)` for a 7-day timeout.

### Transfer Flags

Flags unlock powerful features. Here's what each one does:

#### Two-Phase Transfers (Pending)

- **PENDING** (1): **Creates a pending transfer.**
  Reserves funds without committing them. Perfect for pre-authorizations, escrow, and reservations.

- **POST_PENDING** (2): **Commits a pending transfer.**
  Moves funds from pending to posted. The transfer is now final.

- **VOID_PENDING** (4): **Cancels a pending transfer.**
  Releases the reserved funds. It's like the pending transfer never happened.

**Learn more:** See [Two-Phase Payments](guides/two-phase-payments.md) for practical examples.

#### Automatic Balance Calculations

- **BALANCING_DEBIT** (8): **Auto-calculates amount to zero out the debit account.**
  The ledger calculates how much to transfer to bring the debit account's balance to zero.

- **BALANCING_CREDIT** (16): **Auto-calculates amount to zero out the credit account.**
  The ledger calculates how much to transfer to bring the credit account's balance to zero.

**Learn more:** See [Automatic Balance Calculations](guides/automatic-balance-calculations.md) for practical examples.

#### Advanced Flags

- **CLOSING_DEBIT** (32): **Transfers entire debit account balance (requires PENDING).**
  Similar to `BALANCING_DEBIT` but specifically for closing accounts.

- **CLOSING_CREDIT** (64): **Transfers entire credit account balance (requires PENDING).**
  Similar to `BALANCING_CREDIT` but specifically for closing accounts.

> [!TIP]
> **Combining flags**
>
> You can combine flags using the bitwise OR operator (`|`):
>
> ```php
> flags: TransferFlags::PENDING | TransferFlags::BALANCING_DEBIT
> ```
>
> This creates a pending transfer that automatically calculates the amount.

### Example

```php
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;

// Simple transfer
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('33333333333333333333333333333333'),
        debitAccountId: $aliceId,
        creditAccountId: $bobId,
        amount: 1000,  // $10.00 in cents
        ledger: 1,     // USD
        code: 1,       // Payment
    ),
);
```

## Balance

A **Balance** is a snapshot of an account's financial state. It tracks four numbers:

### Properties

```php
final readonly class Balance
{
    public Amount $debitsPosted;    // Finalized debits
    public Amount $creditsPosted;   // Finalized credits
    public Amount $debitsPending;   // Reserved debits (not yet committed)
    public Amount $creditsPending;  // Reserved credits (not yet committed)
}
```

### Understanding the Four Numbers

Think of it like this:

- **Posted amounts** are final. The money has actually moved.
- **Pending amounts** are reserved. The money is "on hold" but hasn't moved yet.

Here's what each means:

- **debitsPosted**: Total amount debited from this account (finalized)
- **creditsPosted**: Total amount credited to this account (finalized)
- **debitsPending**: Total amount reserved for pending debits
- **creditsPending**: Total amount reserved for pending credits

### Calculating Balances

From these four numbers, you can calculate:

**Net Balance** (what the account actually has):
```
creditsPosted - debitsPosted
```

**Available Balance** (what can be spent):
```
creditsPosted - debitsPosted - debitsPending
```

> [!NOTE]
> **Why subtract debitsPending?**
>
> Pending debits reduce available balance because those funds are reserved. Even though they haven't been finalized, you can't spend them elsewhere.

### Example

Let's say a customer account has:
- `creditsPosted`: $100 (they deposited $100)
- `debitsPosted`: $30 (they spent $30)
- `debitsPending`: $20 (they have a pending $20 charge)
- `creditsPending`: $0

Then:
- **Net balance**: $100 - $30 = $70
- **Available balance**: $100 - $30 - $20 = $50

The customer has $70 in their account, but only $50 is available to spend (because $20 is reserved).

## AccountBalance

An **AccountBalance** is a historical snapshot—a photograph of an account's balance at a specific moment in time.

### Properties

```php
final readonly class AccountBalance
{
    public Identifier $accountId;  // Which account this snapshot is for
    public Balance $balance;       // The balance at that moment
    public Instant $timestamp;     // When this snapshot was taken
}
```

### How It Works

When you enable the `HISTORY` flag on an account, the ledger automatically creates a snapshot after every transfer that affects that account.

This gives you:
- **Audit trails**: See exactly what the balance was at any point in time
- **Statements**: Generate monthly statements showing balance changes
- **Reconciliation**: Compare balances across different time periods
- **Debugging**: Track down when and how a balance changed

The snapshots are **append-only**—they're never updated or deleted. This makes them perfect for compliance and auditing.

### Example: Generating a Statement

```php
// Get all balance snapshots for an account
$balances = $accountBalances
    ->ofAccountId($accountId)
    ->toList();

echo "Account Statement\n";
echo "=================\n\n";

foreach ($balances as $snapshot) {
    $net = $snapshot->balance->creditsPosted->value
         - $snapshot->balance->debitsPosted->value;

    echo sprintf(
        "%s: Balance = $%.2f\n",
        $snapshot->timestamp,
        $net / 100
    );
}
```

> [!TIP]
> **Only enable HISTORY when you need it**
>
> Balance history snapshots take up storage space. Only enable the `HISTORY` flag on accounts where you actually need historical tracking.
>
> Good candidates:
> - Customer accounts (for statements)
> - Revenue accounts (for reporting)
> - Regulatory accounts (for compliance)
>
> Skip it for:
> - Temporary accounts
> - Internal control accounts
> - High-volume accounts where you don't need history

## Value Objects

Everything in Castor Ledgering uses **immutable value objects**. Once created, they never change. This makes your code predictable and safe.

Here are the building blocks:

### Identifier

A 128-bit unique identifier (like a UUID). Every account and transfer has one.

```php
use Castor\Ledgering\Identifier;

// From hexadecimal string (32 hex characters)
$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');

// From raw bytes (16 bytes)
$id = Identifier::fromBytes($binaryData);

// Zero identifier (useful for optional fields)
$id = Identifier::zero();

// Check equality
if ($id1->equals($id2)) {
    // Same identifier
}
```

### Amount

A non-negative integer representing the smallest currency unit (e.g., cents for USD).

```php
use Castor\Ledgering\Amount;

// Create from integer
$amount = Amount::of(1000);  // $10.00

// Zero amount
$amount = Amount::zero();

// Arithmetic
$sum = $amount1->add($amount2);
$diff = $amount1->subtract($amount2);  // Throws if result would be negative

// Comparison
if ($amount->isZero()) {
    // Amount is zero
}

// Access the value
$cents = $amount->value;  // int
```

### Code

An integer code for categorization (ledger codes, account types, transfer types).

```php
use Castor\Ledgering\Code;

// Create from integer
$ledger = Code::of(1);    // USD
$code = Code::of(100);    // Account type

// Access value
$value = $code->value;  // int
```

### Instant

A nanosecond-precision timestamp.

```php
use Castor\Ledgering\Time\Instant;

// Current time
$now = Instant::now();

// From Unix timestamp
$instant = Instant::fromUnixTimestamp(1234567890);
```

### Duration

A time duration in nanoseconds.

```php
use Castor\Ledgering\Time\Duration;

// Common durations
$duration = Duration::ofDays(7);
$duration = Duration::ofHours(24);
$duration = Duration::ofMinutes(30);
$duration = Duration::ofSeconds(60);

// Zero duration (no timeout)
$duration = Duration::zero();
```

## What's Next?

Now that you understand the domain model, learn how to use it:

- **[Working with the Library](working-with-the-library.md)** - Integration patterns and querying
- **[Preventing Overdrafts and Overpayments](guides/preventing-overdrafts-and-overpayments.md)** - Use account flags in practice
- **[Two-Phase Payments](guides/two-phase-payments.md)** - Use pending transfers in practice
- **[Automatic Balance Calculations](guides/automatic-balance-calculations.md)** - Use balancing transfers in practice

