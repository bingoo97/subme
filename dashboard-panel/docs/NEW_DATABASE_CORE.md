# New Database Core

The project now has a clean target database schema in:

- [`dashboard-panel/database/v2/001_core_schema.sql`](/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/database/v2/001_core_schema.sql)
- [`dashboard-panel/database/v2/002_seed_defaults.sql`](/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/database/v2/002_seed_defaults.sql)
- [`dashboard-panel/database/v2/LEGACY_MAPPING.md`](/Users/bodzianek/CascadeProjects/RESELLER/reseller/dashboard-panel/database/v2/LEGACY_MAPPING.md)

## Product scope covered

- user accounts
- admin accounts and roles
- orders
- renewals
- live chat
- crypto assets
- crypto wallet address pool
- wallet assignment history
- crypto deposit requests
- crypto blockchain transaction matching
- bank account pool
- bank account assignment history
- bank transfer requests with manual approval
- content pages
- news
- email templates

## Why this is better than the old schema

- no multi-reseller complexity
- no mixed Polish/English naming
- no parallel duplicate order models as the main architecture
- explicit crypto wallet pool design
- built for safe future scaling across many separate installations

## Crypto wallet flow

The new model supports:

1. storing many wallet addresses per crypto asset
2. keeping a pool of free addresses
3. assigning a different address for every deposit if required
4. assigning multiple historical addresses to the same customer
5. matching real on-chain transactions back to a request and address
6. manual admin assignment only as the default operating mode
7. many different wallets and crypto assets per customer over time

## Bank transfer flow

The new model also supports:

1. storing many bank accounts in a reusable pool
2. manual assignment of a bank account to a customer
3. showing transfer details in the UI after the user selects bank transfer
4. creating a dedicated bank transfer request for one order or top-up
5. fully manual admin approval of the payment
6. using multiple different bank accounts for the same customer over time

## Manual admin workflow

This is now built around your exact process:

1. admin inserts wallet addresses into the database
2. admin picks a free wallet from the pool
3. admin manually assigns it to the customer
4. admin can create a dedicated deposit request for a specific order or top-up
5. admin can assign another wallet later, even for a different cryptocurrency
6. the database prevents one wallet from being active for two customers at once
7. admin can also assign a bank account in the same way
8. user receives the exact bank transfer data in the UI
9. admin confirms the bank payment manually after checking the transfer

## Recommended next implementation phase

1. create the new physical database from the new schema
2. write the legacy importer
3. switch the app module by module:
   - settings
   - customers
   - products
   - orders
   - crypto deposits
   - support chat
4. remove the old compatibility layer only after full migration
