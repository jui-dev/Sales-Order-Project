# Supplies Module

**What it's for:** buying stock from a vendor and getting it into your warehouse — from asking the vendor for goods, to receiving them, to paying for them.

---

## The big picture

The whole process moves through five stages, in order:

**Purchase Order → Record Supply → Receive Goods → Supplier Bill → Payment**

Everything starts with an order to a vendor and ends with a hand-off to accounting.

---

## Step-by-step walkthrough

### 1. Raise a purchase order
Choose the **vendor** you're buying from and the **warehouse** the goods should go to, then add your **product lines**. You can only order what that vendor actually carries, and each line's cost is filled in for you from the vendor's price list.

The order runs **Draft → Approved → Sent**. Only a draft can be edited, and only a *sent* order can have a delivery recorded against it.

!!! note "Every supply starts here"
    There is no way to record a delivery that nobody ordered. If goods turn up unexpectedly, raise the order first.

### 2. Record what turned up
Open a *sent* order and record the delivery. The vendor and warehouse come from the order and can't be changed, and the lines arrive already filled in with whatever is still outstanding — so you only adjust quantities and drop anything that didn't arrive.

You can receive **less** than you ordered; the order simply stays open for the rest. You can't receive more than you ordered, or add a line the order doesn't carry.

The supply is saved as *Pending*, and the order updates itself to *Partially Received* or *Received*.

The way in is **Requested Purchase Orders** on the Supplies page, which lists every order still waiting on a delivery with its own Record Supply button. The order's own page carries the same button.

!!! warning "Stock is not in inventory yet"
    Nothing has entered your warehouse at this point. You've only recorded what arrived at the door — the quantities are **not** yet part of your on-hand stock.

!!! note "Prices don't move here"
    Recording a supply does **not** change any product's price. Prices are set in **Catalog › Product Pricing** and nowhere else. What a delivery cost is recorded for stock valuation, but what somebody typed as the price stands until they change it.

### 3. Receive the goods
This is the moment the stock actually enters the warehouse. The receiving note is marked as received, and the quantities become **real, on-hand, and available to sell**.

It is also the moment the goods go on the books: their value is added to inventory, and the other side parks in *Goods Received Not Invoiced* until the vendor bills you for them. The physical and financial pictures move together, so they can't drift apart.

### 4. Supplier bill is created automatically
As soon as the goods are received, a **supplier bill** for the vendor is generated for you — with all the product lines and the total owed. You don't have to build it by hand.

Posting it clears *Goods Received Not Invoiced* into *Accounts Payable*. It does **not** touch inventory: the goods went into inventory when they physically arrived.

### 5. Post the bill, then pay it
Review the bill and **post** it to confirm what you owe, then **pay** it. This is where the Supplies module hands off to **accounting**.

---

## Status meanings

**Purchase order statuses**

| Status | What it means |
|--------|---------------|
| Draft | Being put together. Still editable. |
| Approved | Signed off internally, not yet sent to the vendor. |
| Sent | With the vendor. Deliveries can now be recorded against it. |
| Partially Received | Some of what was ordered has arrived; the rest is still outstanding. |
| Received | Everything ordered has arrived. |
| Cancelled | Called off before it was fulfilled. |

**Supply statuses**

| Status | What it means |
|--------|---------------|
| Pending | Just recorded — nothing received into the warehouse yet. |
| Confirmed | Details agreed and locked in. |
| Completed | Goods received and the supply is closed out. |

**Received-goods statuses**

| Status | What it means |
|--------|---------------|
| Draft | Receiving note prepared, goods not yet received. Stock is not in inventory. |
| Posted | Goods received. Stock is in the warehouse and available to sell. |

---

!!! note "Key thing to remember"
    Stock only becomes real and sellable at the **Receive Goods** step — **not** when the purchase order is raised, and **not** when the supply is first recorded. Those two steps record intent and arrival; nothing is in inventory until the goods are received.

---

!!! info "Older supplies"
    Supplies recorded before the purchase-order rule existed may have no order behind them. They still open and read normally.
