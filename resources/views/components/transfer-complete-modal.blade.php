@props([
    'pickingList',
    'fromName' => 'the warehouse',
    'toName'   => 'the retailer',
    'units'    => 0,
])

{{--
    Completion confirmation for a warehouse -> retailer transfer.

    Rendered from both the transfer listing and the transfer detail page, so the
    wording and the action are identical wherever the reader completes from. The
    id is keyed on the picking list because the listing renders one per row.
--}}
<div class="modal fade" id="completeTransferModal{{ $pickingList->id }}" tabindex="-1"
     aria-labelledby="completeTransferModalLabel{{ $pickingList->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="completeTransferModalLabel{{ $pickingList->id }}">Complete Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge bg-info">Confirmed</span>
                    <i class="bi bi-arrow-right text-muted"></i>
                    <span class="badge bg-success">Completed</span>
                </div>
                <p class="mb-2">
                    Sends all {{ number_format($units) }} {{ Str::plural('unit', $units) }}
                    to {{ $toName }}.
                </p>
                <p class="text-muted small mb-0">
                    Stock leaves {{ $fromName }} immediately and the reservation is released.
                    This cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep confirmed</button>
                <form action="{{ route('stock-transfers.warehouse-to-retailer.quick-complete', $pickingList) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Complete Transfer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
