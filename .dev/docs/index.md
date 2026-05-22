---
description: A double-entry bookkeeping library for PHP.
---
# Castor Ledgering

Build financial systems with confidence using double-entry bookkeeping, powered by Castor Ledgering.

Whether you are building a payment platform, a lending system, or a marketplace with escrow, this library gives you the tools to keep your books balanced, your balances accurate, and your transactions auditable.

## Install

Castor packages are published in the Castor Composer repository, not Packagist. Add the repository first, then require the package:

```bash
composer config repositories.castor composer https://castor-labs.github.io/php-packages
composer require castor/ledgering
```

## Quick example

```php
<?php

use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\InMemory\AccountBalanceCollection;
use Castor\Ledgering\Storage\InMemory\AccountCollection;
use Castor\Ledgering\Storage\InMemory\TransferCollection;

// Create a ledger with in-memory storage
$ledger = new StandardLedger(
    accounts: new AccountCollection(),
    transfers: new TransferCollection(),
    accountBalances: new AccountBalanceCollection(),
);

// Create two accounts
$aliceId = Identifier::fromHex('11111111111111111111111111111111');
$bobId = Identifier::fromHex('22222222222222222222222222222222');

$ledger->execute(
    CreateAccount::with(id: $aliceId, ledger: 1, code: 100),
    CreateAccount::with(id: $bobId, ledger: 1, code: 200),
);

// Transfer 1000 from Alice to Bob
$ledger->execute(
    CreateTransfer::with(
        id: Identifier::fromHex('33333333333333333333333333333333'),
        debitAccountId: $aliceId,
        creditAccountId: $bobId,
        amount: 1000,
        ledger: 1,
        code: 1,
    ),
);
```

Every transfer debits one account and credits another. The books always balance. Money never appears from nowhere or disappears into nothing.

## Why Castor Ledgering?

Building financial systems is hard. You need accuracy, consistency, auditability, and performance. Castor Ledgering gives you all of this through:

- **Double-entry bookkeeping** — every transfer debits one account and credits another, so the books always balance.
- **RDBMS backend** — built on proven relational databases (PostgreSQL, MySQL) with a domain model inspired by TigerBeetle's design.
- **Type safety** — immutable value objects prevent invalid states at compile time.
- **Rich domain model** — express complex business rules directly in the ledger through account flags, balancing transfers, and two-phase payments.

## What to read next

Start with the [documentation home](pages/index.md) to explore the domain model, integration patterns, and practical guides for real-world use cases like overdraft prevention, payment waterfalls, and currency exchange.
