{{--
    One section's worth of locations as a list.

    Used by both the Warehouses and Retail Partners sections, which differ only
    in their heading - the rows themselves carry no type, since the section
    above them already says what these are.

    Expects: $locations (Collection)
--}}
<div class="table-responsive">
    <table class="table table-hover align-middle location-table mb-0">
        <thead>
            <tr>
                <th>Location</th>
                <th>Contact</th>
                <th class="text-end">Products</th>
                <th class="text-end">Stock</th>
                <th class="text-end">Movements</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($locations as $location)
                <tr class="location-row" data-location-name="{{ strtolower($location->name) }}">
                    <td data-label="Location">
                        <a href="{{ route('stock-locations.show', $location) }}" class="fw-semibold text-decoration-none">
                            {{ $location->name }}
                        </a>
                        <span class="d-block text-muted small">
                            ID {{ $location->id }}
                            @if($location->is_default)
                                · <span class="badge bg-light text-dark border">Default</span>
                            @endif
                        </span>
                    </td>
                    <td data-label="Contact">
                        @if($location->contact_person || $location->contact_number || $location->email)
                            @if($location->contact_person)
                                {{ $location->contact_person }}
                            @endif
                            <span class="d-block text-muted small">
                                {{ $location->contact_number ?: 'No phone' }}
                                @if($location->email)
                                    · {{ $location->email }}
                                @endif
                            </span>
                        @else
                            <span class="text-muted">No contact recorded</span>
                        @endif
                    </td>
                    <td data-label="Products" class="text-end">
                        {{ number_format($location->stockBalances ? $location->stockBalances->count() : 0) }}
                    </td>
                    <td data-label="Stock" class="text-end fw-semibold">
                        {{ number_format($location->stockBalances ? $location->stockBalances->sum('quantity') : 0) }}
                    </td>
                    <td data-label="Movements" class="text-end">
                        {{ number_format($location->stockTransactions ? $location->stockTransactions->count() : 0) }}
                    </td>
                    <td data-label="Status">
                        <span class="badge bg-{{ $location->status === 'active' ? 'success' : 'secondary' }}">
                            {{ ucfirst($location->status) }}
                        </span>
                    </td>
                    <td data-label="Actions" class="text-end">
                        {{-- Solid variants so each action reads by colour at rest,
                             rather than only once the pointer is over it. --}}
                        <div class="btn-group" role="group">
                            <a href="{{ route('stock-locations.show', $location) }}"
                               class="btn btn-sm btn-primary" title="View location">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('stock-locations.edit', $location) }}"
                               class="btn btn-sm btn-warning" title="Edit location">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if(! $location->is_default)
                                <form action="{{ route('stock-locations.destroy', $location) }}" method="POST"
                                      onsubmit="return confirm('Delete {{ $location->name }}? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete location">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
