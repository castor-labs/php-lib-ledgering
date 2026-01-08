# Castor Ledgering Documentation

**Build financial systems with confidence using double-entry bookkeeping, powered by Castor Ledgering.**

Welcome! Whether you're building a payment platform, a lending system, or a marketplace with escrow, this documentation will help you understand and use Castor Ledgering effectively.

## Start Here

New to Castor Ledgering? Start with these:

1. **[Quick Start](../README.md#quick-start)** - Get up and running in 5 minutes
2. **[Domain Model](domain-model.md)** - Understand accounts, transfers, and balances
3. **[Working with the Library](working-with-the-library.md)** - Learn integration patterns

Then pick a guide based on what you're building:

## Practical Guides

Learn by building real-world features:

### Essential Patterns

Start here to understand the core features:

- **[Preventing Overdrafts and Overpayments](guides/preventing-overdrafts-and-overpayments.md)**
  Use account flags to enforce balance constraints. Perfect for customer accounts, loans, and gift cards.

- **[Automatic Balance Calculations](guides/automatic-balance-calculations.md)**
  Let the ledger calculate transfer amounts for you. Eliminate race conditions and implement payment waterfalls.

- **[Two-Phase Payments](guides/two-phase-payments.md)**
  Implement pre-authorizations, escrow, and reservations with pending transfers.

- **[Currency Exchange](guides/currency-exchange.md)**
  Handle multi-currency transfers using liquidity accounts. Perfect for forex, crypto exchanges, and international payments.

### Complete Systems

See everything working together:

- **[Loan Management System](guides/loan-management.md)**
  Build a complete loan system with principal, interest, fees, and payment waterfalls.

## Reference Documentation

Deep dives into specific topics:

- **[Domain Model](domain-model.md)** - Complete reference for accounts, transfers, and balances
- **[Working with the Library](working-with-the-library.md)** - Integration patterns, querying, and best practices

## Why Castor Ledgering?

Building financial systems is hard. You need:
- **Accuracy**: Money must never disappear or appear from nowhere
- **Consistency**: Balances must always be correct, even under high concurrency
- **Auditability**: Every transaction must be traceable
- **Performance**: Handle thousands of transactions per second

Castor Ledgering gives you all of this by:

1. **Double-Entry Bookkeeping** - Every transfer debits one account and credits another. The books always balance.
2. **TigerBeetle Backend** - A purpose-built financial database that's ACID-compliant and blazingly fast.
3. **Type Safety** - Immutable value objects prevent invalid states at compile time.
4. **Rich Domain Model** - Express complex business rules directly in the ledger.

## Philosophy

Castor Ledgering is designed with these principles:

- **Type Safety** - Immutable value objects prevent invalid states
- **Domain-Driven Design** - Rich domain model that expresses business rules
- **Fail-Fast** - Validation happens at construction time
- **Explicit Over Implicit** - Clear, readable code over magic
- **Companion Library** - Designed to work alongside your application code

## Get Help

- **Questions?** Check the guides above or search [GitHub Issues](https://github.com/castor-labs/php-lib-ledgering/issues)
- **Found a bug?** [Create an issue](https://github.com/castor-labs/php-lib-ledgering/issues/new)
- **Want to contribute?** Pull requests are welcome!

## What's Next?

Once you've mastered the basics, explore:
- Building custom account types for your domain
- Implementing complex payment flows
- Integrating with external payment systems
- Generating financial reports and statements

