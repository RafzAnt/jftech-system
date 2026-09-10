@php
    $status = strtolower((string) ($status ?? ''));
    $badge = 'badge-secondary';
    $label = ucfirst($status);

    if (in_array($status, ['paid', 'completed', 'approved', 'success', 'active', 'present'], true)) {
        $badge = 'badge-success';
    } elseif (in_array($status, ['pending'], true)) {
        $badge = 'badge-warning';
    } elseif (in_array($status, ['cancelled', 'denied', 'failed', 'terminated', 'inactive', 'suspended', 'absent'], true)) {
        $badge = 'badge-danger';
    } elseif (in_array($status, ['overdue', 'late'], true)) {
        $badge = 'badge-warning';
    } elseif (in_array($status, ['processed'], true)) {
        $badge = 'badge-info';
    }
@endphp

<span class="badge {{ $badge }}">{{ $label }}</span>
