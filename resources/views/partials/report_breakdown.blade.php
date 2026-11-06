{{--
    A summary table of a printed report, one line per group (see App\Support\ReportBreakdown), with a TOTAL line:
      $heading       the heading above the table, e.g. 'By Payment Method'
      $labelHeading  the heading of the first column, e.g. 'Payment Method'
      $groups        [['label' => 'GCash', 'count' => 3, 'amount' => 1500.0]]
      $columns       [['count', 'No. of Payments', 'int'], ['amount', 'Amount', 'peso']]; types: int, peso, hours
      $share         optional: the column the "% of Total" column is worked out from, e.g. 'amount'
      $empty         optional: what to say when there is nothing to list
      $totalLabel    optional: the label of the TOTAL line (default TOTAL)
--}}
@php
    $format = fn (string $type, $value) => match ($type) {
        'peso' => '₱' . number_format((float) $value, 2),
        'hours' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') ?: '0',
        default => number_format((float) $value),
    };
    $totals = [];
    foreach ($columns as [$key]) {
        $totals[$key] = array_sum(array_column($groups, $key));
    }
    $columnCount = count($columns) + 1 + (isset($share) ? 1 : 0);
@endphp
<section class="report-section">
    <div class="section-heading">{{ $heading }}</div>
    <table class="report-table">
        <thead>
            <tr>
                <th>{{ $labelHeading }}</th>
                @foreach ($columns as [$key, $title])
                    <th class="num">{{ $title }}</th>
                @endforeach
                @if (isset($share))<th class="num">% of Total</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                <tr>
                    <td>{{ $group['label'] }}</td>
                    @foreach ($columns as [$key, $title, $type])
                        <td class="num">{{ $format($type, $group[$key]) }}</td>
                    @endforeach
                    @if (isset($share))<td class="num">{{ ($totals[$share] ?? 0) > 0 ? number_format($group[$share] / $totals[$share] * 100, 1) . '%' : '—' }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $columnCount }}" class="empty">{{ $empty ?? 'Nothing to report.' }}</td></tr>
            @endforelse
        </tbody>
        @if ($groups)
            <tfoot>
                <tr>
                    <td>{{ $totalLabel ?? 'TOTAL' }}</td>
                    @foreach ($columns as [$key, $title, $type])
                        <td class="num">{{ $format($type, $totals[$key]) }}</td>
                    @endforeach
                    @if (isset($share))<td class="num">{{ ($totals[$share] ?? 0) > 0 ? '100.0%' : '—' }}</td>@endif
                </tr>
            </tfoot>
        @endif
    </table>
</section>
