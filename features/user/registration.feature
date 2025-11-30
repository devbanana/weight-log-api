Feature: User Registration
  In order to use the weight tracking system
  As a new user
  I need to be able to register an account

  # This scenario runs on BOTH usecase and e2e suites
  # It verifies the same behavior at both layers
  Scenario: Successfully register a new user
    When I register with:
      | email         | bob@example.com   |
      | date of birth | 20 years ago      |
      | display name  | Bob               |
      | password      | SecurePass123!    |
    Then I should be registered

  # This scenario runs on BOTH usecase and e2e suites
  Scenario: Cannot register with duplicate email
    Given a user exists with email "bob@example.com"
    When I register with:
      | email         | bob@example.com   |
      | date of birth | 20 years ago      |
      | display name  | Bob               |
      | password      | SecurePass123!    |
    Then registration should fail

  @e2e
  Scenario: Registration returns proper HTTP status codes
    Given no user exists with email "api@example.com"
    When I register with:
      | email         | bob@example.com   |
      | date of birth | 20 years ago      |
      | display name  | Bob               |
      | password      | SecurePass123!    |
    Then I should receive a 201 response

  @e2e
  Scenario: Registration fails with invalid email format
    When I register with email "not-an-email" and password "SecurePass123!"
    Then I should receive a 422 response
