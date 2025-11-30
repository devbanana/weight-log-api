Feature: User Registration
  In order to use the weight tracking system
  As a new user
  I need to be able to register an account

  # This scenario runs on BOTH usecase and e2e suites
  # It verifies the same behavior at both layers
  Scenario: Successfully register a new user
    When I register with email "bob@example.com"
    Then I should be registered

  # This scenario runs on BOTH usecase and e2e suites
  Scenario: Cannot register with duplicate email
    Given a user exists with email "existing@example.com"
    When I register with email "existing@example.com"
    Then registration should fail

  @e2e
  Scenario: Registration returns 201 Created on success
    When I register with email "api@example.com"
    Then I should receive a 201 response

  @e2e
  Scenario: Registration fails with invalid email format
    When I register with email "not-an-email"
    Then I should receive a 422 response

  @e2e
  Scenario: Registration returns 409 for duplicate email
    Given a user exists with email "duplicate@example.com"
    When I register with email "duplicate@example.com"
    Then I should receive a 409 response
