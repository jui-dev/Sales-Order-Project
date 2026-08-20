<?php

namespace App\Http\Requests;

/**
 * Same shape as creating one: a draft order's lines can be rewritten wholesale.
 *
 * The vendor is re-validated against its price list by the inherited after()
 * hook, because changing the vendor on a draft can strand lines they do not carry.
 */
class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest
{
}
