# Pending Transfers

## Problem

You need to reserve funds for a future transaction without immediately committing them. Common scenarios:

- Pre-authorization for credit card payments
- Escrow for marketplace transactions
- Reservation systems (hotel bookings, ticket sales)
- Two-phase commit workflows

## Solution

Use **pending transfers** to implement two-phase workflows:

1. **Phase 1 (PENDING)**: Reserve funds by moving them to pending balances
2. **Phase 2a (POST_PENDING)**: Commit the transfer by moving from pending to posted
3. **Phase 2b (VOID_PENDING)**: Cancel the transfer by releasing the pending funds

## Example 1: Credit Card Pre-Authorization

### Problem

A customer makes a purchase. You need to verify they have sufficient funds before shipping the product.

### Solution

Create a pending transfer, then post it when the product ships.

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\TransferFlags;
use Castor\Ledgering\AccountFlags;

// Create accounts
$customerAccountId = Identifier::fromHex('11111111111111111111111111111111');
$merchantAccountId = Identifier::fromHex('22222222222222222222222222222222');

$ledger->execute(
    CreateAccount::with(
        id: $customerAccountId,
        ledger: 1,
        code: 100,
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
    CreateAccount::with(
        id: $merchantAccountId,
        ledger: 1,
        code: 200,
    ),
);

// Step 1: Create pending transfer (pre-authorization)
$pendingId = Identifier::fromHex('33333333333333333333333333333333');

$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $customerAccountId,
        creditAccountId: $merchantAccountId,
        amount: 5000,  // $50.00
        ledger: 1,
        code: 1,
        flags: TransferFlags::PENDING,
    ),
);

// Funds are now reserved in pending balances
// Customer account: debits_pending = $50, available balance reduced by $50

// Step 2a: Product shipped - post the pending transfer
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('44444444444444444444444444444444'),
        debitAccountId: $customerAccountId,  // Not actually used
        creditAccountId: $merchantAccountId,  // Not actually used
        amount: 0,  // Not used
        ledger: 1,
        code: 1,
        pendingId: $pendingId,  // Reference to pending transfer
        flags: TransferFlags::POST_PENDING,
    ),
);

// Funds moved from pending to posted
// Customer account: debits_posted = $50, debits_pending = $0
```

### Voiding Instead of Posting

If the product is out of stock, void the pending transfer:

```php
// Step 2b: Product unavailable - void the pending transfer
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('55555555555555555555555555555555'),
        debitAccountId: $customerAccountId,  // Not actually used
        creditAccountId: $merchantAccountId,  // Not actually used
        amount: 0,  // Not used
        ledger: 1,
        code: 1,
        pendingId: $pendingId,  // Reference to pending transfer
        flags: TransferFlags::VOID_PENDING,
    ),
);

// Funds released from pending
// Customer account: debits_pending = $0, funds available again
```

## Example 2: Escrow for Marketplace

### Problem

A marketplace needs to hold funds in escrow until a service is delivered.

### Solution

Use pending transfers with timeout for automatic expiration.

```php
use Castor\Ledgering\Time\Duration;

// Create escrow account
$escrowAccountId = Identifier::fromHex('66666666666666666666666666666666');

$ledger->execute(
    CreateAccount::with(
        id: $escrowAccountId,
        ledger: 1,
        code: 300,  // Escrow account
    ),
);

// Create pending transfer with 7-day timeout
$pendingId = Identifier::fromHex('77777777777777777777777777777777');

$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $customerAccountId,
        creditAccountId: $escrowAccountId,
        amount: 10000,  // $100.00
        ledger: 1,
        code: 2,
        flags: TransferFlags::PENDING,
        timeout: Duration::ofDays(7),  // Expires in 7 days
    ),
);

// Service delivered - release funds to seller
$sellerAccountId = Identifier::fromHex('88888888888888888888888888888888');

$ledger->execute(
    // Post the pending transfer to escrow
    CreateTransfer::with(
        id: Identifier::fromHex('99999999999999999999999999999999'),
        debitAccountId: $customerAccountId,
        creditAccountId: $escrowAccountId,
        amount: 0,
        ledger: 1,
        code: 2,
        pendingId: $pendingId,
        flags: TransferFlags::POST_PENDING,
    ),
    // Transfer from escrow to seller
    CreateTransfer::with(
        id: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
        debitAccountId: $escrowAccountId,
        creditAccountId: $sellerAccountId,
        amount: 10000,
        ledger: 1,
        code: 3,
    ),
);
```

## Example 3: Hotel Reservation

### Problem

A hotel needs to reserve a room and hold a deposit until check-in.

### Solution

Use pending transfer for the deposit, post on check-in, void on cancellation.

```php
// Create pending transfer for deposit
$depositPendingId = Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');

$ledger->execute(
    CreateTransfer::with(
        id: $depositPendingId,
        debitAccountId: $customerAccountId,
        creditAccountId: $hotelAccountId,
        amount: 20000,  // $200.00 deposit
        ledger: 1,
        code: 10,  // Reservation deposit
        flags: TransferFlags::PENDING,
        timeout: Duration::ofDays(30),  // Reservation expires in 30 days
    ),
);

// Customer checks in - post the deposit
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('cccccccccccccccccccccccccccccccc'),
        debitAccountId: $customerAccountId,
        creditAccountId: $hotelAccountId,
        amount: 0,
        ledger: 1,
        code: 10,
        pendingId: $depositPendingId,
        flags: TransferFlags::POST_PENDING,
    ),
);

// Or customer cancels - void the deposit
// (Use VOID_PENDING instead of POST_PENDING)
```

## Timeout and Expiration

Pending transfers can have timeouts. Use the `ExpirePendingTransfers` command to automatically void expired transfers.

```php
use Castor\Ledgering\ExpirePendingTransfers;

// Expire all pending transfers that have timed out
$ledger->execute(
    ExpirePendingTransfers::now(),
);

// This will automatically void any pending transfers where:
// - timeout > 0
// - (timestamp + timeout) <= current time
// - not already posted or voided
```

## Considerations

1. **Pending Affects Available Balance**: Pending debits reduce available balance even though not posted
2. **Unique IDs**: Each pending transfer and its post/void operation need unique IDs
3. **Reference Required**: POST_PENDING and VOID_PENDING require `pendingId` to reference the original transfer
4. **Idempotency**: Posting or voiding the same pending transfer multiple times is safe (idempotent)
5. **Timeout Optional**: Timeout is optional; use `Duration::zero()` for no timeout
6. **Cannot Modify Amount**: When posting/voiding, the amount from the original pending transfer is used
7. **Balance Constraints**: Pending amounts are included in balance constraint checks

## Common Use Cases

- **Payment Pre-Authorization**: Credit card holds
- **Escrow Services**: Marketplace transactions
- **Reservations**: Hotels, flights, event tickets
- **Two-Phase Commits**: Distributed transaction workflows
- **Refund Holds**: Temporary holds during dispute resolution

## See Also

- [Domain Model](../domain-model.md#transfer-flags) - Transfer flags reference
- [Balance Conditional Transfers](balance-conditional-transfers.md) - How constraints work with pending amounts
- [Balance Invariant Transfers](balance-invariant-transfers.md) - Combining balancing with pending

