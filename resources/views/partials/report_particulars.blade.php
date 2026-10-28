{{--
    A two-column "Particulars | Figure" table of a summary report: $rows = ['Present' => '12'].
    $total (optional) = ['NET PAY', '₱3,000.00'] adds a TOTAL row at the bottom.
--}}
<table class="report-table particulars">
    <thead><tr><th>Particulars</th><th class="num">Figure</th></tr></thead>
    <tbody>
        @foreach ($rows as $label => $value)
            <tr><td>{{ $label }}</td><td class="num">{{ $value }}</td></tr>
        @endforeach
    </tbody>
    @if (! empty($total))
        <tfoot><tr><td>{{ $total[0] }}</td><td class="num">{{ $total[1] }}</td></tr></tfoot>
    @endif
</table>
