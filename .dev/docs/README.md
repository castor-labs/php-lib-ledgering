# Castor Ledgering Documentation

Comprehensive documentation for the Castor Ledgering library.

## Table of Contents

### Getting Started

- [Quick Start](../README.md#quick-start) - Get up and running in minutes
- [Installation](../README.md#installation) - How to install the library

### Core Concepts

- [Domain Model](domain-model.md) - Understanding Accounts, Transfers, and Balances
- [Working with the Library](working-with-the-library.md) - Integration patterns and best practices

### Cookbook

Common patterns and recipes for solving real-world problems:

- [Currency Exchange](cookbook/currency-exchange.md) - Exchange between different currencies
- [Balance Conditional Transfers](cookbook/balance-conditional-transfers.md) - Enforce balance constraints
- [Balance Invariant Transfers](cookbook/balance-invariant-transfers.md) - Automatic balance calculations
- [Pending Transfers](cookbook/pending-transfers.md) - Two-phase transfer workflows

### Advanced Examples

- [Loan Management System](examples/loan-management.md) - Complete example of managing loans with principal, interest, and repayments

## Quick Links

- [GitHub Repository](https://github.com/castor-labs/php-lib-ledgering)
- [Issue Tracker](https://github.com/castor-labs/php-lib-ledgering/issues)

## Philosophy

Castor Ledgering is designed with these principles:

1. **Type Safety** - Immutable value objects prevent invalid states
2. **Domain-Driven Design** - Rich domain model that expresses business rules
3. **Fail-Fast** - Validation happens at construction time
4. **Explicit Over Implicit** - Clear, readable code over magic
5. **Companion Library** - Designed to work alongside your application code

## Support

For questions and support:

- Check the documentation
- Search existing [GitHub Issues](https://github.com/castor-labs/php-lib-ledgering/issues)
- Create a new issue if you've found a bug or have a feature request

