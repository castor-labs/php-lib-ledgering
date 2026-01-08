# Working with the Library

**Learn how to integrate Castor Ledgering into your application.**

Castor Ledgering is designed as a **companion library**—it works alongside your existing application code. You keep your domain models (Users, Orders, Invoices), and the ledger handles the money.

The key insight: **The ledger has its own identifiers, separate from yours.**

Your application has a `User` with ID `abc-123`. The ledger has an `Account` with ID `11111111...`. You link them together using **external identifiers**.

This separation gives you:
- **Flexibility**: Change your application's IDs without touching the ledger
- **Clarity**: Financial data is separate from business data
- **Safety**: The ledger's immutable identifiers prevent accidental changes

## Core Principles

Everything in Castor Ledgering follows these principles:

1. **Separate Identity**: The ledger maintains its own 128-bit identifiers, independent of your application.

2. **External References**: Use `externalId` fields to link ledger entities to your application entities.

3. **Immutability**: All domain objects are immutable and readonly. Once created, they never change.

4. **Type Safety**: Value objects prevent invalid states at compile time. You can't accidentally create a negative amount.

5. **Command Pattern**: You don't call methods like `account.debit()`. Instead, you create commands like `CreateTransfer` and execute them.

## Setting Up the Ledger

Before you can create accounts and transfers, you need to set up the ledger. There are two options:

### Option 1: In-Memory Storage (Development/Testing)

Perfect for unit tests and local development:

```php
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\InMemory\AccountCollection;
use Castor\Ledgering\Storage\InMemory\TransferCollection;
use Castor\Ledgering\Storage\InMemory\AccountBalanceCollection;

$ledger = new StandardLedger(
    accounts: new AccountCollection(),
    transfers: new TransferCollection(),
    accountBalances: new AccountBalanceCollection(),
);
```

This stores everything in memory. Fast, but data is lost when the process ends.

### Option 2: Database Storage (Production)

For production, use a real database (PostgreSQL recommended):

```php
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\Dbal\AccountRepository;
use Castor\Ledgering\Storage\Dbal\TransferRepository;
use Castor\Ledgering\Storage\Dbal\AccountBalanceRepository;
use Castor\Ledgering\Storage\Dbal\TransactionalLedger;
use Doctrine\DBAL\DriverManager;

// Connect to your database
$connection = DriverManager::getConnection([
    'url' => 'postgresql://user:pass@localhost/ledger',
]);

// Wrap the ledger in a transaction manager
$ledger = new TransactionalLedger(
    connection: $connection,
    ledger: new StandardLedger(
        accounts: new AccountRepository($connection),
        transfers: new TransferRepository($connection),
        accountBalances: new AccountBalanceRepository($connection),
    ),
);
```

The `TransactionalLedger` wrapper ensures that all operations are atomic—either everything succeeds, or nothing changes.

> [!TIP]
> **Use transactions in production**
>
> Always use `TransactionalLedger` in production. It ensures that if you execute multiple commands together, they either all succeed or all fail. No partial updates.

## Linking the Ledger to Your Application

This is where the magic happens. You use **external identifiers** to connect ledger entities to your application's domain model.

### Example: Linking Accounts to Users

Let's say you have a `User` in your application. You want to create a ledger account for them.

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\AccountFlags;

// Your application's user
$user = $userRepository->find('abc-123');
$userId = $user->getId();  // 'abc-123'

// Create a ledger account for this user
$accountId = Identifier::fromHex('11111111111111111111111111111111');

$ledger->execute(
    CreateAccount::with(
        id: $accountId,
        ledger: 1,  // USD
        code: 100,  // Checking account
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
        externalIdPrimary: Identifier::hashOf($userId),  // Link to user!
    ),
);
```

Now the ledger account is linked to your user. Later, you can find the account by user ID:

```php
// Find the ledger account for a user
$account = $accounts
    ->ofExternalIdPrimary(Identifier::hashOf($userId))
    ->first();

if ($account === null) {
    // User doesn't have a ledger account yet
}
```

> [!NOTE]
> **Working with different ID formats**
>
> The ledger uses 128-bit identifiers (16 bytes). Here's how to convert your application's IDs:
>
> **For string IDs** (user IDs, order IDs, etc.):
> ```php
> $externalId = Identifier::hashOf($userId);  // Uses MD5 hashing
> ```
>
> **For UUIDs** (already 128-bit):
> ```php
> // If your UUID is in hex format (with or without hyphens)
> $externalId = Identifier::fromHex($uuid);
>
> // If your UUID is in binary format
> $externalId = Identifier::fromBytes($uuidBytes);
> ```
>
> **For ULIDs or other 128-bit identifiers**:
> ```php
> // Convert to bytes first, then use fromBytes()
> $externalId = Identifier::fromBytes($ulid->toBytes());
> ```
>
> The `hashOf()` method is deterministic—the same input always produces the same identifier.

### Example: Linking Transfers to Orders

Same idea for transfers. Link them to your application's orders, invoices, or transactions:

```php
use Castor\Ledgering\CreateTransfer;

// Your application's order
$order = $orderRepository->find('order-12345');
$orderId = $order->getId();

$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('33333333333333333333333333333333'),
        debitAccountId: $customerAccountId,
        creditAccountId: $merchantAccountId,
        amount: 5000,  // $50.00
        ledger: 1,
        code: 1,  // Payment
        externalIdPrimary: Identifier::hashOf($orderId),  // Link to order!
    ),
);
```

Later, you can find the transfer by order ID:

```php
// Find the transfer for an order
$transfer = $transfers
    ->ofExternalIdPrimary(Identifier::hashOf($orderId))
    ->first();
```

### Using Multiple External References

Each entity has **three external reference fields**:

- **externalIdPrimary**: Primary reference (e.g., user ID, order ID)
- **externalIdSecondary**: Secondary reference (e.g., transaction ID, invoice ID)
- **externalCodePrimary**: Numeric code for categorization

You can use all three to link to different parts of your application:

```php
$ledger->execute(
    CreateTransfer::with(
        id: $transferId,
        debitAccountId: $debitAccountId,
        creditAccountId: $creditAccountId,
        amount: 1000,
        ledger: 1,
        code: 1,
        externalIdPrimary: Identifier::hashOf($orderId),      // Order
        externalIdSecondary: Identifier::hashOf($invoiceId),  // Invoice
        externalCodePrimary: 42,  // Your app's category code
    ),
);
```

This gives you maximum flexibility for querying and reporting.

## Querying Data

The library uses the **Reader pattern** for querying. Think of it like a fluent query builder—you chain methods to filter and retrieve data.

### Querying Accounts

```php
// Find a single account by ID
$account = $accounts->ofId($accountId)->first();  // Returns Account|null
$account = $accounts->ofId($accountId)->one();    // Returns Account or throws

// Find by external ID (your application's ID)
$account = $accounts->ofExternalIdPrimary($userId)->first();
$account = $accounts->ofExternalIdSecondary($customerId)->first();

// Get multiple accounts at once (OR query)
$accountList = $accounts->ofId($id1, $id2, $id3)->toList();

// Filter by ledger
$usdAccounts = $accounts->ofLedger(1)->toList();  // All USD accounts

// Filter by code (account type)
$checkingAccounts = $accounts->ofCode(100)->toList();

// Combine filters (AND query)
$customerUsdAccounts = $accounts
    ->ofLedger(1)      // USD
    ->ofCode(100)      // Checking accounts
    ->toList();
```

> [!TIP]
> **All `of*()` methods accept multiple arguments**
>
> You can pass multiple values to any `of*()` method for OR queries:
>
> ```php
> // Find accounts with any of these IDs
> $accounts->ofId($id1, $id2, $id3)->toList();
>
> // Find accounts with any of these codes
> $accounts->ofCode(100, 200, 300)->toList();  // Checking, savings, or loans
>
> // Find accounts on any of these ledgers
> $accounts->ofLedger(1, 2)->toList();  // USD or EUR
> ```
>
> When you chain methods, they work as AND conditions:
> ```php
> // USD accounts that are EITHER checking OR savings
> $accounts->ofLedger(1)->ofCode(100, 200)->toList();
> ```

**When to use `first()` vs `one()`:**
- Use `first()` when the account might not exist (returns `null`)
- Use `one()` when you expect it to exist (throws exception if not found)

### Querying Transfers

```php
// Find a single transfer by ID
$transfer = $transfers->ofId($transferId)->first();

// Find by external ID (your application's order ID, etc.)
$transfer = $transfers->ofExternalIdPrimary($orderId)->first();

// Find all transfers for an account
$debits = $transfers->ofDebitAccount($accountId)->toList();
$credits = $transfers->ofCreditAccount($accountId)->toList();

// Pagination (for large result sets)
$page = $transfers
    ->ofDebitAccount($accountId)
    ->slice(offset: 0, limit: 20)  // First 20 transfers
    ->toList();

// Count without loading all the data
$count = $transfers->ofDebitAccount($accountId)->count();
```

> [!TIP]
> **Use pagination for large result sets**
>
> If an account has thousands of transfers, don't load them all at once. Use `slice()` to paginate:
>
> ```php
> $pageSize = 50;
> $pageNumber = 2;
>
> $transfers = $transfers
>     ->ofDebitAccount($accountId)
>     ->slice(offset: $pageNumber * $pageSize, limit: $pageSize)
>     ->toList();
> ```

### Querying Balance History

```php
// Get all balance snapshots for an account (requires HISTORY flag)
$balances = $accountBalances
    ->ofAccountId($accountId)
    ->toList();

// Get the most recent balance snapshot
$latest = $accountBalances
    ->ofAccountId($accountId)
    ->slice(offset: 0, limit: 1)
    ->first();
```

Remember: Balance history is only available for accounts with the `HISTORY` flag enabled.

## Working with Value Objects

All the basic types in Castor Ledgering are **value objects**—immutable, type-safe wrappers around primitive values.

Here's how to use them:

### Identifier

```php
use Castor\Ledgering\Identifier;

// From hexadecimal string (32 hex characters = 128 bits)
$id = Identifier::fromHex('0123456789abcdef0123456789abcdef');

// From raw bytes (16 bytes = 128 bits)
$id = Identifier::fromBytes($binaryData);

// Zero identifier (useful for optional fields)
$id = Identifier::zero();

// Check equality
if ($id1->equals($id2)) {
    // Same identifier
}

// Access raw bytes
$bytes = $id->bytes;  // string (16 bytes)
```

### Amount

Always use the smallest currency unit (cents for USD, pence for GBP, etc.):

```php
use Castor\Ledgering\Amount;

// Create from integer (cents)
$amount = Amount::of(1000);  // $10.00
$amount = Amount::of(50);    // $0.50

// Zero amount
$amount = Amount::zero();

// Arithmetic
$sum = $amount1->add($amount2);
$diff = $amount1->subtract($amount2);  // Throws if result would be negative

// Comparison
$cmp = $amount1->compare($amount2);  // -1, 0, or 1
if ($amount->isZero()) {
    // Amount is zero
}

// Access the raw value
$cents = $amount->value;  // int
```

> [!WARNING]
> **Always use integers, never floats**
>
> Never use floating-point numbers for money. They have rounding errors that can cause your books to be off by pennies.
>
> ```php
> // ❌ Don't do this
> $amount = 10.50;  // Floating point - BAD!
>
> // ✓ Do this
> $amount = Amount::of(1050);  // 1050 cents = $10.50 - GOOD!
> ```

### Code

```php
use Castor\Ledgering\Code;

// Create from integer
$ledger = Code::of(1);    // USD
$code = Code::of(100);    // Account type

// Access value
$value = $code->value;  // int
```

Codes are just integers, but wrapping them in a `Code` object makes your code more type-safe.

## Error Handling

When something goes wrong, the ledger throws exceptions. Here's how to handle them:

```php
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\ErrorCode;

try {
    $ledger->execute($command);
} catch (ConstraintViolation $e) {
    // Check what went wrong using the errorCode property
    match ($e->errorCode) {
        ErrorCode::AccountAlreadyExists => {
            // You tried to create an account with an ID that already exists
            // This is usually fine - accounts are idempotent
        },
        ErrorCode::AccountNotFound => {
            // You referenced an account that doesn't exist
            // Create it first!
        },
        ErrorCode::DebitsExceedCredits => {
            // Overdraft! The account doesn't have enough funds
            // This happens when DEBITS_MUST_NOT_EXCEED_CREDITS is set
        },
        ErrorCode::LedgerMismatch => {
            // You tried to transfer between accounts with different ledger codes
            // Can't transfer USD to EUR directly!
        },
        default => throw $e,  // Unknown error, re-throw
    };
}
```

### Common Error Codes

- **AccountAlreadyExists**: Account with this ID already exists
- **AccountNotFound**: Referenced account doesn't exist
- **DebitsExceedCredits**: Overdraft prevented by `DEBITS_MUST_NOT_EXCEED_CREDITS` flag
- **CreditsExceedDebits**: Overpayment prevented by `CREDITS_MUST_NOT_EXCEED_DEBITS` flag
- **LedgerMismatch**: Accounts have different ledger codes
- **TransferAlreadyExists**: Transfer with this ID already exists
- **PendingTransferNotFound**: Referenced pending transfer doesn't exist
- **PendingTransferExpired**: Pending transfer has timed out
- **AccountClosed**: Account is closed (has `CLOSED` flag)

> [!NOTE]
> **Handling duplicate operations**
>
> The ledger throws `AccountAlreadyExists` and `TransferAlreadyExists` errors when you try to create duplicates. This is intentional—it lets you decide how to handle them.
>
> For idempotent behavior (safe retries), catch these errors:
>
> ```php
> try {
>     $ledger->execute($createAccount);
> } catch (ConstraintViolation $e) {
>     if ($e->errorCode === ErrorCode::AccountAlreadyExists) {
>         // Already exists, that's fine
>         return;
>     }
>     throw $e;
> }
> ```
>
> This pattern makes retries safe without worrying about duplicates.

## Best Practices

Here are some tips for using Castor Ledgering effectively:

### 1. Generate Identifiers Deterministically

Use consistent hashing of your application IDs:

```php
// ✓ Good: Deterministic
$accountId = Identifier::hashOf($userId);

// ❌ Bad: Random (can't find it later)
$accountId = Identifier::fromHex(bin2hex(random_bytes(16)));
```

### 2. Use External IDs for Lookups

Always query by external ID, not ledger ID:

```php
// ✓ Good: Query by your application's ID
$account = $accounts->ofExternalIdPrimary($userId)->first();

// ❌ Bad: Query by ledger ID (you'd have to store it somewhere)
$account = $accounts->ofId($accountId)->first();
```

### 3. Leverage Account Flags

Let the ledger enforce business rules:

```php
// ✓ Good: Ledger prevents overdrafts automatically
CreateAccount::with(
    flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    // ...
)

// ❌ Bad: Checking balance in application code (race conditions!)
if ($this->getBalance($accountId) >= $amount) {
    $ledger->execute($transfer);  // Might still fail!
}
```

### 4. Enable History Selectively

Only enable the `HISTORY` flag on accounts that need it:

```php
// ✓ Good: Only customer accounts need history
CreateAccount::with(
    code: 100,  // Customer account
    flags: AccountFlags::HISTORY,
    // ...
)

// ❌ Bad: Enabling history on everything (wastes storage)
CreateAccount::with(
    code: 999,  // Temporary control account
    flags: AccountFlags::HISTORY,  // Unnecessary!
    // ...
)
```

### 5. Use Transactions

Wrap multiple commands in a transaction for atomicity:

```php
// ✓ Good: All or nothing
$ledger->execute(
    $createAccount1,
    $createAccount2,
    $createTransfer,
);

// ❌ Bad: Separate executions (partial failures possible)
$ledger->execute($createAccount1);
$ledger->execute($createAccount2);
$ledger->execute($createTransfer);  // Might fail, leaving orphaned accounts
```

### 6. Handle Duplicate Operations

The ledger throws errors for duplicates. Handle them for idempotent retries:

```php
// ✓ Good: Handle duplicates explicitly
try {
    $ledger->execute(
        CreateAccount::with(id: $accountId, /* ... */),
    );
} catch (ConstraintViolation $e) {
    if ($e->errorCode === ErrorCode::AccountAlreadyExists) {
        // Already exists, that's fine
        return;
    }
    throw $e;
}

// ❌ Bad: Letting duplicates propagate as errors
$ledger->execute(
    CreateAccount::with(id: $accountId, /* ... */),
);  // Throws on retry!
```

This pattern makes retries safe without creating duplicates.

### 7. Never Use Floats for Money

Always use integers (smallest currency unit):

```php
// ✓ Good
$amount = Amount::of(1050);  // $10.50

// ❌ Bad
$amount = 10.50;  // Floating point - rounding errors!
```

## What's Next?

Now that you know how to integrate the library, learn the powerful features:

- **[Preventing Overdrafts and Overpayments](guides/preventing-overdrafts-and-overpayments.md)** - Use account flags to enforce constraints
- **[Automatic Balance Calculations](guides/automatic-balance-calculations.md)** - Let the ledger calculate amounts for you
- **[Two-Phase Payments](guides/two-phase-payments.md)** - Implement pre-authorizations and escrow
- **[Loan Management System](guides/loan-management.md)** - See everything working together in a complete system

Or dive deeper into the reference:

- **[Domain Model](domain-model.md)** - Complete reference for all entities and value objects

