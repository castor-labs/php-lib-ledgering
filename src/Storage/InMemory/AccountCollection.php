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

namespace Castor\Ledgering\Storage\InMemory;

use Castor\Ledgering\Account;
use Castor\Ledgering\Identifier;
use Castor\Ledgering\Storage\AccountReader;
use Castor\Ledgering\Storage\AccountWriter;

/**
 * In-memory collection of accounts.
 *
 * Read operations return immutable filtered views.
 * Write operations modify the items array.
 *
 * @extends Collection<Account>
 */
final class AccountCollection extends Collection implements AccountReader, AccountWriter
{
	#[\Override]
	public function ofId(Identifier ...$ids): self
	{
		return $this->filter(
			static function (Account $account) use ($ids): bool {
				foreach ($ids as $id) {
					if ($account->id->equals($id)) {
						return true;
					}
				}

				return false;
			},
		);
	}

	#[\Override]
	public function ofExternalIdPrimary(Identifier ...$ids): self
	{
		return $this->filter(
			static function (Account $account) use ($ids): bool {
				foreach ($ids as $id) {
					if ($account->externalIdPrimary->equals($id)) {
						return true;
					}
				}

				return false;
			},
		);
	}

	#[\Override]
	public function ofExternalIdSecondary(Identifier ...$ids): self
	{
		return $this->filter(
			static function (Account $account) use ($ids): bool {
				foreach ($ids as $id) {
					if ($account->externalIdSecondary->equals($id)) {
						return true;
					}
				}

				return false;
			},
		);
	}

	#[\Override]
	public function write(Account $account): void
	{
		// Find if account already exists and update it
		foreach ($this->items as $i => $existing) {
			if ($existing->id->equals($account->id)) {
				$this->items[$i] = $account;

				return;
			}
		}

		// If not found, append to items
		$this->items[] = $account;
	}
}
