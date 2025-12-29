Castor Ledgering
================

A double-entry bookkeeping ledgering library for PHP.

## Installation

```shell
composer require castor/ledgering
```

## Quick Start

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

For more information read the [documentation](.dev/docs/README.md).