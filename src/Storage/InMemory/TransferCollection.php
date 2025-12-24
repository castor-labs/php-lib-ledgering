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

use Castor\Ledgering\Identifier;
use Castor\Ledgering\Storage\TransferReader;
use Castor\Ledgering\Storage\TransferWriter;
use Castor\Ledgering\Transfer;

/**
 * In-memory collection of transfers.
 *
 * Read operations return immutable filtered views.
 * Write operations modify the items array.
 *
 * @extends Collection<Transfer>
 */
final class TransferCollection extends Collection implements TransferReader, TransferWriter
{
	#[\Override]
	public function ofId(Identifier ...$ids): self
	{
		return $this->filter(
			static function (Transfer $transfer) use ($ids): bool {
				foreach ($ids as $id) {
					if ($transfer->id->equals($id)) {
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
			static function (Transfer $transfer) use ($ids): bool {
				foreach ($ids as $id) {
					if ($transfer->externalIdPrimary->equals($id)) {
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
			static function (Transfer $transfer) use ($ids): bool {
				foreach ($ids as $id) {
					if ($transfer->externalIdSecondary->equals($id)) {
						return true;
					}
				}

				return false;
			},
		);
	}

	#[\Override]
	public function write(Transfer $transfer): void
	{
		// Transfers are immutable - ID acts as idempotency key
		// If transfer already exists, do nothing
		foreach ($this->items as $existing) {
			if ($existing->id->equals($transfer->id)) {
				return;
			}
		}

		// If not found, append to items
		$this->items[] = $transfer;
	}
}
