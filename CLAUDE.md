# Project Guide

> **Note:** This project is a work in progress. Many modules are still changing, so this file intentionally documents **only the Supplies module** for now. It is not a full summary of the project. More will be added as other parts settle down.

---

## Supplies Module

**What it's for:** buying stock from a vendor and getting it into the warehouse.

The process moves through four stages, in order:

**Record Supply → Receive Goods → Supplier Bill → Payment**

1. **Record Supply** — Pick the vendor, the warehouse, the date, and the products being bought (each with a quantity and a cost). The supply starts as *Pending*. Recording a product's cost here also refreshes that product's price automatically.

2. **Receive Goods** — When the goods actually arrive, they are marked as received. This is the step that puts the stock into the warehouse and makes it available to sell.

3. **Supplier Bill** — Once the goods are received, a bill for the vendor is created automatically, listing everything supplied and the total owed.

4. **Payment** — The bill is posted (to confirm what's owed) and then paid. This hands off to accounting.

---

### Key thing to remember

Stock only becomes real and sellable at the **Receive Goods** step — **not** when the supply is first recorded. Recording a supply only notes what's been ordered; nothing is in inventory until the goods are received.
