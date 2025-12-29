Feature: Current User Info
  In order to personalize my experience
  As an authenticated user
  I need to be able to retrieve my account information

  Background:
    Given today's date is "December 15, 2025"
    And a user registered with:
      | email       | alice@example.com |
      | displayName | Alice Wonderland  |
      | dateOfBirth | 1990-05-15        |
      | password    | SecurePass123!    |

  Scenario: Successfully fetch current user info
    Given I am logged in as "alice@example.com" with password "SecurePass123!"
    When I request my user info
    Then I should receive my user info with:
      | email       | alice@example.com |
      | displayName | Alice Wonderland  |

  Scenario: User info includes registration timestamp in ISO 8601 format
    Given I am logged in as "alice@example.com" with password "SecurePass123!"
    When I request my user info
    Then my user info should include a valid registration timestamp

  Scenario: User info includes user ID
    Given I am logged in as "alice@example.com" with password "SecurePass123!"
    When I request my user info
    Then my user info should include my user ID

  @e2e
  Scenario: Cannot access user info without authentication
    When I request my user info without authentication
    Then I should receive a 401 Unauthorized error
