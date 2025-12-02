# Flash-Sale Checkout
Laravel Interview Task — Flash-Sale Checkout

- Assumptions and invariants enforced:
    1- Product Management:
        - Products have a stock_quantity, reserved, and sold fields to track inventory.
        - A hold is a temporary reservation of stock for a product. The reserved field is updated when a hold is created and released when the hold expires.
    
    2- Hold Expiry:
        - Holds are assigned a hold_expires_at timestamp when created. If the hold is not processed before this time, it is considered expired, and the reserved stock is released back to the available stock.
        - The expiration check runs on a scheduled task (holds:release-expired) that processes holds marked as expired.
    
    3- Order Management:
        - Orders are linked to products and hold reservations. If an order is successfully paid via a webhook, it updates the status of the associated order from pending to paid.
        - When an order is created, it remains in a pending state for up to 5 minutes.
        - If the order is not paid within 5 minutes, its status is automatically updated to cancelled.
    
    4- Webhook Handling:
        - Webhook requests that update order statuses can be idempotent, ensuring that retrying the same request does not result in inconsistent data.
        - The webhook may receive success values (either true or false). If false, the order remains pending, and if true, the order is updated to paid.
    
    5- Concurrency and Race Conditions:
        -All critical operations, such as creating holds and processing webhook payments, ensure proper locking and transaction management to handle concurrency.
        - The system ensures that no overselling occurs by checking the availability before reserving stock.


- How to run the app and tests (must have Laravel 12, Composer, Redis, and PHP installed)
  1- Open xampp, start Apache and MySQL.
  2- run these commands in order:
      php artisan migrate
      composer run dev
      php artisan test

- Where to see logs/metrics:
  you can see logs/metrics in storage\logs\metrics.log file in this path
      
