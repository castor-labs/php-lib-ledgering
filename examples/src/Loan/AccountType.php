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

/**
 * Account types used in the loan simulator.
 *
 * The loan simulator models a simple loan with interest accrual and waterfall repayments.
 * It uses a chart of accounts to track the loan lifecycle from disbursement to full repayment.
 *
 * Account Code Scheme:
 * - 1xxx: Asset accounts (debit normal balance)
 * - 5xxx: Liability accounts (credit normal balance)
 */
enum AccountType: int
{
	/**
	 * Revenue Account
	 *
	 * Code: 1000
	 * Normal Balance: Debit (Asset)
	 * Flags: None
	 *
	 * Collects all interest and fee income earned by the lender.
	 * When interest accrues or fees are charged, they are credited to this account.
	 *
	 * Balance (debits - credits) represents total income earned from the loan.
	 */
	case Revenue = 1000;

	/**
	 * Loan Principal Account
	 *
	 * Code: 1001
	 * Normal Balance: Debit (Asset)
	 * Flags: CREDITS_MUST_NOT_EXCEED_DEBITS
	 *
	 * Tracks the outstanding principal amount owed by the customer.
	 * - Debited when loan is disbursed (customer owes principal)
	 * - Credited when customer makes principal repayments
	 *
	 * Balance (debits - credits) represents outstanding principal.
	 * The CREDITS_MUST_NOT_EXCEED_DEBITS flag prevents overpayment of principal
	 * beyond what was originally borrowed.
	 */
	case Principal = 1001;

	/**
	 * Loan Interest Account
	 *
	 * Code: 1002
	 * Normal Balance: Debit (Asset)
	 * Flags: CREDITS_MUST_NOT_EXCEED_DEBITS
	 *
	 * Tracks accrued interest owed by the customer.
	 * - Debited when interest accrues (customer owes more interest)
	 * - Credited when customer makes interest repayments
	 *
	 * Balance (debits - credits) represents outstanding interest.
	 * The CREDITS_MUST_NOT_EXCEED_DEBITS flag prevents overpayment of interest
	 * beyond what has accrued.
	 */
	case Interest = 1002;

	/**
	 * Loan Fees Account
	 *
	 * Code: 1003
	 * Normal Balance: Debit (Asset)
	 * Flags: CREDITS_MUST_NOT_EXCEED_DEBITS
	 *
	 * Tracks fees charged to the customer (e.g., late payment fees, processing fees).
	 * - Debited when fees are charged (customer owes fees)
	 * - Credited when customer makes fee repayments
	 *
	 * Balance (debits - credits) represents outstanding fees.
	 * The CREDITS_MUST_NOT_EXCEED_DEBITS flag prevents overpayment of fees
	 * beyond what has been charged.
	 */
	case Fees = 1003;

	/**
	 * Customer Cash Account
	 *
	 * Code: 5000
	 * Normal Balance: Credit (Liability)
	 * Flags: None
	 *
	 * Represents the customer's available funds.
	 * - Credited when loan is disbursed (customer receives money)
	 * - Debited when customer makes repayments
	 *
	 * Balance (credits - debits) represents customer's cash position.
	 */
	case CustomerCash = 5000;

	/**
	 * Control Account
	 *
	 * Code: 5001
	 * Normal Balance: Credit (Liability)
	 * Flags: DEBITS_MUST_NOT_EXCEED_CREDITS
	 *
	 * Temporary holding account for the repayment waterfall process.
	 * Ensures atomic allocation of payments across fees, interest, and principal.
	 *
	 * Flow:
	 * 1. Customer payment is credited to this account
	 * 2. Balancing transfers allocate funds in priority order:
	 *    - Fees (highest priority)
	 *    - Interest
	 *    - Principal
	 *    - Overpayment (lowest priority)
	 * 3. Account returns to zero balance after waterfall completes
	 *
	 * The DEBITS_MUST_NOT_EXCEED_CREDITS flag ensures we can't allocate
	 * more than the payment amount received.
	 */
	case Control = 5001;

	/**
	 * Overpayment Account
	 *
	 * Code: 5002
	 * Normal Balance: Credit (Liability)
	 * Flags: None
	 *
	 * Holds excess repayments made by the customer after all obligations are paid.
	 * - Credited when customer pays more than total owed
	 * - Could be debited to refund customer or apply to future obligations
	 *
	 * Balance (credits - debits) represents amount we owe back to the customer.
	 */
	case Overpayment = 5002;
}
