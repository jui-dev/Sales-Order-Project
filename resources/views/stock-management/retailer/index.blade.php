@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Retailer Stock Management</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Retailer</th>
                                    <th>Product</th>
                                    <th>Transaction Type</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Value</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                <tr>
                                    <td>{{ $transaction->transfer_date->format('Y-m-d H:i') }}</td>
                                    <td>{{ $transaction->retailer->name }}</td>
                                    <td>{{ $transaction->product->name }}</td>
                                    <td>
                                        <span class="badge bg-{{ $transaction->transaction_type === 'transfer_in' ? 'success' : 'danger' }}">
                                            {{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                                        </span>
                                    </td>
                                    <td>{{ $transaction->stock_quantity ?? $transaction->quantity }}</td>
                                    <td>{{ number_format($transaction->unit_cost, 2) }}</td>
                                    <td>{{ number_format($transaction->total_value, 2) }}</td>
                                    <td>{{ $transaction->reference_number }}</td>
                                    <td>
                                        <span class="badge bg-{{ $transaction->status === 'completed' ? 'success' : 'warning' }}">
                                            {{ ucfirst($transaction->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $transaction->notes }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $transactions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 