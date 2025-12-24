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

use Castor\Ledgering\Identifier;
use Castor\Ledgering\Transfer;

/**
 * Represents a collection of transfers that can be read and transformed.
 *
 * @extends Reader<Transfer>
 */
interface TransferReader extends Reader
{
	/**
	 * Filter transfers by their IDs.
	 *
	 * @param Identifier ...$ids One or more transfer IDs to filter by
	 */
	public function ofId(Identifier ...$ids): self;

	/**
	 * Filter transfers by their primary external IDs.
	 *
	 * @param Identifier ...$ids One or more primary external IDs to filter by
	 */
	public function ofExternalIdPrimary(Identifier ...$ids): self;

	/**
	 * Filter transfers by their secondary external IDs.
	 *
	 * @param Identifier ...$ids One or more secondary external IDs to filter by
	 */
	public function ofExternalIdSecondary(Identifier ...$ids): self;
}
