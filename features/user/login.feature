Feature: User Login
  In order to access my weight tracking data
  As a registered user
  I need to be able to log in to my account

  Background:
    Given a user exists with email "bob@example.com" and password "SecurePass123!"

  Scenario: Successfully log in with valid credentials
    When I log in with email "bob@example.com" and password "SecurePass123!"
    Then I should be logged in

  Scenario: Email is case-insensitive for login
    When I log in with email "BOB@EXAMPLE.COM" and password "SecurePass123!"
    Then I should be logged in

  @e2e
  Scenario: Successful login returns authentication token
    When I log in with email "bob@example.com" and password "SecurePass123!"
    Then I should receive an authentication token

  Scenario: Cannot log in with wrong password
    When I log in with email "bob@example.com" and password "WrongPassword!"
    Then login should fail due to invalid credentials

  Scenario: Cannot log in with non-existent email
    When I log in with email "nonexistent@example.com" and password "SecurePass123!"
    Then login should fail due to invalid credentials

  Scenario: Cannot log in with invalid email format
    When I log in with email "not-an-email" and password "SecurePass123!"
    Then login should fail due to invalid email format

  Scenario: Cannot log in with empty email
    When I log in with email "" and password "SecurePass123!"
    Then login should fail due to invalid email format

  Scenario: Cannot log in with empty password
    When I log in with email "bob@example.com" and password ""
    Then login should fail due to invalid password
