<?php

declare(strict_types=1);

/**
 * @project Castor Ledgering
 * @link https://github.com/castor-labs/php-lib-ledgering
 * @package castor/ledgering
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2024-2025 CastorLabs Ltd
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Castor\Ledgering;

/**
 * Exception thrown when a ledger constraint is violated.
 *
 * This exception represents business rule violations in the ledger system.
 */
final class ConstraintViolation extends \RuntimeException
{
	// Account-related errors
	public const string ACCOUNT_ALREADY_EXISTS = 'account_already_exists';

	public const string ACCOUNT_NOT_FOUND = 'account_not_found';

	public const string ACCOUNT_CLOSED = 'account_closed';

	// Transfer-related errors
	public const string TRANSFER_ALREADY_EXISTS = 'transfer_already_exists';

	public const string PENDING_TRANSFER_NOT_FOUND = 'pending_transfer_not_found';

	public const string PENDING_TRANSFER_EXPIRED = 'pending_transfer_expired';

	// Balance constraint errors
	public const string DEBITS_EXCEED_CREDITS = 'debits_exceed_credits';

	public const string CREDITS_EXCEED_DEBITS = 'credits_exceed_debits';

	public const string INSUFFICIENT_PENDING_BALANCE = 'insufficient_pending_balance';

	// Validation errors
	public const string SAME_DEBIT_AND_CREDIT_ACCOUNT = 'same_debit_and_credit_account';

	public const string LEDGER_MISMATCH = 'ledger_mismatch';

	public const string ZERO_AMOUNT = 'zero_amount';

	public const string PENDING_ID_REQUIRED = 'pending_id_required';

	private function __construct(
		public readonly string $errorCode,
		string $message,
	) {
		parent::__construct($message);
	}

	public static function accountAlreadyExists(Identifier $id): self
	{
		return new self(
			self::ACCOUNT_ALREADY_EXISTS,
			\sprintf('Account with ID %s already exists', $id->toHex()),
		);
	}

	public static function accountNotFound(Identifier $id): self
	{
		return new self(
			self::ACCOUNT_NOT_FOUND,
			\sprintf('Account with ID %s not found', $id->toHex()),
		);
	}

	public static function accountClosed(Identifier $id): self
	{
		return new self(
			self::ACCOUNT_CLOSED,
			\sprintf('Account with ID %s is closed', $id->toHex()),
		);
	}

	public static function transferAlreadyExists(Identifier $id): self
	{
		return new self(
			self::TRANSFER_ALREADY_EXISTS,
			\sprintf('Transfer with ID %s already exists', $id->toHex()),
		);
	}

	public static function pendingTransferNotFound(Identifier $pendingId): self
	{
		return new self(
			self::PENDING_TRANSFER_NOT_FOUND,
			\sprintf('Pending transfer with ID %s not found', $pendingId->toHex()),
		);
	}

	public static function pendingTransferExpired(Identifier $pendingId): self
	{
		return new self(
			self::PENDING_TRANSFER_EXPIRED,
			\sprintf('Pending transfer with ID %s has expired', $pendingId->toHex()),
		);
	}

	public static function debitsExceedCredits(Identifier $accountId): self
	{
		return new self(
			self::DEBITS_EXCEED_CREDITS,
			\sprintf('Account %s: debits would exceed credits', $accountId->toHex()),
		);
	}

	public static function creditsExceedDebits(Identifier $accountId): self
	{
		return new self(
			self::CREDITS_EXCEED_DEBITS,
			\sprintf('Account %s: credits would exceed debits', $accountId->toHex()),
		);
	}

	public static function insufficientPendingBalance(Identifier $accountId): self
	{
		return new self(
			self::INSUFFICIENT_PENDING_BALANCE,
			\sprintf('Account %s: insufficient pending balance', $accountId->toHex()),
		);
	}

	public static function sameDebitAndCreditAccount(Identifier $accountId): self
	{
		return new self(
			self::SAME_DEBIT_AND_CREDIT_ACCOUNT,
			\sprintf('Debit and credit account cannot be the same: %s', $accountId->toHex()),
		);
	}

	public static function ledgerMismatch(Code $debitLedger, Code $creditLedger): self
	{
		return new self(
			self::LEDGER_MISMATCH,
			\sprintf('Debit account ledger %d does not match credit account ledger %d', $debitLedger->value, $creditLedger->value),
		);
	}

	public static function zeroAmount(): self
	{
		return new self(
			self::ZERO_AMOUNT,
			'Transfer amount cannot be zero',
		);
	}

	public static function pendingIdRequired(): self
	{
		return new self(
			self::PENDING_ID_REQUIRED,
			'Pending ID is required for POST_PENDING and VOID_PENDING transfers',
		);
	}
}
