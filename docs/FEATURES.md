# Sales Order System — Features & Flows

A functional map of the system: what it does, and how each feature flows from the user's first click to the final side effect on stock and the accounting ledger.

**Stack:** Laravel 12 · PHP 8.2 · Blade + Bootstrap 5 (CDN) · DataTables · Chart.js · DomPDF
**Architecture:** Controller → Service → Model, with Eloquent **Observers** carrying most of the state machine. Access is session-authenticated and permission-gated on every route (§17). A meaningful amount of business logic also lives directly in `routes/web.php` closures.

> **Read this first:** §19 lists features that appear in the UI but are **not functional**, and §20 lists known correctness issues. Treat §1–§18 as "how it is meant to work"; check §19/§20 before relying on any single flow.

---

## Contents

| # | Feature area | Status |
|---|---|---|
| 1 | [Dashboard](#1-dashboard) | Working |
| 2 | [Products & Categories](#2-products--categories) | Working |
| 3 | [Catalog › Product Pricing](#3-catalog--product-pricing) | Working — simple mode by default |
| 4 | [Customers & Vendors](#4-customers--vendors) | Working |
| 5 | [Purchase Orders](#5-purchase-orders) | Working — required before every supply |
| 6 | [Supplies](#6-supplies) | Working |
| 7 | [Goods Receipt Notes (GRN)](#7-goods-receipt-notes-grn) | Working — the main stock-in path |
| 8 | [Supplier Bills & AP Payments](#8-supplier-bills--ap-payments) | Working |
| 9 | [Orders (Sales)](#9-orders-sales) | Working |
| 10 | [Picking & Transfers](#10-picking--transfers) | Working (logic lives in route closures) |
| 11 | [Invoices & Customer Payments](#11-invoices--customer-payments) | Working |
| 12 | [Returns (Customer / Vendor / Retailer)](#12-returns) | Working — the most complex feature |
| 13 | [Credit Notes & Debit Notes](#13-credit-notes--debit-notes) | Working |
| 14 | [Stock Management & Locations](#14-stock-management--locations) | Read-only |
| 15 | [Accounting](#15-accounting) | Working |
| 16 | [Reports](#16-reports) | Working — some exports missing |
| 17 | [Users, Roles & Permissions](#17-users-roles--permissions) | Working |
| 18 | [Reference › Action Effects](#18-reference--action-effects) | Working |
| 19 | [Features present in the UI but not functional](#19-features-present-in-the-ui-but-not-functional) | Reference |
| 20 | [Known correctness issues](#20-known-correctness-issues) | Reference |
| 21 | [Where the logic lives](#21-where-the-logic-lives) | Reference |

---

## 0. Core concepts you need before reading the flows

Five ideas explain almost every flow in the app.

**Goods move between location types, and that direction names the feature.** The navigation is organised by movement direction: `Vendor → Warehouse`, `Warehouse → Retailer`, `Warehouse → Customer`, `Retailer → Customer`. This is the central domain concept.

**`stock_transactions` is the ledger; `product_stocks` is the balance.** Every physical movement writes a `StockTransaction` row (inbound/outbound, typed). `ProductStock` holds the per-location `quantity` and `reserved_quantity`. `products.available_stocks` is a rolled-up cache of `SUM(quantity) − SUM(reserved_quantity)` across all locations, recomputed automatically by `ProductStockObserver`.

**Returns are not a separate table.** A return *is* a `StockTransaction` row with `transaction_type` of `customer_return`, `vendor_return`, or `retailer_return`. The old `returns` table was dropped.

**Picking lists are the moment stock actually moves.** Confirming an order only *reserves* stock. The physical decrement happens when a `PickingList` flips to `completed`, which `PickingListObserver` intercepts.

**Nothing is reachable without an authority behind it.** Session login, roles and permissions gate every route — not by a middleware argument written out route by route, but through one table (`App\Support\RoutePermissions`) applied by `EnforceRoutePermissions`, with `RoutePermissionCoverageTest` failing the build if a new route is neither listed nor gated on itself. See §17.

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

## 3. Catalog › Product Pricing

**Entry:** `/product-pricing` · **Controller:** `ProductPricingController` · **Service:** `SimplePricingService`, `PriceResolver` · **Permission:** `product-pricing.view` / `.manage`

The one place prices are set. Nothing else in the application writes them — not recording a supply, not receiving goods.

**The model underneath.** A price is not a column but a record with a lifetime: purchase prices live on one price list per vendor, sale prices on one per fulfilment kind, each row effective-dated. `PriceResolver` answers "what does this cost, from this vendor, on this date" by reading the list in force. That is what lets an order keep the price it was actually placed at after somebody edits the catalogue.

**Simple mode** (`config('pricing.simple_mode')`, on by default) hides that. One product has **one** purchase price whoever supplies it, and a fixed markup (`config('pricing.default_markup')`, 25%) gives one selling price and one gross profit. `SimplePricingService` writes the same figure into every underlying list, so the readers — purchase orders, sales orders, the resolver — are unchanged and nothing is a special case for them.

Setting `PRICING_SIMPLE_MODE=false` restores the full per-vendor editor with every price already on file and nothing to migrate. Simple mode is deliberate and temporary: the pricing concept is richer than the rest of the system currently needs.

**Flow**

1. `/product-pricing` lists products with their cost, markup, selling price and margin, filterable and sortable.
2. Opening one shows the editor. In simple mode that is a single cost field; with the flag off, a cost per vendor and a selling price derived from each, per location kind.
3. Saving writes through `SimplePricingService`, which stamps a new effective-dated row on every relevant list rather than updating in place.
4. `/product-pricing/products/{product}/history` shows every price the product has ever carried, and when it changed.

**Rules worth knowing**

- **A price locks once it has been charged.** Once a price appears on a document a customer or vendor has seen, editing it writes a new effective-dated row; the old one stays, and the document keeps reading the one it was placed under.
- **An unpriced product cannot be ordered.** Order lines refuse a product with no sale price in force rather than quoting zero — see `UnpricedProductsCannotBeOrderedTest`.
- **Receiving goods does not reprice anything.** A GRN records what the delivery actually cost in the costing ledger, which is what stock is valued at, but the selling price is whatever somebody typed until they change it.

---

## 4. Customers & Vendors

**Entry:** Inventory → Customers / Vendors

Straightforward CRUD, near-identical for both. List (DataTables, client-side, 25/page) → Create → Edit → Detail.

- **Customer detail** additionally shows an **Orders** history table.
- **Vendor detail** additionally shows a **Supply History** table.

Customers get filter and sort options in the toolbar; vendors do not.

---

## 5. Purchase Orders

**Entry:** `/purchase-orders` · **Controller:** `PurchaseOrderController` · **Service:** `PurchaseOrderService` · **Permission:** `purchase-orders.view` / `.manage`

Stage 1 of buying. A warehouse asks a vendor for goods, and **every supply must have one behind it** — there is no way to record a delivery that nobody ordered.

**Lifecycle:** `draft → approved → sent → partially_received → received`, plus `cancelled` from anywhere before receipt.

**Flow**

1. **Create** (`purchase-orders.create`). Pick the vendor and the destination warehouse, then add lines. You can only order what that vendor carries — `PurchaseOrderService::assignableProducts()` restricts the picker to the vendor's product list, fetched by `purchase-orders.vendor-products` — and each line's cost is prefilled from that vendor's price list.
2. **Approve** (`purchase-orders.approve`), then **Send** (`purchase-orders.send`). Only a draft can be edited; only a sent order can be delivered against.
3. **Record a supply against it.** The order's own page carries a Record Supply button, and `supplies/purchase-orders` (`SupplyController::awaitingOrders`) lists every order still waiting on a delivery with the same button on each. This is the only entry point to §6.
4. As supplies are recorded, `PurchaseOrderService::refreshReceivedQuantities()` recounts what has arrived and moves the order to *Partially Received* or *Received* on its own.

**Rules worth knowing**

- **A delivery cannot differ from the order it is against.** Recorded lines are checked against what is still outstanding; you may receive less than was ordered, or drop a line that did not turn up, but you cannot invent a line or exceed the quantity ordered.
- The vendor and warehouse come from the order and cannot be changed on the supply.
- Supplies recorded before this rule existed may have no order behind them. They still open and read normally.

---

## 6. Supplies

**Entry:** Supplies → **Requested Purchase Orders** · **Controller:** `SupplyController` · **Views:** `supplies/{index,create,edit,show,purchase-orders,completion-options}.blade.php` · **Permission:** `supplies.view` / `.manage`

Stage 2. A supply records **what actually turned up** against an order (§5) — not what was asked for. Statuses: `pending → confirmed → completed` (or `cancelled`).

**Recording a supply**

1. Open a *sent* purchase order, from its own page or from **Requested Purchase Orders** (`supplies.purchase-orders`), which lists every order still waiting on a delivery. There is no other way in: `SupplyController::create` requires an order behind it.
2. The vendor and destination warehouse come from the order and cannot be changed.
3. Lines arrive prefilled with what is still outstanding. Adjust quantities down and drop anything that did not arrive; you cannot add a line the order does not carry or exceed what it asked for.
4. Save → supply created with status `pending`, `total_cost` summed from line subtotals, linked to the order by `supplies.purchase_order_id`.
5. **Side effect, immediately:** `PurchaseOrderService::refreshReceivedQuantities()` recounts what has arrived and moves the order to *Partially Received* or *Received*.

> **Recording a supply moves neither stock nor prices.** Stock becomes real at the GRN (§7). Prices are set in Product Pricing (§3) and nowhere else — an earlier version of this flow wrote each line's cost back to the product's `purchase_price` and re-derived the selling price from it, so a single cheap delivery silently repriced the catalogue. It no longer does.

**Completing a supply**

Intended flow: mark the supply `completed`, which creates a **draft GRN** (`SupplyService::complete()`). The GRN is what actually posts stock — completing a supply deliberately moves no inventory.

A **"Complete Supply — Choose Receiving Method"** screen exists for selecting a receiving location.

These were once unreachable: the `supplies.completed`, `.update`, `.destroy` and `.store` routes were shadowed by placeholder closures registered later in `routes/web.php` than the real controller routes, so "Mark Completed" flashed a success message and did nothing. The placeholders have been deleted and every one of them now reaches `SupplyController`.

---

## 7. Goods Receipt Notes (GRN)

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

## 8. Supplier Bills & AP Payments

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

## 9. Orders (Sales)

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

- **Via picking (normal):** completing the picking list triggers the physical stock deduction and then sets the order to `completed` and generates the invoice. See §10.
- **Via direct status change:** `OrderController@updateStatus` with `status=completed` opens a transaction, checks whether a completed picking list already exists, and **only deducts stock if one does not** (otherwise the picking observer already did it). It then generates an invoice if none exists and redirects to it.

> ⚠️ **Order edit and delete are non-functional.** Both routes are shadowed by placeholder closures that flash a success message and change nothing.

---

## 10. Picking & Transfers

**Entry:** Picking & Transfers menu

The largest surface in the app, split by movement direction. The shared mechanic across all of them:

> **`PickingListObserver` is where stock physically moves.** When a picking list's status changes into `completed`, `closed`, or `verified`, the observer — inside one database transaction, with row locking — releases the reservation at the source, decrements source quantity, writes an outbound `StockTransaction`, and (if the destination is a Warehouse or Retailer) increments destination quantity and writes a matching inbound transaction. For customer deliveries the destination is `Customer`, so there is no inbound leg.

Statuses: `pending → open → picked → verified → closed / completed`, or `cancelled`.

The inbound Vendor → Warehouse direction has no picking screen of its own: receiving is driven entirely by the GRN flow (§7).

### 10a. Warehouse → Retailers

A full transfer workflow, implemented entirely in `routes/web.php` closures.

1. **Dashboard** shows warehouse/retailer counts, transfer counts, an order-generated vs manual split, and per-location total stock.
2. **Create Stock Transfer**: pick source warehouse, destination retailer, and line items. Warehouse stock is preloaded as JSON per product so availability filtering happens client-side with no round trip.
3. **On save** (one DB transaction): creates a `StockTransfer` (`pending`), a `PickingList` (`pending`), and items; creates missing `ProductStock` rows at zero; **increments `reserved_quantity`** at the source warehouse.
4. **Process**: enter picked quantities per item (capped at requested), or use **Quick Complete** to fill everything at requested quantity.
5. Completing the list cascades: picking list → `completed` (which fires the observer and moves stock), then `StockTransfer` → `completed`.
6. `StockTransferObserver` then posts an inter-location inventory journal entry for the total cost of goods moved.
7. **Cancel** rolls reservations back (`reserved_quantity −= requested`, floored at zero) and cancels the items, list, and transfer.

### 10b. Warehouse → Customers

The fulfilment side of a confirmed sales order.

1. Open the picking list created when the order was confirmed.
2. Mark it complete (`PATCH .../update-status` with `completed`) — all items are set to picked-in-full and `completed_at` is stamped.
3. `PickingListObserver` releases reservations and decrements warehouse stock.
4. Because the list references an `Order`, the observer also sets the order to `completed` and calls `InvoiceService::generateFromOrder()`.
5. User is redirected to the invoice: *"Picking completed! Invoice has been generated automatically."*

### 10c. Retailers → Customers

Same shape for retailer-fulfilled orders. Supports completing the whole list (idempotent — re-completing returns an informational message) or marking a single item picked in full. This is the only one of the three picking dashboards whose statistics endpoint returns real data.

### 10d. All Picking Lists & Transaction Flow

- **All Picking Lists** — a combined list across every direction, with type/status/date filters.
- **Transaction Flow** — an overview dashboard combining stock summary, recent movements, warehouses, and retailers, with a flow diagram partial.
- **Product Transaction History** (reachable by drilling into a product) shows total supplied, total sold, total transferred, current total/available/reserved stock, per-location balances, movements, and related picking lists.

---

## 11. Invoices & Customer Payments

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

## 12. Returns

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

## 13. Credit Notes & Debit Notes

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

## 14. Stock Management & Locations

**Entry:** Stock Management menu

**Stock Transaction Records** (`stock-management.index`) is the system's audit view over all inventory movement. Four summary cards (Total / Completed / Pending / Today), then a table of every transaction: ID, Product, Location, Type, Reference, Quantity, Unit Cost, Total Cost, Date, Status. Filterable by product search, location type and location, transaction type, status, and date range. 50 per page.

Drilling into a product opens its full movement history.

**Stock Locations** lists warehouses and retailers with computed stock data.

> ⚠️ **Stock Locations is read-only in practice.** Create, update, and delete all return placeholder responses, and there is no edit view — the edit route will error. The CSV export button on Stock Transaction Records builds a URL for an export endpoint **that has no registered route**; the export method is implemented but unreachable.

---

## 15. Accounting

**Entry:** Accounting menu · **Code:** `app/Accounting/` — nothing outside it writes to the ledger.

**The chart of accounts** is defined once, in `config/accounting.php`, and `ChartOfAccounts::sync()` reconciles the `accounts` table to it on boot. Business logic never names an account code — it names a **role** (`App\Accounting\AccountRole`) and `AccountResolver` looks the code up.

| | | | | |
|---|---|---|---|---|
| `1000` Cash | `1100` Accounts Receivable | `1200` Inventory | `1250` Input Tax Recoverable | |
| `2000` Accounts Payable | `2050` Goods Received Not Invoiced | `2100` Sales Tax Payable | | |
| `3000` Owner's Equity | `3010` Retained Earnings | `3100` Opening Balance Equity | | |
| `4000` Sales Revenue | `4100` Sales Discounts | `4200` Sales Returns & Allowances | | |
| `5000` Cost of Goods Sold | `5100` Purchase Returns | | | |

Accounts carry three flags the ledger enforces: `control_of` (every line must name the customer or vendor it belongs to), `requires_location` (every line must name the stock location), and `is_postable` (rollup accounts refuse direct posting).

**Dimensions, not accounts.** `journal_entry_lines` carries `party`, `location` and `product` alongside the account, which is what keeps the chart small and the control accounts reconcilable. AR/AP are single accounts — what each customer owes is a `GROUP BY party_id` over the control account, the subsidiary ledger read straight off the general ledger rather than rebuilt from the invoices and hoped to agree. Inventory is one account, so per-warehouse value is a `GROUP BY location_id` and the parts necessarily add up to the whole. Revenue and cost of sales are posted one line per invoice or credit-note line, each carrying its product and the location it sold from, so profit per product comes from the same accounts the totals do.

**Posting rules.** Every entry is built by a rule in `app/Accounting/Posting/` and written by `PostingEngine`. The registry in `AccountingServiceProvider` is the whole answer to "what does this system post, and when?". Entries are idempotent on `(source_type, source_id, rule_key)` — a unique index — so one document can raise several entries (a supplier bill raises one when posted and another when paid) and re-running a rule is a no-op.

**System vs. manual.** `journal_entries.origin` decides whether an entry is reviewed:

- **`system`** — the ledger's own record of a document a person already confirmed. Written **posted**, immutable from birth. Approving the system's own arithmetic a second time adds no control; it only holds the books behind reality.
- **`manual`** — typed by a person, and the only path that runs `draft → approved → posted`, which is where segregation of duties actually belongs.

A posted entry can never be edited or deleted — the model throws. Correcting one means `PostingEngine::reverse()`, which mirrors it and links the two.

**Manual entry flow**

1. Accounting → Journal Entries → **New Journal Entry**.
2. Enter date, description and a unique reference.
3. Add lines in a double-entry editor — two rows seeded, live debit/credit totals as you type.
4. Save → **debits must equal credits** and the total must exceed zero, or it is rejected outright.
5. From the detail screen: **Approve**, **Reject** or **Post**. Posting an unbalanced entry throws.

**Periods.** `fiscal_periods` are calendar months, created on demand as open. `PeriodGuard` refuses any posting into a period that is not open. `PeriodCloseService::close()` posts a real closing entry moving revenue and expense into Retained Earnings; `reopen()` reverses that entry rather than deleting it.

**Reading.** `LedgerService` is every read, in SQL. Nothing stores a balance — every figure is derived by summing the lines of **posted** entries, so a balance cannot drift from the entries behind it. Balances are signed debit-minus-credit throughout; presentation flips the sign, the ledger never does. The income statement calls `excludingClosingEntries()`, since a closing entry is dated inside the period it closes and counting it would report nil profit for every closed period. The balance sheet does not exclude them, which is how Retained Earnings becomes a real posted balance.

**Checking it.** `ReconciliationService` and the **Accounting Health** page (`accounting.health`) check the general ledger against everything else that claims to know the same numbers: trial balance, the accounting equation, AR against the sales ledger, AP against the purchase ledger, GR-IR against undelivered bills, and inventory against stock on hand. `php artisan accounting:rebuild` deletes the ledger and replays every business document through the posting rules — how a change to a rule is applied to history, and the proof that the ledger holds nothing the documents do not already know.

**Money.** `App\Accounting\Money` — integer minor units, immutable. No float arithmetic anywhere in the accounting code, so balance is decidable rather than approximately true.

**Audit Trail** — filterable log of system actions (invoice created, payment created, supplier bill posted/paid, return approved/rejected/completed, journal posted), by date range, action and user.

---

## 16. Reports

**Entry:** Reports / Accounting menus

| Report | Date control | Export |
|---|---|---|
| **Daily Profit** | start + end | Print only |
| **Trial Balance** | as-of date | PDF ✓ · CSV ✗ |
| **Income Statement** | start + end | PDF ✓ · CSV ✗ |
| **Balance Sheet** | as-of date | ✗ (buttons present) |
| **Cash Flow Statement** | start + end | ✗ (buttons present) |

**Daily Profit** is the richest: an overall summary (net revenue, returns, profit, margin), then separate **Warehouse Sales** and **Retailer Sales** breakdowns, then a per-day detail table. It is read off the ledger — net revenue is `−(4000 + 4100 + 4200)` and cost of sales is `5000 + 5100`, summed over posted journal lines — so it is net of returns and agrees with the Income Statement. Per-product and per-location rows come from the `product` and `location` dimensions those lines carry. Only posted documents count: an order that has not been invoiced is not yet revenue.

> ⚠️ All four financial statements show PDF and CSV buttons, but **CSV is not implemented anywhere** (it flashes an error), and **Balance Sheet and Cash Flow PDF export is dead** — the PDF methods exist in the controller but have no routes.

---

## 17. Users, Roles & Permissions

**Entry:** `/users` · **Controller:** `UserController` · **Login:** `Auth\LoginController` · **Permission:** `users.view` / `.manage`

Session login, roles and a permission catalogue. There is **no self-registration and no password reset** by design: accounts are created by an administrator on the Users screen.

**How access is enforced**

Every route file is registered in one place, `bootstrap/app.php`:

- `routes/auth.php` gets the `web` group only, so the login page stays reachable.
- `routes/web.php` gets `web` + `auth` + `EnforceRoutePermissions`.
- `routes/api.php` gets session + CSRF + `auth` + `EnforceRoutePermissions` under an `api` prefix. It is called by `fetch()` from Blade pages, so it authenticates by session cookie — otherwise it would be an unauthenticated way around every gate on the web side.

`EnforceRoutePermissions` applies one table, `App\Support\RoutePermissions`, rather than a middleware argument written out route by route. The rule, unless the table overrides it: a GET needs `<module>.view` **or** `<module>.manage`; anything else needs `<module>.manage`. A read is satisfied by either because a manage holder should not have to be granted view as well.

Gating two hundred routes by hand would have meant two hundred places for the next one to be forgotten, which is what happened before: the permission catalogue described the whole application while only nine of its permissions were ever checked, and everything else was gated by an `@can` in the sidebar — which hides a link and stops nobody who types the URL.

`RoutePermissionCoverageTest` is what stops that coming back. It walks every registered route and fails the build if one is neither in the table nor gated on itself. Only `login`, `logout` and the `/up` health check are allowed to answer before auth.

The check runs **before** route-model binding (`prependToPriorityList` in `bootstrap/app.php`). Otherwise binding 404s on a missing id before the gate runs, and the same request answers 404 or 403 depending on what is in the database — telling a user with no rights to a record whether it exists.

**Flow**

1. A guest hitting any URL is redirected to `/login`. `POST /login` is rate limited to 5 attempts a minute, keyed on the submitted email plus the IP, so guessing one account's password cannot lock out everybody else behind the same address.
2. On success the session id is rotated, so a pre-login session cannot be replayed.
3. `/users` lists accounts; create, edit and delete each need `users.manage`, while reading the list needs only `users.view` — separated so a read-only supervisor role can be granted one without the other.
4. The sidebar is gated by the same permissions, so it shows only what the signed-in user can reach.

**Seeded access.** `RolePermissionSeeder` creates the permission catalogue (grouped Dashboard, Catalog, Master Data, Procurement, Picking & Transfers, Returns, Stock, Sales, Accounting, Reports, Reference, Administration) and an `admin` role holding all of it. `UserSeeder` promotes user #1 to `admin@example.com` rather than creating a second user, because roughly thirty call sites across observers and services attribute their work with `auth()->id() ?? 1` and the `audit_logs` foreign key points at that same row.

---

## 18. Reference › Action Effects

**Entry:** `/reference/action-effects` · **Controller:** `ActionEffectsController` · **Source:** `App\Support\Nav\ActionCatalog` · **Permission:** `reference.view`

A reference page documenting what each action in the application triggers elsewhere — which modules a confirm, a post or an approve writes into.

The architecture puts side effects in observers, which means the chain is not visible from the controller: you have to know the observers exist. This page is the answer to that, rendered straight from `ActionCatalog` — the same table the post-action notification panel reads its headline from, so the two cannot drift. `ActionCatalogIntegrityTest` checks the catalogue against the routes it names.

After an action runs, `TrackTriggeredEffects` records what it touched and the layout badges the affected sidebar entries, so the user sees where the work landed without being told to go looking.

---

## 19. Features present in the UI but not functional

These render, are linked, or look implemented — but do nothing. Worth fixing or hiding.

**Stubbed screens:**

- **Warehouse Receiving** (`/warehouse/receiving/*`) — every route returns the same empty view. Not linked in the nav; the routes exist only to prevent "route not defined" errors.
- **Stock Locations edit** — `StockLocationController::edit()` returns the *create* form, commented in the code as a placeholder. There is no `stock-locations/edit.blade.php`.

**Implemented but not routed** (the method exists and works; nothing can call it):

- Stock Management CSV export — `StockManagementController::export()`.
- Balance Sheet and Cash Flow PDF — `ReportController::balanceSheetPdf()` and `::cashFlowStatementPdf()`.

**Silently ignored:**

- **Trial Balance / Income Statement CSV.** Both validate `export` as `in:pdf,csv`, so `?export=csv` is accepted — but only the `pdf` branch is written. A CSV request falls through and renders the ordinary HTML page, which looks like the export failed to download rather than like it was never built.
- **Credit Note / Debit Note "Download PDF"** returns `view('credit-notes.pdf')` — an HTML page, not a PDF. DomPDF is already wired up for invoices and reports; this one was never converted.

**Unreachable:** `resources/views/test.blade.php` — a scratch view no route renders.

---

## 20. Known correctness issues

Flagged for triage — these affect data integrity, not just UI polish. Every entry here has been checked against the code; issues listed in earlier revisions of this document and since fixed have been removed rather than left standing.

**Two competing picking-list creation paths.** `OrderService::confirm()` and `OrderObserver::updated()` both create a picking list and reserve stock when an order is confirmed, with different scopes — the observer handles only retailer fulfilment. Both guard on an existing list, so no duplicate is written, but which one runs decides how the reservation is made, and the behaviour depends on call ordering rather than on a decision anybody made.

**The audit trail silently discards half of what it is given.** `StockTransaction::logStatusChange()` passes `old_values` and `new_values` to `AuditLog::create()`, but `AuditLog::$fillable` lists only `user_id`, `action`, `description`, `subject_type` and `subject_id`. Mass assignment drops the other two without error, so every return status change is logged with no before/after detail.

**`SupplyObserver` is registered but does not exist.** `AppServiceProvider` imports `App\Observers\SupplyObserver` and registers it behind a `class_exists()` guard, so the registration silently no-ops. Harmless today, and misleading to read.

**`ChartOfAccountsSeeder` runs on every request** from `AppServiceProvider::boot()`. It is idempotent (`firstOrCreate` / `updateOrInsert`), so it corrupts nothing, but it puts a table scan in front of every page load and belongs in migration or a deploy step.

---

## 21. Where the logic lives

| Concern | Location |
|---|---|
| Route authorisation table | `app/Support/RoutePermissions.php`, `app/Http/Middleware/EnforceRoutePermissions.php` |
| Session login | `app/Http/Controllers/Auth/LoginController.php`, `routes/auth.php` |
| Stock balance recalculation | `app/Observers/ProductStockObserver.php`, `StockTransactionObserver.php` |
| Physical stock movement | `app/Observers/PickingListObserver.php` |
| Return state machine + stock effects | `app/Models/StockTransaction.php` (`approve`/`reject`/`complete`/`updateProductStock`) |
| Return creation & validation | `app/Services/ReturnService.php` |
| Purchase order lifecycle | `app/Services/PurchaseOrderService.php` |
| Supply recording (against an order) | `app/Services/SupplyService.php`, `PurchaseOrderService::recordSupply()` |
| GRN posting → stock in **and** ledger | `app/Services/GrnService.php`, `app/Accounting/Posting/GoodsReceiptPostingRule.php` |
| Supplier bill auto-creation | `app/Observers/GrnObserver.php` |
| Pricing (set, resolve, lock) | `app/Services/SimplePricingService.php`, `PriceResolver`, `config/pricing.php` |
| Order confirm → reservation | `app/Services/OrderService.php` |
| Invoice generation | `app/Services/InvoiceService.php` |
| **What the ledger posts, and when** | `app/Accounting/Posting/` (one rule per document) + the registry in `AccountingServiceProvider` |
| Journal writing & balance validation | `app/Accounting/PostingEngine.php` |
| Reading the ledger (every balance, in SQL) | `app/Accounting/LedgerService.php` |
| Ledger vs. everything else | `app/Accounting/Reconciliation/ReconciliationService.php` |
| Reversing journals for returns | `app/Services/ReturnJournalHandler.php` |
| Workflow stage logic | `app/Support/` — one file per flow (`OrderWorkflow`, `SuppliesWorkflow`, `ReturnWorkflow`) |
| What an action triggers elsewhere | `app/Support/Nav/ActionCatalog.php` |
| **Warehouse→Retailer transfer workflow** | `routes/web.php` (closures) |
| Navigation menu | `resources/views/layouts/app.blade.php` |

> `app/Services/AccountingService.php` is **deprecated and not a code path** — nothing in `app/` calls it. It survives only because several test files find its "build me an entry from account codes" shape convenient. Write through `PostingEngine`, read through `LedgerService`.
