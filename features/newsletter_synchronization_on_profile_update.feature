@mailchimp @profile_update
Feature: Mailchimp newsletter synchronization on customer profile update
    In order to receive newsletters at my current email address
    As a customer
    I want my Mailchimp member data to stay in sync when I edit my profile

    Background:
        Given the store operates on a single channel in "United States"
        And the channel is configured with Mailchimp audience "test-audience-id"
        And there is a user "john@example.com" identified by "Pa$$w0rd!"
        And I am logged in as "john@example.com"

    Scenario: Subscribed customer updates a profile field
        Given the customer "john@example.com" is subscribed to the newsletter
        And the customer "john@example.com" is already synced to Mailchimp
        When I want to modify my profile
        And I specify the first name as "Johnny"
        And I specify the last name as "Doe"
        And I save my changes
        Then I should be notified that it has been successfully edited
        And the customer should be synced to Mailchimp
        And no Mailchimp member should have been removed

    Scenario: Subscribed customer changes their email address
        Given the customer "john@example.com" is subscribed to the newsletter
        And the customer "john@example.com" is already synced to Mailchimp
        When I want to modify my profile
        And I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the customer email as "john.new@example.com"
        And I save my changes
        Then I should be notified that it has been successfully edited
        And the Mailchimp member with email "john@example.com" should have been removed
        And the Mailchimp ecommerce customer should have been removed
        And the customer with email "john.new@example.com" should be synced to Mailchimp

    Scenario: Non-subscribed customer updates their profile
        When I want to modify my profile
        And I specify the first name as "Johnny"
        And I specify the last name as "Doe"
        And I save my changes
        Then I should be notified that it has been successfully edited
        And the customer should not be synced to Mailchimp
        And no Mailchimp member should have been removed

    Scenario: Non-subscribed customer changes their email address
        When I want to modify my profile
        And I specify the first name as "John"
        And I specify the last name as "Doe"
        And I specify the customer email as "john.new@example.com"
        And I save my changes
        Then I should be notified that it has been successfully edited
        And the Mailchimp member with email "john@example.com" should have been removed
        And the Mailchimp ecommerce customer should have been removed
        And the customer should not be synced to Mailchimp
