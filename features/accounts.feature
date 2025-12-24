Feature: Account Operations
  As a ledger system
  I need to support account creation and management
  So that I can track balances and enforce constraints

  Background:
    Given a ledger with code 1

  # ============================================================================
  # Account Creation
  # ============================================================================

  Scenario: Create a basic account
    When I create an account with:
      | ledger | 1   |
      | code   | 100 |
    Then the account should be created successfully
    And the account should have debits_posted of 0
    And the account should have credits_posted of 0
    And the account should have debits_pending of 0
    And the account should have credits_pending of 0

  Scenario: Prevent duplicate account creation (idempotency)
    Given an account "account-1" exists with:
      | ledger | 1   |
      | code   | 100 |
    When I create an account "account-1" with:
      | ledger | 1   |
      | code   | 100 |
    Then the account creation should fail with "already exists"

  Scenario: Create account with external identifiers
    When I create an account with:
      | ledger      | 1                    |
      | code        | 100                  |
      | external_id | customer-12345       |
    Then the account should be created successfully
    And the account external_id should be "customer-12345"

  # ============================================================================
  # Account Flags
  # ============================================================================

  Scenario: Create account with DEBITS_MUST_NOT_EXCEED_CREDITS flag
    When I create an account with:
      | ledger | 1                                |
      | code   | 100                              |
      | flags  | DEBITS_MUST_NOT_EXCEED_CREDITS   |
    Then the account should be created successfully
    And the account should have flag "DEBITS_MUST_NOT_EXCEED_CREDITS"

  Scenario: Create account with CREDITS_MUST_NOT_EXCEED_DEBITS flag
    When I create an account with:
      | ledger | 1                                |
      | code   | 100                              |
      | flags  | CREDITS_MUST_NOT_EXCEED_DEBITS   |
    Then the account should be created successfully
    And the account should have flag "CREDITS_MUST_NOT_EXCEED_DEBITS"

  Scenario: Create account with HISTORY flag for balance tracking
    When I create an account with:
      | ledger | 1       |
      | code   | 100     |
      | flags  | HISTORY |
    Then the account should be created successfully
    And the account should have flag "HISTORY"

  Scenario: Create account with CLOSED flag
    When I create an account with:
      | ledger | 1      |
      | code   | 100    |
      | flags  | CLOSED |
    Then the account should be created successfully
    And the account should have flag "CLOSED"

  Scenario: Create account with multiple flags
    When I create an account with:
      | ledger | 1                                              |
      | code   | 100                                            |
      | flags  | DEBITS_MUST_NOT_EXCEED_CREDITS,HISTORY,CLOSED  |
    Then the account should be created successfully
    And the account should have flag "DEBITS_MUST_NOT_EXCEED_CREDITS"
    And the account should have flag "HISTORY"
    And the account should have flag "CLOSED"

  Scenario: Reject mutually exclusive flags
    When I create an account with:
      | ledger | 1                                                                |
      | code   | 100                                                              |
      | flags  | DEBITS_MUST_NOT_EXCEED_CREDITS,CREDITS_MUST_NOT_EXCEED_DEBITS   |
    Then the account creation should fail with "mutually exclusive flags"

  # ============================================================================
  # Account Codes and Ledgers
  # ============================================================================

  Scenario: Create accounts with different codes in same ledger
    When I create accounts:
      | id        | ledger | code |
      | account-1 | 1      | 100  |
      | account-2 | 1      | 200  |
      | account-3 | 1      | 300  |
    Then all accounts should be created successfully

  Scenario: Create accounts in different ledgers
    When I create accounts:
      | id        | ledger | code |
      | account-1 | 1      | 100  |
      | account-2 | 2      | 100  |
      | account-3 | 3      | 100  |
    Then all accounts should be created successfully

  Scenario: Account codes are used for categorization
    When I create an account with:
      | ledger | 1   |
      | code   | 100 |
    Then the account code should be 100
    And the account can be queried by code 100

  # ============================================================================
  # Balance Queries
  # ============================================================================

  Scenario: Query account balance after transfers
    Given an account "account-1" exists with:
      | ledger | 1   |
      | code   | 100 |
    And an account "account-2" exists with:
      | ledger | 1   |
      | code   | 200 |
    When I create a transfer with:
      | debit_account  | account-1 |
      | credit_account | account-2 |
      | amount         | 1000      |
    Then account "account-1" should have debits_posted of 1000
    And account "account-2" should have credits_posted of 1000

  Scenario: Query account with pending balances
    Given an account "account-1" exists with:
      | ledger | 1   |
      | code   | 100 |
    And an account "account-2" exists with:
      | ledger | 1   |
      | code   | 200 |
    When I create a pending transfer with:
      | debit_account  | account-1 |
      | credit_account | account-2 |
      | amount         | 1000      |
    Then account "account-1" should have debits_pending of 1000
    And account "account-1" should have debits_posted of 0
    And account "account-2" should have credits_pending of 1000
    And account "account-2" should have credits_posted of 0

