@extends('layouts.print_report')

@php
    $reportTitle = 'Attendance Report';
    // What was typed in the search box is part of what the report covers.
    $reportCoverage = $periodLabel . (($location['searchTerm'] ?? '') !== '' ? ' · Search: ' . $location['searchTerm'] : '');
    $reportBranch = \App\Support\ReportFormat::branch($selectedBranch ?? null);
    // Waits a moment so the QR codes are drawn before the print dialog opens.
    $printDelay = 300;
    $hoursOf = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.') ?: '0';
@endphp

@push('head')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
@endpush

@section('content')

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

    @include('partials.report_totals', ['items' => [
        'Employees on record' => number_format($employeesCounted),
        'Total Entries' => number_format(count($attendanceRecords)),
        'Present' => number_format($totalPresent),
        'Late' => number_format($totalLate),
        'Absent' => number_format($totalAbsent),
        'Days with overtime' => number_format($overtimeDays),
        'Overtime worked' => \App\Support\PayrollCalculator::duration($overtimeMinutes),
        'Total Hours Worked' => $hoursOf($totalHours),
    ]])
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
