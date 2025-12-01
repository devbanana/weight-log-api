Feature: User Registration
  In order to use the weight tracking system
  As a new user
  I need to be able to register an account

  Scenario: Successfully register a new user
    When I register with email "bob@example.com"
    Then I should be registered

  Scenario: Cannot register with duplicate email
    Given a user exists with email "existing@example.com"
    When I register with email "existing@example.com"
    Then registration should fail due to duplicate email

  Scenario: Cannot register with duplicate email in different case
    Given a user exists with email "test@example.com"
    When I register with email "TEST@EXAMPLE.COM"
    Then registration should fail due to duplicate email

  Scenario: Cannot register with invalid email format
    When I register with email "not-an-email"
    Then registration should fail due to invalid email format
