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

/**
 * Helper class to manage loan lifecycle in tests.
 */
final class Loan
{
	private function __construct(
		public readonly Identifier $revenueId,
		public readonly Identifier $customerCashId,
		public readonly Identifier $principalId,
		public readonly Identifier $feesId,
		public readonly Identifier $interestId,
		public readonly Identifier $overpaymentId,
		public readonly Identifier $controlAccountId,
	) {}

	/**
	 * Create a new loan with all necessary accounts.
	 */
	public static function setup(Ledger $ledger, int $ledgerId = 1): self
	{
		$revenueId = Identifier::fromHex('11111111111111111111111111111111');
		$customerCashId = Identifier::fromHex('22222222222222222222222222222222');
		$principalId = Identifier::fromHex('33333333333333333333333333333333');
		$feesId = Identifier::fromHex('44444444444444444444444444444444');
		$interestId = Identifier::fromHex('55555555555555555555555555555555');
		$overpaymentId = Identifier::fromHex('77777777777777777777777777777777');
		$controlAccountId = Identifier::fromHex('66666666666666666666666666666666');

		$ledger->execute(
			// Revenue account (Asset) - collects fees and interest
			CreateAccount::with(
				id: $revenueId,
				ledger: $ledgerId,
				code: 500,
			),
			// Customer cash account (Liability) - customer's funds (tracking only)
			CreateAccount::with(
				id: $customerCashId,
				ledger: $ledgerId,
				code: 100,
			),
			// Loan Principal (Asset) - amount lent to customer
			CreateAccount::with(
				id: $principalId,
				ledger: $ledgerId,
				code: 301,
				flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
			),
			// Loan Fees (Asset) - fees owed by customer
			CreateAccount::with(
				id: $feesId,
				ledger: $ledgerId,
				code: 302,
				flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
			),
			// Loan Interest (Asset) - interest owed by customer
			CreateAccount::with(
				id: $interestId,
				ledger: $ledgerId,
				code: 303,
				flags: AccountFlags::CREDITS_MUST_NOT_EXCEED_DEBITS,
			),
			// Overpayment account (Liability) - holds excess repayments
			CreateAccount::with(
				id: $overpaymentId,
				ledger: $ledgerId,
				code: 400,
			),
			// Control account - temporary holding for repayment
			CreateAccount::with(
				id: $controlAccountId,
				ledger: $ledgerId,
				code: 900,
			),
		);

		return new self(
			revenueId: $revenueId,
			customerCashId: $customerCashId,
			principalId: $principalId,
			feesId: $feesId,
			interestId: $interestId,
			overpaymentId: $overpaymentId,
			controlAccountId: $controlAccountId,
		);
	}

	/**
	 * Disburse the loan to the customer.
	 */
	public function disburse(Ledger $ledger, int $amountInCents, Identifier $transferId, int $ledgerId = 1): void
	{
		$ledger->execute(
			CreateTransfer::with(
				id: $transferId,
				debitAccountId: $this->principalId,
				creditAccountId: $this->customerCashId,
				amount: $amountInCents,
				ledger: $ledgerId,
				code: 1,
			),
		);
	}

	/**
	 * Add fees to the loan.
	 */
	public function addFees(Ledger $ledger, int $amountInCents, Identifier $transferId, int $ledgerId = 1): void
	{
		$ledger->execute(
			CreateTransfer::with(
				id: $transferId,
				debitAccountId: $this->feesId,
				creditAccountId: $this->revenueId,
				amount: $amountInCents,
				ledger: $ledgerId,
				code: 10,
			),
		);
	}

	/**
	 * Accrue interest on the loan.
	 */
	public function accrueInterest(Ledger $ledger, int $amountInCents, Identifier $transferId, int $ledgerId = 1): void
	{
		$ledger->execute(
			CreateTransfer::with(
				id: $transferId,
				debitAccountId: $this->interestId,
				creditAccountId: $this->revenueId,
				amount: $amountInCents,
				ledger: $ledgerId,
				code: 11,
			),
		);
	}

	/**
	 * Make a payment on the loan using waterfall allocation.
	 *
	 * The payment is allocated in priority order:
	 * 1. Fees (fully paid first)
	 * 2. Interest (fully paid second)
	 * 3. Principal (gets remaining balance)
	 * 4. Overpayment (any excess)
	 *
	 * @param Ledger $ledger The ledger to execute transfers on
	 * @param int $amountInCents The payment amount in cents
	 * @param Identifier $paymentTransferId The ID for the initial payment transfer
	 * @param Identifier $feesTransferId The ID for the fees allocation transfer
	 * @param Identifier $interestTransferId The ID for the interest allocation transfer
	 * @param Identifier $principalTransferId The ID for the principal allocation transfer
	 * @param Identifier $overpaymentTransferId The ID for the overpayment allocation transfer
	 * @param int $ledgerId The ledger ID
	 */
	public function makePayment(
		Ledger $ledger,
		int $amountInCents,
		Identifier $paymentTransferId,
		Identifier $feesTransferId,
		Identifier $interestTransferId,
		Identifier $principalTransferId,
		Identifier $overpaymentTransferId,
		int $ledgerId = 1,
	): void {
		// Step 1: Transfer payment from customer cash to control account
		$ledger->execute(
			CreateTransfer::with(
				id: $paymentTransferId,
				debitAccountId: $this->customerCashId,
				creditAccountId: $this->controlAccountId,
				amount: $amountInCents,
				ledger: $ledgerId,
				code: 20,
			),
		);

		// Step 2: Allocate to fees (priority 1)
		// Use both flags: pay min(what's in control, what fees needs)
		$ledger->execute(
			CreateTransfer::with(
				id: $feesTransferId,
				debitAccountId: $this->controlAccountId,
				creditAccountId: $this->feesId,
				amount: 0,
				ledger: $ledgerId,
				code: 21,
				flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
			),
		);

		// Step 3: Allocate to interest (priority 2)
		// Use both flags: pay min(what's in control, what interest needs)
		$ledger->execute(
			CreateTransfer::with(
				id: $interestTransferId,
				debitAccountId: $this->controlAccountId,
				creditAccountId: $this->interestId,
				amount: 0,
				ledger: $ledgerId,
				code: 22,
				flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
			),
		);

		// Step 4: Allocate to principal (priority 3)
		// Use both flags: pay min(what's in control, what principal needs)
		$ledger->execute(
			CreateTransfer::with(
				id: $principalTransferId,
				debitAccountId: $this->controlAccountId,
				creditAccountId: $this->principalId,
				amount: 0,
				ledger: $ledgerId,
				code: 23,
				flags: TransferFlags::BALANCING_DEBIT | TransferFlags::BALANCING_CREDIT,
			),
		);

		// Step 5: Allocate remainder to overpayment (priority 4)
		$ledger->execute(
			CreateTransfer::with(
				id: $overpaymentTransferId,
				debitAccountId: $this->controlAccountId,
				creditAccountId: $this->overpaymentId,
				amount: 0,
				ledger: $ledgerId,
				code: 24,
				flags: TransferFlags::BALANCING_DEBIT,
			),
		);
	}
}
