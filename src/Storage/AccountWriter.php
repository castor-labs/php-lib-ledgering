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

namespace Castor\Ledgering\Storage;

use Castor\Ledgering\Account;

/**
 * Writes accounts to storage.
 *
 * @internal You should not use this interface directly.
 */
interface AccountWriter
{
	/**
	 * Write an account to storage.
	 *
	 * @param Account $account The account to write
	 *
	 * @throws StorageError if write operation fails
	 */
	public function write(Account $account): void;
}
