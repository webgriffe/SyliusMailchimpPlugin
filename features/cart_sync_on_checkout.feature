@mailchimp @ui
Feature: Cart synchronization with Mailchimp
    In order to target customers with abandoned cart campaigns
    As an e-commerce store
    I want carts to be synchronized to Mailchimp when items are added, updated or removed

    Background:
        Given the store operates on a single channel in "United States"
        And the channel is configured with Mailchimp audience "test-audience-id"
        And the store has a product "T-Shirt" priced at "$29.99"

    Scenario: Cart is created in Mailchimp when a customer adds a product
        When I add product "T-Shirt" to the cart
        Then the cart should be synced to Mailchimp
        And the last cart sync should have 1 line

    Scenario: Cart is updated in Mailchimp when a customer adds the same product again
        Given I have product "T-Shirt" added to the cart
        When I add product "T-Shirt" to the cart
        Then the cart should be synced to Mailchimp
        And the last cart sync should have 1 line
        And the last cart sync line should have quantity 2

    @mink:chromedriver
    Scenario: Cart is updated in Mailchimp when a customer removes an item
        Given I have product "T-Shirt" added to the cart
        When I remove product "T-Shirt" from the cart
        Then the cart should be synced to Mailchimp
        And the last cart sync should have 0 lines

    @mink:chromedriver
    Scenario: Cart is removed from Mailchimp when a customer clears the cart
        Given I have product "T-Shirt" added to the cart
        When I clear my cart
        Then the cart should be removed from Mailchimp
