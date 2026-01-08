# Two-Phase Payments

**Learn how to implement pre-authorizations, escrow, and reservations using pending transfers—the foundation of modern payment systems.**

## The Problem: Money Needs to Wait Sometimes

Imagine you're booking a hotel room. The hotel wants to make sure you have enough money to pay, but they don't want to charge you until you check in. What do they do?

They **pre-authorize** your card—reserving the funds without actually transferring them. The money is "on hold." You can't spend it elsewhere, but the hotel hasn't received it yet.

Later, when you check in, the hotel **captures** the pre-authorization, and the money actually transfers. Or if you cancel, they **void** it, and the funds are released back to you.

This is called a **two-phase payment**:
1. **Phase 1**: Reserve the funds (pending)
2. **Phase 2a**: Commit the transfer (post)
3. **Phase 2b**: Cancel the transfer (void)

This pattern is everywhere in modern finance:
- **Credit card pre-authorizations**: Gas stations, hotels, car rentals
- **Escrow**: Marketplace transactions, real estate
- **Reservations**: Event tickets, flights, restaurant bookings
- **Two-phase commits**: Distributed transactions

**The challenge**: How do you implement this in a ledger?

## The Solution: Pending Transfers

Castor Ledgering provides **pending transfers** that implement two-phase workflows natively.

### The Three Flags

1. **`PENDING`**: Creates a pending transfer (phase 1)
2. **`POST_PENDING`**: Commits a pending transfer (phase 2a)
3. **`VOID_PENDING`**: Cancels a pending transfer (phase 2b)

### How It Works

When you create a pending transfer:
- Funds move from `posted` to `pending` balances
- The funds are reserved (can't be spent elsewhere)
- No money has actually transferred yet
- The transfer has a unique ID you can reference later

When you post a pending transfer:
- Funds move from `pending` to `posted` balances
- The transfer is now permanent
- Money has actually transferred

When you void a pending transfer:
- Funds move from `pending` back to `posted` balances
- The reservation is cancelled
- It's like the pending transfer never happened

> [!NOTE]
> **Pending vs Posted balances**
>
> Every account has four balance components:
> - `debits_posted`: Permanent debits
> - `credits_posted`: Permanent credits
> - `debits_pending`: Reserved debits (not yet committed)
> - `credits_pending`: Reserved credits (not yet committed)
>
> **Available balance** = `credits_posted - debits_posted - debits_pending`
>
> Pending amounts reduce your available balance even though they're not posted yet.

## Example 1: Credit Card Pre-Authorization

### The Scenario

You're building an e-commerce platform. A customer makes a purchase for £50. You need to:
1. Verify they have sufficient funds
2. Reserve the funds
3. Ship the product
4. Capture the payment when shipped
5. Or void it if the product is out of stock

### The Implementation

First, create the accounts:

```php
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\TransferFlags;
use Castor\Ledgering\AccountFlags;

$customerId = Identifier::fromHex('11111111111111111111111111111111');
$merchantId = Identifier::fromHex('22222222222222222222222222222222');

$ledger->execute(
    CreateAccount::with(
        id: $customerId,
        ledger: 1,
        code: 100,  // Customer account
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
    CreateAccount::with(
        id: $merchantId,
        ledger: 1,
        code: 200,  // Merchant account
    ),
);

// Customer has £100 in their account
```

**Phase 1: Create the pre-authorization**

```php
$pendingId = Identifier::fromHex('33333333333333333333333333333333');

$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $customerId,
        creditAccountId: $merchantId,
        amount: 5000,  // £50.00
        ledger: 1,
        code: 1,  // Purchase
        flags: TransferFlags::PENDING,
    ),
);
```

**What happened?**

Customer account:
- `credits_posted`: £100 (unchanged)
- `debits_posted`: £0 (unchanged)
- `debits_pending`: £50 (new!)
- **Available balance**: £100 - £0 - £50 = £50

The £50 is reserved. The customer can't spend it elsewhere, but the merchant hasn't received it yet.

> [!TIP]
> **Checking available balance**
>
> When displaying balances to customers, show the available balance:
>
> ```php
> $account = $accounts->ofId($customerId)->one();
> $available = $account->balance->creditsPosted->value
>            - $account->balance->debitsPosted->value
>            - $account->balance->debitsPending->value;
>
> echo "Available: £" . ($available / 100);  // £50.00
> ```
>


> [!NOTE]
> **Why are debitAccountId and creditAccountId ignored?**
>
> When you use `POST_PENDING` or `VOID_PENDING`, the ledger uses the accounts from the original pending transfer. You still need to provide them (the API requires it), but they're ignored.
>
> Convention is to use the same accounts as the original transfer for clarity.

**Phase 2b: Product out of stock—void the pre-authorization**

Instead of posting, you could void:

```php
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('55555555555555555555555555555555'),
        debitAccountId: $customerId,
        creditAccountId: $merchantId,
        amount: 0,
        ledger: 1,
        code: 1,
        pendingId: $pendingId,
        flags: TransferFlags::VOID_PENDING,
    ),
);
```

**What happened?**

Customer account:
- `credits_posted`: £100 (unchanged)
- `debits_posted`: £0 (unchanged)
- `debits_pending`: £0 (released!)
- **Available balance**: £100 - £0 - £0 = £100

The reservation is cancelled. The customer has their full £100 available again. It's like the pending transfer never happened.

### The Complete Flow

```php
// 1. Customer makes purchase
$pendingId = $this->createPendingTransfer($customerId, $merchantId, 5000);

// 2. Check inventory
if ($this->isInStock($productId)) {
    // 3a. Ship product and capture payment
    $this->shipProduct($productId);
    $this->postPendingTransfer($pendingId);
} else {
    // 3b. Out of stock, void the pre-authorization
    $this->voidPendingTransfer($pendingId);
    $this->notifyCustomer("Product out of stock, no charge");
}
```

## Example 2: Marketplace Escrow

### The Scenario

You're building a marketplace like eBay or Airbnb. A buyer purchases from a seller. You need to:
1. Hold the buyer's funds in escrow
2. Wait for the seller to deliver
3. Release funds to the seller when delivered
4. Or refund the buyer if there's a problem

### The Implementation

```php
$buyerId = Identifier::fromHex('66666666666666666666666666666666');
$sellerId = Identifier::fromHex('77777777777777777777777777777777');
$escrowId = Identifier::fromHex('88888888888888888888888888888888');

$ledger->execute(
    CreateAccount::with(id: $buyerId, ledger: 1, code: 100),
    CreateAccount::with(id: $sellerId, ledger: 1, code: 100),
    CreateAccount::with(id: $escrowId, ledger: 1, code: 300),  // Escrow account
);
```

**Step 1: Buyer makes purchase—funds go to escrow (pending)**

```php
use Castor\Ledgering\Time\Duration;

$pendingId = Identifier::fromHex('99999999999999999999999999999999');

$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $buyerId,
        creditAccountId: $escrowId,
        amount: 10000,  // £100.00
        ledger: 1,
        code: 10,  // Escrow deposit
        flags: TransferFlags::PENDING,
        timeout: Duration::ofDays(7),  // Expires in 7 days
    ),
);
```

The buyer's £100 is now pending. They can't spend it, but it hasn't moved to escrow yet.

> [!NOTE]
> **Timeouts for automatic expiration**
>
> The `timeout` parameter specifies how long the pending transfer is valid. After the timeout:
> - The pending transfer can be automatically voided
> - Use `ExpirePendingTransfers::now()` to void all expired transfers
>
> This is useful for:
> - Preventing funds from being locked forever
> - Automatic cleanup of abandoned transactions
> - Regulatory compliance (e.g., pre-auths must expire after 7 days)

**Step 2: Seller ships—post the escrow deposit**

```php
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
        debitAccountId: $buyerId,
        creditAccountId: $escrowId,
        amount: 0,
        ledger: 1,
        code: 10,
        pendingId: $pendingId,
        flags: TransferFlags::POST_PENDING,
    ),
);
```

Now the £100 is actually in escrow (posted). The buyer has been charged.

**Step 3: Buyer confirms delivery—release funds to seller**

```php
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
        debitAccountId: $escrowId,
        creditAccountId: $sellerId,
        amount: 10000,  // £100.00
        ledger: 1,
        code: 11,  // Escrow release
    ),
);
```

The seller receives the £100. Transaction complete!

**Alternative: Buyer disputes—refund from escrow**

If there's a problem, refund the buyer instead:

```php
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('cccccccccccccccccccccccccccccccc'),
        debitAccountId: $escrowId,
        creditAccountId: $buyerId,
        amount: 10000,  // £100.00
        ledger: 1,
        code: 12,  // Escrow refund
    ),
);
```

> [!TIP]
> **Why post to escrow, then transfer to seller?**
>
> You might wonder: why not just create a pending transfer directly from buyer to seller?
>
> ```php
> // Why not this?
> CreateTransfer::with(
>     debitAccountId: $buyerId,
>     creditAccountId: $sellerId,  // Direct to seller
>     flags: TransferFlags::PENDING,
>     // ...
> )
> ```
>
> You could! But using an escrow account gives you:
> - **Visibility**: You can see how much is in escrow at any time
> - **Flexibility**: You can hold funds for multiple transactions
> - **Fees**: You can deduct marketplace fees before releasing to seller
> - **Disputes**: You can refund or partially refund from escrow
>
> It's a more flexible pattern for marketplaces.



## Example 3: Event Ticket Reservations

### The Scenario

You're selling concert tickets. Customers need time to complete checkout, but you don't want to oversell. You need to:
1. Reserve tickets when added to cart
2. Hold the reservation for 10 minutes
3. Complete the purchase if they check out
4. Release the tickets if they abandon the cart

### The Implementation

```php
$customerId = Identifier::fromHex('dddddddddddddddddddddddddddddddd');
$ticketInventoryId = Identifier::fromHex('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');

$ledger->execute(
    CreateAccount::with(id: $customerId, ledger: 1, code: 100),
    CreateAccount::with(
        id: $ticketInventoryId,
        ledger: 1,
        code: 400,  // Ticket inventory
        flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
    ),
);

// Ticket inventory starts with 1000 tickets
// (represented as credits)
```

**Step 1: Customer adds tickets to cart—create reservation**

```php
use Castor\Ledgering\Time\Duration;

$reservationId = Identifier::fromHex('ffffffffffffffffffffffffffffffff');

$ledger->execute(
    CreateTransfer::with(
        id: $reservationId,
        debitAccountId: $ticketInventoryId,  // Reduce inventory
        creditAccountId: $customerId,  // Reserve for customer
        amount: 200,  // 2 tickets
        ledger: 1,
        code: 20,  // Ticket reservation
        flags: TransferFlags::PENDING,
        timeout: Duration::ofMinutes(10),  // 10-minute hold
    ),
);
```

**What happened?**

Ticket inventory:
- `credits_posted`: 1000 (total tickets)
- `debits_posted`: 0
- `debits_pending`: 2 (reserved!)
- **Available**: 1000 - 0 - 2 = 998 tickets

The 2 tickets are reserved. Other customers can't buy them, but this customer hasn't paid yet.

**Step 2a: Customer completes checkout—confirm reservation**

```php
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('00000000000000000000000000000001'),
        debitAccountId: $ticketInventoryId,
        creditAccountId: $customerId,
        amount: 0,
        ledger: 1,
        code: 20,
        pendingId: $reservationId,
        flags: TransferFlags::POST_PENDING,
    ),
);
```

The tickets are now sold. Inventory is permanently reduced.

**Step 2b: Customer abandons cart—release reservation**

If the customer doesn't check out within 10 minutes:

```php
// Run this periodically (e.g., every minute)
use Castor\Ledgering\ExpirePendingTransfers;

$ledger->execute(
    ExpirePendingTransfers::now(),
);
```

This automatically voids all pending transfers that have exceeded their timeout. The tickets are released back to inventory.

> [!TIP]
> **Automatic expiration with cron jobs**
>
> Set up a cron job to expire pending transfers:
>
> ```php
> // Run every minute
> $ledger->execute(ExpirePendingTransfers::now());
> ```
>
> This ensures:
> - Abandoned carts don't lock inventory forever
> - Customers see accurate availability
> - No manual cleanup needed
>
> You can also expire specific transfers manually:
>
> ```php
> $ledger->execute(
>     CreateTransfer::with(
>         id: Identifier::generate(),
>         debitAccountId: $ticketInventoryId,
>         creditAccountId: $customerId,
>         amount: 0,
>         ledger: 1,
>         code: 20,
>         pendingId: $reservationId,
>         flags: TransferFlags::VOID_PENDING,
>     ),
> );
> ```

## Combining Pending with Balancing

You can combine `PENDING` with balancing flags for powerful workflows.

### Example: Reserve Entire Balance

```php
$pendingId = Identifier::fromHex('00000000000000000000000000000002');

$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $customerId,
        creditAccountId: $escrowId,
        amount: 0,  // Will be calculated!
        ledger: 1,
        code: 99,
        flags: TransferFlags::PENDING | TransferFlags::BALANCING_DEBIT,
    ),
);
```

This creates a pending transfer for the **entire balance** of the customer account. The amount is calculated atomically.

Later, you can post or void it:

```php
// Post it
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::generate(),
        debitAccountId: $customerId,
        creditAccountId: $escrowId,
        amount: 0,
        ledger: 1,
        code: 99,
        pendingId: $pendingId,
        flags: TransferFlags::POST_PENDING,
    ),
);
```

> [!NOTE]
> **Balancing calculation happens when creating the pending transfer**
>
> When you combine `PENDING` with balancing flags:
> - The amount is calculated when you create the pending transfer
> - That amount is reserved in pending balances
> - When you post, the same amount moves to posted balances
>
> You **cannot** use balancing flags with `POST_PENDING` or `VOID_PENDING`.

## Important Considerations

### 1. Pending Transfers Must Be Resolved

Every pending transfer must eventually be either posted or voided. Leaving pending transfers unresolved:
- Locks funds indefinitely
- Confuses customers
- Violates regulations (in some jurisdictions)

Use timeouts and automatic expiration to prevent this.

### 2. Timeouts Are Not Automatic

Setting a `timeout` doesn't automatically void the transfer. You must:
- Call `ExpirePendingTransfers::now()` periodically
- Or manually void expired transfers

The timeout is just metadata that marks when the transfer should be expired.

### 3. Pending Transfers Are Immutable

Once created, you cannot modify a pending transfer. You can only:
- Post it (commit)
- Void it (cancel)

If you need to change the amount, you must void the original and create a new one.

### 4. Pending Transfers Affect Available Balance

Pending debits reduce available balance:

```
available = credits_posted - debits_posted - debits_pending
```

Make sure to account for this when displaying balances to users.

### 5. Cannot Post/Void Twice

Once a pending transfer is posted or voided, it's done. You cannot:
- Post a voided transfer
- Void a posted transfer
- Post or void the same transfer twice

The ledger will reject these operations.


## Common Patterns

### Pattern 1: Pre-Authorization with Partial Capture

Sometimes you pre-authorize more than you actually charge (e.g., gas stations).

```php
// Pre-authorize £100
$pendingId = Identifier::generate();
$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $customerId,
        creditAccountId: $merchantId,
        amount: 10000,  // £100
        ledger: 1,
        code: 1,
        flags: TransferFlags::PENDING,
    ),
);

// Customer only pumps £45 of gas
// Void the original pre-auth
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::generate(),
        debitAccountId: $customerId,
        creditAccountId: $merchantId,
        amount: 0,
        ledger: 1,
        code: 1,
        pendingId: $pendingId,
        flags: TransferFlags::VOID_PENDING,
    ),
);

// Create a new regular transfer for the actual amount
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::generate(),
        debitAccountId: $customerId,
        creditAccountId: $merchantId,
        amount: 4500,  // £45
        ledger: 1,
        code: 1,
    ),
);
```

> [!NOTE]
> **Why not partial post?**
>
> TigerBeetle (the underlying ledger) doesn't support partial posting of pending transfers. You must post or void the full amount.
>
> To "partially capture," you:
> 1. Void the original pending transfer
> 2. Create a new regular transfer for the actual amount

### Pattern 2: Multiple Pending Transfers

You can have multiple pending transfers on the same account:

```php
// Reserve for purchase 1
$pending1 = Identifier::generate();
$ledger->execute(
    CreateTransfer::with(
        id: $pending1,
        debitAccountId: $customerId,
        creditAccountId: $merchant1Id,
        amount: 5000,  // £50
        ledger: 1,
        code: 1,
        flags: TransferFlags::PENDING,
    ),
);

// Reserve for purchase 2
$pending2 = Identifier::generate();
$ledger->execute(
    CreateTransfer::with(
        id: $pending2,
        debitAccountId: $customerId,
        creditAccountId: $merchant2Id,
        amount: 3000,  // £30
        ledger: 1,
        code: 1,
        flags: TransferFlags::PENDING,
    ),
);

// Customer account:
// debits_pending = £50 + £30 = £80
// available = credits_posted - debits_posted - £80
```

Each pending transfer is independent. You can post or void them separately.

### Pattern 3: Escrow with Fees

Deduct marketplace fees before releasing to seller:

```php
// Buyer pays £100 to escrow
$pendingId = Identifier::generate();
$ledger->execute(
    CreateTransfer::with(
        id: $pendingId,
        debitAccountId: $buyerId,
        creditAccountId: $escrowId,
        amount: 10000,  // £100
        ledger: 1,
        code: 10,
        flags: TransferFlags::PENDING,
    ),
);

// Post to escrow
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::generate(),
        debitAccountId: $buyerId,
        creditAccountId: $escrowId,
        amount: 0,
        ledger: 1,
        code: 10,
        pendingId: $pendingId,
        flags: TransferFlags::POST_PENDING,
    ),
);

// Release to seller (£95) and marketplace (£5)
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::generate(),
        debitAccountId: $escrowId,
        creditAccountId: $sellerId,
        amount: 9500,  // £95 to seller
        ledger: 1,
        code: 11,
    ),
    CreateTransfer::with(
        id: Identifier::generate(),
        debitAccountId: $escrowId,
        creditAccountId: $marketplaceFeeId,
        amount: 500,  // £5 fee
        ledger: 1,
        code: 12,
    ),
);
```

## Common Use Cases Summary

| Use Case | Pattern | Timeout |
|----------|---------|---------|
| **Credit card pre-auth** | PENDING → POST/VOID | 7 days (regulation) |
| **Marketplace escrow** | PENDING → POST → regular transfer | 30 days |
| **Ticket reservations** | PENDING → POST/VOID | 10-15 minutes |
| **Hotel deposits** | PENDING → POST/VOID | Check-in date |
| **Subscription trials** | PENDING → POST/VOID | Trial end date |
| **Auction bids** | PENDING → POST/VOID | Auction end |

## Key Takeaways

1. **Pending transfers implement two-phase workflows** natively in the ledger.

2. **Three flags**: `PENDING` (create), `POST_PENDING` (commit), `VOID_PENDING` (cancel).

3. **Pending amounts reduce available balance** even though they're not posted yet.

4. **Use timeouts** to prevent funds from being locked forever.

5. **Call `ExpirePendingTransfers::now()` periodically** to automatically void expired transfers.

6. **Can combine with balancing flags** when creating the pending transfer, but not when posting/voiding.

7. **Pending transfers are immutable**—you can only post or void them, not modify them.

8. **No partial posting**—you must post or void the full amount.

## When to Use Pending Transfers

**Use pending transfers when:**
- You need to verify funds before committing
- You're waiting for external confirmation (shipping, delivery)
- You want to prevent overselling (inventory, tickets)
- You need to implement escrow or holds
- Regulations require pre-authorizations

**Don't use pending transfers when:**
- You're ready to commit immediately
- You don't need to reserve funds
- The transaction is simple and one-phase

For simple transactions, use regular transfers. Pending transfers add complexity—use them only when you need two-phase workflows.

## What's Next?

Now that you understand two-phase payments, you might want to learn about:

- **[Automatic Balance Calculations](automatic-balance-calculations.md)**: Combine pending with balancing flags for powerful workflows
- **[Preventing Overdrafts and Overpayments](preventing-overdrafts-and-overpayments.md)**: Use account flags to enforce balance constraints
- **[Loan Management](loan-management.md)**: See pending transfers in action in a complete loan system

## See Also

- [Domain Model](../domain-model.md#transfer-flags) - Complete reference for transfer flags
- [Working with the Library](../working-with-the-library.md) - Integration patterns and best practices




