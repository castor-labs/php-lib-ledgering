#!/usr/bin/env php
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

require_once __DIR__.'/../vendor/autoload.php';

use Castor\Ledgering\Example\Loan\Simulator;
use Castor\Ledgering\Example\PoundSterling;
use Castor\Ledgering\Example\Rate;
use Castor\Ledgering\Time\Instant;

// ============================================================================
// Helper Functions
// ============================================================================

function formatDate(Instant $instant): string
{
	return date('Y-m-d H:i:s', $instant->seconds);
}

function printStatus(Simulator $loan): void
{
	$status = $loan->getStatus();

	echo "\n";
	echo "=== Loan Status ===\n";
	echo 'Current Date: '.formatDate($status['current_date'])."\n";
	echo "\n";

	if (!$status['disbursed']) {
		echo "Status: NOT DISBURSED\n";
		echo "\n";

		return;
	}

	echo "Principal:\n";
	echo '  Original:    '.$status['principal']['original']->format()."\n";
	echo '  Paid:        '.$status['principal']['paid']->format()."\n";
	echo '  Outstanding: '.$status['principal']['owed']->format()."\n";
	echo "\n";

	echo "Interest:\n";
	echo '  Accrued:     '.$status['interest']['accrued']->format()."\n";
	echo '  Paid:        '.$status['interest']['paid']->format()."\n";
	echo '  Outstanding: '.$status['interest']['owed']->format()."\n";
	echo "\n";

	echo "Fees:\n";
	echo '  Charged:     '.$status['fees']['charged']->format()."\n";
	echo '  Paid:        '.$status['fees']['paid']->format()."\n";
	echo '  Outstanding: '.$status['fees']['owed']->format()."\n";
	echo "\n";

	if (!$status['overpayment']['balance']->isZero()) {
		echo 'Overpayment: '.$status['overpayment']['balance']->format()."\n";
		echo "\n";
	}

	$totalOwed = $status['principal']['owed']->add($status['interest']['owed'])->add($status['fees']['owed']);
	echo 'Total Outstanding: '.$totalOwed->format()."\n";
	echo "\n";
}

function printTransactions(Simulator $loan): void
{
	$transactions = $loan->getTransactions();

	if (empty($transactions)) {
		echo "No transactions yet.\n";

		return;
	}

	echo "\n";
	echo "=== Transaction History ===\n";
	echo "\n";

	foreach ($transactions as $tx) {
		$date = formatDate($tx['date']);

		switch ($tx['type']) {
			case 'interest_accrual':
				echo "[{$date}] Interest Accrued: ".$tx['amount']->format();
				echo " ({$tx['days']} days on ".$tx['principal']->format().")\n";

				break;
			case 'fee':
				echo "[{$date}] Fee Charged: ".$tx['amount']->format()."\n";

				break;
			case 'disbursement':
				echo "[{$date}] Loan Disbursed: ".$tx['amount']->format()."\n";

				break;
			case 'repayment':
				echo "[{$date}] Repayment: ".$tx['amount']->format()."\n";
				if (!$tx['fees']->isZero()) {
					echo '  → Fees:      '.$tx['fees']->format()."\n";
				}
				if (!$tx['interest']->isZero()) {
					echo '  → Interest:  '.$tx['interest']->format()."\n";
				}
				if (!$tx['principal']->isZero()) {
					echo '  → Principal: '.$tx['principal']->format()."\n";
				}
				if (!$tx['overpayment']->isZero()) {
					echo '  → Overpayment: '.$tx['overpayment']->format()."\n";
				}

				break;
		}
	}

	echo "\n";
}

function printHelp(): void
{
	echo "\n";
	echo "Available commands:\n";
	echo "  disburse              - Disburse the loan\n";
	echo "  days <n>              - Advance the clock by <n> days\n";
	echo "  cycle [n]             - Advance to 1st of next month and accrue interest (n times, default: 1)\n";
	echo "  accrue                - Accrue interest based on days since last accrual\n";
	echo "  fee <amount>          - Add a fee (amount in pounds, e.g., 'fee 25.50')\n";
	echo "  repay <amount>        - Make a repayment (amount in pounds)\n";
	echo "  status                - Show current loan status and balances\n";
	echo "  transactions          - Show transaction history\n";
	echo "  help                  - Show this help message\n";
	echo "  exit                  - Exit the simulator\n";
	echo "\n";
}

// ============================================================================
// Main REPL
// ============================================================================

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           Loan Simulator - Interactive REPL                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Setup loan
echo "Let's set up your loan.\n";
echo "\n";

$apr = null;
while ($apr === null) {
	echo 'Enter APR (as decimal, e.g., 0.15 for 15% or just 15%): ';
	$input = trim(fgets(\STDIN));

	try {
		$apr = Rate::parse($input);
		// Validate it's reasonable (0% to 1000%)
		if ($apr->toPercentage()->toFloat() < 0 || $apr->toPercentage()->toFloat() > 1000) {
			echo "Invalid APR. Please enter a value between 0% and 1000%.\n";
			$apr = null;
		}
	} catch (Exception $e) {
		echo "Invalid APR format.\n";
		$apr = null;
	}
}

$amount = null;
while ($amount === null) {
	echo 'Enter loan amount (in pounds, e.g., 1000): ';
	$input = trim(fgets(\STDIN));

	try {
		$amount = PoundSterling::parse($input);
		if ($amount->isZero()) {
			echo "Amount must be positive.\n";
			$amount = null;
		}
	} catch (Exception $e) {
		echo "Invalid amount.\n";
	}
}

echo "\n";
echo "Loan created:\n";
echo '  Amount: '.$amount->format()."\n";
echo '  APR:    '.$apr->formatAsPercentage()."\n";
echo '  Daily Rate: '.$apr->divide(365)->formatAsPercentage(6)."\n";
echo "\n";
echo "Type 'help' for available commands.\n";

$loan = Simulator::create($apr, $amount);

// Main REPL loop
$running = true;
while ($running) {
	echo 'loan> ';
	$input = trim(fgets(\STDIN));

	if (empty($input)) {
		continue;
	}

	$parts = preg_split('/\s+/', $input, 2);
	$command = strtolower($parts[0]);
	$args = $parts[1] ?? '';

	try {
		switch ($command) {
			case 'help':
				printHelp();

				break;
			case 'disburse':
				$loan->disburse();
				echo '✓ Loan disbursed: '.$amount->format()."\n";

				break;
			case 'days':
				$days = (int) $args;
				if ($days <= 0) {
					echo "Error: Days must be a positive number.\n";

					break;
				}
				$loan->advanceDays($days);
				echo "✓ Advanced {$days} day(s) forward.\n";
				$status = $loan->getStatus();
				echo '  Current date: '.formatDate($status['current_date'])."\n";

				break;
			case 'cycle':
				$cycles = !empty($args) ? (int) $args : 1;
				if ($cycles < 1) {
					echo "Error: Cycle count must be at least 1.\n";

					break;
				}

				$results = $loan->cycle($cycles);

				if ($cycles === 1) {
					$result = $results[0];
					echo "✓ Advanced {$result['days']} day(s) to 1st of next month.\n";
					echo '  Current date: '.formatDate($result['date'])."\n";
					if (!$result['interest']->isZero()) {
						echo '  Interest accrued: '.$result['interest']->format()."\n";
					}
				} else {
					$totalDays = array_sum(array_column($results, 'days'));
					$totalInterest = PoundSterling::zero();
					foreach ($results as $result) {
						$totalInterest = $totalInterest->add($result['interest']);
					}
					$lastResult = end($results);

					echo "✓ Completed {$cycles} billing cycle(s).\n";
					echo "  Total days advanced: {$totalDays}\n";
					echo '  Current date: '.formatDate($lastResult['date'])."\n";
					if (!$totalInterest->isZero()) {
						echo '  Total interest accrued: '.$totalInterest->format()."\n";
					}

					echo "\n  Cycle breakdown:\n";
					foreach ($results as $i => $result) {
						$cycleNum = $i + 1;
						echo "    Cycle {$cycleNum}: {$result['days']} days, ".$result['interest']->format()." interest\n";
					}
				}

				break;
			case 'accrue':
				$statusBefore = $loan->getStatus();
				$interestBefore = $statusBefore['interest']['accrued'];

				$loan->accrueInterest();

				$statusAfter = $loan->getStatus();
				$interestAfter = $statusAfter['interest']['accrued'];
				$accrued = $interestAfter->subtract($interestBefore);

				if (!$accrued->isZero()) {
					echo '✓ Interest accrued: '.$accrued->format()."\n";
				} else {
					echo "✓ No interest accrued (0 days passed or no outstanding principal).\n";
				}

				break;
			case 'fee':
				$feeAmount = PoundSterling::parse($args);
				if ($feeAmount->isZero()) {
					echo "Error: Fee amount must be positive.\n";

					break;
				}
				$loan->addFee($feeAmount);
				echo '✓ Fee added: '.$feeAmount->format()."\n";

				break;
			case 'repay':
				$repayAmount = PoundSterling::parse($args);
				if ($repayAmount->isZero()) {
					echo "Error: Repayment amount must be positive.\n";

					break;
				}
				$loan->repay($repayAmount);
				echo '✓ Repayment processed: '.$repayAmount->format()."\n";

				// Show breakdown
				$transactions = $loan->getTransactions();
				$lastTx = end($transactions);
				if (!$lastTx['fees']->isZero()) {
					echo '  → Fees:      '.$lastTx['fees']->format()."\n";
				}
				if (!$lastTx['interest']->isZero()) {
					echo '  → Interest:  '.$lastTx['interest']->format()."\n";
				}
				if (!$lastTx['principal']->isZero()) {
					echo '  → Principal: '.$lastTx['principal']->format()."\n";
				}
				if (!$lastTx['overpayment']->isZero()) {
					echo '  → Overpayment: '.$lastTx['overpayment']->format()."\n";
				}

				if ($loan->isFullyPaid()) {
					echo "\n";
					echo "🎉 Congratulations! The loan is fully paid off!\n";
					echo "\n";

					$status = $loan->getStatus();
					$totalRevenue = $status['interest']['paid']->add($status['fees']['paid']);
					$totalPaid = $status['principal']['paid']->add($totalRevenue)->add($status['overpayment']['balance']);
					$extraPaid = $totalRevenue;

					echo "Loan Summary:\n";
					echo '  Principal borrowed:      '.$status['principal']['original']->format()."\n";
					echo '  Total revenue collected: '.$totalRevenue->format()."\n";
					echo '    (Interest: '.$status['interest']['paid']->format().', Fees: '.$status['fees']['paid']->format().")\n";
					echo '  Extra paid by customer:  '.$extraPaid->format()."\n";
					echo '  Total paid by customer:  '.$totalPaid->format()."\n";
					if (!$status['overpayment']['balance']->isZero()) {
						echo '  Overpayment balance:     '.$status['overpayment']['balance']->format()."\n";
					}
					echo "\n";

					printStatus($loan);
					printTransactions($loan);
					$running = false;
				}

				break;
			case 'status':
				printStatus($loan);

				break;
			case 'transactions':
				printTransactions($loan);

				break;
			case 'exit':
			case 'quit':
				echo "Exiting loan simulator.\n";
				$running = false;

				break;
			default:
				echo "Unknown command: {$command}\n";
				echo "Type 'help' for available commands.\n";

				break;
		}
	} catch (RuntimeException $e) {
		echo 'Error: '.$e->getMessage()."\n";
	} catch (Exception $e) {
		echo 'Error: '.$e->getMessage()."\n";
	}
}

echo "\n";
echo "Goodbye!\n";
echo "\n";
