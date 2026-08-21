<?php

namespace App\Http\Requests;

use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceListRowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorisation is applied by the route's permission middleware.
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array'],
            'rows.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Translate the posted rows into what PriceListService::bulkSet expects.
     *
     * Rows are keyed by price_list_item id, and re-scoped to the list being
     * edited before anything is written - a tampered form must not be able to
     * reprice a row belonging to somebody else's list. The same guard
     * VendorService::updatePriceList applies.
     *
     * @return array<int, array{product_id: int, unit_price: float, min_quantity: int}>
     */
    public function rowsForService(PriceList $list): array
    {
        $posted = $this->input('rows', []);

        $items = PriceListItem::whereIn('id', array_keys($posted))
            ->where('price_list_id', $list->id)
            ->whereNull('ends_at')
            ->get();

        return $items->map(fn (PriceListItem $item) => [
            'product_id' => $item->product_id,
            'unit_price' => (float) $posted[$item->id]['unit_price'],
            'min_quantity' => (int) $item->min_quantity,
        ])->all();
    }
}
