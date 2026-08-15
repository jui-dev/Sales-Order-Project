# Sales Order System

An inventory and accounting system for a distribution business that holds stock in more than one place. It keeps what is physically on the shelf and what the books say about it from drifting apart.

## Why it exists

Every time goods move, two things change: something physical, and something financial. Receiving a delivery raises stock *and* creates a debt to the vendor. Shipping an order lowers stock *and* creates an invoice *and* moves inventory value into cost of sales.

Track those separately — a stock sheet here, an accounts file there — and they disagree within days. You stop trusting the stock figure, and the books stop describing what you actually own.

So both sides are derived from the same events rather than maintained side by side. One action records one movement, and stock, payables, receivables and the ledger all follow from it.

## Core concepts

Four ideas explain most of the system.

**Direction names the feature.** Goods move between location types, and that direction is how the app is organised: Vendor → Warehouse, Warehouse → Retailer, Warehouse → Customer, Retailer → Customer.

**There is a ledger and there is a balance.** `stock_transactions` is append-only — every physical movement writes a typed row. `product_stocks` holds the current per-location quantity. `products.available_stocks` is a cache of `SUM(quantity) − SUM(reserved_quantity)`, recomputed automatically. A balance is never changed without a ledger row explaining it, so any stock figure can be traced back to the movements that produced it.

**Returns are not a separate table.** A return is a `stock_transactions` row typed `customer_return`, `vendor_return`, or `retailer_return` — one engine, three behaviours.

**Reserving stock and moving it are different events.** Confirming an order raises `reserved_quantity`: the goods are spoken for but still on the shelf. The physical decrement happens later, when the picking list completes. Keeping these apart is what stops two orders from selling the same unit.

## What it does

**Buying — purchase to payment**
Record a supply against a vendor, receive the goods, get billed, pay the bill. Stock only becomes real at the receiving step; recording a supply notes what was ordered and nothing more. Posting a goods-receipt note raises the stock and auto-creates the supplier bill.

**Selling — order to cash**
Take a customer order, confirm it (reserving stock), pick and dispatch it, invoice, take payment. Completing the picking list is what moves stock, closes the order and generates the invoice.

**Returns**
Customer, vendor and retailer returns run through one engine. Approving a return moves the stock and raises a credit or debit note; retailer returns are an internal move with no financial side. Quantities are validated against the original document net of anything already returned, so nothing can be over-returned.

**Accounting**
Double-entry ledger with a chart of accounts and journal entries. Invoices, payments, supplier bills and returns post their own balanced entries. Unbalanced entries are refused at the service layer. Credit and debit notes post *reversing* entries linked to the entry they offset, so history is never rewritten.

**Reporting**
Trial balance, income statement, balance sheet, cash flow and daily profit, plus a KPI dashboard and a filterable audit trail. Invoices and reports export to PDF.

Full flow-by-flow detail, including known gaps, is in [docs/FEATURES.md](docs/FEATURES.md).

## Architecture

Controller → Service → Model, with **Eloquent observers carrying the side effects**.

Roughly 28 service classes hold the business logic and 10 observers act as an event-driven state machine. Posting a goods-receipt note, for instance, writes stock-in rows; observers then raise the warehouse balance, recompute the cached availability figure, and separately create a draft supplier bill.

The reason is single-responsibility for side effects. Stock has to move whether the trigger was a picking screen, a status change or a return approval — putting the movement in one observer means those paths cannot diverge. The tradeoff is that the chain is not visible from the controller; you have to know the observers exist.

Every stock movement runs inside a database transaction with row locking. Workflow stage logic lives in one place per flow, under [`app/Support/`](app/Support/).

## Tech stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12, PHP 8.2 |
| Database | MySQL |
| Views | Blade, server-rendered |
| UI | Bootstrap 5.3, jQuery, DataTables |
| Charts | Chart.js |
| PDF | DomPDF |
| Tests | PHPUnit |
| Docs | MkDocs Material |

Server-rendered rather than an SPA: this is an internal back-office tool with no offline or mobile requirement, so an API layer and a JS build pipeline would have added work without adding anything a user would notice.

## Getting started

Requires PHP 8.2+, Composer and MySQL.

```bash
git clone <repository-url>
cd Sales-Order-Project
composer install

cp .env.example .env
php artisan key:generate
# set DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env

php artisan migrate --seed
php artisan serve
```

Seeding loads the chart of accounts, product categories, warehouses, retailers and sample reference data.

## Tests

```bash
php artisan test
```

23 test classes, weighted toward the places where a bug is expensive and silent rather than spread evenly: stock calculation, return posting, journal reversal and gross-profit maths.

## Status

A working project, still being built on.

**There is no authentication** — no login, roles or permissions. It is built as a single-operator internal tool, and adding auth would be the first step toward anything wider. One consequence worth knowing: the audit trail records actions but cannot meaningfully attribute them.

Some screens are further along than others. [docs/FEATURES.md](docs/FEATURES.md) §12–13 lists what is stubbed or known to be wrong; it is kept honest deliberately, so read it before relying on any single flow.
