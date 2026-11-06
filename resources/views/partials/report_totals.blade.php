{{-- The totals at the bottom of a printed report: $items = ['Total Bills' => '12', 'Paid' => ['₱1,000.00', '3 bill(s)']]; an array value is [figure, note]. More than 4 lines are set in two columns to keep the report on one page. --}}
@php($columns = count($items) > 4 ? array_chunk($items, (int) ceil(count($items) / 2), true) : [$items])
<section class="report-section">
    <div class="section-heading">Totals</div>
    <div class="{{ count($columns) > 1 ? 'pair' : '' }}">
        @foreach ($columns as $column)
            <table class="report-table particulars">
                <thead><tr><th>Details</th><th class="num">Amount / Count</th></tr></thead>
                <tbody>
                    @foreach ($column as $label => $value)
                        <tr>
                            <td>{{ $label }}@if (is_array($value) && isset($value[1])) <small>({{ $value[1] }})</small>@endif</td>
                            <td class="num">{{ is_array($value) ? $value[0] : $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    </div>
</section>
