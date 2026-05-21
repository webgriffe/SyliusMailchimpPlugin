@mailchimp @order
Feature: Order synchronization with Mailchimp
    In order to track sales and converted carts
    As an e-commerce store
    I want orders to be synchronized to Mailchimp when checkout is completed

    Background:
        Given the store operates on a single channel in "United States"
        And the channel is configured with Mailchimp audience "test-audience-id"
        And the store has a product "T-Shirt" priced at "$29.99"
        And the store ships everywhere for Free
        And the store allows paying Offline
        And there is a user "customer@example.com" identified by "Pa$$w0rd!"
        And I am logged in as "customer@example.com"

    Scenario: Order is created in Mailchimp when checkout is completed
        Given I have product "T-Shirt" added to the cart
        And the cart has been synced to Mailchimp
        And I addressed the cart
        And I chose "Free" shipping method and "Offline" payment method
        When I confirm my order
        Then I should see the thank you page
        Then the order should be synced to Mailchimp
        And the last order sync should have 1 line
        And the last order sync should reference the synced cart
