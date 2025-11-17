Feature: User Registration
  In order to use the weight tracking system
  As a new user
  I need to be able to register an account

  # This scenario runs on BOTH usecase and e2e suites
  # It verifies the same behavior at both layers
  @critical
  Scenario: Successfully register a new user
    When I register with email "john@example.com" and password "SecurePass123!"
    Then the user should be registered

  # This scenario runs on BOTH usecase and e2e suites
  Scenario: Cannot register with duplicate email
    Given a user exists with email "jane@example.com"
    When I register with email "jane@example.com" and password "SecurePass123!"
    Then registration should fail

  # This scenario runs ONLY on the e2e suite (HTTP-specific)
  @e2e
  Scenario: Registration returns proper HTTP status codes
    Given no user exists with email "api@example.com"
    When I register with email "api@example.com" and password "SecurePass123!"
    Then I should receive a 201 response

  # This scenario runs ONLY on the e2e suite (HTTP-specific)
  @e2e
  Scenario: Registration fails with invalid email format
    When I register with email "not-an-email" and password "SecurePass123!"
    Then I should receive a 422 response
