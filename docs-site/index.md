# Supplies Module

**What it's for:** buying stock from a vendor and getting it into your warehouse — from recording the purchase, to receiving the goods, to paying the vendor.

---

## The big picture

The whole process moves through four stages, in order:

**Record Supply → Receive Goods → Supplier Bill → Payment**

Everything starts with a vendor and ends with a hand-off to accounting.

---

## Step-by-step walkthrough

### 1. Record a new supply
Choose the **vendor** you're buying from, the **warehouse** the goods will go to, the **date**, and add your **product lines** — each with a **quantity** and a **unit cost**. The supply is saved as *Pending* and its total cost is added up for you.

When you record the cost of a product here, that product's purchase price is refreshed automatically, and its selling price is recalculated from your markup. So supplying stock keeps your pricing up to date.

### 2. Confirm / complete the supply
Once the details are right, you move the supply forward. A **receiving note** is prepared so the warehouse team knows what to expect.

!!! warning "Stock is not in inventory yet"
    At this point nothing has arrived. You've only agreed what's coming — the quantities are **not** yet part of your on-hand stock.

### 3. Receive the goods
This is the moment the stock actually enters the warehouse. The receiving note is marked as received, and the quantities become **real, on-hand, and available to sell**. This is the step that changes your inventory.

### 4. Supplier bill is created automatically
As soon as the goods are received, a **supplier bill** for the vendor is generated for you — with all the product lines and the total owed. You don't have to build it by hand.

### 5. Post the bill, then pay it
Review the bill and **post** it to confirm what you owe, then **pay** it. This is where the Supplies module hands off to **accounting**.

---

## Status meanings

**Supply statuses**

| Status | What it means |
|--------|---------------|
| Pending | Just recorded — nothing received yet. |
| Processing | Being worked on / prepared. |
| Confirmed | Details agreed and locked in. |
| Completed | Goods received and the supply is closed out. |

**Received-goods statuses**

| Status | What it means |
|--------|---------------|
| Draft | Receiving note prepared, goods not yet received. Stock is not in inventory. |
| Posted | Goods received. Stock is now in the warehouse and available to sell. |

---

!!! note "Key thing to remember"
    Stock only becomes real and sellable at the **Receive Goods** step — **not** when the supply is first recorded. Recording a supply only notes what's been ordered; nothing is in inventory until the goods are received.

---

## For the project owner

!!! danger "Needs attention: duplicate supply routes"
    The Supplies feature currently has a **second, duplicate set of routes** (leftover placeholder/scaffolding) sitting alongside the real, working one. These extra entries don't do anything useful and can clash with the real flow. Recommend cleaning them up before the module is finished, to avoid confusion and hard-to-trace bugs.
