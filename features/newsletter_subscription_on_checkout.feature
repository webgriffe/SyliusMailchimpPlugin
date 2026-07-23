@mailchimp @order
Feature: Newsletter subscription from checkout complete step
    In order to grow the newsletter audience
    As an e-commerce store
    I want customers who opt in on the checkout complete step to be subscribed to the newsletter

    Background:
        Given the store operates on a single channel in "United States"
        And the channel is configured with Mailchimp audience "test-audience-id"
        And the store has a product "T-Shirt" priced at "$29.99"
        And the store ships everywhere for Free
        And the store allows paying Offline

    @ui @javascript
    Scenario: Guest customer is subscribed to the newsletter after checking the checkbox on checkout complete
        Given I have product "T-Shirt" added to the cart
        And I addressed the cart with email "guest-newsletter@example.com"
        And I chose "Free" shipping method and "Offline" payment method
        When I check the newsletter subscription checkbox
        And I confirm my order
        Then I should see the thank you page
        And the customer should be subscribed to the newsletter

    @ui @javascript
    Scenario: Logged in customer is subscribed to the newsletter after checking the checkbox on checkout complete
        Given there is a user "customer-newsletter@example.com" identified by "Pa$$w0rd!"
        And I am logged in as "customer-newsletter@example.com"
        And I have product "T-Shirt" added to the cart
        And I addressed the cart
        And I chose "Free" shipping method and "Offline" payment method
        When I check the newsletter subscription checkbox
        And I confirm my order
        Then I should see the thank you page
        And the customer should be subscribed to the newsletter
