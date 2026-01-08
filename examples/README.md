# Loan Simulator REPL

An interactive loan simulation tool with APR-based interest accrual and waterfall repayments.

## Running the Simulator

```bash
docker compose run --rm ledgering php examples/loan-repl
```

Or if you have PHP installed locally:

```bash
./examples/loan-repl
```

## Setup

When you start the REPL, you'll be prompted to configure your loan:

1. **APR** (Annual Percentage Rate): Enter as a decimal (e.g., `0.15` for 15%)
2. **Loan Amount**: Enter in pounds (e.g., `1000` for £1,000)

## Available Commands

### `disburse`
Disburse the loan to the customer. This must be done before any other operations.

```
loan> disburse
✓ Loan disbursed: £1,000.00
```

### `days <n>`
Advance the clock forward by `n` days. Does not automatically accrue interest.

```
loan> days 30
✓ Advanced 30 day(s) forward.
  Current date: 2026-02-07 14:10:01
```

### `cycle [n]`
Advance to the 1st of the next month and automatically accrue interest. Optionally specify the number of cycles to run (default: 1). This is useful for simulating monthly billing cycles.

**Single cycle:**
```
loan> cycle
✓ Advanced 23 day(s) to 1st of next month.
  Current date: 2026-02-01 00:00:00
  Interest accrued: £9.45
```

**Multiple cycles:**
```
loan> cycle 3
✓ Completed 3 billing cycle(s).
  Total days advanced: 82
  Current date: 2026-04-01 00:00:00
  Total interest accrued: £33.70

  Cycle breakdown:
    Cycle 1: 23 days, £9.45 interest
    Cycle 2: 28 days, £11.51 interest
    Cycle 3: 31 days, £12.74 interest
```

### `accrue`
Accrue interest based on the number of days since the last accrual (or disbursement).
Uses simple daily interest: `principal * (APR / 365) * days`.

```
loan> accrue
✓ Interest accrued: £12.33
```

### `fee <amount>`
Add a fee to the loan. Amount in pounds.

```
loan> fee 25
✓ Fee added: £25.00
```

### `repay <amount>`
Make a repayment. Uses waterfall allocation in priority order:
1. Fees (highest priority)
2. Interest
3. Principal
4. Overpayment (if payment exceeds total owed)

```
loan> repay 50
✓ Repayment processed: £50.00
  → Fees:      £25.00
  → Interest:  £12.33
  → Principal: £12.67
```

### `status`
Display current loan status including all account balances.

```
loan> status

=== Loan Status ===
Current Date: 2026-02-07 14:10:01

Principal:
  Original:    £1,000.00
  Paid:        £12.67
  Outstanding: £987.33

Interest:
  Accrued:     £12.33
  Paid:        £12.33
  Outstanding: £0.00

Fees:
  Charged:     £25.00
  Paid:        £25.00
  Outstanding: £0.00

Total Outstanding: £987.33
```

### `transactions`
Show transaction history with details.

```
loan> transactions

=== Transaction History ===

[2026-02-07 14:10:01] Interest Accrued: £12.33 (30 days on £1,000.00)
[2026-02-07 14:10:01] Fee Charged: £25.00
[2026-02-07 14:10:01] Repayment: £50.00
  → Fees:      £25.00
  → Interest:  £12.33
  → Principal: £12.67
```

### `help`
Display help message with all available commands.

### `exit` or `quit`
Exit the simulator.

## Example Session

```
loan> disburse
✓ Loan disbursed: £1,000.00

loan> cycle
✓ Advanced 23 day(s) to 1st of next month.
  Current date: 2026-02-01 00:00:00
  Interest accrued: £9.45

loan> fee 25
✓ Fee added: £25.00

loan> repay 50
✓ Repayment processed: £50.00
  → Fees:      £25.00
  → Interest:  £9.45
  → Principal: £15.55

loan> cycle
✓ Advanced 28 day(s) to 1st of next month.
  Current date: 2026-03-01 00:00:00
  Interest accrued: £11.33

loan> status
[... shows current balances ...]

loan> exit
```

## How It Works

The simulator uses a double-entry ledger system with the following accounts:

- **Revenue**: Collects interest and fee income (lender's perspective)
- **Customer Cash**: Tracks customer's funds
- **Control**: Temporary holding account for waterfall repayment allocation
- **Principal**: Amount owed by customer (asset)
- **Interest**: Interest owed by customer (asset)
- **Fees**: Fees owed by customer (asset)
- **Overpayment**: Holds excess repayments

### Interest Accrual
When interest accrues:
- Debit: Interest account (increases amount owed)
- Credit: Revenue account (recognizes income)

### Fee Charges
When fees are added:
- Debit: Fees account (increases amount owed)
- Credit: Revenue account (recognizes income)

### Repayments
Repayments use a waterfall allocation:
1. Payment goes from Customer Cash → Control account
2. Control → Fees (priority 1)
3. Control → Interest (priority 2)
4. Control → Principal (priority 3)
5. Control → Overpayment (any remainder)

Each step uses `BALANCING_DEBIT | BALANCING_CREDIT` flags to transfer the minimum of:
- What's available in the control account
- What's needed in the target account

This ensures proper waterfall behavior where higher-priority debts are paid first.

