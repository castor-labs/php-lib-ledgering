Feature: Transfer Operations
  As a ledger system
  I need to support various transfer operations
  So that I can accurately track financial transactions following TigerBeetle semantics

  Background:
    Given a ledger with code 1
    And an account "debit-account" with:
      | ledger | 1   |
      | code   | 100 |
    And an account "credit-account" with:
      | ledger | 1   |
      | code   | 200 |

  # ============================================================================
  # Basic Transfer Operations
  # ============================================================================

  Scenario: Create a simple transfer between accounts
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
      | ledger         | 1              |
      | code           | 1              |
    Then the transfer should be created successfully
    And account "debit-account" should have debits_posted of 1000
    And account "credit-account" should have credits_posted of 1000

  Scenario: Prevent duplicate transfer creation (idempotency)
    Given a transfer "transfer-1" exists with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    When I create a transfer "transfer-1" with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    Then the transfer creation should fail with "already exists"

  # ============================================================================
  # Transfer Validation
  # ============================================================================

  Scenario: Reject transfer with same debit and credit account
    When I create a transfer with:
      | debit_account  | debit-account |
      | credit_account | debit-account |
      | amount         | 1000          |
    Then the transfer creation should fail with "debit and credit accounts must be different"

  Scenario: Reject transfer with zero amount
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 0              |
    Then the transfer creation should fail with "amount must be greater than zero"

  Scenario: Reject transfer when debit account does not exist
    When I create a transfer with:
      | debit_account  | non-existent-account |
      | credit_account | credit-account       |
      | amount         | 1000                 |
    Then the transfer creation should fail with "debit account not found"

  Scenario: Reject transfer when credit account does not exist
    When I create a transfer with:
      | debit_account  | debit-account        |
      | credit_account | non-existent-account |
      | amount         | 1000                 |
    Then the transfer creation should fail with "credit account not found"

  Scenario: Reject transfer when ledgers do not match
    Given an account "other-ledger-account" with:
      | ledger | 2   |
      | code   | 300 |
    When I create a transfer with:
      | debit_account  | debit-account        |
      | credit_account | other-ledger-account |
      | amount         | 1000                 |
      | ledger         | 1                    |
    Then the transfer creation should fail with "accounts must be in the same ledger"

  # ============================================================================
  # Balance Constraints
  # ============================================================================

  Scenario: Enforce DEBITS_MUST_NOT_EXCEED_CREDITS constraint
    Given account "debit-account" has flag "DEBITS_MUST_NOT_EXCEED_CREDITS"
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    Then the transfer creation should fail with "debits would exceed credits"

  Scenario: Allow debit when credits exist with DEBITS_MUST_NOT_EXCEED_CREDITS
    Given account "debit-account" has flag "DEBITS_MUST_NOT_EXCEED_CREDITS"
    And account "debit-account" has credits_posted of 2000
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    Then the transfer should be created successfully
    And account "debit-account" should have debits_posted of 1000
    And account "debit-account" should have credits_posted of 2000

  Scenario: Enforce CREDITS_MUST_NOT_EXCEED_DEBITS constraint
    Given account "credit-account" has flag "CREDITS_MUST_NOT_EXCEED_DEBITS"
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    Then the transfer creation should fail with "credits would exceed debits"

  Scenario: Reject transfer to closed account
    Given account "debit-account" has flag "CLOSED"
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    Then the transfer creation should fail with "account is closed"

  # ============================================================================
  # Pending Transfers (Two-Phase Transfers)
  # ============================================================================

  Scenario: Create a pending transfer
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
      | flags          | PENDING        |
    Then the transfer should be created successfully
    And account "debit-account" should have debits_pending of 1000
    And account "debit-account" should have debits_posted of 0
    And account "credit-account" should have credits_pending of 1000
    And account "credit-account" should have credits_posted of 0

  Scenario: Post a pending transfer
    Given a pending transfer "pending-1" exists with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    When I create a transfer with:
      | pending_id | pending-1     |
      | flags      | POST_PENDING  |
    Then the transfer should be created successfully
    And account "debit-account" should have debits_posted of 1000


    And account "debit-account" should have debits_pending of 0
    And account "credit-account" should have credits_posted of 1000
    And account "credit-account" should have credits_pending of 0

  Scenario: Void a pending transfer
    Given a pending transfer "pending-1" exists with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    When I create a transfer with:
      | pending_id | pending-1     |
      | flags      | VOID_PENDING  |
    Then the transfer should be created successfully
    And account "debit-account" should have debits_pending of 0
    And account "debit-account" should have debits_posted of 0
    And account "credit-account" should have credits_pending of 0
    And account "credit-account" should have credits_posted of 0

  Scenario: POST_PENDING requires a pending_id
    When I create a transfer with:
      | flags | POST_PENDING |
    Then the transfer creation should fail with "pending_id is required"

  Scenario: POST_PENDING with non-existent pending transfer
    When I create a transfer with:
      | pending_id | non-existent-transfer |
      | flags      | POST_PENDING          |
    Then the transfer creation should fail with "pending transfer not found"

  Scenario: Enforce balance constraints on pending transfers
    Given account "debit-account" has flag "DEBITS_MUST_NOT_EXCEED_CREDITS"
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
      | flags          | PENDING        |
    Then the transfer creation should fail with "debits would exceed credits"

  # ============================================================================
  # Balancing Transfers
  # ============================================================================

  Scenario: Balancing debit transfer calculates amount automatically
    Given account "debit-account" has:
      | debits_posted  | 1500 |
      | credits_posted | 2000 |
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | flags          | BALANCING_DEBIT |
    Then the transfer should be created successfully
    And the transfer amount should be 1500
    And account "debit-account" should have debits_posted of 3000
    And account "debit-account" should have credits_posted of 2000
    And account "credit-account" should have credits_posted of 1500

  Scenario: Balancing credit transfer calculates amount automatically
    Given account "credit-account" has:
      | debits_posted  | 2000 |
      | credits_posted | 3000 |
    When I create a transfer with:
      | debit_account  | debit-account   |
      | credit_account | credit-account  |
      | flags          | BALANCING_CREDIT |
    Then the transfer should be created successfully
    And the transfer amount should be 2000
    And account "credit-account" should have debits_posted of 4000
    And account "credit-account" should have credits_posted of 3000
    And account "debit-account" should have debits_posted of 2000

  # ============================================================================
  # Closing Transfers
  # ============================================================================

  Scenario: Closing debit transfer moves entire balance
    Given account "debit-account" has:
      | debits_posted  | 1500 |
      | credits_posted | 3000 |
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | flags          | CLOSING_DEBIT,PENDING |
    Then the transfer should be created successfully
    And the transfer amount should be 3000
    And account "debit-account" should have credits_posted of 3000
    And account "debit-account" should have debits_pending of 3000

  Scenario: CLOSING_DEBIT requires PENDING flag
    Given account "debit-account" has:
      | debits_posted  | 1500 |
      | credits_posted | 3000 |
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | flags          | CLOSING_DEBIT  |
    Then the transfer creation should fail with "CLOSING_DEBIT requires PENDING flag"

  # ============================================================================
  # Balance History
  # ============================================================================

  Scenario: Record balance history when HISTORY flag is set
    Given account "debit-account" has flag "HISTORY"
    And account "credit-account" has flag "HISTORY"
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    Then the transfer should be created successfully
    And account "debit-account" should have 1 balance history entry
    And account "credit-account" should have 1 balance history entry

  Scenario: Balance history shares timestamp with transfer
    Given account "debit-account" has flag "HISTORY"
    And account "credit-account" has flag "HISTORY"
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    Then the transfer should be created successfully
    And the debit account balance history timestamp should match the transfer timestamp
    And the credit account balance history timestamp should match the transfer timestamp

  Scenario: No balance history when HISTORY flag is not set
    When I create a transfer with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
    Then the transfer should be created successfully
    And account "debit-account" should have 0 balance history entries
    And account "credit-account" should have 0 balance history entries

  # ============================================================================
  # Complex Scenarios
  # ============================================================================

  Scenario: Multiple transfers update balances correctly
    When I create transfers:
      | id          | debit_account  | credit_account | amount |
      | transfer-1  | debit-account  | credit-account | 1000   |
      | transfer-2  | debit-account  | credit-account | 500    |
      | transfer-3  | credit-account | debit-account  | 300    |
    Then all transfers should be created successfully
    And account "debit-account" should have debits_posted of 1500
    And account "debit-account" should have credits_posted of 300
    And account "credit-account" should have credits_posted of 1500
    And account "credit-account" should have debits_posted of 300

  Scenario: Pending transfer timeout (conceptual - implementation specific)
    Given a pending transfer "pending-1" exists with:
      | debit_account  | debit-account  |
      | credit_account | credit-account |
      | amount         | 1000           |
      | timeout        | 60             |
    When 61 seconds have passed
    Then the pending transfer "pending-1" should be expired
    And attempting to post "pending-1" should fail with "pending transfer expired"
