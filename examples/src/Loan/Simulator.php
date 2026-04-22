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

namespace Castor\Ledgering\Example\Loan;

use Castor\Ledgering\AccountFlags;
use Castor\Ledgering\CreateAccount;
use Castor\Ledgering\CreateTransfer;
use Castor\Ledgering\Example\PoundSterling;
use Castor\Ledgering\Example\Rate;
use Castor\Ledgering\FixedClock;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Ledger;
use Castor\Ledgering\StandardLedger;
use Castor\Ledgering\Storage\InMemory\AccountBalanceCollection;
use Castor\Ledgering\Storage\InMemory\AccountCollection;
use Castor\Ledgering\Storage\InMemory\TransferCollection;
use Castor\Ledgering\Time\Instant;
use Castor\Ledgering\TransferFlags;

/**
 * Loan Simulator with APR-based interest accrual and waterfall repayments.
 */
final class Simulator
{
	private bool $disbursed = false;

	private ?Instant $lastAccrualDate = null;

	private int $transactionCounter = 0;

	private array $transactions = [];

	public function __construct(
		private readonly Ledger $ledger,
		private readonly AccountCollection $accounts,
		private readonly TransferCollection $transfers,
		private readonly FixedClock $clock,
		private readonly Rate $apr,
		private readonly PoundSterling $principalAmount,
		private readonly Identifier $revenueId,
		private readonly Identifier $customerCashId,
		private readonly Identifier $controlAccountId,
		private readonly Identifier $principalId,
		private readonly Identifier $interestId,
		private readonly Identifier $feesId,
		private readonly Identifier $overpaymentId,
	) {}

	public static function create(Rate $apr, PoundSterling $amount): self
	{
		$accounts = new AccountCollection();
		$transfers = new TransferCollection();
		$balances = new AccountBalanceCollection();
		$clock = FixedClock::at((int) \time());

		$ledger = new StandardLedger($accounts, $transfers, $balances, $clock);

		// Create accounts
		$revenueId = Identifier::random();
		$customerCashId = Identifier::random();
		$controlAccountId = Identifier::random();
		$principalId = Identifier::random();
		$interestId = Identifier::random();
		$feesId = Identifier::random();
		$overpaymentId = Identifier::random();

		$ledger->execute(
			// Revenue account (Asset) - collects fees and interest income
			CreateAccount::with(
				id: $revenueId,
				ledger: 1,
				code: AccountType::Revenue->value,
			),
			// Customer cash account - customer's funds
			CreateAccount::with(
				id: $customerCashId,
				ledger: 1,
				code: AccountType::CustomerCash->value,
			),
			// Control account - temporary holding for repayment waterfall
			CreateAccount::with(
				id: $controlAccountId,
				ledger: 1,
				code: AccountType::Control->value,
				flags: AccountFlags::DEBITS_MUST_NOT_EXCEED_CREDITS,
			),
			// Loan Principal (Asset) - amount owed by customer
			CreateAccount::with(
				id: $principalId,
				ledger: 1,
				code: AccountType::Principal->value,
				flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
			),
			// Loan Interest (Asset) - interest owed by customer
			CreateAccount::with(
				id: $interestId,
				ledger: 1,
				code: AccountType::Interest->value,
				flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
			),
			// Loan Fees (Asset) - fees owed by customer
			CreateAccount::with(
				id: $feesId,
				ledger: 1,
				code: AccountType::Fees->value,
				flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
			),
			// Overpayment account - holds excess repayments
			CreateAccount::with(
				id: $overpaymentId,
				ledger: 1,
				code: AccountType::Overpayment->value,
			),
		);

		return new self(
			ledger: $ledger,
			accounts: $accounts,
			transfers: $transfers,
			clock: $clock,
			apr: $apr,
			principalAmount: $amount,
			revenueId: $revenueId,
			customerCashId: $customerCashId,
			controlAccountId: $controlAccountId,
			principalId: $principalId,
			interestId: $interestId,
			feesId: $feesId,
			overpaymentId: $overpaymentId,
		);
	}

	public function disburse(): void
	{
		if ($this->disbursed) {
			throw new \RuntimeException('Loan already disbursed');
		}

		$transferId = $this->nextTransferId();

		$this->ledger->execute(
			CreateTransfer::with(
				id: $transferId,
				debitAccountId: $this->principalId,
				creditAccountId: $this->customerCashId,
				amount: $this->principalAmount->pence,
				ledger: 1,
				code: TransferType::Disbursement->value,
			),
		);

		$this->disbursed = true;
		$this->lastAccrualDate = $this->clock->now();

		$this->transactions[] = [
			'type' => 'disbursement',
			'date' => $this->clock->now(),
			'amount' => $this->principalAmount,
		];
	}

	public function advanceDays(int $days): void
	{
		$this->ensureDisbursed();

		if ($days < 0) {
			throw new \InvalidArgumentException('Days must be non-negative');
		}

		$seconds = $days * 86400; // 24 * 60 * 60
		$newTime = Instant::of($this->clock->now()->seconds + $seconds, $this->clock->now()->nano);
		$this->clock->setNow($newTime);

		// Automatically accrue interest after advancing time
		$this->accrueInterest();
	}

	public function cycle(int $count = 1): array
	{
		$this->ensureDisbursed();

		if ($count < 1) {
			throw new \InvalidArgumentException('Cycle count must be at least 1');
		}

		$results = [];

		for ($i = 0; $i < $count; $i++) {
			$currentTimestamp = $this->clock->now()->seconds;
			$currentDate = \getdate($currentTimestamp);

			// Calculate the first day of next month
			$nextMonth = $currentDate['mon'] + 1;
			$nextYear = $currentDate['year'];

			if ($nextMonth > 12) {
				$nextMonth = 1;
				$nextYear++;
			}

			$firstDayOfNextMonth = \mktime(0, 0, 0, $nextMonth, 1, $nextYear);
			$daysDifference = (int) (($firstDayOfNextMonth - $currentTimestamp) / 86400);

			// Advance to first day of next month
			$newTime = Instant::of($firstDayOfNextMonth, 0);
			$this->clock->setNow($newTime);

			// Get interest before accrual
			$interest = $this->accounts->ofId($this->interestId)->one();
			$interestBefore = $interest->balance->debitsPosted->value - $interest->balance->creditsPosted->value;

			// Automatically accrue interest
			$this->accrueInterest();

			// Get interest after accrual
			$interest = $this->accounts->ofId($this->interestId)->one();
			$interestAfter = $interest->balance->debitsPosted->value - $interest->balance->creditsPosted->value;
			$interestAccruedPence = $interestAfter - $interestBefore;

			$results[] = [
				'days' => $daysDifference,
				'date' => $this->clock->now(),
				'interest' => PoundSterling::ofPence($interestAccruedPence),
			];
		}

		return $results;
	}

	public function accrueInterest(): void
	{
		$this->ensureDisbursed();

		$currentDate = $this->clock->now();
		$daysSinceLastAccrual = $this->calculateDaysSince($this->lastAccrualDate, $currentDate);

		if ($daysSinceLastAccrual === 0) {
			// No accrual needed
			return;
		}

		// Get outstanding principal balance
		$principal = $this->accounts->ofId($this->principalId)->one();
		$outstandingPrincipalPence = $principal->balance->debitsPosted->value - $principal->balance->creditsPosted->value;
		$outstandingPrincipal = PoundSterling::ofPence($outstandingPrincipalPence);

		if ($outstandingPrincipal->isZero()) {
			// No principal to accrue interest on
			$this->lastAccrualDate = $currentDate;

			return;
		}

		// Calculate interest: principal * (APR / 365) * days
		$dailyRate = $this->apr->divide(365);
		$interestAmount = $outstandingPrincipal->multiply($dailyRate->toFloat() * $daysSinceLastAccrual);

		if ($interestAmount->isZero()) {
			$this->lastAccrualDate = $currentDate;

			return;
		}

		$transferId = $this->nextTransferId();

		$this->ledger->execute(
			CreateTransfer::with(
				id: $transferId,
				debitAccountId: $this->interestId,
				creditAccountId: $this->revenueId,
				amount: $interestAmount->pence,
				ledger: 1,
				code: TransferType::InterestAccrual->value,
			),
		);

		$this->lastAccrualDate = $currentDate;

		$this->transactions[] = [
			'type' => 'interest_accrual',
			'date' => $currentDate,
			'amount' => $interestAmount,
			'days' => $daysSinceLastAccrual,
			'principal' => $outstandingPrincipal,
		];
	}

	public function addFee(PoundSterling $amount): void
	{
		$this->ensureDisbursed();

		if ($amount->isZero()) {
			throw new \InvalidArgumentException('Fee amount must be positive');
		}

		$transferId = $this->nextTransferId();

		$this->ledger->execute(
			CreateTransfer::with(
				id: $transferId,
				debitAccountId: $this->feesId,
				creditAccountId: $this->revenueId,
				amount: $amount->pence,
				ledger: 1,
				code: TransferType::FeeCharge->value,
			),
		);

		$this->transactions[] = [
			'type' => 'fee',
			'date' => $this->clock->now(),
			'amount' => $amount,
		];
	}

	public function repay(PoundSterling $amount): void
	{
		$this->ensureDisbursed();

		if ($amount->isZero()) {
			throw new \InvalidArgumentException('Repayment amount must be positive');
		}

		// Automatically accrue interest before processing payment
		$this->accrueInterest();

		// Step 1: Receive payment from customer cash into control account
		$paymentTransferId = $this->nextTransferId();
		$this->ledger->execute(
			CreateTransfer::with(
				id: $paymentTransferId,
				debitAccountId: $this->customerCashId,
				creditAccountId: $this->controlAccountId,
				amount: $amount->pence,
				ledger: 1,
				code: TransferType::PaymentReceived->value,
			),
		);

		// Step 2-5: Waterfall allocation (executed atomically in single batch)
		$feesTransferId = $this->nextTransferId();
		$interestTransferId = $this->nextTransferId();
		$principalTransferId = $this->nextTransferId();
		$overpaymentTransferId = $this->nextTransferId();

		$this->ledger->execute(
			// Step 2: Allocate to fees (priority 1)
			CreateTransfer::with(
				id: $feesTransferId,
				debitAccountId: $this->controlAccountId,
				creditAccountId: $this->feesId,
				amount: 0,
				ledger: 1,
				code: TransferType::PaymentToFees->value,
				flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
			),
			// Step 3: Allocate to interest (priority 2)
			CreateTransfer::with(
				id: $interestTransferId,
				debitAccountId: $this->controlAccountId,
				creditAccountId: $this->interestId,
				amount: 0,
				ledger: 1,
				code: TransferType::PaymentToInterest->value,
				flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
			),
			// Step 4: Allocate to principal (priority 3)
			CreateTransfer::with(
				id: $principalTransferId,
				debitAccountId: $this->controlAccountId,
				creditAccountId: $this->principalId,
				amount: 0,
				ledger: 1,
				code: TransferType::PaymentToPrincipal->value,
				flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
			),
			// Step 5: Allocate to overpayment (any remaining balance)
			CreateTransfer::with(
				id: $overpaymentTransferId,
				debitAccountId: $this->controlAccountId,
				creditAccountId: $this->overpaymentId,
				amount: 0,
				ledger: 1,
				code: TransferType::PaymentToOverpayment->value,
				flags: TransferFlags::BALANCING_DEBIT,
			),
		);

		// Get actual amounts allocated
		$feesTransfer = $this->transfers->ofId($feesTransferId)->one();
		$interestTransfer = $this->transfers->ofId($interestTransferId)->one();
		$principalTransfer = $this->transfers->ofId($principalTransferId)->one();
		$overpaymentTransfer = $this->transfers->ofId($overpaymentTransferId)->one();

		$this->transactions[] = [
			'type' => 'repayment',
			'date' => $this->clock->now(),
			'amount' => $amount,
			'fees' => PoundSterling::ofPence($feesTransfer->amount->value),
			'interest' => PoundSterling::ofPence($interestTransfer->amount->value),
			'principal' => PoundSterling::ofPence($principalTransfer->amount->value),
			'overpayment' => PoundSterling::ofPence($overpaymentTransfer->amount->value),
		];
	}

	public function getStatus(): array
	{
		$principal = $this->accounts->ofId($this->principalId)->one();
		$interest = $this->accounts->ofId($this->interestId)->one();
		$fees = $this->accounts->ofId($this->feesId)->one();
		$overpayment = $this->accounts->ofId($this->overpaymentId)->one();

		return [
			'disbursed' => $this->disbursed,
			'current_date' => $this->clock->now(),
			'principal' => [
				'owed' => PoundSterling::ofPence($principal->balance->debitsPosted->value - $principal->balance->creditsPosted->value),
				'paid' => PoundSterling::ofPence($principal->balance->creditsPosted->value),
				'original' => PoundSterling::ofPence($principal->balance->debitsPosted->value),
			],
			'interest' => [
				'owed' => PoundSterling::ofPence($interest->balance->debitsPosted->value - $interest->balance->creditsPosted->value),
				'paid' => PoundSterling::ofPence($interest->balance->creditsPosted->value),
				'accrued' => PoundSterling::ofPence($interest->balance->debitsPosted->value),
			],
			'fees' => [
				'owed' => PoundSterling::ofPence($fees->balance->debitsPosted->value - $fees->balance->creditsPosted->value),
				'paid' => PoundSterling::ofPence($fees->balance->creditsPosted->value),
				'charged' => PoundSterling::ofPence($fees->balance->debitsPosted->value),
			],
			'overpayment' => [
				'balance' => PoundSterling::ofPence($overpayment->balance->creditsPosted->value),
			],
		];
	}

	public function getTransactions(): array
	{
		return $this->transactions;
	}

	public function getApr(): Rate
	{
		return $this->apr;
	}

	public function isFullyPaid(): bool
	{
		if (!$this->disbursed) {
			return false;
		}

		$principal = $this->accounts->ofId($this->principalId)->one();
		$outstandingPrincipal = $principal->balance->debitsPosted->value - $principal->balance->creditsPosted->value;

		return $outstandingPrincipal === 0;
	}

	private function ensureDisbursed(): void
	{
		if (!$this->disbursed) {
			throw new \RuntimeException('Loan must be disbursed first');
		}
	}

	private function nextTransferId(): Identifier
	{
		$this->transactionCounter++;
		$hex = \str_pad(\dechex($this->transactionCounter), 32, '0', \STR_PAD_LEFT);

		return Identifier::fromHex($hex);
	}

	private function calculateDaysSince(Instant $from, Instant $to): int
	{
		$secondsDiff = $to->seconds - $from->seconds;

		return (int) \floor($secondsDiff / 86400);
	}
}
