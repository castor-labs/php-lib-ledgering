# Feature Specifications

This directory contains Gherkin feature files that document the expected behavior of the ledgering system following TigerBeetle semantics.

## Purpose

These feature files serve as:

1. **Living Documentation**: Human-readable specifications of how the ledger system should behave
2. **Test Scenarios**: Comprehensive coverage of all transfer and account operations
3. **Behavior Reference**: Clear examples for developers implementing or using the ledger
4. **Acceptance Criteria**: Testable scenarios that can be automated with Behat or similar tools

## Files

### `accounts.feature`

Documents account creation and management:
- Basic account creation
- Account flags (DEBITS_MUST_NOT_EXCEED_CREDITS, CREDITS_MUST_NOT_EXCEED_DEBITS, HISTORY, CLOSED)
- External identifiers
- Balance queries
- Idempotency

### `transfers.feature`

Documents all transfer operations following TigerBeetle semantics:
- **Basic Transfers**: Simple transfers between accounts
- **Validation**: Account existence, ledger matching, zero amounts, same account checks
- **Balance Constraints**: Enforcement of account flags
- **Pending Transfers**: Two-phase transfers (create, post, void)
- **Balancing Transfers**: Auto-calculated amounts to zero out accounts
- **Closing Transfers**: Moving entire account balances
- **Balance History**: Tracking balance changes over time
- **Complex Scenarios**: Multiple transfers, timeouts

## TigerBeetle Semantics

These features follow [TigerBeetle](https://tigerbeetle.com/) semantics, which provides:

- **Double-entry accounting**: Every transfer has a debit and credit account
- **Atomic operations**: Transfers either succeed completely or fail
- **Balance constraints**: Accounts can enforce overdraft protection
- **Two-phase transfers**: Pending transfers that can be posted or voided
- **Idempotency**: Duplicate operations are safely ignored
- **Nanosecond precision**: Timestamps with nanosecond accuracy
- **Balance history**: Optional tracking of balance changes over time

## Key Concepts

### Account Flags

- **DEBITS_MUST_NOT_EXCEED_CREDITS**: Prevents overdrafts (e.g., customer cash accounts)
- **CREDITS_MUST_NOT_EXCEED_DEBITS**: Prevents overpayment (e.g., loan accounts)
- **HISTORY**: Enables balance history tracking
- **CLOSED**: Marks account as closed (no new transfers allowed)

### Transfer Flags

- **PENDING**: Creates a two-phase transfer (reserves funds)
- **POST_PENDING**: Commits a pending transfer (moves from pending to posted)
- **VOID_PENDING**: Cancels a pending transfer (releases reserved funds)
- **BALANCING_DEBIT**: Auto-calculates amount to zero out debit account
- **BALANCING_CREDIT**: Auto-calculates amount to zero out credit account
- **CLOSING_DEBIT**: Transfers entire debit account balance (requires PENDING)
- **CLOSING_CREDIT**: Transfers entire credit account balance (requires PENDING)

### Balance Fields

Each account has four balance fields:
- **debits_posted**: Confirmed debit transactions
- **credits_posted**: Confirmed credit transactions
- **debits_pending**: Reserved debit amounts (two-phase transfers)
- **credits_pending**: Reserved credit amounts (two-phase transfers)

## Implementation

These feature files are currently **documentation only**. To implement automated testing:

1. **Install Behat** (PHP BDD framework):
   ```bash
   composer require --dev behat/behat
   ```

2. **Initialize Behat**:
   ```bash
   vendor/bin/behat --init
   ```

3. **Implement Step Definitions** in `features/bootstrap/FeatureContext.php`:
   - Parse Gherkin steps
   - Map to ledger operations
   - Assert expected outcomes

4. **Run Tests**:
   ```bash
   vendor/bin/behat
   ```

## Example Step Definitions

```php
/**
 * @When I create a transfer with:
 */
public function iCreateATransferWith(TableNode $table): void
{
    $data = $table->getRowsHash();
    $this->lastTransfer = CreateTransfer::with(
        id: Identifier::generate(),
        debitAccountId: $this->accounts[$data['debit_account']]->id,
        creditAccountId: $this->accounts[$data['credit_account']]->id,
        amount: Amount::of((int) $data['amount']),
        ledger: Code::of((int) $data['ledger']),
        code: Code::of((int) $data['code']),
    );
    
    try {
        $this->ledger->execute($this->lastTransfer);
    } catch (ConstraintViolation $e) {
        $this->lastError = $e;
    }
}

/**
 * @Then the transfer should be created successfully
 */
public function theTransferShouldBeCreatedSuccessfully(): void
{
    Assert::null($this->lastError, 'Transfer creation failed: ' . $this->lastError?->getMessage());
}
```

## Reading the Features

Each scenario follows the **Given-When-Then** pattern:

- **Given**: Sets up the initial state (preconditions)
- **When**: Performs an action (the behavior being tested)
- **Then**: Asserts the expected outcome (postconditions)
- **And**: Continues the previous step type

Example:
```gherkin
Scenario: Create a simple transfer between accounts
  Given an account "debit-account" exists
  And an account "credit-account" exists
  When I create a transfer with amount 1000
  Then the transfer should be created successfully
  And account "debit-account" should have debits_posted of 1000
  And account "credit-account" should have credits_posted of 1000
```

## Contributing

When adding new features or scenarios:

1. Use clear, descriptive scenario names
2. Follow the existing Gherkin style
3. Group related scenarios with comments
4. Include both success and failure cases
5. Document edge cases and constraints
6. Keep scenarios focused and atomic

## References

- [TigerBeetle Documentation](https://docs.tigerbeetle.com/)
- [Gherkin Reference](https://cucumber.io/docs/gherkin/reference/)
- [Behat Documentation](https://docs.behat.org/)

