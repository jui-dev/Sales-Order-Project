{{--
    Stock flow diagram.

    The routes stock takes are a branch, not a line: everything arrives at a
    warehouse, and leaves it either shipped direct through the online platform
    or pushed out to a retailer who serves the customer locally. The old
    version drew that with Bootstrap columns, offsets and blank spacer cells,
    so the "arrows" never met the boxes they pointed at. One SVG on a fixed
    coordinate grid puts every connector exactly where it belongs.

    Needs $warehouses and $retailers (collections) from the parent view.
--}}
@php
    // Left to right on a 900x330 grid. Every box is 170x76, so its centre is
    // x + 85, and the connector geometry below is written against these.
    $nodes = [
        [
            'x' => 16, 'y' => 127,
            'title' => 'Vendors',
            'sub' => 'Supply products in',
            'fill' => '#eef3f8', 'stroke' => '#c3d5e6',
            'ink' => '#35597f', 'ink_muted' => '#617f9d',
        ],
        [
            // The hub, so it carries the only solid fill on the diagram.
            'x' => 248, 'y' => 127,
            'title' => 'Warehouses',
            'sub' => $warehouses->count() . ' stock ' . \Illuminate\Support\Str::plural('location', $warehouses->count()),
            'fill' => '#2c6e49', 'stroke' => '#2c6e49',
            'ink' => '#ffffff', 'ink_muted' => 'rgba(255,255,255,0.78)',
        ],
        [
            'x' => 480, 'y' => 28,
            'title' => 'Online Platform',
            'sub' => 'Shipped from warehouse',
            'fill' => '#e9f5f4', 'stroke' => '#b5ddd8',
            'ink' => '#227a6e', 'ink_muted' => '#4f8b84',
        ],
        [
            'x' => 480, 'y' => 226,
            'title' => 'Retailers',
            'sub' => $retailers->count() . ' retail ' . \Illuminate\Support\Str::plural('partner', $retailers->count()),
            'fill' => '#fdf4e6', 'stroke' => '#eeddb6',
            'ink' => '#8f6a17', 'ink_muted' => '#8a7440',
        ],
        [
            'x' => 712, 'y' => 127,
            'title' => 'Customers',
            'sub' => 'End recipients',
            'fill' => '#fdeee9', 'stroke' => '#f3cec0',
            'ink' => '#ad4d2c', 'ink_muted' => '#96604c',
        ],
    ];
@endphp

<div class="flow-diagram">
    <svg class="flow-diagram__canvas" viewBox="0 0 900 330" role="img"
         aria-labelledby="flowDiagramTitle flowDiagramDesc">
        <title id="flowDiagramTitle">Stock flow</title>
        <desc id="flowDiagramDesc">
            Vendors supply the warehouses. From a warehouse, stock either ships direct to
            customers through the online platform, or is distributed to retailers who
            deliver to customers locally.
        </desc>

        <defs>
            <marker id="flowArrow" viewBox="0 0 10 10" refX="9" refY="5"
                    markerWidth="5" markerHeight="5" orient="auto-start-reverse">
                <path d="M0,0 L10,5 L0,10 Z" fill="#93a89e"/>
            </marker>
        </defs>

        {{-- Connectors. Elbows are rounded so the branch reads as one path. --}}
        <g fill="none" stroke="#b8c8c0" stroke-width="2" stroke-linecap="round">
            <path d="M186,165 H244" marker-end="url(#flowArrow)"/>
            <path d="M418,165 H442 Q450,165 450,157 V74 Q450,66 458,66 H476" marker-end="url(#flowArrow)"/>
            <path d="M418,165 H442 Q450,165 450,173 V256 Q450,264 458,264 H476" marker-end="url(#flowArrow)"/>
            <path d="M650,66 H674 Q682,66 682,74 V157 Q682,165 690,165 H708" marker-end="url(#flowArrow)"/>
            <path d="M650,264 H674 Q682,264 682,256 V173 Q682,165 690,165 H708" marker-end="url(#flowArrow)"/>
        </g>

        {{-- What each edge does, since the box names only say where stock lands --}}
        <g class="flow-diagram__edge-label">
            <text x="215" y="156" text-anchor="middle">supplies</text>
            <text x="457" y="118">ships direct</text>
            <text x="457" y="214">distributes</text>
        </g>

        @foreach($nodes as $node)
            <g>
                <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="170" height="76" rx="12"
                      fill="{{ $node['fill'] }}" stroke="{{ $node['stroke'] }}" stroke-width="1.5"/>
                <text class="flow-diagram__node-title" x="{{ $node['x'] + 85 }}" y="{{ $node['y'] + 32 }}"
                      text-anchor="middle" fill="{{ $node['ink'] }}">{{ $node['title'] }}</text>
                <text class="flow-diagram__node-sub" x="{{ $node['x'] + 85 }}" y="{{ $node['y'] + 53 }}"
                      text-anchor="middle" fill="{{ $node['ink_muted'] }}">{{ $node['sub'] }}</text>
            </g>
        @endforeach
    </svg>
</div>

@once
@push('styles')
<style>
/* The diagram keeps its aspect ratio and scrolls sideways rather than
   shrinking its labels past reading size on a narrow screen. */
.flow-diagram {
    overflow-x: auto;
    padding-bottom: 0.25rem;
}

.flow-diagram__canvas {
    display: block;
    width: 100%;
    min-width: 620px;
    height: auto;
    font-family: inherit;
}

.flow-diagram__node-title {
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.01em;
}

.flow-diagram__node-sub {
    font-size: 11.5px;
    font-weight: 400;
}

.flow-diagram__edge-label {
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.04em;
    fill: #7d8f86;
}
</style>
@endpush
@endonce
