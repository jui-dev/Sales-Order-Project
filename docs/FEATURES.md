# Sales Order System — Features & Flows

A functional map of the system: what it does, and how each feature flows from the user's first click to the final side effect on stock and the accounting ledger.

**Stack:** Laravel 12 · PHP 8.2 · Blade + Bootstrap 5 (CDN) · DataTables · Chart.js · DomPDF
**Architecture:** Controller → Service → Model, with Eloquent **Observers** carrying most of the state machine. A meaningful amount of business logic also lives directly in `routes/web.php` closures.

> **Read this first:** §12 lists features that appear in the UI but are **not functional**, and §13 lists known correctness issues. Treat §1–§11 as "how it is meant to work"; check §12/§13 before relying on any single flow.

---

## Contents

| # | Feature area | Status |
|---|---|---|
| 1 | [Dashboard](#1-dashboard) | Working |
| 2 | [Products & Categories](#2-products--categories) | Working |
| 3 | [Customers & Vendors](#3-customers--vendors) | Working |
| 4 | [Supplies](#4-supplies) | Working — placeholder routes removed |
| 5 | [Goods Receipt Notes (GRN)](#5-goods-receipt-notes-grn) | Working — the main stock-in path |
| 6 | [Supplier Bills & AP Payments](#6-supplier-bills--ap-payments) | Working |
| 7 | [Orders (Sales)](#7-orders-sales) | Partial — edit/delete are stubbed |
| 8 | [Picking & Transfers](#8-picking--transfers) | Working (logic lives in route closures) |
| 9 | [Invoices & Customer Payments](#9-invoices--customer-payments) | Working |
| 10 | [Returns (Customer / Vendor / Retailer)](#10-returns) | Working — the most complex feature |
| 11 | [Credit Notes & Debit Notes](#11-credit-notes--debit-notes) | Working |
| 12 | [Stock Management & Locations](#12-stock-management--locations) | Read-only |
| 13 | [Accounting](#13-accounting) | Working |
| 14 | [Reports](#14-reports) | Working — some exports missing |
| 15 | [Features present in the UI but not functional](#15-features-present-in-the-ui-but-not-functional) | Reference |
| 16 | [Known correctness issues](#16-known-correctness-issues) | Reference |
| 17 | [Where the logic lives](#17-where-the-logic-lives) | Reference |

---

## 0. Core concepts you need before reading the flows

Four ideas explain almost every flow in the app.

**Goods move between location types, and that direction names the feature.** The navigation is organised by movement direction: `Vendor → Warehouse`, `Warehouse → Retailer`, `Warehouse → Customer`, `Retailer → Customer`. This is the central domain concept.

**`stock_transactions` is the ledger; `product_stocks` is the balance.** Every physical movement writes a `StockTransaction` row (inbound/outbound, typed). `ProductStock` holds the per-location `quantity` and `reserved_quantity`. `products.available_stocks` is a rolled-up cache of `SUM(quantity) − SUM(reserved_quantity)` across all locations, recomputed automatically by `ProductStockObserver`.

**Returns are not a separate table.** A return *is* a `StockTransaction` row with `transaction_type` of `customer_return`, `vendor_return`, or `retailer_return`. The old `returns` table was dropped.

**Picking lists are the moment stock actually moves.** Confirming an order only *reserves* stock. The physical decrement happens when a `PickingList` flips to `completed`, which `PickingListObserver` intercepts.

**There is no authentication.** No login, no roles, no permissions — every URL is publicly reachable. Actor fields fall back to `auth()->id() ?? 1`. See §13.

---

## 1. Dashboard

**Entry:** `/` · **View:** `resources/views/dashboard.blade.php`

The landing screen. Six KPI cards across the top — Sales Today, Returns Today, Pending Orders, Stock Value, Outstanding (unpaid invoice total), and total Products.

**Flow**

1. User lands on `/`. All KPI and chart data is queried inline in the Blade template on first render.
2. Five charts render via Chart.js: Sales Trend (30d line), Order Status Distribution (doughnut), Top Selling Products (horizontal bar, top 10), Stock Movement (inbound vs outbound), and Returns Breakdown (customer vs vendor per day).
3. User clicks **30D / 7D / 90D** in the Sales Trend header → four AJAX calls refetch all charts at the new period (`/dashboard/sales-trend`, `/top-products`, `/stock-movement`, `/returns-breakdown`).
4. A **Low Stock Alert** table lists products at or below 10 units, with a direct Edit link.
5. A **Recent Activity** feed shows the last 5 orders followed by the last 5 returns.
6. **Quick Actions** cards jump straight to create screens for Products, Customers, Orders, and Stock Transfers.
7. Every 5 minutes the KPI values silently refresh from `/dashboard/stats`.

---

## 2. Products & Categories

**Entry:** Inventory → Products · **Views:** `products/{index,create,edit,show,stock-analysis}.blade.php`

**Browsing and filtering**

The list supports search, category, subcategory, price range, stock range, and sorting, via the shared `<x-unified-search>` toolbar. Columns: ID, Name (+SKU), Category, Selling Price, Available Stocks, Purchase Price, GP.

Note the on-screen banner: *"Products are managed through supplies and orders. Stock levels are automatically updated."* Stock is never edited directly here — it is a consequence of supply and order activity.

**Creating a product**

1. Inventory → Products → **Add New Product**.
2. Enter name, SKU, description, prices.
3. Pick a **Category**; the **Subcategory** dropdown repopulates via AJAX (`products/ajax/subcategories`). Categories are a self-referencing tree (`parent_id`), displayed as `Electronics > Smartphones`.
4. Save → redirected to the product detail page.

**Automatic pricing (important, and easy to miss)**

If `auto_pricing_enabled` is on, `ProductObserver` recalculates pricing on every save:

```
selling_price = purchase_price + (purchase_price × markup%)
gross_profit  = selling_price − purchase_price
```

Markup falls back to `config('pricing.default_markup')`, default 25%.

`purchase_price` is written in exactly **one** place: `GrnService::applyReceivedCost()`, when a GRN is posted and the goods physically arrive. Ordering a product — raising a purchase order, or recording a supply — does not touch pricing at all.

A zero `purchase_price` means "never received", and derivation is skipped rather than pricing the product at 0.00. If a caller supplies both a cost and a selling price but no markup (a seeder, an import), `ProductObserver` infers the markup from them instead of overwriting the price.

Products carry **no selling price input**. The product form asks for a **markup %**; what you pay is set per vendor on that vendor's price list (`vendor_products`), because the same product can cost different amounts from different vendors.

> **Changed.** This used to work the other way round: `SupplyItemObserver` wrote a supply line's `unit_cost` straight to `purchase_price` at supply-creation time and forced `auto_pricing_enabled = true`, so the most recent supply cost silently rewrote the product's selling price globally — before the goods had even arrived. That observer has been deleted.

**Product detail** shows stock balances per location, transaction history, and stock analysis. A separate **Stock Analysis** screen (`products.stock-analysis`) gives per-product analytics.

The `GP` column only shows a value once the product has at least one order in `confirmed` or `completed` status; otherwise it is blank by design.

---

## 3. Customers & Vendors

**Entry:** Inventory → Customers / Vendors

Straightforward CRUD, near-identical for both. List (DataTables, client-side, 25/page) → Create → Edit → Detail.

- **Customer detail** additionally shows an **Orders** history table.
- **Vendor detail** additionally shows a **Supply History** table.

Customers get filter and sort options in the toolbar; vendors do not.

---

## 4. Supplies

> Supplies are no longer the purchase order. A real **Purchase Order** module now sits in front of them
> (`purchase_orders` / `purchase_order_items`, `PurchaseOrderService`): a warehouse's request to a vendor,
> running Draft → Approved → Sent, with `quantity_ordered` vs `quantity_received` so a delivery can be
> short and the order stay open. Recording a supply against an order creates an ordinary Supply linked by
> `supplies.purchase_order_id`, so everything downstream (GRN, bill, payment) is unchanged.
>
> A Supply is now what **arrived**, not what was ordered.

**Entry:** Inventory → Supplies · **Views:** `supplies/{index,create,edit,show,completion-options}.blade.php`

A supply is an inbound purchase from a vendor into a warehouse. Statuses: `pending → confirmed → completed` (or `cancelled`).

**Recording a supply**

1. Inventory → Supplies → **Record New Supply**.
2. Choose vendor, destination warehouse, supply date, notes.
3. Add line items. The form is heavily interactive: product typeahead with category/subcategory filtering, live per-row stock display at the selected location, duplicate-product prevention, and a running **Total Cost** footer.
4. Save → supply created with status `pending`, `total_cost` summed from line subtotals.
5. **Side effect, immediately:** each `SupplyItem` created updates its product's `purchase_price` and re-runs auto-pricing (see §2).

**Completing a supply**

Intended flow: mark the supply `completed`, which creates a **draft GRN** (`SupplyService::complete()`). The GRN is what actually posts stock — completing a supply deliberately moves no inventory.

A **"Complete Supply — Choose Receiving Method"** screen exists for selecting a receiving location.

> ⚠️ **Currently non-functional.** The `supplies.completed`, `.processing`, `.process-completion`, `.update`, `.destroy`, and `.store` routes are all shadowed by placeholder closures registered later in `routes/web.php` than the real controller routes. Clicking "Mark Completed" flashes a success message and does nothing. `SupplyService::complete()` and `::confirm()` are correct but unreachable from the UI. See §15.

---

## 5. Goods Receipt Notes (GRN)

**Entry:** Purchases → Good Receipt Notes · **Views:** `grns/{index,edit,show}.blade.php`

**This is the primary stock-in path for the entire system.** There is no create screen — GRNs are generated from supplies.

Statuses: `draft → posted` (`verified` exists in the schema but is unused).

**Posting a GRN — the full chain**

1. User opens a draft GRN and posts it (`PATCH /grns/{id}/status`).
2. `GrnService::transitionStatus()` opens a transaction and sets status to `posted`, then runs `postStock()`:
   - Creates a `StockTransfer` from Vendor → Warehouse, marked `completed`.
   - For each supply item, creates an **inbound** `StockTransaction` (`stock_in`, `completed`, referencing the GRN).
   - Creates matching `StockTransferItem` rows.
   - Marks the parent **supply as `completed`**.
   - Stamps `grn.posted_at`.
3. `StockTransactionObserver` fires on each new transaction → `updateProductStock()` increments the warehouse `ProductStock.quantity`.
4. `ProductStockObserver` fires → recomputes `products.available_stocks`.
5. **In parallel,** `GrnObserver` sees the status change to `posted` and auto-creates a **draft Supplier Bill** with one line per supply item, plus an audit log entry.
6. The user is redirected to the new Supplier Bill.

There is a guard worth knowing: a `stock_in` transaction that references a GRN has **no stock effect unless that GRN is `posted`**. Stock and GRN state cannot drift apart.

GRNs can only be deleted while `draft`. The GRN edit screen is intentionally read-only — GRNs are managed through their supply.

---

## 6. Supplier Bills & AP Payments

**Entry:** Purchases → Supplier Bills / Supplier Bills Payment

Statuses: `draft → posted`. Payment state lives on a separate `SupplierBillPayment` record (`unpaid → paid`).

**Posting a bill**

1. Open the draft bill → **Post**.
2. `SupplierBillService::postSupplierBill()` requires status `draft` and no existing purchase journal.
3. Posts a journal entry — **`1200 Inventory` Dr / `2000 Accounts Payable` Cr** — created in **draft** status.
4. Sets bill to `posted`, stamps `posted_at`, links the journal.
5. Creates a `SupplierBillPayment` row, `unpaid`, for the full amount.
6. Writes an audit log (`supplier_bill_posted`) and redirects to the **Payment Information** screen.

**Paying a bill**

1. From the bill or payment-info screen → **Mark as Paid**.
2. Requires status `posted`, an existing payment record that is not already paid, and no prior payment journal.
3. Posts a journal entry — **`2000 Accounts Payable` Dr / `1000 Cash` Cr** — again in **draft** status.
4. Stamps `paid_at` on both the bill and the payment record; payment status → `paid`.
5. Audit log `supplier_bill_paid`.

The UI wraps both actions in a loading overlay to prevent double-submission.

**Supplier Bills Payment** is a read-only list with status filtering and payment statistics.

---

## 7. Orders (Sales)

**Entry:** Sales → Orders · **Views:** `orders/{index,create,edit,show}.blade.php`

Statuses: `pending → confirmed → completed`, plus `processing` and `cancelled`.

**Creating an order**

1. Sales → Orders → **New Order**.
2. Select a customer.
3. Add product line items. Only products with `available_stocks > 0` are offered.
4. For each product, a **fulfillment location** dropdown loads asynchronously showing which warehouses/retailers can actually supply it and how much is free (`quantity − reserved_quantity`). Live subtotal and order total update as you type.
5. Save → order created with status `pending`.
   - `total_amount` is summed from line items.
   - ⚠️ The order-level `fulfillment_location` is derived **from the first product row only**, though each `OrderItem` also stores its own location.

**Confirming an order — reserves stock, does not move it**

1. Order detail → **Confirm Order** modal → posts `status=confirmed`.
2. `OrderService::confirm()` runs:
   - Refuses if already confirmed or if a picking list already exists.
   - Creates a `PickingList` (from the fulfillment location, to `Customer`, status `pending`) with one item per order line.
   - **Increments `ProductStock.reserved_quantity`** at the fulfillment location — stock is now spoken for but still physically present.
   - Sets order status to `confirmed`.
3. `OrderObserver` also fires and recalculates per-unit gross profit for each product on the order.
4. User is redirected to the **Warehouse → Customer Picking** screen for the new picking list.

**Completing an order**

Two paths converge here:

- **Via picking (normal):** completing the picking list triggers the physical stock deduction and then sets the order to `completed` and generates the invoice. See §8.
- **Via direct status change:** `OrderController@updateStatus` with `status=completed` opens a transaction, checks whether a completed picking list already exists, and **only deducts stock if one does not** (otherwise the picking observer already did it). It then generates an invoice if none exists and redirects to it.

> ⚠️ **Order edit and delete are non-functional.** Both routes are shadowed by placeholder closures that flash a success message and change nothing.

---

## 8. Picking & Transfers

**Entry:** Picking & Transfers menu

The largest surface in the app, split by movement direction. The shared mechanic across all of them:

> **`PickingListObserver` is where stock physically moves.** When a picking list's status changes into `completed`, `closed`, or `verified`, the observer — inside one database transaction, with row locking — releases the reservation at the source, decrements source quantity, writes an outbound `StockTransaction`, and (if the destination is a Warehouse or Retailer) increments destination quantity and writes a matching inbound transaction. For customer deliveries the destination is `Customer`, so there is no inbound leg.

Statuses: `pending → open → picked → verified → closed / completed`, or `cancelled`.

The inbound Vendor → Warehouse direction has no picking screen of its own: receiving is driven entirely by the GRN flow (§5).

### 8a. Warehouse → Retailers

A full transfer workflow, implemented entirely in `routes/web.php` closures.

1. **Dashboard** shows warehouse/retailer counts, transfer counts, an order-generated vs manual split, and per-location total stock.
2. **Create Stock Transfer**: pick source warehouse, destination retailer, and line items. Warehouse stock is preloaded as JSON per product so availability filtering happens client-side with no round trip.
3. **On save** (one DB transaction): creates a `StockTransfer` (`pending`), a `PickingList` (`pending`), and items; creates missing `ProductStock` rows at zero; **increments `reserved_quantity`** at the source warehouse.
4. **Process**: enter picked quantities per item (capped at requested), or use **Quick Complete** to fill everything at requested quantity.
5. Completing the list cascades: picking list → `completed` (which fires the observer and moves stock), then `StockTransfer` → `completed`.
6. `StockTransferObserver` then posts an inter-location inventory journal entry for the total cost of goods moved.
7. **Cancel** rolls reservations back (`reserved_quantity −= requested`, floored at zero) and cancels the items, list, and transfer.

### 8b. Warehouse → Customers

The fulfilment side of a confirmed sales order.

1. Open the picking list created when the order was confirmed.
2. Mark it complete (`PATCH .../update-status` with `completed`) — all items are set to picked-in-full and `completed_at` is stamped.
3. `PickingListObserver` releases reservations and decrements warehouse stock.
4. Because the list references an `Order`, the observer also sets the order to `completed` and calls `InvoiceService::generateFromOrder()`.
5. User is redirected to the invoice: *"Picking completed! Invoice has been generated automatically."*

### 8c. Retailers → Customers

Same shape for retailer-fulfilled orders. Supports completing the whole list (idempotent — re-completing returns an informational message) or marking a single item picked in full. This is the only one of the three picking dashboards whose statistics endpoint returns real data.

### 8d. All Picking Lists & Transaction Flow

- **All Picking Lists** — a combined list across every direction, with type/status/date filters.
- **Transaction Flow** — an overview dashboard combining stock summary, recent movements, warehouses, and retailers, with a flow diagram partial.
- **Product Transaction History** (reachable by drilling into a product) shows total supplied, total sold, total transferred, current total/available/reserved stock, per-location balances, movements, and related picking lists.

---

## 9. Invoices & Customer Payments

**Entry:** Sales → Invoices

Invoices are **never created manually** — they are generated from a completed order. `InvoiceService::generateFromOrder()` is idempotent and returns the existing invoice if one is already linked.

**Generation**

- Invoice number: `INV` + zero-padded increment.
- `subtotal` = sum of order item subtotals; **`tax` and `discount` are hardcoded to 0**, so `total = subtotal`.
- `payment_status` starts as `unpaid`; order items are copied into invoice items.
- `InvoiceObserver` then posts a journal entry — **`1100 Accounts Receivable` Dr / `4000 Sales Revenue` Cr** — in draft status.

**Recording a payment**

1. Open the invoice → record a payment with amount, method, and optional reference/notes.
2. `PaymentService::recordPayment()` creates a `Payment` (status `completed`).
3. It recomputes the sum of all payments and sets the invoice to `paid` (stamping `paid_at`) if fully covered, otherwise `partially_paid` (clearing `paid_at`).
4. `PaymentObserver` posts a journal entry — **`1000 Cash` Dr / `1100 Accounts Receivable` Cr**.

**Downloading** an invoice produces a real PDF via DomPDF, available from both the list row and the detail page.

> ⚠️ Invoice edit, update, and delete routes exist but point at controller methods that do not exist — they will error. There is no payments list or detail screen.

---

## 10. Returns

**Entry:** Returns menu · **Views:** `returns/{index,create,create-with-data,show}.blade.php`

Three return types share one engine. All are `StockTransaction` rows.

| Type | References | Stock effect on approval | Financial document |
|---|---|---|---|
| **Customer return** | Invoice | **+** stock at the return location | Credit Note |
| **Vendor return** | Supplier Bill | **−** warehouse stock | Debit Note |
| **Retailer return** | Stock Transfer | **−** retailer, **+** source warehouse | None (internal move) |

**Creating a return** — `returns/create.blade.php` is the app's most complex screen: a progressively-revealed wizard in a single page.

1. **Choose return type** — customer, vendor, or retailer.
2. **Choose the source party** — the relevant dropdown loads via AJAX. Note: only invoices with `payment_status = 'paid'` are offered for customer returns.
3. **Choose the reference document** — invoice, supplier bill, or stock transfer. A retailer return's stock transfer **must be `completed`**; anything else is rejected.
4. **Select items and quantities.** Each quantity is validated client-side and then **server-side** against the original document minus everything already returned — over-returning is blocked.
5. **Destination is resolved automatically** per product (`returns/ajax/product-return-destination/...`) — the user does not choose where stock goes back to.
6. Enter a return reason (max 500 chars) and review the live validation summary.
7. Submit → one `StockTransaction` per item. Customer and vendor returns start `pending`; **retailer returns start `issued`.**

**Approving a return** — this is where everything happens.

1. Return detail → **Approve**.
2. `StockTransaction::approve()` checks the status is legal (retailer returns may be approved from `issued` or `pending`; the others only from `pending`), sets status to `approved`, then:
3. Runs `updateProductStock()` — the physical movement, per the table above.
4. Auto-generates the financial document: customer → **Credit Note** (`issued`), vendor → **Debit Note** (`issued`), retailer → nothing.
5. Writes an audit log and redirects to the generated note (or, for retailer returns, back with a message describing the stock move and noting that no journal entry is created).

**Other transitions:** `approved → completed` (no further stock effect — stock already moved at approval). `pending → rejected`. `pending → cancelled`. Returns can only be edited or deleted while `pending`, and editing is limited to quantity, reason, and notes.

The returns list is filterable by type, location, product, status, and date, and the nav links straight to type-filtered views.

> ⚠️ The **Reject** action is wired to a controller method that does not exist and will error.

---

## 11. Credit Notes & Debit Notes

**Entry:** Returns → Credit Notes / Debit Notes

Structurally symmetric. Credit notes face customers and reference an invoice; debit notes face vendors and reference a supplier bill. Both are created automatically when a return is approved — never by hand.

Statuses: `draft, pending, issued, posted, cancelled, expired`. They arrive as `issued`.

**Two-step accounting approval**

1. **Post the note** → `ReturnJournalHandler` creates a **reversing** journal entry in **draft**:
   - Credit note: `5200 Sales Returns & Allowances` Dr / `1100 AR` Cr, plus `1200 Inventory` Dr / `5000 COGS` Cr at cost.
   - Debit note: `5100 Purchase Returns` Dr / `2100 AP` Cr, plus `1200 Inventory` Cr / `5000 COGS` Dr at cost.
   - The entry is marked `is_reverse` and linked back to the original sales or purchase journal it reverses. **If that original journal is missing, posting fails.**
   - Note status → `posted`.
2. **Post the journal entry** → the draft journal moves to `posted` and `posted_at` is stamped.

A note can be cancelled while active and unapplied. Application against an invoice or bill is supported at the service layer (`remaining_amount` / `applied_amount`) but has no UI.

> ⚠️ The **Download** action on both credit and debit notes returns an HTML view, not a PDF — the PDF generation is a documented TODO. The debit note PDF template also contains a hardcoded `Your Company Name` placeholder.

---

## 12. Stock Management & Locations

**Entry:** Stock Management menu

**Stock Transaction Records** (`stock-management.index`) is the system's audit view over all inventory movement. Four summary cards (Total / Completed / Pending / Today), then a table of every transaction: ID, Product, Location, Type, Reference, Quantity, Unit Cost, Total Cost, Date, Status. Filterable by product search, location type and location, transaction type, status, and date range. 50 per page.

Drilling into a product opens its full movement history.

**Stock Locations** lists warehouses and retailers with computed stock data.

> ⚠️ **Stock Locations is read-only in practice.** Create, update, and delete all return placeholder responses, and there is no edit view — the edit route will error. The CSV export button on Stock Transaction Records builds a URL for an export endpoint **that has no registered route**; the export method is implemented but unreachable.

---

## 13. Accounting

**Entry:** Accounting menu

**Chart of Accounts** — hierarchical accounts (code, name, type, optional parent). Default accounts are seeded automatically. Index and create only; no edit or detail screen.

Seeded accounts: `1000` Cash · `1100` AR · `1200` Inventory · `1300` Intercompany Receivable · `2000` AP · `2100` AP *(duplicate)* · `3000` Owner's Equity · `3010` Retained Earnings · `4000` Sales Revenue · `4100` Sales Returns *(seeded but never used)* · `5000` COGS · `5100` Purchase Returns · `5200` Sales Returns & Allowances.

**Journal Entries** — statuses `draft → approved / rejected → posted`.

1. Accounting → Journal Entries → **New Journal Entry**.
2. Enter date, description, and a unique reference.
3. Add line items in a double-entry editor — the form seeds two rows (the minimum) and shows live debit/credit totals as you type. Each line needs an account and either a debit or a credit.
4. Save → the entry is validated: **debits must equal credits** and the total must be greater than zero, or it is rejected outright.
5. From the detail screen: **Approve**, **Reject**, or **Post**. Posting an unbalanced entry throws.

Entries created by the system carry a polymorphic link back to their source (invoice, payment, supplier bill, credit/debit note), and reversing entries link to the entry they reverse.

**Audit Trail** — filterable log of system actions (invoice created, payment created, supplier bill posted/paid, return approved/rejected/completed, journal posted, etc.), by date range, action, and user.

---

## 14. Reports

**Entry:** Reports / Accounting menus

| Report | Date control | Export |
|---|---|---|
| **Daily Profit** | start + end | Print only |
| **Trial Balance** | as-of date | PDF ✓ · CSV ✗ |
| **Income Statement** | start + end | PDF ✓ · CSV ✗ |
| **Balance Sheet** | as-of date | ✗ (buttons present) |
| **Cash Flow Statement** | start + end | ✗ (buttons present) |

**Daily Profit** is the richest: an overall summary (products sold, revenue, profit, average margin), then separate **Warehouse Sales** and **Retailer Sales** breakdowns, then a per-day detail table. Profit is computed per order item as `(unit_price − purchase_price) × quantity`.

> ⚠️ All four financial statements show PDF and CSV buttons, but **CSV is not implemented anywhere** (it flashes an error), and **Balance Sheet and Cash Flow PDF export is dead** — the PDF methods exist in the controller but have no routes.

---

## 15. Features present in the UI but not functional

These render, are linked, and appear to work — but do nothing or error. Worth fixing or hiding.

**Shadowed by placeholder closures** (the real controller method exists but a later route in `routes/web.php` overrides it — success message, no effect):

- Order **update** and **delete**
- Supply **store**, **update**, **delete**, **mark processing**, **mark completed**, **process completion**, **generate missing picking lists**

**Routes pointing at missing controller methods** (these will throw):

- `products.transaction-history`
- `returns.reject` — the Reject button on a return
- `invoices.edit` / `update` / `destroy`
- `supplier-bills.edit` / `update` / `destroy`

**Stubbed modules:**

- **Warehouse Receiving** (`/warehouse/receiving/*`) — every route returns the same empty view. Not linked in the nav; the routes exist only to prevent "route not defined" errors.
- **Stock Locations** create/edit/update/delete — placeholders, and no edit view exists.

**Missing or dead exports:**

- Stock Management CSV export — implemented, no route.
- Balance Sheet / Cash Flow PDF — implemented, no routes.
- Trial Balance / Income Statement CSV — not implemented.
- Credit Note / Debit Note "Download PDF" — returns HTML, not a PDF.

**Unreachable screens** (exist but not linked from the nav): Pending Warehouse-to-Retailer Transfers · Retailer Stock Management · two duplicate "All Picking Lists" implementations · `auth/login.blade.php` · `welcome.blade.php` · `test.blade.php`.

**Also note:** the entire `routes/api.php` file is never loaded — `bootstrap/app.php` does not register an `api` route file, and `RouteServiceProvider` is not in `bootstrap/providers.php`. Every `apiIndex`/`apiShow` method across the controllers is dead code. Two `/debug/*` routes returning raw JSON are live in production routing.

---

## 16. Known correctness issues

Flagged for triage — these affect data integrity, not just UI polish.

**No authentication of any kind.** No login route, no auth middleware on any route, no roles or permissions. Every URL, including destructive actions and financial posting, is publicly reachable. A single seeded user exists (`system@example.com` / `password`) and actor fields fall back to user ID 1.

**Accounts Payable is split across two accounts.** Supplier bills post to `2000`; vendor returns and debit notes post to `2100`. Both are seeded with the name "Accounts Payable". AP will never reconcile across purchases and returns.

**Sales record revenue but never relieve inventory.** Invoice posting writes AR/Revenue only — there is no COGS or inventory entry at the time of sale. Return journals nonetheless credit `5000 COGS` and debit `1200 Inventory`, so the reversal has no matching original.

**Partial picks over-deduct.** `PickingListObserver` deducts `quantity_requested`, not `quantity_picked`. Picking 3 of 5 still removes 5 from stock.

**Return actor and amount are never persisted.** The `approve()`, `reject()`, and `complete()` methods read and write a `return_data` attribute that is not a database column and is not cast — so `approved_by`, `approved_at`, `return_amount`, `rejection_reason` and friends silently return null/zero.

**Cancelling a return destroys its reason.** `cancel()` overwrites the `notes` field with a JSON blob, discarding the original return reason.

**Debit note pricing always falls back.** `DebitNoteService` reads `$supplierBillItem->unit_price`, but the column is `unit_cost`. Every debit note is priced from the product's current cost rather than the billed cost.

**Invalid enum values are written.** Picking list item status is `pending|picked|short` in the schema, but route closures write `'cancelled'` and `'partial'`. MySQL will reject or truncate these.

**Two competing picking-list creation paths.** `OrderService::confirm()` and `OrderObserver::updated` both create picking lists and reservations on order confirmation, with different scopes (the observer only handles retailer fulfilment). Behaviour depends on ordering; an `exists()` guard suppresses the duplicate list, but the reservation increments do not re-run either.

**Stock transfer sub-accounts always fall back.** `StockTransferObserver` matches location type against the strings `'warehouse'`/`'retailer'`, but the column stores fully-qualified class names — so per-location inventory sub-accounts always resolve to the generic `1200-LO{id}` fallback.

**Partial dedup protection on stock updates.** `updateProductStock()` guards against re-entry with a cache flag, but the flag is only written in the final generic branch — the vendor-return and retailer-return branches are unprotected against double application.

**Duplicate and dead code.** `ReturnJournalService` is a near-exact copy of `ReturnJournalHandler` (only the Handler is wired up). `SupplyObserver` is registered but the file does not exist. `InvoiceObserver` is registered twice. `AuditLog::create()` is called in several places with `old_values`/`new_values`/`metadata` keys that are neither fillable nor columns — those payloads are silently discarded.

**Operational oddity:** `ChartOfAccountsSeeder` runs on **every request** from `AppServiceProvider::boot()`.

---

## 17. Where the logic lives

| Concern | Location |
|---|---|
| Stock balance recalculation | `app/Observers/ProductStockObserver.php`, `StockTransactionObserver.php` |
| Physical stock movement | `app/Observers/PickingListObserver.php` |
| Return state machine + stock effects | `app/Models/StockTransaction.php` (`approve`/`reject`/`complete`/`updateProductStock`) |
| Return creation & validation | `app/Services/ReturnService.php` |
| GRN posting → stock in | `app/Services/GrnService.php` |
| Supplier bill auto-creation | `app/Observers/GrnObserver.php` |
| Auto-pricing | `app/Observers/ProductObserver.php`, `SupplyItemObserver.php` |
| Order confirm → reservation | `app/Services/OrderService.php` |
| Invoice generation | `app/Services/InvoiceService.php` |
| Journal posting & balance validation | `app/Services/AccountingService.php` |
| Reversing journals for returns | `app/Services/ReturnJournalHandler.php` |
| **Warehouse→Retailer transfer workflow** | `routes/web.php` (closures, ~lines 815–1090) |
| Navigation menu | `resources/views/layouts/app.blade.php` |
