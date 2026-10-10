@php
    $auditFilters = $filters ?? [];
    $actionOptions = [
        'created_price' => 'Created Price',
        'updated_price' => 'Updated Price',
        'changed_price' => 'Changed Price',
        'added_discount' => 'Added Discount',
        'updated_discount' => 'Updated Discount',
        'removed_discount' => 'Removed Discount',
        'scheduled_price' => 'Scheduled Price',
        'activated_price' => 'Activated Price',
        'deactivated_price' => 'Deactivated Price',
        'bulk_price_update' => 'Bulk Price Update',
    ];
@endphp

<!-- Audit Filters -->
<div style="margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="view" value="audit">

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <select name="action" class="form-select" style="font-size: 0.85rem; padding: 0.5rem;">
                <option value="">All Actions</option>
                @foreach ($actionOptions as $val => $label)
                <option value="{{ $val }}" {{ ($auditFilters['action'] ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <input type="date" name="date_from" class="form-input" value="{{ $auditFilters['date_from'] ?? '' }}" style="font-size: 0.85rem; padding: 0.5rem;">
        </div>

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <input type="date" name="date_to" class="form-input" value="{{ $auditFilters['date_to'] ?? '' }}" style="font-size: 0.85rem; padding: 0.5rem;">
        </div>

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <select name="admin_id" class="form-select" style="font-size: 0.85rem; padding: 0.5rem;">
                <option value="">All Admins</option>
                @foreach (($admins ?? []) as $admin)
                <option value="{{ $admin->id }}" {{ ($auditFilters['admin_id'] ?? '') == $admin->id ? 'selected' : '' }}>{{ htmlspecialchars($admin->full_name) }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <a href="price_management.php?view=audit" class="btn btn-sm btn-secondary">Reset</a>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Audit Log</span>
        <span class="badge badge-info" style="font-size: 0.75rem;">Read-Only</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($logs ?? []) as $log)
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $log['created_at'] ? date('M d, Y h:i A', strtotime($log['created_at'])) : '-' }}</td>
                        <td>{{ htmlspecialchars($log['admin_name'] ?? 'System') }}</td>
                        <td>
                            @php
                                $actionLabels = [
                                    'created_price' => 'Created Price',
                                    'updated_price' => 'Updated Price',
                                    'changed_price' => 'Changed Price',
                                    'added_discount' => 'Added Discount',
                                    'updated_discount' => 'Updated Discount',
                                    'removed_discount' => 'Removed Discount',
                                    'scheduled_price' => 'Scheduled Price',
                                    'activated_price' => 'Activated Price',
                                    'deactivated_price' => 'Deactivated Price',
                                    'bulk_price_update' => 'Bulk Price Update',
                                ];
                                $label = $actionLabels[$log['action'] ?? ''] ?? ucfirst($log['action'] ?? '');
                            @endphp
                            <span class="badge badge-secondary" style="font-size: 0.7rem;">{{ $label }}</span>
                        </td>
                        <td><strong>{{ htmlspecialchars($log['product_name'] ?? 'N/A') }}</strong></td>
                        <td>{{ htmlspecialchars($log['sku'] ?? 'N/A') }}</td>
                        <td style="color: var(--text-muted); font-size: 0.8rem; max-width: 200px;">
                            @if ($log['old_value'])
                                @php
                                    $oldVal = json_decode($log['old_value'], true);
                                @endphp
                                @if (is_array($oldVal))
                                    @foreach ($oldVal as $k => $v)
                                        <div>{{ $k }}: {{ $v }}</div>
                                    @endforeach
                                @else
                                    {{ htmlspecialchars($log['old_value']) }}
                                @endif
                            @else
                                <span style="color: var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.8rem; max-width: 200px;">
                            @if ($log['new_value'])
                                @php
                                    $newVal = json_decode($log['new_value'], true);
                                @endphp
                                @if (is_array($newVal))
                                    @foreach ($newVal as $k => $v)
                                        <div>{{ $k }}: {{ $v }}</div>
                                    @endforeach
                                @else
                                    {{ htmlspecialchars($log['new_value']) }}
                                @endif
                            @else
                                <span style="color: var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.8rem; max-width: 200px;">{{ htmlspecialchars($log['reason'] ?? '—') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem;">No audit log entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
