# Domain Model

The Castor Ledgering library is built around three core entities: **Accounts**, **Transfers**, and **Account Balances**. All entities are immutable and use value objects for type safety.

## Account

An **Account** represents a ledger account that holds balances and tracks financial activity.

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

### Key Concepts

- **id**: Unique identifier for the account. Must be unique across all accounts.
- **ledger**: Identifies the currency or asset type. Transfers can only occur between accounts with the same ledger.
- **code**: Application-defined account type (e.g., 100 for checking, 200 for savings, 300 for revenue).
- **flags**: Control account behavior (see Account Flags below).
- **externalId**: Links to your application's entities (e.g., user ID, customer ID).
- **balance**: Tracks debits and credits, both posted and pending.

### Account Flags

Flags control account behavior and constraints:

- **NONE** (0): No special behavior
- **DEBITS_MUST_NOT_EXCEED_CREDITS** (1): Prevents overdrafts - `debits_posted + debits_pending ≤ credits_posted`
- **CREDITS_MUST_NOT_EXCEED_DEBITS** (2): Prevents overpayment - `credits_posted + credits_pending ≤ debits_posted`
- **HISTORY** (4): Enables balance history tracking (creates AccountBalance snapshots)
- **CLOSED** (8): Marks account as closed (no new transfers allowed)

**Note**: `DEBITS_MUST_NOT_EXCEED_CREDITS` and `CREDITS_MUST_NOT_EXCEED_DEBITS` are mutually exclusive.

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

A **Transfer** represents a movement of value between two accounts using double-entry bookkeeping.

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

### Key Concepts

- **Debit and Credit**: In double-entry bookkeeping, every transfer debits one account and credits another
- **amount**: Non-negative integer representing the smallest currency unit (e.g., cents)
- **ledger**: Must match both accounts' ledger values
- **code**: Application-defined transfer type (e.g., 1 for payment, 2 for refund)
- **flags**: Control transfer behavior (see Transfer Flags below)
- **pendingId**: References a pending transfer when posting or voiding

### Transfer Flags

Flags control transfer behavior:

- **NONE** (0): Normal posted transfer
- **PENDING** (1): Creates a pending transfer (reserves funds)
- **POST_PENDING** (2): Posts a pending transfer (commits reserved funds)
- **VOID_PENDING** (4): Voids a pending transfer (releases reserved funds)
- **BALANCING_DEBIT** (8): Auto-calculates amount to balance debit account
- **BALANCING_CREDIT** (16): Auto-calculates amount to balance credit account
- **CLOSING_DEBIT** (32): Transfers entire debit account balance (requires PENDING)
- **CLOSING_CREDIT** (64): Transfers entire credit account balance (requires PENDING)

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

A **Balance** tracks the financial state of an account with separate tracking for posted and pending amounts.

### Properties

```php
final readonly class Balance
{
    public Amount $debitsPosted;    // Committed debits
    public Amount $creditsPosted;   // Committed credits
    public Amount $debitsPending;   // Reserved debits
    public Amount $creditsPending;  // Reserved credits
}
```

### Understanding Balances

- **Posted**: Finalized, committed amounts
- **Pending**: Reserved amounts from pending transfers
- **Net Balance**: `creditsPosted - debitsPosted`
- **Available Balance**: `creditsPosted - debitsPosted - debitsPending`

## AccountBalance

An **AccountBalance** is a historical snapshot of an account's balance at a specific point in time.

### Properties

```php
final readonly class AccountBalance
{
    public Identifier $accountId;  // Account this balance belongs to
    public Balance $balance;       // Balance snapshot
    public Instant $timestamp;     // When this snapshot was taken
}
```

### Key Concepts

- Only created for accounts with the `HISTORY` flag enabled
- Snapshots are created after each transfer affecting the account
- Allows querying historical balances and generating statements
- Append-only (never updated or deleted)

### Example

```php
// Query balance history for an account
$balances = $accountBalances
    ->ofAccountId($accountId)
    ->toList();

foreach ($balances as $balance) {
    echo "At {$balance->timestamp}: {$balance->balance->creditsPosted->value}\n";
}
```

## Value Objects

All domain concepts use immutable value objects:

- **Identifier**: 128-bit unique identifier (UUID-like)
- **Amount**: Non-negative integer amount
- **Code**: Integer code for categorization
- **Instant**: Nanosecond-precision timestamp
- **Duration**: Time duration in seconds

See the [Working with the Library](working-with-the-library.md) guide for more details on using these types.

