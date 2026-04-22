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
 * Transfer types used in the loan simulator.
 *
 * Each transfer type represents a specific operation in the loan lifecycle.
 * Transfer codes are organized by category for clarity.
 */
enum TransferType: int
{
	/**
	 * Loan Disbursement (Code: 10)
	 *
	 * Transfer the loan principal to the customer.
	 *
	 * Flow:
	 *   Debit:  Principal Account (customer now owes principal)
	 *   Credit: Customer Cash Account (customer receives funds)
	 *
	 * This is the first transfer in the loan lifecycle, executed once when
	 * the loan is disbursed to the customer.
	 */
	case Disbursement = 10;

	/**
	 * Interest Accrual (Code: 20)
	 *
	 * Accrue interest on the outstanding principal balance.
	 *
	 * Flow:
	 *   Debit:  Interest Account (customer owes more interest)
	 *   Credit: Revenue Account (lender earns interest income)
	 *
	 * Interest accrues daily based on the formula:
	 *   Interest = Principal × (APR / 365) × Days
	 *
	 * This transfer is executed automatically when time advances or before
	 * processing repayments to ensure accurate allocation.
	 */
	case InterestAccrual = 20;

	/**
	 * Fee Charge (Code: 30)
	 *
	 * Charge a fee to the customer (e.g., late payment fee, processing fee).
	 *
	 * Flow:
	 *   Debit:  Fees Account (customer owes fee)
	 *   Credit: Revenue Account (lender earns fee income)
	 *
	 * Fees are charged as needed based on business rules (e.g., missed payments,
	 * special processing requests).
	 */
	case FeeCharge = 30;

	/**
	 * Payment Received (Code: 40)
	 *
	 * Receive a repayment from the customer into the control account.
	 *
	 * Flow:
	 *   Debit:  Customer Cash Account (customer pays from their funds)
	 *   Credit: Control Account (payment held for waterfall allocation)
	 *
	 * This is the first step in the repayment waterfall. The payment is held
	 * in the control account before being allocated to fees, interest, principal,
	 * and overpayment in priority order.
	 */
	case PaymentReceived = 40;

	/**
	 * Payment to Fees (Code: 41)
	 *
	 * Allocate payment to outstanding fees (highest priority in waterfall).
	 *
	 * Flow:
	 *   Debit:  Control Account (reduce available payment)
	 *   Credit: Fees Account (reduce fees owed)
	 *
	 * Flags: BALANCING_DEBIT | BALANCING_CREDIT
	 *
	 * This is a balancing transfer that allocates as much as possible from the
	 * control account to pay down fees, up to the lesser of:
	 * - Available payment in control account
	 * - Outstanding fees balance
	 */
	case PaymentToFees = 41;

	/**
	 * Payment to Interest (Code: 42)
	 *
	 * Allocate payment to outstanding interest (second priority in waterfall).
	 *
	 * Flow:
	 *   Debit:  Control Account (reduce available payment)
	 *   Credit: Interest Account (reduce interest owed)
	 *
	 * Flags: BALANCING_DEBIT | BALANCING_CREDIT
	 *
	 * This is a balancing transfer that allocates remaining payment after fees
	 * to pay down interest, up to the lesser of:
	 * - Remaining payment in control account
	 * - Outstanding interest balance
	 */
	case PaymentToInterest = 42;

	/**
	 * Payment to Principal (Code: 43)
	 *
	 * Allocate payment to outstanding principal (third priority in waterfall).
	 *
	 * Flow:
	 *   Debit:  Control Account (reduce available payment)
	 *   Credit: Principal Account (reduce principal owed)
	 *
	 * Flags: BALANCING_DEBIT | BALANCING_CREDIT
	 *
	 * This is a balancing transfer that allocates remaining payment after fees
	 * and interest to pay down principal, up to the lesser of:
	 * - Remaining payment in control account
	 * - Outstanding principal balance
	 */
	case PaymentToPrincipal = 43;

	/**
	 * Payment to Overpayment (Code: 44)
	 *
	 * Allocate excess payment to overpayment account (lowest priority in waterfall).
	 *
	 * Flow:
	 *   Debit:  Control Account (reduce available payment to zero)
	 *   Credit: Overpayment Account (hold excess for customer)
	 *
	 * Flags: BALANCING_DEBIT
	 *
	 * This is a balancing transfer that allocates any remaining payment after
	 * all obligations (fees, interest, principal) are fully paid. The overpayment
	 * can be refunded to the customer or applied to future obligations.
	 */
	case PaymentToOverpayment = 44;
}
