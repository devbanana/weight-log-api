Feature: User Registration
  In order to use the weight tracking system
  As a new user
  I need to be able to register an account

  # This scenario runs on ALL three suites: domain, application, and api
  # It verifies the same behavior at all layers
  @critical
  Scenario: Successfully register a new user
    Given no user exists with email "john@example.com"
    When I register with email "john@example.com" and password "SecurePass123!"
    Then the user should be registered

  # This scenario runs on domain and application suites only (not full API)
  Scenario: Cannot register with duplicate email
    Given a user exists with email "jane@example.com"
    When I register with email "jane@example.com" and password "SecurePass123!"
    Then registration should fail

  # This scenario runs ONLY on the API suite (infrastructure-specific)
  @api
  Scenario: Registration returns proper HTTP status codes
    Given no user exists with email "api@example.com"
    When I register with email "api@example.com" and password "SecurePass123!"
    Then I should receive a 201 response

  # This scenario runs ONLY on the API suite
  @api
  Scenario: Registration fails with invalid email format
    When I register with email "not-an-email" and password "SecurePass123!"
    Then I should receive a 422 response
