Feature: User Registration
  In order to use the weight tracking system
  As a new user
  I need to be able to register an account

  Scenario: Successfully register a new user
    When I register with:
      | email       | bob@example.com |
      | dateOfBirth | 1990-05-15      |
      | displayName | Bob Bobbington  |
      | password    | SecurePass123!  |
    Then I should be registered

  Scenario: Cannot register with duplicate email
    Given a user exists with email "existing@example.com"
    When I register with:
      | email       | existing@example.com |
      | dateOfBirth | 1990-05-15           |
      | displayName | Another User         |
      | password    | AnotherPass456!      |
    Then registration should fail due to duplicate email

  Scenario: Cannot register with duplicate email in different case
    Given a user exists with email "test@example.com"
    When I register with:
      | email       | TEST@EXAMPLE.COM |
      | dateOfBirth | 1990-05-15       |
      | displayName | Test User        |
      | password    | AnotherPass456!  |
    Then registration should fail due to duplicate email

  Scenario: Cannot register with invalid email format
    When I register with:
      | email       | not-an-email   |
      | dateOfBirth | 1990-05-15     |
      | displayName | Bob Bobbington |
      | password    | SecurePass123! |
    Then registration should fail due to invalid email format

  Scenario: Cannot register with password too short
    When I register with:
      | email       | bob@example.com |
      | dateOfBirth | 1990-05-15      |
      | displayName | Bob Bobbington  |
      | password    | short           |
    Then registration should fail due to invalid password

  Scenario: Cannot register if under 18 years old
    Given today's date is "December 12, 2025"
    When I register with:
      | email       | young@example.com |
      | dateOfBirth | 2010-06-15        |
      | displayName | Young User        |
      | password    | SecurePass123!    |
    Then registration should fail because user is under 18

  Scenario: Can register if exactly 18 years old
    Given today's date is "December 12, 2025"
    When I register with:
      | email       | adult@example.com |
      | dateOfBirth | 2007-12-12        |
      | displayName | Just Adult        |
      | password    | SecurePass123!    |
    Then I should be registered

  Scenario: Cannot register with empty date of birth
    When I register with:
      | email       | bob@example.com |
      | dateOfBirth |                 |
      | displayName | Bob Bobbington  |
      | password    | SecurePass123!  |
    Then registration should fail due to invalid date of birth

  Scenario: Cannot register with invalid date of birth format
    When I register with:
      | email       | bob@example.com |
      | dateOfBirth | invalid-date    |
      | displayName | Bob Bobbington  |
      | password    | SecurePass123!  |
    Then registration should fail due to invalid date of birth

  Scenario: Cannot register with future date of birth
    When I register with:
      | email       | bob@example.com |
      | dateOfBirth | 2030-01-01      |
      | displayName | Bob Bobbington  |
      | password    | SecurePass123!  |
    Then registration should fail because date of birth is in the future

  Scenario: Cannot register with empty display name
    When I register with:
      | email       | bob@example.com |
      | dateOfBirth | 1990-05-15      |
      | displayName |                 |
      | password    | SecurePass123!  |
    Then registration should fail due to invalid display name

  Scenario: Cannot register with whitespace-only display name
    When I register with a whitespace-only display name
    Then registration should fail due to invalid display name

  Scenario: Cannot register with display name exceeding 50 characters
    When I register with:
      | email       | bob@example.com                                                  |
      | dateOfBirth | 1990-05-15                                                       |
      | displayName | This is a very long display name that exceeds fifty characters!! |
      | password    | SecurePass123!                                                   |
    Then registration should fail due to invalid display name
