Feature: User Registration
  In order to use the weight tracking system
  As a new user
  I need to be able to register an account

  Scenario: Successfully register a new user
    When I register with email "bob@example.com" and password "SecurePass123!"
    Then I should be registered

  Scenario: Cannot register with duplicate email
    Given a user exists with email "existing@example.com" and password "SecurePass123!"
    When I register with email "existing@example.com" and password "AnotherPass456!"
    Then registration should fail due to duplicate email

  Scenario: Cannot register with duplicate email in different case
    Given a user exists with email "test@example.com" and password "SecurePass123!"
    When I register with email "TEST@EXAMPLE.COM" and password "AnotherPass456!"
    Then registration should fail due to duplicate email

  Scenario: Cannot register with invalid email format
    When I register with email "not-an-email" and password "SecurePass123!"
    Then registration should fail due to invalid email format

  Scenario: Cannot register with password too short
    When I register with email "bob@example.com" and password "short"
    Then registration should fail due to invalid password
