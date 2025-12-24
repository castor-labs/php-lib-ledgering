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
 * Represents a 16-byte identifier (128-bit).
 *
 * Can be created from bytes or hexadecimal string.
 */
final readonly class Identifier
{
	private const int BYTE_LENGTH = 16;

	private const int HEX_LENGTH = 32;

	private const string ZERO_BYTES = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";

	private function __construct(
		public string $bytes,
	) {}

	public function __toString(): string
	{
		return $this->toUuid();
	}

	/**
	 * Create a zero identifier (all bytes are 0x00).
	 */
	public static function zero(): self
	{
		return new self(self::ZERO_BYTES);
	}

	/**
	 * Create an identifier from raw bytes.
	 *
	 * @throws \InvalidArgumentException if not exactly 16 bytes
	 */
	public static function fromBytes(string $bytes): self
	{
		if (\strlen($bytes) !== self::BYTE_LENGTH) {
			throw new \InvalidArgumentException(
				\sprintf('Identifier must be exactly %d bytes, got %d', self::BYTE_LENGTH, \strlen($bytes)),
			);
		}

		return new self($bytes);
	}

	/**
	 * Create an identifier from hexadecimal string.
	 *
	 * Accepts formats:
	 * - 32 hex characters: "0123456789abcdef0123456789abcdef"
	 * - UUID format: "01234567-89ab-cdef-0123-456789abcdef"
	 *
	 * @throws \InvalidArgumentException if invalid hex string
	 */
	public static function fromHex(string $hex): self
	{
		// Remove hyphens (UUID format)
		$hex = \str_replace('-', '', $hex);

		if (\strlen($hex) !== self::HEX_LENGTH) {
			throw new \InvalidArgumentException(
				\sprintf('Hex string must be exactly %d characters, got %d', self::HEX_LENGTH, \strlen($hex)),
			);
		}

		$bytes = \hex2bin($hex);
		if ($bytes === false) {
			throw new \InvalidArgumentException('Failed to decode hex string');
		}

		return new self($bytes);
	}

	/**
	 * Get the hexadecimal representation (lowercase, no hyphens).
	 */
	public function toHex(): string
	{
		return \bin2hex($this->bytes);
	}

	/**
	 * Get the UUID format representation (with hyphens).
	 */
	public function toUuid(): string
	{
		$hex = $this->toHex();

		return \sprintf(
			'%s-%s-%s-%s-%s',
			\substr($hex, 0, 8),
			\substr($hex, 8, 4),
			\substr($hex, 12, 4),
			\substr($hex, 16, 4),
			\substr($hex, 20, 12),
		);
	}

	public function equals(self $other): bool
	{
		return $this->bytes === $other->bytes;
	}

	/**
	 * Check if this identifier is zero (all bytes are 0x00).
	 */
	public function isZero(): bool
	{
		return $this->bytes === self::ZERO_BYTES;
	}
}
