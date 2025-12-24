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

use Castor\Ledgering\AccountBalance;
use Castor\Ledgering\Identifier;

/**
 * Represents a collection of account balances that can be read and transformed.
 *
 * @extends Reader<AccountBalance>
 */
interface AccountBalanceReader extends Reader
{
	/**
	 * Filter account balances by their account IDs.
	 *
	 * @param Identifier ...$ids One or more account IDs to filter by
	 */
	public function ofAccountId(Identifier ...$ids): self;
}
