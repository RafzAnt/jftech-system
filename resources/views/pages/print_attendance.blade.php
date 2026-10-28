@extends('layouts.print_report')

@php
    $reportTitle = 'Attendance Report';
    // What was typed in the search box is part of what the report covers.
    $reportCoverage = $periodLabel . (($location['searchTerm'] ?? '') !== '' ? ' · Search: ' . $location['searchTerm'] : '');
    $reportBranch = \App\Support\ReportFormat::branch($selectedBranch ?? null);
    // Waits a moment so the QR codes are drawn before the print dialog opens.
    $printDelay = 300;
    $time = fn ($value) => $value ? date('h:i A', strtotime($value)) : '—';
@endphp

@push('head')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
@endpush

@section('content')
    @include('partials.report_summary', ['items' => [
        'Total Entries' => number_format(count($attendanceRecords)),
        'Present' => number_format($totalPresent),
        'Late' => number_format($totalLate),
        'Absent' => number_format($totalAbsent),
    ]])

    @if (! empty($attendanceQrCodes))
        <section class="qr-codes" aria-label="Attendance QR codes">
            @foreach ($attendanceQrCodes as $qr)
                <div class="qr-code">
                    <strong>{{ strtoupper($qr['qr_type']) }} QR CODE</strong>
                    <div id="attendanceQr{{ ucfirst($qr['qr_type']) }}"></div>
                    <small>{{ $qr['qr_code'] }}</small>
                    <small>Valid: {{ date('h:i A', strtotime($qr['valid_from'])) }} – {{ date('h:i A', strtotime($qr['valid_until'])) }}</small>
                </div>
            @endforeach
        </section>
    @endif

    <table class="report-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th>Date</th>
                <th>Employee</th>
                <th>Position</th>
                <th>Branch</th>
                <th>Morning In</th>
                <th>Morning Out</th>
                <th>Afternoon In</th>
                <th>Afternoon Out</th>
                <th>Overtime</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendanceRecords as $att)
                <tr>
                    <td class="no">{{ $loop->iteration }}</td>
                    <td>{{ date('M j, Y', strtotime($att['attendance_date'])) }}</td>
                    <td>{{ $att['full_name'] }}<br><small>{{ $att['employee_code'] }}</small></td>
                    <td>{{ $att['position'] ?: '—' }}</td>
                    <td>{{ $att['branch_name'] ?: '—' }}</td>
                    <td>{{ $time($att['morning_time_in']) }}</td>
                    <td>{{ $time($att['morning_time_out']) }}</td>
                    <td>{{ $time($att['afternoon_time_in']) }}</td>
                    <td>{{ $time($att['afternoon_time_out']) }}</td>
                    <td>{{ ! empty($att['overtime_time_in']) && ! empty($att['overtime_time_out']) ? $time($att['overtime_time_in']) . ' - ' . $time($att['overtime_time_out']) . ' (' . (int) $att['overtime_minutes'] . ' min)' : '—' }}</td>
                    <td>
                        @if ($att['status_morning'] === 'present' || $att['status_afternoon'] === 'present')
                            Present
                        @elseif ($att['status_morning'] === 'late' || $att['status_afternoon'] === 'late')
                            Late
                        @else
                            Absent
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="empty">No attendance records found.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="10">TOTAL ENTRIES</td>
                <td class="center">{{ number_format(count($attendanceRecords)) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection

@push('scripts')
    <script>
        @foreach ($attendanceQrCodes as $qr)
        new QRCode(document.getElementById('attendanceQr{{ ucfirst($qr['qr_type']) }}'), {
            text: @json($qr['qr_code']),
            width: 104,
            height: 104,
            colorDark: '#0a1628',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
        @endforeach
    </script>
@endpush
