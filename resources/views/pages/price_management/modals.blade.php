@php
    $currencyCode = strtoupper(trim((string) ($currency ?? 'PHP')));
    $currencySymbol = $currencyCode === 'PHP' ? '₱' : $currencyCode;
    $currencyPrefix = $currencyCode === 'PHP' ? '₱' : $currencyCode . ' ';
@endphp
@php
    $productsJson = $products ?? [];
    $discountTypes = [
        ['value' => 'none', 'label' => 'None'],
        ['value' => 'percentage', 'label' => 'Percentage'],
        ['value' => 'fixed', 'label' => 'Fixed Amount'],
    ];
    $changeReasons = [
        'Supplier price increase',
        'Cost adjustment',
        'Promotional pricing',
        'Market adjustment',
        'Other',
    ];
    $statusOptions = [
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'scheduled', 'label' => 'Scheduled'],
        ['value' => 'inactive', 'label' => 'Inactive'],
    ];
@endphp

<!-- ===== ADD/EDIT PRICE MODAL ===== -->
<div class="modal-overlay" id="addPriceModal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.5rem">
                @include('partials.modal_logo')
                <h3 class="modal-title" id="addPriceModalTitle">Add Price</h3>
            </div>
            <button class="modal-close" onclick="closeModal('addPriceModal')">&times;</button>
        </div>
        <form method="POST" action="price_management.php" class="needs-validation" onsubmit="return validatePriceForm()">
            @csrf
            <input type="hidden" name="action" id="addPriceAction" value="add_price">
            <input type="hidden" name="price_id" id="editPriceId">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Product *</label>
                    <input type="text" name="product_name_display" id="addProductName" class="form-input" placeholder="Select a product" readonly required style="cursor: pointer;" onclick="openProductSelector('add_price')">
                    <input type="hidden" name="product_id" id="addProductId" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Product Code</label>
                    <input type="text" name="sku_display" id="addProductSku" class="form-input" readonly style="background: var(--input-bg); color: var(--text-muted);">
                </div>
                <div class="form-group">
                    <label class="form-label">Base Price ({{ $currencySymbol }}) *</label>
                    <input type="number" name="base_price" id="basePriceInput" class="form-input" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency" id="currencyInput" class="form-input" value="{{ $currency }}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" id="discountTypeInput" class="form-select" onchange="toggleDiscountFields()">
                        @foreach ($discountTypes as $dt)
                        <option value="{{ $dt['value'] }}">{{ $dt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Value</label>
                    <div id="discountValueWrapper">
                        <input type="number" name="discount_value" id="discountValueInput" class="form-input" step="0.01" min="0" value="0" onchange="updateFinalPrice()">
                    </div>
                </div>
                <div class="form-group" style="background: rgba(0, 212, 255, 0.05); border-radius: 8px; padding: 0.75rem;">
                    <label class="form-label" style="color: var(--accent); margin-bottom: 0.25rem;">Final Price</label>
                    <div id="finalPriceDisplay" style="font-size: 1.25rem; font-weight: 700; color: var(--success);">{{ $currencyPrefix }}0.00</div>
                    <input type="hidden" name="final_price" id="finalPriceInput" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="effective_from" id="effectiveFromInput" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="effective_until" id="effectiveUntilInput" class="form-input" onchange="validateDateRange()">
                </div>
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" id="statusInput" class="form-select" required>
                        @foreach ($statusOptions as $so)
                        <option value="{{ $so['value'] }}">{{ $so['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason / Notes</label>
                    <input type="text" name="reason" class="form-input" placeholder="e.g. Supplier price increase">
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPriceModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Save Price</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== CHANGE PRICE MODAL ===== -->
<div class="modal-overlay" id="changePriceModal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.5rem">
                @include('partials.modal_logo')
                <h3 class="modal-title">Change Price</h3>
            </div>
            <button class="modal-close" onclick="closeModal('changePriceModal')">&times;</button>
        </div>
        <form method="POST" action="price_management.php" class="needs-validation" onsubmit="return validateChangePriceForm()">
            @csrf
            <input type="hidden" name="action" value="change_price">
            <input type="hidden" name="price_id" id="changePriceId">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <input type="text" id="changeProductName" class="form-input" readonly style="background: var(--input-bg); color: var(--text-muted);">
                </div>
                <div class="form-group">
                    <label class="form-label">Current Price ({{ $currencySymbol }})</label>
                    <input type="text" id="changeCurrentPrice" class="form-input" readonly style="background: var(--input-bg); color: var(--text-muted);">
                    <input type="hidden" name="previous_final_price" id="changePreviousFinalPrice">
                </div>
                <div class="form-group">
                    <label class="form-label">New Base Price ({{ $currencySymbol }}) *</label>
                    <input type="number" name="base_price" id="changeNewPrice" class="form-input" step="0.01" min="0" required>
                </div>
                <div class="form-group" style="background: rgba(0, 212, 255, 0.05); border-radius: 8px; padding: 0.75rem;">
                    <label class="form-label" style="color: var(--accent); margin-bottom: 0.25rem;">Difference</label>
                    <div id="changeDiff" style="font-size: 1.1rem; font-weight: 700;">₱ 0.00</div>
                    <div id="changePct" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">0% change</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" id="changeDiscountType" class="form-select" onchange="updateChangeFinalPrice()">
                        @foreach ($discountTypes as $dt)
                        <option value="{{ $dt['value'] }}" {{ $dt['value'] === 'none' ? 'selected' : '' }}>{{ $dt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Value</label>
                    <input type="number" name="discount_value" id="changeDiscountValue" class="form-input" step="0.01" min="0" value="0" onchange="updateChangeFinalPrice()">
                </div>
                <div class="form-group">
                    <label class="form-label">New Final Price</label>
                    <div id="changeFinalPriceDisplay" style="font-size: 1.25rem; font-weight: 700; color: var(--success);">₱ 0.00</div>
                    <input type="hidden" name="final_price" id="changeFinalPriceInput" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Reason *</label>
                    <select name="reason" id="changeReason" class="form-select" required>
                        @foreach ($changeReasons as $reason)
                        <option value="{{ $reason }}">{{ $reason }}</option>
                        @endforeach
                        <option value="Other">Other (specify below)</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" name="reason_custom" id="changeReasonCustom" class="form-input" placeholder="Enter custom reason" style="display: none;">
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('changePriceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== DISCOUNT MODAL ===== -->
<div class="modal-overlay" id="discountModal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.5rem">
                @include('partials.modal_logo')
                <h3 class="modal-title" id="discountModalTitle">Add Discount</h3>
            </div>
            <button class="modal-close" onclick="closeModal('discountModal')">&times;</button>
        </div>
        <form method="POST" action="price_management.php" class="needs-validation" onsubmit="return validateDiscountForm()">
            @csrf
            <input type="hidden" name="action" value="update_discount">
            <input type="hidden" name="price_id" id="discountPriceId">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <input type="text" id="discountProductName" class="form-input" readonly style="background: var(--input-bg); color: var(--text-muted);">
                </div>
                <div class="form-group">
                    <label class="form-label">Base Price ({{ $currencySymbol }})</label>
                    <input type="text" id="discountBasePrice" class="form-input" readonly style="background: var(--input-bg); color: var(--text-muted);">
                    <input type="hidden" name="base_price" id="discountBasePriceInput">
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type *</label>
                    <select name="discount_type" id="discountTypeSelect" class="form-select" onchange="updateDiscountFinalPrice()" required>
                        @foreach ($discountTypes as $dt)
                        <option value="{{ $dt['value'] }}" {{ $dt['value'] === 'none' ? 'selected' : '' }}>{{ $dt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Value</label>
                    <input type="number" name="discount_value" id="discountValueSelect" class="form-input" step="0.01" min="0" value="0" onchange="updateDiscountFinalPrice()">
                </div>
                <div class="form-group" style="background: rgba(0, 212, 255, 0.05); border-radius: 8px; padding: 0.75rem;">
                    <label class="form-label" style="color: var(--accent); margin-bottom: 0.25rem;">Final Price</label>
                    <div id="discountFinalPriceDisplay" style="font-size: 1.25rem; font-weight: 700; color: var(--success);">₱ 0.00</div>
                    <input type="hidden" name="final_price" id="discountFinalPriceInput" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="effective_from" id="discountEffectiveFrom">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="effective_until" id="discountEffectiveUntil">
                </div>
                <div class="form-group">
                    <label class="form-label">Reason / Notes</label>
                    <input type="text" name="reason" class="form-input" placeholder="e.g. Seasonal promotion">
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('discountModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Save Discount</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== SCHEDULE PRICE MODAL ===== -->
<div class="modal-overlay" id="schedulePriceModal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.5rem">
                @include('partials.modal_logo')
                <h3 class="modal-title">Schedule Price Change</h3>
            </div>
            <button class="modal-close" onclick="closeModal('schedulePriceModal')">&times;</button>
        </div>
        <form method="POST" action="price_management.php" class="needs-validation" onsubmit="return validateScheduleForm()">
            @csrf
            <input type="hidden" name="action" value="schedule_price">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Product *</label>
                    <input type="text" name="product_name_display" id="scheduleProductName" class="form-input" placeholder="Select a product" readonly required style="cursor: pointer;" onclick="openProductSelector('schedule_price')">
                    <input type="hidden" name="product_id" id="scheduleProductId" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Product Code</label>
                    <input type="text" name="sku_display" id="scheduleProductSku" class="form-input" readonly style="background: var(--input-bg); color: var(--text-muted);">
                </div>
                <div class="form-group">
                    <label class="form-label">Base Price ({{ $currencySymbol }}) *</label>
                    <input type="number" name="base_price" id="scheduleBasePrice" class="form-input" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency" class="form-input" value="{{ $currency }}" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type</label>
                    <select name="discount_type" id="scheduleDiscountType" class="form-select" onchange="toggleScheduleDiscount()">
                        @foreach ($discountTypes as $dt)
                        <option value="{{ $dt['value'] }}" {{ $dt['value'] === 'none' ? 'selected' : '' }}>{{ $dt['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Value</label>
                    <input type="number" name="discount_value" id="scheduleDiscountValue" class="form-input" step="0.01" min="0" value="0" onchange="updateScheduleFinalPrice()">
                </div>
                <div class="form-group" style="background: rgba(0, 212, 255, 0.05); border-radius: 8px; padding: 0.75rem;">
                    <label class="form-label" style="color: var(--accent); margin-bottom: 0.25rem;">Final Price</label>
                    <div id="scheduleFinalPriceDisplay" style="font-size: 1.25rem; font-weight: 700; color: var(--success);">₱ 0.00</div>
                    <input type="hidden" name="final_price" id="scheduleFinalPriceInput" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="effective_from" id="scheduleEffectiveFrom" class="form-input" required min="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="effective_until" id="scheduleEffectiveUntil" class="form-input">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Reason / Notes</label>
                    <input type="text" name="reason" class="form-input" placeholder="e.g. New supplier pricing">
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('schedulePriceModal')">Cancel</button>
                <button type="submit" class="btn btn-info" style="color: var(--primary);">Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== DEACTIVATE CONFIRMATION MODAL ===== -->
<div class="modal-overlay" id="deactivateModal">
    <div class="modal-content modal-medium">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.5rem">
                @include('partials.modal_logo')
                <h3 class="modal-title" id="deactivateModalTitle">Set Price Inactive</h3>
            </div>
            <button class="modal-close" onclick="closeModal('deactivateModal')">&times;</button>
        </div>
        <form method="POST" action="price_management.php">
            @csrf
            <input type="hidden" name="action" value="deactivate_price">
            <input type="hidden" name="price_id" id="deactivatePriceId">
            <input type="hidden" name="reason" id="deactivateReasonInput" value="Set inactive manually">
            <div style="margin-bottom: 1.5rem;">
                <p style="color: var(--text-secondary);">Set this price as inactive for <strong id="deactivateProductName"></strong>?</p>
            </div>
            <div class="form-group">
                <label class="form-label">Reason</label>
                <input type="text" name="reason" class="form-input" placeholder="Why are you deactivating this price?">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deactivateModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Set Inactive</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== BULK UPDATE MODAL ===== -->
<div class="modal-overlay" id="bulkUpdateModal">
    <div class="modal-content xl">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.5rem">
                @include('partials.modal_logo')
                <h3 class="modal-title">Update Multiple Prices</h3>
            </div>
            <button class="modal-close" onclick="closeModal('bulkUpdateModal')">&times;</button>
        </div>
        <form method="POST" action="price_management.php" id="bulkUpdateForm">
            @csrf
            <input type="hidden" name="action" value="bulk_update">
            <input type="hidden" name="product_ids" id="bulkProductIds">

            <div class="form-group">
                <label class="form-label">Operation *</label>
                <select name="operation" id="bulkOperation" class="form-select" required onchange="toggleBulkValueField()">
                    <option value="">Select operation</option>
                    <option value="increase_percentage">Increase by Percentage (%)</option>
                    <option value="decrease_percentage">Decrease by Percentage (%)</option>
                    <option value="increase_fixed">Increase by Fixed Amount</option>
                    <option value="decrease_fixed">Decrease by Fixed Amount</option>
                    <option value="set_price">Set Specific Price</option>
                </select>
            </div>

            <div class="form-group" id="bulkValueField" style="display: none;">
                <label class="form-label" id="bulkValueLabel">Value</label>
                <input type="number" name="value" id="bulkValue" class="form-input" step="0.01" min="0">
            </div>

            <div class="form-group">
                <label class="form-label">Reason *</label>
                <input type="text" name="reason" class="form-input" placeholder="e.g. Quarterly price adjustment" required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" id="confirmBulkUpdate" required> Confirm changes
                </label>
                <div id="bulkPreview" style="margin-top: 1rem; display: none;">
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.5rem;">Preview:</p>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem; background: var(--input-bg);">
                        <table class="data-table" style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current</th>
                                    <th>New</th>
                                </tr>
                            </thead>
                            <tbody id="bulkPreviewBody">
                                <tr><td colspan="3" style="text-align:center;color:var(--text-muted);">Select product(s) and operation to preview</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
        <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap; margin-top: 1rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('bulkUpdateModal')">Cancel</button>
            <button type="submit" form="bulkUpdateForm" class="btn btn-success" id="bulkUpdateApplyBtn">Apply Changes</button>
        </div>
    </div>
</div>

<!-- ===== PRODUCT SELECTOR MODAL ===== -->
<div class="modal-overlay" id="productSelectorModal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:.5rem">
                @include('partials.modal_logo')
                <h3 class="modal-title">Select Product</h3>
            </div>
            <button class="modal-close" onclick="closeModal('productSelectorModal')">&times;</button>
        </div>
        <div class="search-box" style="margin-bottom: 1rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;"><circle cx="11" cy="11" r="8"/><line x1="21"  y1="21" x2="15.657" y2="15.657"/></svg>
            <input type="text" class="search-input" placeholder="Search by product name, product code, or number..." id="productSearchInput"
                   onkeyup="searchProductsList(this.value)">
        </div>
        <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px;">
            <table class="data-table" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Product Code</th>
                        <th>Category</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="productListBody">
                    @foreach (($products ?? []) as $product)
                    <tr data-product='@json($product)'>
                        <td>{{ htmlspecialchars($product['item_name'] ?? '') }}</td>
                        <td>{{ htmlspecialchars($product['sku'] ?? '') }}</td>
                        <td>{{ htmlspecialchars($product['category'] ?? '') }}</td>
                        <td><button class="btn btn-sm btn-primary" onclick="selectProduct({{ json_encode($product) }})">Select</button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap; margin-top: 1rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('productSelectorModal')">Cancel</button>
        </div>
    </div>
</div>
