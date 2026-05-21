@mailchimp @registering
Feature: Mailchimp newsletter synchronization during customer registration
    In order to respect customer preferences and privacy
    As a visitor
    I want to be synced to Mailchimp only when I opt in to the newsletter

    Background:
        Given the store operates on a single channel in "United States"
        And on this channel account verification is not required
        And the channel is configured with Mailchimp audience "test-audience-id"

    Scenario: Customer registers without subscribing to the newsletter
        When I want to register a new account
        And I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the email as "john@example.com"
        And I specify the password as "Pa$$w0rd!"
        And I confirm this password
        And I register this account
        Then the customer should not be synced to Mailchimp

    Scenario: Customer registers and subscribes to the newsletter
        When I want to register a new account
        And I specify the first name as "Jane"
        And I specify the last name as "Doe"
        And I specify the email as "jane@example.com"
        And I specify the password as "Pa$$w0rd!"
        And I confirm this password
        And I subscribe to the newsletter
        And I register this account
        Then the customer should be synced to Mailchimp
