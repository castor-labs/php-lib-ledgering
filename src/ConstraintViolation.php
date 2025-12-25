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
final class ConstraintViolation extends \Exception
{
	private function __construct(
		public readonly ErrorCode $errorCode,
		string $message,
	) {
		parent::__construct($message, $errorCode->value);
	}

	public static function accountAlreadyExists(Identifier $id): self
	{
		return new self(
			ErrorCode::AccountAlreadyExists,
			\sprintf('Account with ID %s already exists', $id->toHex()),
		);
	}

	public static function accountNotFound(Identifier $id): self
	{
		return new self(
			ErrorCode::AccountNotFound,
			\sprintf('Account with ID %s not found', $id->toHex()),
		);
	}

	public static function accountClosed(Identifier $id): self
	{
		return new self(
			ErrorCode::AccountClosed,
			\sprintf('Account with ID %s is closed', $id->toHex()),
		);
	}

	public static function transferAlreadyExists(Identifier $id): self
	{
		return new self(
			ErrorCode::TransferAlreadyExists,
			\sprintf('Transfer with ID %s already exists', $id->toHex()),
		);
	}

	public static function pendingTransferNotFound(Identifier $pendingId): self
	{
		return new self(
			ErrorCode::PendingTransferNotFound,
			\sprintf('Pending transfer with ID %s not found', $pendingId->toHex()),
		);
	}

	public static function pendingTransferExpired(Identifier $pendingId): self
	{
		return new self(
			ErrorCode::PendingTransferExpired,
			\sprintf('Pending transfer with ID %s has expired', $pendingId->toHex()),
		);
	}

	public static function debitsExceedCredits(Identifier $accountId): self
	{
		return new self(
			ErrorCode::DebitsExceedCredits,
			\sprintf('Account %s: debits would exceed credits', $accountId->toHex()),
		);
	}

	public static function creditsExceedDebits(Identifier $accountId): self
	{
		return new self(
			ErrorCode::CreditsExceedDebits,
			\sprintf('Account %s: credits would exceed debits', $accountId->toHex()),
		);
	}

	public static function insufficientPendingBalance(Identifier $accountId): self
	{
		return new self(
			ErrorCode::InsufficientPendingBalance,
			\sprintf('Account %s: insufficient pending balance', $accountId->toHex()),
		);
	}

	public static function sameDebitAndCreditAccount(Identifier $accountId): self
	{
		return new self(
			ErrorCode::SameDebitAndCreditAccount,
			\sprintf('Debit and credit account cannot be the same: %s', $accountId->toHex()),
		);
	}

	public static function ledgerMismatch(Code $debitLedger, Code $creditLedger): self
	{
		return new self(
			ErrorCode::LedgerMismatch,
			\sprintf('Debit account ledger %d does not match credit account ledger %d', $debitLedger->value, $creditLedger->value),
		);
	}

	public static function zeroAmount(): self
	{
		return new self(
			ErrorCode::ZeroAmount,
			'Transfer amount cannot be zero',
		);
	}

	public static function pendingIdRequired(): self
	{
		return new self(
			ErrorCode::PendingIdRequired,
			'Pending ID is required for POST_PENDING and VOID_PENDING transfers',
		);
	}

	public static function mutuallyExclusiveFlags(string ...$flags): self
	{
		return new self(
			ErrorCode::InvalidFlags,
			\sprintf('Flags %s are mutually exclusive', \implode(', ', $flags)),
		);
	}

	public static function cannotCombine(Identifier $accountId): self
	{
		return new self(
			ErrorCode::AccountClosed,
			\sprintf('Account %s is closed', $accountId->toHex()),
		);
	}

	public static function closingRequiresPending(): self
	{
		return new self(
			ErrorCode::InvalidFlags,
			'CLOSING_DEBIT and CLOSING_CREDIT require PENDING flag',
		);
	}

	public static function postAndVoidPendingCannotBeCombinedWithBalancing(): self
	{
		return new self(
			ErrorCode::InvalidFlags,
			'POST_PENDING and VOID_PENDING cannot be combined with balancing flags',
		);
	}

	public static function pendingCannotBeCombinedWithPostOrVoid(): self
	{
		return new self(
			ErrorCode::InvalidFlags,
			'PENDING cannot be combined with POST_PENDING or VOID_PENDING',
		);
	}
}
