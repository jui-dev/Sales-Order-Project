# Supplies Module — Workflow Guide

**Purpose:** The Supplies module is how you buy stock from a vendor and get it into your warehouse — from recording the purchase, to receiving the goods, to paying the vendor.

---

## The big picture

The whole process moves through five stages, in order:

**Purchase Order → Record Supply → Receive Goods → Supplier Bill → Payment**

Everything starts with a vendor and ends with a hand-off to accounting.

The first stage is optional in practice — a supply can still be recorded on its own for a delivery that arrived without an order behind it.

---

## Step-by-step walkthrough

1. **Raise a purchase order.** *(optional)*
   Choose the **vendor** and the **warehouse** the goods should be delivered to, then add the products you want. You can only order what that vendor actually carries, and each line's cost is filled in from the vendor's agreed price — you can still change it for a one-off deal. The order is saved as a **Draft**, then **Approved** and **Sent** to the vendor. Nothing has moved yet; you have only asked.

2. **Record a new supply.**
   Choose the **vendor** you're buying from, the **warehouse** the goods will go to, the **date**, and add your **product lines** — each with a **quantity** and a **unit cost**. The supply is saved with a *Pending* status and its total cost is added up for you.
   *Good to know:* recording a supply does **not** change any prices. Ordering something, or writing down that it turned up, is not the same as having it — pricing only moves at the **Receive Goods** step below.

3. **Confirm / complete the supply.**
   Once the details are right, you move the supply forward. A **receiving note** (the goods-received document) is prepared so the warehouse team knows what to expect.
   ⚠️ **Important:** at this point the stock is *not* in your inventory yet. Nothing has arrived — you've only agreed what's coming.

4. **Receive the goods.**
   This is the moment the stock actually enters the warehouse. The receiving note is marked as received, and the quantities become **real, on-hand, and available to sell**. This is the step that changes your inventory.

5. **Supplier bill is created automatically.**
   As soon as the goods are received, a **supplier bill** for the vendor is generated for you — with all the product lines and the total owed. You don't have to build it by hand.

6. **Post the bill, then pay it.**
   Review the bill and **post** it to confirm what you owe, then **pay** it. This is where the Supplies module hands off to **accounting**.

---

## Status meanings

**Supply statuses**

| Status | What it means |
|--------|---------------|
| Pending | Just recorded — nothing received yet. |
| Confirmed | Details agreed and locked in. |
| Completed | Goods received and the supply is closed out. |
| Cancelled | Abandoned; will never be received. |

**Purchase order statuses**

| Status | What it means |
|--------|---------------|
| Draft | Being put together; lines can still change. |
| Approved | Signed off internally. The lines are now fixed. |
| Sent | With the vendor, waiting for delivery. |
| Partially Received | Some of what you ordered has arrived; the rest is still outstanding. |
| Received | Everything ordered has arrived. |
| Cancelled | Abandoned; will never be received. |

**Received-goods statuses**

| Status | What it means |
|--------|---------------|
| Draft | Receiving note prepared, goods not yet received. Stock is not in inventory. |
| Posted | Goods received. Stock is now in the warehouse and available to sell. |

---

## 🔑 Key thing to remember

> **Stock only becomes real and sellable at the "Receive Goods" step.**
> Recording a supply does *not* add anything to your inventory — it just records what you've ordered. The quantities count as on-hand stock only once the goods are received (the receiving note is *Posted*).

---

## ✅ Recently resolved

The Supplies feature used to carry a **second, duplicate set of routes** — leftover placeholder scaffolding — alongside the real one. Because the duplicates were registered last, they won: "Mark Completed" flashed a success message and changed nothing. Those placeholders have been removed, so the buttons now do what they say.

A "Processing" supply status was also documented and colour-coded in the UI, but was never a valid value in the database and nothing ever wrote it. It has been dropped.
