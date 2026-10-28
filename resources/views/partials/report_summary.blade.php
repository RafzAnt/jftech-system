{{-- Key figures above the tables of a printed report: $items = ['Total Bills' => '12', 'Paid' => ['₱1,000.00', '3 bill(s)']]. --}}
<div class="summary">
    @foreach ($items as $label => $value)
        <div>
            <span>{{ $label }}</span>
            <strong>{{ is_array($value) ? $value[0] : $value }}</strong>
            @if (is_array($value) && isset($value[1]))
                <small>{{ $value[1] }}</small>
            @endif
        </div>
    @endforeach
</div>
