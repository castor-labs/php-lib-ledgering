# Building a Loan Management System: A Practical Guide

Let's build a loan management system together. This guide will walk you through how to use the Castor Ledgering library to model loans with interest accrual, fees, and waterfall repayments. We'll explain not just the *how*, but the *why* behind each decision.

## Understanding the Problem

When you lend money to someone, you need to track several things:

1. **Principal**: The original amount you lent
2. **Interest**: The cost of borrowing, calculated over time
3. **Fees**: Additional charges (late fees, processing fees, etc.)
4. **Repayments**: Money coming back from the borrower

The tricky part? When a borrower makes a payment, you need to decide how to allocate it. Do you pay off fees first? Interest? Principal? This is called a **payment waterfall**, and getting it right is crucial for both legal compliance and business logic.

## The Account Topology

Think of accounts as buckets that hold money. In double-entry accounting, money doesn't just appear or disappear—it always moves from one bucket to another. Let's set up our buckets:

> [!NOTE]
> **What is double-entry accounting?**
> It's a system where every financial transaction affects at least two accounts. If you debit (increase) one account, you must credit (decrease) another by the same amount. This keeps everything balanced and makes it impossible to "lose" money in your system.

### The Revenue Account (Your Income)

```php
CreateAccount::with(
    id: $revenueId,
    ledger: 1,      // USD
    code: 500,      // Revenue account type
)
```

This is where you collect your earnings—interest and fees. It's an **asset account** from your perspective: the more money here, the better.

> [!NOTE]
> **What's an asset?**
> In accounting, an asset is something of value you own or something owed to you. Cash is an asset. Money owed to you (accounts receivable) is also an asset. The revenue account accumulates your earnings, which increases your assets.

### The Customer's Cash Account

```php
CreateAccount::with(
    id: $customerCashId,
    ledger: 1,
    code: 1000,
)
```

This represents the customer's available funds. When you disburse a loan, money flows into this account. When they repay, money flows out.

> [!NOTE]
> In a real system, this might represent their bank account or a wallet in your application. For this example, we're simplifying by assuming the customer has unlimited funds to make payments.

### The Control Account (Waterfall Orchestrator)

```php
CreateAccount::with(
    id: $controlAccountId,
    ledger: 1,
    code: 1001,
    flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
)
```

Here's where it gets interesting. The control account is a **temporary holding area** for repayments. When a customer makes a payment, we:

1. Move their money into the control account
2. Allocate it to fees, interest, and principal in priority order
3. The control account ends up empty again

The `DEBITS_MUST_NOT_EXCEED_CREDITS` flag is crucial—it prevents us from allocating more money than we actually received. Think of it as overdraft protection.

> [!NOTE]
> **Understanding debits and credits**
> This is confusing for developers! In accounting:
> - **Debit** doesn't mean "subtract" and **credit** doesn't mean "add"
> - For asset accounts (like cash): debits increase the balance, credits decrease it
> - For liability accounts (like loans owed): credits increase the balance, debits decrease it
>
> The flag `DEBITS_MUST_NOT_EXCEED_CREDITS` on the control account means: "You can't take out (debit) more than you put in (credit)." It's like saying your bank account can't go negative.

### The Principal Account (What They Owe You)

```php
CreateAccount::with(
    id: $principalId,
    ledger: 1,
    code: 2001,
    flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
)
```

This tracks the original loan amount. When you disburse £1,000, you **debit** this account (increasing what they owe). When they repay principal, you **credit** it (reducing what they owe).

The `CREDITS_MUST_NOT_EXCEED_DEBITS` flag prevents them from "overpaying" the principal beyond what they actually borrowed. You can't pay back more principal than you took out!

**Why is this an asset?** Because it represents money owed to you. In accounting terms, "accounts receivable" are assets.

> [!NOTE]
> **The principal account is backwards from what you might expect!**
> Since this is an asset account (money owed to you):
> - When you lend £1,000: **debit** principal (asset goes up)
> - When they repay £100: **credit** principal (asset goes down)
>
> The balance is: `debits - credits = amount still owed`

### The Interest Account (Accrued Interest)

```php
CreateAccount::with(
    id: $interestId,
    ledger: 1,
    code: 3001,
    flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
)
```

Interest accumulates over time based on the outstanding principal. Like the principal account, this is an asset—it's money owed to you.

> [!NOTE]
> **What does "accrued" mean?**
> Accrual means recognizing something when it's earned, not when cash changes hands. Interest accrues (accumulates) every day, even if the customer hasn't paid it yet. This is different from cash accounting, where you'd only count it when you receive payment.

### The Fees Account

```php
CreateAccount::with(
    id: $feesId,
    ledger: 1,
    code: 4001,
    flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
)
```

When you charge a late fee or processing fee, you debit this account. When the customer pays it off, you credit it.

### The Overpayment Account

```php
CreateAccount::with(
    id: $overpaymentId,
    ledger: 1,
    code: 5001,
)
```

What happens if a customer pays more than they owe? The excess goes here. You might refund it, or apply it to future payments.

## Disbursing the Loan

When you give someone a loan, you're creating an obligation. Here's how it works:

```php
CreateTransfer::with(
    id: $transferId,
    debitAccountId: $principalId,      // They now owe you
    creditAccountId: $customerCashId,  // They receive the money
    amount: $principalAmountPence,
    ledger: 1,
    code: 10,  // Disbursement transaction type
)
```

**What's happening here?**

- **Debit Principal**: Increases what they owe you (asset goes up)
- **Credit Customer Cash**: Gives them the money

After this transfer:
- Principal account shows £1,000 debit (they owe you £1,000)
- Customer cash account shows £1,000 credit (they have £1,000)

> [!NOTE]
> **Why not just set a balance?**
> You might wonder: "Why not just set `principal.balance = 1000`?"
>
> In double-entry accounting, you never directly set balances. Every change happens through a transfer. This creates an audit trail—you can always trace where money came from and where it went. It's like Git for money: every change is a commit, and you can always see the history.

## Accruing Interest: Time is Money

Interest is the cost of borrowing money over time. If you lend £1,000 at 15% APR (Annual Percentage Rate), the borrower owes you more each day they haven't paid you back.

### Calculating Daily Interest

```php
// Calculate interest: principal * (APR / 365) * days
$dailyRate = $this->apr / 365.0;
$interestAmount = (int) round($outstandingPrincipal * $dailyRate * $daysSinceLastAccrual);
```

**Why divide by 365?** APR is an *annual* rate. To get the daily rate, we divide by the number of days in a year.

**Example**: £1,000 at 15% APR for 30 days:
- Daily rate: 0.15 / 365 = 0.000410959
- Interest: £1,000 × 0.000410959 × 30 = £12.33

> [!WARNING]
> **This is a simplified interest calculation!**
>
> In real-world finance, interest calculation can be much more complex:
>
> - **Day count conventions**: Some systems use 360 days (30/360), others use actual days (Actual/365 or Actual/360)
> - **Leap years**: Should you use 365 or 366?
> - **Compounding frequency**: Daily, monthly, or annual compounding?
> - **APR vs APY**: APR doesn't account for compounding; APY (Annual Percentage Yield) does
>
> For consumer loans in many jurisdictions, you must follow specific regulations (like the Truth in Lending Act in the US). Always consult with financial and legal experts for production systems.

### Recording Interest Accrual

When interest accrues, you're recognizing revenue you've earned:

```php
CreateTransfer::with(
    id: $transferId,
    debitAccountId: $interestId,    // They now owe you interest
    creditAccountId: $revenueId,    // You've earned revenue
    amount: $interestAmount,
    ledger: 1,
    code: 20,  // Interest accrual transaction type
)
```

**What's happening?**

- **Debit Interest**: Increases what they owe you in interest (asset goes up)
- **Credit Revenue**: Recognizes your income (you've earned this money)

This is **accrual accounting**—you recognize revenue when you earn it, not when you receive cash.

> [!NOTE]
> **Revenue vs Cash**
>
> This is a key accounting concept that confuses developers:
> - **Revenue** (income statement): You've earned the money
> - **Cash** (balance sheet): You've received the money
>
> When interest accrues, you've earned it (revenue goes up), but you haven't received cash yet. The customer still owes it to you (interest receivable, an asset). When they pay, the asset converts to cash.

### The Compounding Effect

Here's something important: interest accrues on the **outstanding principal**, not the original principal. If they make a payment that reduces the principal, future interest calculations use the lower amount.

```php
// Get outstanding principal balance
$principal = $this->accounts->ofId($this->principalId)->one();
$outstandingPrincipal = $principal->balance->debitsPosted->value
                      - $principal->balance->creditsPosted->value;
```

**Example**:
- Month 1: £1,000 principal → £12.33 interest
- Customer pays £200 (£12.33 interest + £187.67 principal)
- Month 2: £812.33 principal → £10.02 interest (less than before!)

> [!NOTE]
> **This is simple interest, not compound interest**
>
> In our implementation, interest accrues on the principal only. In compound interest systems, unpaid interest gets added to the principal (capitalized), and future interest accrues on the new, higher amount.
>
> Example of compound interest:
> - Month 1: £1,000 principal → £12.33 interest (unpaid)
> - Capitalize: £12.33 interest → principal (now £1,012.33)
> - Month 2: £1,012.33 principal → £12.48 interest (higher!)
>
> See the "Advanced Topics" section for how to implement this.

## Billing Cycles

Many lenders operate on billing cycles—regular intervals when they:

1. Accrue interest for the period
2. Generate a statement showing what's owed
3. Set a payment due date

**Common billing cycle patterns:**

- **Monthly on the 1st**: Credit cards often bill on the first of each month
- **Monthly on anniversary date**: Personal loans might bill on the same day each month (e.g., if you borrowed on the 15th, you're billed on the 15th of each month)
- **Bi-weekly**: Some payroll-linked loans bill every two weeks
- **Custom**: Commercial loans might have negotiated billing schedules

> [!NOTE]
> **Billing cycles are a business rule, not a ledger feature**
>
> The ledger doesn't know about billing cycles—it just records transactions. Your application decides:
> - When to accrue interest
> - When to generate statements
> - When payments are due
>
> You could accrue interest daily, weekly, monthly, or on-demand. The ledger doesn't care.

### Example: Monthly Billing on the 1st

Our `LoanSimulator` example implements a simple monthly billing cycle that advances to the 1st of each month:

```php
public function cycle(int $count = 1): array
{
    for ($i = 0; $i < $count; $i++) {
        // Calculate the first day of next month
        $nextMonth = $currentDate['mon'] + 1;
        $nextYear = $currentDate['year'];

        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }

        $firstDayOfNextMonth = mktime(0, 0, 0, $nextMonth, 1, $nextYear);

        // Advance time
        $this->clock->setNow(Instant::of($firstDayOfNextMonth, 0));

        // Automatically accrue interest
        $this->accrueInterest();
    }
}
```

**Why is this useful?** You can simulate months or years of loan activity instantly:

```php
$loan->cycle(12);  // Simulate a full year of monthly accruals
```

Each cycle:
1. Advances time to the 1st of the next month
2. Accrues interest for all the days that passed since last accrual
3. (In a real system, you'd also generate a statement here)

This is just one way to do it. You could easily modify this to:
- Bill on the anniversary date instead
- Bill bi-weekly
- Accrue interest daily but only generate statements monthly
- Skip weekends/holidays


## Adding Fees

Fees are simpler than interest—they're one-time charges:

```php
CreateTransfer::with(
    id: $transferId,
    debitAccountId: $this->feesId,    // They owe you a fee
    creditAccountId: $this->revenueId, // You've earned revenue
    amount: $amountCents,
    ledger: 1,
    code: 30,  // Fee transaction type
)
```

Just like interest, fees increase what the customer owes (debit fees) and recognize your revenue (credit revenue).

## The Payment Waterfall: Priority Matters

Here's where things get sophisticated. When a customer makes a payment, you need to allocate it in a specific order. This is called a **payment waterfall** because money "flows down" through priorities:

1. **Fees** (highest priority)
2. **Interest**
3. **Principal**
4. **Overpayment** (anything left over)

### Why This Order?

This is a common legal and business requirement:
- **Fees first**: Penalties and charges take priority
- **Interest second**: You want to collect the cost of lending
- **Principal third**: The original loan amount
- **Overpayment last**: Excess goes into a holding account

> [!WARNING]
> **Payment allocation varies by loan type and jurisdiction!**
>
> The waterfall order we're using (fees → interest → principal) is common for consumer loans and credit cards, but it's not universal:
>
> - **Mortgages**: Often allocate to interest first, then principal (no fees in the waterfall)
> - **Student loans**: May have different rules based on subsidy status
> - **Commercial loans**: Often negotiated in the loan agreement
> - **Regulatory requirements**: Some jurisdictions mandate specific allocation orders
>
> In the US, the CARD Act of 2009 requires credit card payments above the minimum to be allocated to the highest-interest balance first. Always check local regulations and loan agreements!

### Why Use a Waterfall Instead of Calculating Upfront?

You might be thinking: "Why not just read the account balances, calculate how much goes to each bucket, and create simple transfers?"

```php
// Why not do this?
$feesOwed = $this->getFeesOwed();
$interestOwed = $this->getInterestOwed();
$principalOwed = $this->getPrincipalOwed();

$toFees = min($paymentAmount, $feesOwed);
$remaining = $paymentAmount - $toFees;

$toInterest = min($remaining, $interestOwed);
$remaining -= $toInterest;

$toPrincipal = min($remaining, $principalOwed);
// ... etc
```

This would work, but there's a critical problem: **consistency**.

#### The Consistency Problem

In a distributed system (or even a multi-threaded application), account balances can change between when you read them and when you write the transfers. This is called a **race condition**.

**Scenario:**
1. Thread A reads: fees = £25, interest = £10
2. Thread B adds a new fee: fees = £50
3. Thread A writes transfers based on old data (fees = £25)
4. **Result**: The new £25 fee is ignored!

#### The Ledger Guarantees Consistency

When you use balancing transfers, the ledger handles this atomically:

```php
$this->ledger->execute(
    $feesTransfer,      // BALANCING flags
    $interestTransfer,  // BALANCING flags
    $principalTransfer, // BALANCING flags
    $overpaymentTransfer,
);
```

The ledger:
1. **Locks** the accounts involved
2. Reads the current balances
3. Calculates the transfer amounts
4. Applies all transfers atomically
5. **Unlocks** the accounts

This is a **write model** operation—it modifies state in a way that guarantees consistency. You're not reading a potentially stale view and then writing based on it. The ledger ensures the balances you're working with are current at the moment of execution.

> [!NOTE]
> **Write Model vs Read Model**
>
> This is a key concept in CQRS (Command Query Responsibility Segregation):
>
> - **Write Model**: Modifies state, must be consistent, uses commands
> - **Read Model**: Queries state, can be eventually consistent, optimized for display
>
> When you use `getStatus()` to display loan information, that's a read model—it's a view of the data at a point in time. It might be slightly stale, and that's okay for display purposes.
>
> When you process a payment, that's a write model—it must be consistent. You can't afford to allocate money based on stale data. The balancing transfers ensure the write happens atomically with the current state.

#### Why This Matters

Imagine a production system where:
- Customers can make payments via web, mobile app, and phone
- Interest accrues automatically every night
- Fees can be added by customer service reps
- Multiple payments might arrive simultaneously

Without atomic operations, you'd have race conditions everywhere. The waterfall approach with balancing transfers gives you:

1. **Correctness**: No race conditions
2. **Simplicity**: No complex locking logic in your application code
3. **Auditability**: Each transfer is recorded with its actual amount
4. **Flexibility**: Change the priority order by reordering transfers

### How the Waterfall Works

The magic happens with **balancing transfers**. Let's break it down step by step.

#### Step 1: Receive the Payment

```php
CreateTransfer::with(
    id: $paymentTransferId,
    debitAccountId: $this->customerCashId,      // Take from customer
    creditAccountId: $this->controlAccountId,   // Put in control account
    amount: $amountCents,
    ledger: 1,
    code: 40,
)
```

The control account now has the full payment amount as a credit balance.


#### Step 2: Allocate to Fees (Priority 1)

```php
CreateTransfer::with(
    id: $feesTransferId,
    debitAccountId: $this->controlAccountId,
    creditAccountId: $this->feesId,
    amount: 0,  // We don't know the amount yet!
    ledger: 1,
    code: 41,
    flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
)
```

**Wait, amount is 0?** Yes! The `BALANCING_DEBIT | BALANCING_CREDIT` flags tell the ledger:

> "Transfer as much as possible from the control account to pay off fees, but don't exceed either account's balance."

**How much transfers?**
- If fees owed: £25
- If control account has: £200
- **Transfer**: £25 (pays off all fees)

**What if there's not enough?**
- If fees owed: £25
- If control account has: £10
- **Transfer**: £10 (partial payment)

The `BALANCING_CREDIT` flag looks at the fees account and says "don't credit more than the debit balance" (can't overpay fees). The `BALANCING_DEBIT` flag looks at the control account and says "don't debit more than the credit balance" (can't spend money you don't have).

> [!NOTE]
> **How balancing transfers work**
>
> When you set `amount: 0` with balancing flags, the ledger calculates the amount for you:
>
> ```
> amount = min(
>     control_account.credits - control_account.debits,  // Available to spend
>     fees_account.debits - fees_account.credits         // Amount owed
> )
> ```
>
> This happens atomically inside the ledger, using the current balances at the moment of execution. You don't have to worry about race conditions or stale data.

#### Step 3: Allocate to Interest (Priority 2)

```php
CreateTransfer::with(
    id: $interestTransferId,
    debitAccountId: $this->controlAccountId,
    creditAccountId: $this->interestId,
    amount: 0,
    ledger: 1,
    code: 42,
    flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
)
```

Same logic, but now we're paying off interest. The control account has less money now (fees were already deducted), so this transfer gets whatever's left, up to the amount of interest owed.

#### Step 4: Allocate to Principal (Priority 3)

```php
CreateTransfer::with(
    id: $principalTransferId,
    debitAccountId: $this->controlAccountId,
    creditAccountId: $this->principalId,
    amount: 0,
    ledger: 1,
    code: 43,
    flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
)
```

After fees and interest are paid, whatever's left goes toward principal.

#### Step 5: Allocate to Overpayment (Catch-All)

```php
CreateTransfer::with(
    id: $overpaymentTransferId,
    debitAccountId: $this->controlAccountId,
    creditAccountId: $this->overpaymentId,
    amount: 0,
    ledger: 1,
    code: 44,
    flags: TransferFlags::BALANCING_DEBIT,  // Only balance the debit side
)
```

Notice this one only has `BALANCING_DEBIT`, not `BALANCING_CREDIT`. Why?

The overpayment account has no limit—it can accept any amount. We just want to drain whatever's left in the control account. This ensures the control account ends up at zero.

### The Atomic Batch

Here's a crucial detail: all four transfers (steps 2-5) are executed in a **single batch**:

```php
$this->ledger->execute(
    $feesTransfer,
    $interestTransfer,
    $principalTransfer,
    $overpaymentTransfer,
);
```

This is atomic—either all succeed or all fail. The balancing happens in order, so fees get first priority, then interest, then principal, then overpayment.

> [!NOTE]
> **What does "atomic" mean?**
>
> Atomic means "all or nothing"—either the entire batch succeeds, or none of it does. There's no in-between state where some transfers succeeded and others failed.
>
> This is crucial for financial systems. Imagine if the payment was deducted from the customer's account, but the allocation to fees/interest/principal failed. The money would be "lost" in the control account. Atomic operations prevent this.

### A Complete Example

Let's walk through a real scenario:

**Loan State:**
- Principal owed: £1,000
- Interest owed: £33.70
- Fees owed: £25
- **Total owed**: £1,058.70

**Customer pays**: £200

**What happens:**

1. **Receive payment**: £200 → control account (control balance: £200)
2. **Allocate to fees**: £25 → fees account (control balance: £175)
3. **Allocate to interest**: £33.70 → interest account (control balance: £141.30)
4. **Allocate to principal**: £141.30 → principal account (control balance: £0)
5. **Allocate to overpayment**: £0 → overpayment account (nothing left)

**Result:**
- Fees: fully paid ✓
- Interest: fully paid ✓
- Principal: £1,000 - £141.30 = £858.70 remaining
- Overpayment: £0

The customer still owes £858.70 in principal.

### Why This Approach is Powerful

You might be thinking: "This seems complex. Why not just calculate the amounts in PHP and create normal transfers?"

You could! But using balancing transfers has advantages:

1. **Correctness**: The ledger enforces the constraints atomically. No race conditions.
2. **Simplicity**: You don't need complex if/else logic to handle partial payments.
3. **Consistency**: The allocation happens with current balances, not stale data.
4. **Auditability**: Each transfer is recorded with its actual amount.
5. **Flexibility**: Want to change the priority order? Just reorder the transfers.

The key insight: **let the ledger do the work**. It's designed to handle concurrent access, atomic operations, and balance constraints. Your application code stays simple and declarative.



## Checking if the Loan is Paid Off

A loan is fully paid when all three obligation accounts have zero balance:

```php
public function isFullyPaid(): bool
{
    $principal = $this->accounts->ofId($this->principalId)->one();
    $interest = $this->accounts->ofId($this->interestId)->one();
    $fees = $this->accounts->ofId($this->feesId)->one();

    $principalOwed = $principal->balance->debitsPosted->value
                   - $principal->balance->creditsPosted->value;
    $interestOwed = $interest->balance->debitsPosted->value
                  - $interest->balance->creditsPosted->value;
    $feesOwed = $fees->balance->debitsPosted->value
              - $fees->balance->creditsPosted->value;

    return $principalOwed === 0 && $interestOwed === 0 && $feesOwed === 0;
}
```

Remember: debits increase what they owe, credits decrease it. When debits equal credits, the balance is zero.

## Putting It All Together

Let's see a complete loan lifecycle:

```php
// Create a £5,000 loan at 15% APR
$loan = LoanSimulator::create(0.15, 500000);

// Disburse the loan
$loan->disburse();

// Simulate 3 months
$loan->cycle(3);

// Customer makes a payment
$loan->repay(20000);  // £200

// Simulate 6 more months
$loan->cycle(6);

// Add a late fee
$loan->addFee(2500);  // £25

// Final payment
$loan->repay(500000);  // £5,000

// Check if paid off
if ($loan->isFullyPaid()) {
    echo "Loan is fully paid!";
}
```

## Key Takeaways

### Account Topology

Your account structure reflects your business model:

- **Revenue account**: Where you collect income (interest + fees)
- **Customer cash**: Represents the customer's funds
- **Control account**: Temporary holding area for waterfall allocation
- **Principal, Interest, Fees**: Track what the customer owes (assets)
- **Overpayment**: Holds excess payments

### Account Flags Are Your Safety Net

- `DEBITS_MUST_NOT_EXCEED_CREDITS`: Prevents overdrafts (control account)
- `CREDITS_MUST_NOT_EXCEED_DEBITS`: Prevents overpayment (obligation accounts)

These flags aren't just validation—they're business rules enforced at the ledger level.

### Balancing Transfers Are Magical

Instead of writing complex allocation logic, you declare your intent:

```php
flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT
```

The ledger figures out the right amount based on account balances and constraints. This is declarative programming at its finest.

### Double-Entry Keeps You Honest

Every transfer has two sides. Money doesn't appear or disappear—it moves. This makes your system:

- **Auditable**: Every transaction is recorded
- **Balanced**: Assets always equal liabilities
- **Debuggable**: You can trace every penny

### Time Matters

Interest accrues over time, so you need to track:

- When the loan was disbursed
- When interest was last accrued
- The current date/time

The `FixedClock` lets you control time for testing and simulation.

### Consistency Over Convenience

Use the write model (balancing transfers) for operations that modify state, even if it seems more complex than reading balances and calculating. The consistency guarantees are worth it.

## Advanced Topics

### Multiple Loans

In a real system, you'd have many loans. You might:

1. Use `externalIdPrimary` to link accounts to loan IDs
2. Create separate account sets for each loan
3. Query accounts by external ID to get loan-specific data

Example:

```php
// Create accounts for loan #12345
CreateAccount::with(
    id: $principalId,
    ledger: 1,
    code: 2001,
    externalIdPrimary: Identifier::fromString('loan-12345'),
    flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
)

// Later, query all accounts for this loan
$loanAccounts = $accounts
    ->ofExternalIdPrimary(Identifier::fromString('loan-12345'))
    ->toList();
```

### Partial Payments

The waterfall handles partial payments automatically. If a customer pays £10 but owes £1,000:

- Fees get paid first (up to £10)
- Interest gets whatever's left
- Principal might get nothing

No special code needed—the balancing transfers handle it.


### Refunding Overpayments

If a customer overpays, you might want to refund them:

```php
$overpayment = $this->accounts->ofId($this->overpaymentId)->one();
$overpaymentBalance = $overpayment->balance->creditsPosted->value;

if ($overpaymentBalance > 0) {
    $this->ledger->execute(
        CreateTransfer::with(
            id: $transferId,
            debitAccountId: $this->overpaymentId,
            creditAccountId: $this->customerCashId,
            amount: $overpaymentBalance,
            ledger: 1,
            code: 50,  // Refund
        ),
    );
}
```

### Different Waterfall Orders

Want to prioritize principal over interest? Just reorder the transfers:

```php
$this->ledger->execute(
    $principalTransfer,   // Priority 1
    $interestTransfer,    // Priority 2
    $feesTransfer,        // Priority 3
    $overpaymentTransfer,
);
```

The order in the batch determines the priority.

> [!NOTE]
> **Amortization schedules**
>
> Mortgages typically use an amortization schedule where each payment is split between interest and principal in a predetermined way. Early payments are mostly interest; later payments are mostly principal.
>
> This is different from our waterfall approach. In an amortization schedule, you'd calculate the split upfront:
>
> ```php
> // Month 1 payment: £1,000
> $interestPortion = $outstandingPrincipal * $monthlyRate;  // £500
> $principalPortion = $payment - $interestPortion;          // £500
> ```
>
> You could implement this with normal transfers (not balancing), since the amounts are predetermined.

### Compound Interest

Our implementation uses simple daily interest, but you could implement compound interest by:

1. Accruing interest daily
2. Capitalizing unpaid interest (moving it to principal)
3. Future interest accrues on the new, higher principal

```php
// Capitalize unpaid interest
$interest = $this->accounts->ofId($this->interestId)->one();
$unpaidInterest = $interest->balance->debitsPosted->value
                - $interest->balance->creditsPosted->value;

if ($unpaidInterest > 0) {
    $this->ledger->execute(
        CreateTransfer::with(
            id: $transferId,
            debitAccountId: $this->principalId,
            creditAccountId: $this->interestId,
            amount: $unpaidInterest,
            ledger: 1,
            code: 25,  // Interest capitalization
        ),
    );
}
```

> [!WARNING]
> **Compound interest regulations**
>
> Many jurisdictions have strict rules about compound interest:
> - Some prohibit "interest on interest" for consumer loans
> - Others require specific disclosures
> - Credit cards often compound daily, but this must be clearly disclosed
>
> Always check local regulations before implementing compound interest!

## Common Pitfalls

### Forgetting to Accrue Interest

Interest doesn't accrue automatically—you need to call `accrueInterest()` at appropriate times (e.g., before payments, at month-end).

**Why?** The ledger doesn't know about your business rules. It's your responsibility to tell it when to accrue interest.

### Wrong Account Flags

If you forget `CREDITS_MUST_NOT_EXCEED_DEBITS` on obligation accounts, customers could "overpay" and create negative balances. The flags are essential.

**Example of what goes wrong:**

```php
// Oops, no flags!
CreateAccount::with(
    id: $principalId,
    ledger: 1,
    code: 2001,
    // Missing: flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS
)

// Later...
// Principal owed: £100
// Customer pays: £200
// Without the flag, principal balance becomes -£100 (they "overpaid")
// The extra £100 should have gone to overpayment!
```

### Incorrect Waterfall Order

The order of transfers in the batch matters! Make sure you understand your business requirements for payment allocation.

**Example:**

```php
// Wrong order - principal gets paid before interest!
$this->ledger->execute(
    $principalTransfer,   // Oops, this should be last
    $interestTransfer,
    $feesTransfer,
    $overpaymentTransfer,
);
```

### Not Tracking Last Accrual Date

If you don't track when interest was last accrued, you might:
- Accrue interest twice for the same period
- Miss accrual periods entirely

Always update `lastAccrualDate` after accruing.

### Reading Balances for Write Operations

Don't do this:

```php
// BAD: Reading balances, then writing based on stale data
$feesOwed = $this->getFeesOwed();  // Read
$toFees = min($payment, $feesOwed);
$this->ledger->execute(
    CreateTransfer::with(
        amount: $toFees,  // Based on potentially stale data!
        // ...
    )
);
```

Do this instead:

```php
// GOOD: Let the ledger calculate atomically
$this->ledger->execute(
    CreateTransfer::with(
        amount: 0,
        flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
        // ...
    )
);
```

## Testing Your Implementation

The loan REPL (`examples/loan-repl`) is a great testing tool:

```bash
php examples/loan-repl
```

Try these scenarios:

1. **Full repayment**: Disburse, accrue, pay exact amount
2. **Partial payments**: Pay less than owed, see waterfall in action
3. **Overpayment**: Pay more than owed, check overpayment account
4. **Multiple cycles**: Simulate months/years of activity
5. **Fees**: Add fees, see them get priority in waterfall
6. **Race conditions**: Try to think of scenarios where concurrent operations might cause issues (they won't, because of atomic operations!)


## Conclusion

Building a loan management system with Castor Ledgering gives you:

- **Correctness**: Double-entry accounting ensures balances always match
- **Consistency**: Atomic operations prevent race conditions
- **Flexibility**: Change business rules by adjusting account flags and transfer order
- **Auditability**: Every transaction is recorded and traceable
- **Simplicity**: Balancing transfers eliminate complex allocation logic

The key is understanding how accounts, transfers, and flags work together to model your business domain. Once you grasp these concepts, you can build sophisticated financial systems with confidence.

### The Mental Model

Think of the ledger as a database with special guarantees:

- **Accounts** are tables that hold balances
- **Transfers** are transactions that move money between accounts
- **Flags** are constraints that enforce business rules
- **Balancing transfers** are stored procedures that calculate amounts atomically

You're not just storing data—you're encoding your business logic into the structure of accounts and the flow of transfers.

### When to Use This Approach

This architecture works well for:

- **Consumer loans**: Personal loans, payday loans, installment loans
- **Credit cards**: Revolving credit with fees and interest
- **Subscriptions**: Recurring billing with usage charges
- **Invoicing**: Track what customers owe, apply payments
- **Wallets**: Digital wallets with deposits, withdrawals, and transfers

It's less suitable for:

- **High-frequency trading**: Too much overhead for microsecond latency
- **Simple balance tracking**: If you just need a number, a single field might suffice
- **Read-heavy workloads**: The write model is optimized for consistency, not read performance

### Learning More

The concepts we've covered—double-entry accounting, accrual accounting, payment waterfalls, atomic operations—are fundamental to financial systems. If you want to go deeper:

- **Accounting basics**: Learn about debits, credits, assets, liabilities, and equity
- **Financial regulations**: Understand Truth in Lending, CARD Act, and other consumer protection laws
- **CQRS and Event Sourcing**: Separate read and write models for scalability
- **TigerBeetle**: A distributed financial accounting database (inspiration for this library)

## Next Steps

- Read the [Domain Model](../domain-model.md) documentation for detailed API reference
- Explore the [loan REPL source code](../../../examples/loan-repl) to see the complete implementation
- Try building your own financial domain model (subscriptions, invoicing, etc.)
- Experiment with different account topologies and waterfall orders

## Appendix: Accounting Concepts for Developers

### Debits and Credits

This is the most confusing part for developers. Here's a cheat sheet:

**For Asset Accounts** (things you own or are owed):
- **Debit** = Increase (you have more)
- **Credit** = Decrease (you have less)

**For Liability Accounts** (things you owe):
- **Debit** = Decrease (you owe less)
- **Credit** = Increase (you owe more)

**For Revenue Accounts** (income):
- **Debit** = Decrease (rare)
- **Credit** = Increase (you earned money)

**For Expense Accounts** (costs):
- **Debit** = Increase (you spent money)
- **Credit** = Decrease (rare)

**Why is it backwards?** It's not! It's about perspective. When you deposit money in a bank:
- From your perspective: asset (cash) increases → debit
- From the bank's perspective: liability (they owe you) increases → credit

The bank credits your account because they owe you more. It's confusing because we use "credit" colloquially to mean "add money," but in accounting it means "increase a liability."

### The Accounting Equation

```
Assets = Liabilities + Equity
```

Everything must balance. If assets go up, either liabilities or equity must go up by the same amount.

In our loan system:
- **Assets**: Principal, Interest, Fees (money owed to you), Revenue (money you've earned)
- **Liabilities**: Customer Cash (money you owe them, if they overpay)
- **Equity**: Not modeled in our simple example

### Accrual vs Cash Accounting

**Accrual Accounting** (what we use):
- Recognize revenue when earned, not when cash received
- Recognize expenses when incurred, not when cash paid
- More accurate picture of financial health
- Required for most businesses

**Cash Accounting**:
- Recognize revenue when cash received
- Recognize expenses when cash paid
- Simpler, but can be misleading
- Allowed for small businesses

Example:
- You accrue £100 interest in January (revenue recognized)
- Customer pays in February (cash received)
- Accrual: Revenue in January, Cash in February
- Cash: Revenue in February

### Why Double-Entry?

Double-entry accounting was invented in the 15th century and is still used today because:

1. **Self-balancing**: If debits don't equal credits, you know there's an error
2. **Complete picture**: You see both sides of every transaction
3. **Fraud prevention**: Harder to hide missing money
4. **Audit trail**: Every change is recorded

It's like version control for money—you can always trace where it came from and where it went.

Happy coding! 🚀

