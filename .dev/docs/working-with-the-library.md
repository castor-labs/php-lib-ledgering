# Working with the Library

Castor Ledgering is designed as a **companion library** to your application code. It maintains its own identifiers while providing external identifier fields to link with your application's entities.

## Philosophy

The library follows these design principles:

1. **Separate Identity**: The ledger maintains its own 128-bit identifiers independent of your application
2. **External References**: Use `externalId` fields to link ledger entities to your application entities
3. **Immutability**: All domain objects are immutable and readonly
4. **Type Safety**: Value objects prevent invalid states at compile time
5. **Command Pattern**: Operations are expressed as commands, not method calls

## Setting Up the Ledger

### In-Memory Storage (Development/Testing)

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

### Database Storage (Production)

```php
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\Dbal\AccountRepository;
use Castor\Ledgering\Storage\Dbal\TransferRepository;
use Castor\Ledgering\Storage\Dbal\AccountBalanceRepository;
use Castor\Ledgering\Storage\Dbal\TransactionalLedger;
use Doctrine\DBAL\DriverManager;

$connection = DriverManager::getConnection([
    'url' => 'postgresql://user:pass@localhost/ledger',
]);

$ledger = new TransactionalLedger(
    connection: $connection,
    ledger: new StandardLedger(
        accounts: new AccountRepository($connection),
        transfers: new TransferRepository($connection),
        accountBalances: new AccountBalanceRepository($connection),
    ),
);
```

## Using External Identifiers

External identifiers link ledger entities to your application's domain model.

### Linking Accounts to Users

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\AccountFlags;

// Your application's user
$userId = 'user-uuid-from-your-app';

// Create ledger account linked to user
$accountId = Identifier::fromHex('11111111111111111111111111111111');

$ledger->execute(
    CreateAccount::with(
        id: $accountId,
        ledger: 1,  // USD
        code: 100,  // Checking account
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
        externalIdPrimary: Identifier::fromHex(md5($userId)),  // Link to user
    ),
);

// Later, find account by user ID
$account = $accounts
    ->ofExternalIdPrimary(Identifier::fromHex(md5($userId)))
    ->first();
```

### Linking Transfers to Orders

```php
use Castor\Ledgering\CreateTransfer;

// Your application's order
$orderId = 'order-12345';

$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('33333333333333333333333333333333'),
        debitAccountId: $customerAccountId,
        creditAccountId: $merchantAccountId,
        amount: 5000,  // $50.00
        ledger: 1,
        code: 1,  // Payment
        externalIdPrimary: Identifier::fromHex(md5($orderId)),  // Link to order
    ),
);

// Later, find transfer by order ID
$transfer = $transfers
    ->ofExternalIdPrimary(Identifier::fromHex(md5($orderId)))
    ->first();
```

### Multiple External References

Each entity has two external identifier fields and one external code field:

- **externalIdPrimary**: Primary reference (e.g., user ID, order ID)
- **externalIdSecondary**: Secondary reference (e.g., transaction ID, invoice ID)
- **externalCodePrimary**: Numeric code for categorization

```php
$ledger->execute(
    CreateTransfer::with(
        id: $transferId,
        debitAccountId: $debitAccountId,
        creditAccountId: $creditAccountId,
        amount: 1000,
        ledger: 1,
        code: 1,
        externalIdPrimary: Identifier::fromHex(md5($orderId)),
        externalIdSecondary: Identifier::fromHex(md5($invoiceId)),
        externalCodePrimary: 42,  // Your application's category code
    ),
);
```

## Querying Data

The library uses the **Reader pattern** for querying. Readers return immutable filtered views that can be chained.

### Account Queries

```php
// Find account by ID
$account = $accounts->ofId($accountId)->first();  // Returns Account|null
$account = $accounts->ofId($accountId)->one();    // Returns Account or throws

// Find by external ID
$account = $accounts->ofExternalIdPrimary($userId)->first();
$account = $accounts->ofExternalIdSecondary($customerId)->first();

// Get multiple accounts
$accountList = $accounts->ofId($id1, $id2, $id3)->toList();
```

### Transfer Queries

```php
// Find transfer by ID
$transfer = $transfers->ofId($transferId)->first();

// Find by external ID
$transfer = $transfers->ofExternalIdPrimary($orderId)->first();

// Find all transfers for an account
$debits = $transfers->ofDebitAccount($accountId)->toList();
$credits = $transfers->ofCreditAccount($accountId)->toList();

// Pagination
$page = $transfers
    ->ofDebitAccount($accountId)
    ->slice(offset: 0, limit: 20)
    ->toList();

// Count without loading
$count = $transfers->ofDebitAccount($accountId)->count();
```

### Balance History Queries

```php
// Get balance history for an account (requires HISTORY flag)
$balances = $accountBalances
    ->ofAccountId($accountId)
    ->toList();

// Get latest balance
$latest = $accountBalances
    ->ofAccountId($accountId)
    ->slice(offset: 0, limit: 1)
    ->first();
```

## Working with Value Objects

### Identifier

```php
use Castor\Ledgering\Identifier;

// From hexadecimal string (32 hex chars = 128 bits)
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

```php
use Castor\Ledgering\Amount;

// Create from integer (smallest currency unit, e.g., cents)
$amount = Amount::of(1000);  // $10.00

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

// Access value
$cents = $amount->value;  // int
```

### Code

```php
use Castor\Ledgering\Code;

// Create from integer
$ledger = Code::of(1);    // USD
$code = Code::of(100);    // Account type

// Access value
$value = $code->value;  // int
```

## Error Handling

The library uses exceptions for error conditions:

```php
use Castor\Ledgering\ConstraintViolation;
use Castor\Ledgering\ErrorCode;

try {
    $ledger->execute($command);
} catch (ConstraintViolation $e) {
    match ($e->getCode()) {
        ErrorCode::AccountAlreadyExists->value => // Handle duplicate
        ErrorCode::AccountNotFound->value => // Handle not found
        ErrorCode::InsufficientFunds->value => // Handle overdraft
        ErrorCode::LedgerMismatch->value => // Handle currency mismatch
        default => throw $e,
    };
}
```

## Best Practices

1. **Generate Identifiers Deterministically**: Use consistent hashing of your application IDs
2. **Use External IDs for Lookups**: Query by external ID, not ledger ID
3. **Leverage Account Flags**: Use constraints to enforce business rules at the ledger level
4. **Enable History Selectively**: Only enable HISTORY flag on accounts that need it
5. **Use Transactions**: Wrap multiple commands in a transaction for atomicity
6. **Handle Idempotency**: Commands are idempotent - duplicate account creation is rejected, duplicate transfers are ignored

## Next Steps

- [Domain Model](domain-model.md) - Deep dive into entities
- [Cookbook](cookbook/) - Common patterns and recipes
- [Loan Management Example](examples/loan-management.md) - Complete real-world example

