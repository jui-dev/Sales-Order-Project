@props(['stages'])

{{--
    Position of a record in the supplies workflow. Build $stages with
    App\Support\SuppliesWorkflow so every page in the chain agrees.

    Numbered markers are used because the stages are a genuine sequence -
    the order is information the reader needs, not decoration.
--}}
<div class="detail-card mb-4">
    <div class="detail-card__body">
        <div class="workflow-rail">
            @foreach($stages as $index => $stage)
                {{-- An <a> without href stays plain text, so one element covers
                     both linked and unlinked stages. --}}
                <a class="workflow-stage workflow-stage--{{ $stage['state'] }}"
                   @if($stage['url']) href="{{ $stage['url'] }}" @endif>
                    <span class="workflow-stage__marker">
                        @if($stage['state'] === 'done')
                            <i class="bi bi-check-lg"></i>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </span>
                    <span class="workflow-stage__name">{{ $stage['name'] }}</span>
                    <span class="workflow-stage__meta">{{ $stage['meta'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
