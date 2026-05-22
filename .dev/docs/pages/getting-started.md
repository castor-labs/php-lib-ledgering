---
description: Install Castor Ledgering, create your first ledger, and run the loan simulator.
---
# Getting started

Castor Ledgering is a double-entry bookkeeping library for PHP. This guide gets you from installation to your first in-memory ledger and points you to the example loan simulator.

## Installation

Castor packages are published in the Castor Composer repository, not Packagist. Add the repository first:

```json
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://castor-labs.github.io/php-packages"
    }
  ]
}
```

Then require the package:

```shell
composer require castor/ledgering
```

You can also configure the repository from the command line:

```bash
composer config repositories.castor composer https://castor-labs.github.io/php-packages
composer require castor/ledgering
```

## Quick start

Create an in-memory ledger, add two accounts, and transfer an amount between them:

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

## Try the loan simulator

Want to see the ledger in action? Try the interactive loan simulator that demonstrates interest accrual, fees, and waterfall repayments:

```bash
docker compose run --rm ledgering php examples/loan-repl
```

The simulator lets you:

- Create a loan with custom APR and principal amount
- Advance time and accrue interest
- Add fees and make repayments
- See how waterfall allocation works (Fees → Interest → Principal)

See the [examples README](https://github.com/castor-labs/php-lib-ledgering/blob/main/examples/README.md) for detailed usage instructions.

## Next steps

- Read the [domain model](domain-model.md) to understand accounts, transfers, balances, flags, and identifiers.
- Learn integration patterns in [Working with the library](working-with-the-library.md).
- Explore practical guides for [overdraft prevention](guides/preventing-overdrafts-and-overpayments.md), [two-phase payments](guides/two-phase-payments.md), [currency exchange](guides/currency-exchange.md), and [loan management](guides/loan-management.md).
