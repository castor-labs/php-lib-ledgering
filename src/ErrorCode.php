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
 * Error codes for constraint violations.
 *
 * Each error code represents a specific business rule violation in the ledger system.
 */
enum ErrorCode: int
{
	// Account-related errors (1000-1999)
	case AccountAlreadyExists = 1000;
	case AccountNotFound = 1001;
	case AccountClosed = 1002;

	// Transfer-related errors (2000-2999)
	case TransferAlreadyExists = 2000;
	case PendingTransferNotFound = 2001;
	case PendingTransferExpired = 2002;
	case PendingTransferAlreadyPosted = 2003;
	case PendingTransferAlreadyVoided = 2004;
	case ExceedsPendingTransferAmount = 2005;

	// Balance constraint errors (3000-3999)
	case DebitsExceedCredits = 3000;
	case CreditsExceedDebits = 3001;
	case InsufficientPendingBalance = 3002;

	// Validation errors (4000-4999)
	case SameDebitAndCreditAccount = 4000;
	case LedgerMismatch = 4001;
	case ZeroAmount = 4002;
	case PendingIdRequired = 4003;
	case InvalidFlags = 4004;
}
