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

namespace Castor\Ledgering\PHPUnit;

use Castor\Ledgering\Infra\Database;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit extension that bootstraps the database for tests marked with #[Group('integration')].
 *
 * This extension:
 * - Initializes the database schema once before the first database test
 * - Closes the database connection after all tests finish
 */
final class DatabaseExtension implements Extension
{
	#[\Override]
	public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
	{
		$facade->registerSubscriber(new DatabasePreparationSubscriber());
		$facade->registerSubscriber(new DatabaseCleanupSubscriber());
		$facade->registerSubscriber(new DatabaseShutdownSubscriber());
	}
}

/**
 * Subscriber that initializes the database before database tests.
 */
final class DatabasePreparationSubscriber implements PreparationStartedSubscriber
{
	private static bool $initialized = false;

	#[\Override]
	public function notify(PreparationStarted $event): void
	{
		// Check if the test is in the 'integration' group
		if (!$this->isDbTest($event)) {
			return;
		}

		// Initialize database only once
		if (!self::$initialized) {
			Database::initialize();
			self::$initialized = true;
		}
	}

	private function isDbTest(PreparationStarted $event): bool
	{
		$test = $event->test();

		// Get test metadata to check for groups
		$metadata = $test->metadata();

		// Check if test has 'integration' group
		if ($metadata->isGroup('integration')) {
			return true;
		}

		return false;
	}
}

/**
 * Subscriber that runs after each database test.
 *
 * Database is NOT reset between tests. Tests are expected to use unique
 * identifiers and scoped queries to avoid interference with each other.
 */
final class DatabaseCleanupSubscriber implements FinishedSubscriber
{
	#[\Override]
	public function notify(Finished $event): void
	{
		// No-op: tests are designed to be independent without requiring
		// database truncation between runs.
	}
}

/**
 * Subscriber that closes the database connection after all tests finish.
 */
final class DatabaseShutdownSubscriber implements ExecutionFinishedSubscriber
{
	#[\Override]
	public function notify(ExecutionFinished $event): void
	{
		Database::close();
	}
}
