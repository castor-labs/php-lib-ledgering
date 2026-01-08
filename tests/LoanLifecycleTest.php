<?php

declare(strict_types=1);

/**
 * @project Castor Ledgering
 * @link https://github.com/castor-labs/php-lib-ledgering
 * @package castor/ledgering
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2024-2026 CastorLabs Ltd
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Castor\Ledgering;

use Castor\Ledgering\Storage\InMemory\AccountBalanceCollection;
use Castor\Ledgering\Storage\InMemory\AccountCollection;
use Castor\Ledgering\Storage\InMemory\TransferCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for loan lifecycle scenarios including disbursement, fees, interest, and repayments.
 *
 * These tests demonstrate waterfall repayment allocation across multiple loan components
 * in priority order: Fees → Interest → Principal → Overpayment.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class LoanLifecycleTest extends TestCase
{
	private Ledger $ledger;

	private AccountCollection $accounts;

	private TransferCollection $transfers;

	#[\Override]
	protected function setUp(): void
	{
		$this->accounts = new AccountCollection();
		$this->transfers = new TransferCollection();
		$balances = new AccountBalanceCollection();
		$clock = FixedClock::at();

		$this->ledger = new StandardLedger(
			$this->accounts,
			$this->transfers,
			$balances,
			$clock,
		);
	}

	#[Test]
	public function it_performs_partial_repayment(): void
	{
		// Setup loan
		$loan = Loan::setup($this->ledger);

		// Disburse $1000 loan
		$loan->disburse($this->ledger, 100000, Identifier::fromHex('a0000000000000000000000000000001'));

		// Add $50 in fees
		$loan->addFees($this->ledger, 5000, Identifier::fromHex('a0000000000000000000000000000002'));

		// Accrue $100 in interest
		$loan->accrueInterest($this->ledger, 10000, Identifier::fromHex('a0000000000000000000000000000003'));

		// Total owed: $50 (fees) + $100 (interest) + $1000 (principal) = $1150

		// Make a $300 payment
		$loan->makePayment(
			ledger: $this->ledger,
			amountInCents: 30000,
			paymentTransferId: Identifier::fromHex('b0000000000000000000000000000001'),
			feesTransferId: Identifier::fromHex('b0000000000000000000000000000002'),
			interestTransferId: Identifier::fromHex('b0000000000000000000000000000003'),
			principalTransferId: Identifier::fromHex('b0000000000000000000000000000004'),
			overpaymentTransferId: Identifier::fromHex('b0000000000000000000000000000005'),
		);

		// ASSERT: Verify waterfall allocation
		// Fees should be fully paid ($50)
		$fees = $this->accounts->ofId($loan->feesId)->one();
		self::assertSame(5000, $fees->balance->debitsPosted->value, 'Fees debits');
		self::assertSame(5000, $fees->balance->creditsPosted->value, 'Fees credits (fully paid)');

		// Interest should be fully paid ($100)
		$interest = $this->accounts->ofId($loan->interestId)->one();
		self::assertSame(10000, $interest->balance->debitsPosted->value, 'Interest debits');
		self::assertSame(10000, $interest->balance->creditsPosted->value, 'Interest credits (fully paid)');

		// Principal should be partially paid ($150 out of $1000)
		$principal = $this->accounts->ofId($loan->principalId)->one();
		self::assertSame(100000, $principal->balance->debitsPosted->value, 'Principal debits');
		self::assertSame(15000, $principal->balance->creditsPosted->value, 'Principal credits (partially paid)');

		// Overpayment should be zero (no excess payment)
		$overpayment = $this->accounts->ofId($loan->overpaymentId)->one();
		self::assertSame(0, $overpayment->balance->debitsPosted->value, 'Overpayment debits');
		self::assertSame(0, $overpayment->balance->creditsPosted->value, 'Overpayment credits');

		// Control account should be empty (all $300 allocated)
		$control = $this->accounts->ofId($loan->controlAccountId)->one();
		$controlNet = $control->balance->creditsPosted->value - $control->balance->debitsPosted->value;
		self::assertSame(0, $controlNet, 'Control account should be balanced');

		// Verify the transfer amounts
		$feesTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000002'))->one();
		self::assertSame(5000, $feesTransfer->amount->value, 'Fees transfer amount'); // $50

		$interestTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000003'))->one();
		self::assertSame(10000, $interestTransfer->amount->value, 'Interest transfer amount'); // $100

		$principalTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000004'))->one();
		self::assertSame(15000, $principalTransfer->amount->value, 'Principal transfer amount'); // $150

		$overpaymentTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000005'))->one();
		self::assertSame(0, $overpaymentTransfer->amount->value, 'Overpayment transfer amount'); // $0
	}

	#[Test]
	public function it_handles_overpayment(): void
	{
		// Setup loan
		$loan = Loan::setup($this->ledger);

		// Disburse $100 loan
		$loan->disburse($this->ledger, 10000, Identifier::fromHex('a0000000000000000000000000000001'));

		// Add $10 in fees
		$loan->addFees($this->ledger, 1000, Identifier::fromHex('a0000000000000000000000000000002'));

		// Accrue $20 in interest
		$loan->accrueInterest($this->ledger, 2000, Identifier::fromHex('a0000000000000000000000000000003'));

		// Total owed: $10 (fees) + $20 (interest) + $100 (principal) = $130

		// Make a $200 overpayment
		$loan->makePayment(
			ledger: $this->ledger,
			amountInCents: 20000,
			paymentTransferId: Identifier::fromHex('b0000000000000000000000000000001'),
			feesTransferId: Identifier::fromHex('b0000000000000000000000000000002'),
			interestTransferId: Identifier::fromHex('b0000000000000000000000000000003'),
			principalTransferId: Identifier::fromHex('b0000000000000000000000000000004'),
			overpaymentTransferId: Identifier::fromHex('b0000000000000000000000000000005'),
		);

		// ASSERT: All loan components fully paid
		$fees = $this->accounts->ofId($loan->feesId)->one();
		self::assertSame(1000, $fees->balance->debitsPosted->value);
		self::assertSame(1000, $fees->balance->creditsPosted->value);

		$interest = $this->accounts->ofId($loan->interestId)->one();
		self::assertSame(2000, $interest->balance->debitsPosted->value);
		self::assertSame(2000, $interest->balance->creditsPosted->value);

		$principal = $this->accounts->ofId($loan->principalId)->one();
		self::assertSame(10000, $principal->balance->debitsPosted->value);
		self::assertSame(10000, $principal->balance->creditsPosted->value);

		// Overpayment should have $70 ($200 - $130)
		$overpayment = $this->accounts->ofId($loan->overpaymentId)->one();
		self::assertSame(0, $overpayment->balance->debitsPosted->value);
		self::assertSame(7000, $overpayment->balance->creditsPosted->value, 'Overpayment should be $70');

		// Control account should be empty
		$control = $this->accounts->ofId($loan->controlAccountId)->one();
		$controlNet = $control->balance->creditsPosted->value - $control->balance->debitsPosted->value;
		self::assertSame(0, $controlNet);

		// Verify overpayment transfer amount
		$overpaymentTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000005'))->one();
		self::assertSame(7000, $overpaymentTransfer->amount->value, 'Overpayment transfer should be $70');
	}

	#[Test]
	public function it_handles_persistent_debt_scenario(): void
	{
		// Setup loan
		$loan = Loan::setup($this->ledger);

		// Disburse $1000 loan
		$loan->disburse($this->ledger, 100000, Identifier::fromHex('a0000000000000000000000000000001'));

		// Add $50 in fees
		$loan->addFees($this->ledger, 5000, Identifier::fromHex('a0000000000000000000000000000002'));

		// Accrue $100 in interest
		$loan->accrueInterest($this->ledger, 10000, Identifier::fromHex('a0000000000000000000000000000003'));

		// Total owed: $50 (fees) + $100 (interest) + $1000 (principal) = $1150
		// Customer can only afford $120 (enough for interest + partial fees)
		// Payment: $120 should cover:
		//   - $50 fees (fully paid - priority 1)
		//   - $70 interest (partially paid - priority 2)
		//   - $0 principal (untouched - priority 3)
		$loan->makePayment(
			ledger: $this->ledger,
			amountInCents: 12000,
			paymentTransferId: Identifier::fromHex('b0000000000000000000000000000001'),
			feesTransferId: Identifier::fromHex('b0000000000000000000000000000002'),
			interestTransferId: Identifier::fromHex('b0000000000000000000000000000003'),
			principalTransferId: Identifier::fromHex('b0000000000000000000000000000004'),
			overpaymentTransferId: Identifier::fromHex('b0000000000000000000000000000005'),
		);

		// ASSERT: Verify waterfall allocation for persistent debt
		// Fees should be fully paid ($50)
		$fees = $this->accounts->ofId($loan->feesId)->one();
		self::assertSame(5000, $fees->balance->debitsPosted->value, 'Fees debits');
		self::assertSame(5000, $fees->balance->creditsPosted->value, 'Fees credits (fully paid)');

		// Interest should be partially paid ($70 out of $100)
		$interest = $this->accounts->ofId($loan->interestId)->one();
		self::assertSame(10000, $interest->balance->debitsPosted->value, 'Interest debits (owed)');
		self::assertSame(7000, $interest->balance->creditsPosted->value, 'Interest credits (partially paid)');

		// Principal should remain completely untouched ($0 paid out of $1000)
		$principal = $this->accounts->ofId($loan->principalId)->one();
		self::assertSame(100000, $principal->balance->debitsPosted->value, 'Principal debits (owed)');
		self::assertSame(0, $principal->balance->creditsPosted->value, 'Principal credits (unpaid)');

		// No overpayment
		$overpayment = $this->accounts->ofId($loan->overpaymentId)->one();
		self::assertSame(0, $overpayment->balance->debitsPosted->value, 'Overpayment debits');
		self::assertSame(0, $overpayment->balance->creditsPosted->value, 'Overpayment credits');

		// Control account should be empty (all $120 allocated)
		$control = $this->accounts->ofId($loan->controlAccountId)->one();
		$controlNet = $control->balance->creditsPosted->value - $control->balance->debitsPosted->value;
		self::assertSame(0, $controlNet, 'Control account should be balanced');

		// Verify the transfer amounts
		$feesTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000002'))->one();
		self::assertSame(5000, $feesTransfer->amount->value, 'Fees transfer amount'); // $50

		$interestTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000003'))->one();
		self::assertSame(7000, $interestTransfer->amount->value, 'Interest transfer amount'); // $70

		$principalTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000004'))->one();
		self::assertSame(0, $principalTransfer->amount->value, 'Principal transfer amount'); // $0

		$overpaymentTransfer = $this->transfers->ofId(Identifier::fromHex('b0000000000000000000000000000005'))->one();
		self::assertSame(0, $overpaymentTransfer->amount->value, 'Overpayment transfer amount'); // $0
	}
}
