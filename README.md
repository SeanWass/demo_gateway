## Demo payment gateway

Welcome to the documentation for my payment gateway assessement. I will dive right into it.

### Database choice.

I chose to go with PostgreSQL over MySQL as it is the preferred database choice for most payment payment gateways.
It has a number of other advantages as well, such as better scaling under high load(no table locks etc). It also handles decimals
and currency more accurately with regards to precision and rounding.

Within the database, I am storing all payments, payment events, retry attempts and refunds.
All migrations are in place.

### Reusable Payment logic

I created a payment service that is called from the PaymentController.php. This service determines which gateway to use. By doing it this way, many different gateways(such as payfast, stripe etc) should be able to be integrated seamlessly. I also wrapped the different
actions(authorise, capture, void, refund) with 2 wrappers, namely a idempotency wrapper and a retry wrapper. This should ensure that from our side everything remains the same. 

For the retry wrapper, I created an array of different strategies to be used for different exceptions that might occur. This way you can customise what retry strategy to use(if any) for different exceptions that are thrown.

### Payment flow
Within the payment model, I set up functions that do checks to ensure that only valid payment transistions are allowed.

