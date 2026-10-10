/**
 * JFTECH Price Management
 * Front-end interactions: modals, product selection, action menus, bulk update
 */

(function () {
    'use strict';

    const _currency = document.currentScript?.dataset?.currency || 'PHP';

    function getCSRFToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /* ==================== Currency Helpers ==================== */

    window.currencySymbol = function () {
        return _currency;
    };

    window.currencyDisplaySymbol = function () {
        return _currency.toUpperCase() === 'PHP' ? '₱' : _currency;
    };

    window.formatCurrency = function (amount) {
        const symbol = window.currencyDisplaySymbol();
        const prefix = symbol === '₱' ? symbol : symbol + ' ';
        return prefix + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    };

    window.calcFinalPrice = function (base, type, value) {
        base = parseFloat(base) || 0;
        value = parseFloat(value) || 0;
        if (type === 'percentage') {
            return Math.round(base * (100 - value) / 100 * 100) / 100;
        } else if (type === 'fixed') {
            return Math.max(0, Math.round((base - value) * 100) / 100);
        }
        return Math.round(base * 100) / 100;
    };

    function updateFinalPriceField(baseId, typeId, valueId, displayId, hiddenId) {
        const base = document.getElementById(baseId)?.value || 0;
        const type = document.getElementById(typeId)?.value || 'none';
        const value = document.getElementById(valueId)?.value || 0;
        const final = window.calcFinalPrice(base, type, value);
        const display = document.getElementById(displayId);
        if (display) display.textContent = window.formatCurrency(final);
        const hidden = document.getElementById(hiddenId);
        if (hidden) hidden.value = final;
    }

    window.updateFinalPrice = function () {
        updateFinalPriceField('basePriceInput', 'discountTypeInput', 'discountValueInput', 'finalPriceDisplay', 'finalPriceInput');
    };

    window.toggleDiscountFields = function () {
        const type = document.getElementById('discountTypeInput')?.value || 'none';
        const wrapper = document.getElementById('discountValueWrapper');
        if (wrapper) wrapper.style.display = type === 'none' ? 'none' : 'block';
        window.updateFinalPrice();
        window.updateChangeFinalPrice();
        window.updateDiscountFinalPrice();
        window.updateScheduleFinalPrice();
    };

    window.updateChangeFinalPrice = function () {
        updateFinalPriceField('changeNewPrice', 'changeDiscountType', 'changeDiscountValue', 'changeFinalPriceDisplay', 'changeFinalPriceInput');

        const currentPrice = parseFloat(document.getElementById('changeCurrentPrice')?.value) || 0;
        const newFinal = parseFloat(document.getElementById('changeFinalPriceInput')?.value) || 0;
        const diff = newFinal - currentPrice;
        const diffEl = document.getElementById('changeDiff');
        if (diffEl) diffEl.textContent = (diff >= 0 ? '+' : '') + window.formatCurrency(diff);
        const pct = currentPrice > 0 ? (diff / currentPrice) * 100 : 0;
        const pctEl = document.getElementById('changePct');
        if (pctEl) pctEl.textContent = (pct >= 0 ? '+' : '') + pct.toFixed(2) + '% change';
    };

    window.updateDiscountFinalPrice = function () {
        updateFinalPriceField('discountBasePrice', 'discountTypeSelect', 'discountValueSelect', 'discountFinalPriceDisplay', 'discountFinalPriceInput');
    };

    window.updateScheduleFinalPrice = function () {
        updateFinalPriceField('scheduleBasePrice', 'scheduleDiscountType', 'scheduleDiscountValue', 'scheduleFinalPriceDisplay', 'scheduleFinalPriceInput');
    };

    window.toggleScheduleDiscount = function () {
        window.updateScheduleFinalPrice();
    };

    /* ==================== Validation ==================== */

    window.validateDateRange = function () {
        const from = document.getElementById('effectiveFromInput')?.value;
        const until = document.getElementById('effectiveUntilInput')?.value;
        if (from && until && until < from) {
            alert('End Date must be on or after Start Date.');
            return false;
        }
        return true;
    };

    window.validatePriceForm = function () {
        const base = document.getElementById('basePriceInput')?.value;
        if (!base || parseFloat(base) < 0) { alert('Price cannot be negative.'); return false; }
        if (!document.getElementById('addProductId')?.value) { alert('Please select a product.'); return false; }
        if (!document.getElementById('effectiveFromInput')?.value) { alert('Start Date is required.'); return false; }
        return window.validateDateRange();
    };

    window.validateChangePriceForm = function () {
        const base = document.getElementById('changeNewPrice')?.value;
        if (!base || parseFloat(base) < 0) { alert('Price cannot be negative.'); return false; }
        const reason = document.getElementById('changeReason')?.value;
        if (!reason) { alert('A reason is required.'); return false; }
        return true;
    };

    window.validateDiscountForm = function () {
        const type = document.getElementById('discountTypeSelect')?.value;
        const value = parseFloat(document.getElementById('discountValueSelect')?.value || 0);
        const base = parseFloat(document.getElementById('discountBasePrice')?.value || 0);
        if (type === 'percentage' && value > 100) { alert('Percentage discount must be between 0 and 100.'); return false; }
        if (type === 'fixed' && value > base) { alert('Discount cannot exceed the product price.'); return false; }
        return true;
    };

    window.validateScheduleForm = function () {
        const base = document.getElementById('scheduleBasePrice')?.value;
        if (!base || parseFloat(base) < 0) { alert('Price cannot be negative.'); return false; }
        if (!document.getElementById('scheduleProductId')?.value) { alert('Please select a product.'); return false; }
        const from = document.getElementById('scheduleEffectiveFrom')?.value;
        if (!from) { alert('Start Date is required.'); return false; }
        const until = document.getElementById('scheduleEffectiveUntil')?.value;
        if (until && until < from) { alert('End Date must be on or after Start Date.'); return false; }
        return true;
    };

    /* ==================== Modal Openers ==================== */

    window.openAddPriceModal = function () {
        document.getElementById('addPriceModalTitle').textContent = 'Add Price';
        document.getElementById('addPriceAction').value = 'add_price';
        document.getElementById('editPriceId').value = '';
        document.getElementById('addProductName').value = '';
        document.getElementById('addProductSku').value = '';
        document.getElementById('addProductId').value = '';
        document.getElementById('basePriceInput').value = '';
        document.getElementById('currencyInput').value = currencySymbol();
        document.getElementById('discountTypeInput').value = 'none';
        document.getElementById('discountValueInput').value = '0';
        window.toggleDiscountFields();
        document.getElementById('effectiveFromInput').value = new Date().toISOString().split('T')[0];
        document.getElementById('effectiveUntilInput').value = '';
        document.getElementById('statusInput').value = 'draft';
        document.querySelector('#addPriceModal input[name="reason"]').value = '';
        openModal('addPriceModal');
    };

    window.openEditPriceModal = function (price) {
        document.getElementById('addPriceModalTitle').textContent = 'Edit Price';
        document.getElementById('addPriceAction').value = 'edit_price';
        document.getElementById('editPriceId').value = price.id;
        document.getElementById('addProductName').value = price.product_name || '';
        document.getElementById('addProductSku').value = price.sku || '';
        document.getElementById('addProductId').value = price.product_id;
        document.getElementById('basePriceInput').value = price.base_price;
        document.getElementById('currencyInput').value = price.currency || currencySymbol();
        document.getElementById('discountTypeInput').value = price.discount_type || 'none';
        document.getElementById('discountValueInput').value = price.discount_value || 0;
        window.toggleDiscountFields();
        document.getElementById('effectiveFromInput').value = price.effective_from || '';
        document.getElementById('effectiveUntilInput').value = price.effective_until || '';
        document.getElementById('statusInput').value = price.status || 'draft';
        document.querySelector('#addPriceModal input[name="reason"]').value = '';
        openModal('addPriceModal');
    };

    window.openChangePriceModal = function (price) {
        document.getElementById('changePriceId').value = price.id;
        document.getElementById('changeProductName').value = price.product_name || '';
        const currentFinal = parseFloat(price.final_price) || 0;
        document.getElementById('changeCurrentPrice').value = currentFinal.toFixed(2);
        document.getElementById('changePreviousFinalPrice').value = currentFinal.toFixed(2);
        document.getElementById('changeNewPrice').value = price.base_price || currentFinal;
        document.getElementById('changeDiscountType').value = price.discount_type || 'none';
        document.getElementById('changeDiscountValue').value = price.discount_value || 0;
        document.getElementById('changeReason').value = '';
        document.getElementById('changeReasonCustom').style.display = 'none';
        window.updateChangeFinalPrice();
        openModal('changePriceModal');
    };

    window.openDiscountModal = function (price) {
        const isNew = !price.id;
        document.getElementById('discountModalTitle').textContent = isNew ? 'Add Discount' : 'Edit Discount';
        document.getElementById('discountPriceId').value = price.id || '';
        document.getElementById('discountProductName').value = price.product_name || '';
        document.getElementById('discountBasePrice').value = price.base_price || 0;
        document.getElementById('discountBasePriceInput').value = price.base_price || 0;
        document.getElementById('discountTypeSelect').value = price.discount_type || 'none';
        document.getElementById('discountValueSelect').value = price.discount_value || 0;
        document.getElementById('discountEffectiveFrom').value = price.effective_from || new Date().toISOString().split('T')[0];
        document.getElementById('discountEffectiveUntil').value = price.effective_until || '';
        document.querySelector('#discountModal input[name="reason"]').value = '';
        window.updateDiscountFinalPrice();
        openModal('discountModal');
    };

    window.openScheduleModal = function (price) {
        const isNew = !price.id;
        if (isNew) {
            document.getElementById('scheduleProductName').value = '';
            document.getElementById('scheduleProductSku').value = '';
            document.getElementById('scheduleProductId').value = '';
        } else {
            document.getElementById('scheduleProductName').value = price.product_name || '';
            document.getElementById('scheduleProductSku').value = price.sku || '';
            document.getElementById('scheduleProductId').value = price.product_id;
            document.getElementById('scheduleBasePrice').value = price.base_price;
            document.getElementById('scheduleDiscountType').value = price.discount_type || 'none';
            document.getElementById('scheduleDiscountValue').value = price.discount_value || 0;
            document.getElementById('scheduleEffectiveFrom').value = price.effective_from || '';
            document.getElementById('scheduleEffectiveUntil').value = price.effective_until || '';
            document.getElementById('editPriceId').value = price.id;
        }
        window.updateScheduleFinalPrice();
        openModal('schedulePriceModal');
    };

    window.openDeactivateModal = function (priceId, productName) {
        document.getElementById('deactivatePriceId').value = priceId;
        document.getElementById('deactivateProductName').textContent = productName;
        openModal('deactivateModal');
    };

    /* ==================== Product Selector ==================== */

    window.openProductSelector = function (mode) {
        window._productSelectorMode = mode;
        const searchInput = document.getElementById('productSearchInput');
        if (searchInput) searchInput.value = '';
        searchProductsList('');
        openModal('productSelectorModal');
    };

    window.selectProduct = function (product) {
        if (window._productSelectorMode === 'add_price') {
            document.getElementById('addProductName').value = product.item_name;
            document.getElementById('addProductSku').value = product.sku || 'N/A';
            document.getElementById('addProductId').value = product.id;
        } else if (window._productSelectorMode === 'schedule_price') {
            document.getElementById('scheduleProductName').value = product.item_name;
            document.getElementById('scheduleProductSku').value = product.sku || 'N/A';
            document.getElementById('scheduleProductId').value = product.id;
        }
        closeModal('productSelectorModal');
    };

    window.searchProductsList = function (term) {
        const body = document.getElementById('productListBody');
        if (!body) return;

        if (!term || term.length < 1) {
            return;
        }

        fetch('/price-management/products/search?q=' + encodeURIComponent(term), {
            headers: { 'X-CSRF-TOKEN': getCSRFToken(), 'Accept': 'application/json' }
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data.length) {
                    body.innerHTML = data.data.map(p =>
                        '<tr data-product=\'' + JSON.stringify(p).replace(/'/g, '&#39;') + '\'>' +
                        '<td>' + (p.item_name || '') + '</td>' +
                        '<td>' + (p.sku || '') + '</td>' +
                        '<td>' + (p.category || '') + '</td>' +
                        '<td><button class="btn btn-sm btn-primary" onclick="selectProduct(' + JSON.stringify(p).replace(/'/g, '&#39;') + ')">Select</button></td>' +
                        '</tr>'
                    ).join('');
                } else {
                    body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No products found</td></tr>';
                }
            })
            .catch(() => {
                body.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--danger);">Error loading products</td></tr>';
            });
    };

    /* ==================== Action Menu ==================== */

    window.toggleActionMenu = function (btn) {
        const dropdown = btn.nextElementSibling;
        if (!dropdown) return;
        dropdown.classList.toggle('show');
        if (dropdown.classList.contains('show')) {
            document.addEventListener('click', function close(e) {
                if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                    document.removeEventListener('click', close);
                }
            });
        }
    };

    /* ==================== Bulk Update ==================== */

    window.updateBulkBar = function () {
        const anyChecked = document.querySelectorAll('.price-checkbox:checked').length > 0;
        const bar = document.getElementById('bulkUpdateBar');
        if (bar) bar.classList.toggle('show', anyChecked);
        const count = document.querySelectorAll('.price-checkbox:checked').length;
        const countEl = document.getElementById('selectedCount');
        if (countEl) countEl.textContent = count + ' selected';

        const ids = Array.from(document.querySelectorAll('.price-checkbox:checked'))
            .map(cb => cb.value)
            .join(',');
        const bulkIds = document.getElementById('bulkProductIds');
        if (bulkIds) bulkIds.value = ids;
    };

    window.toggleBulkBar = function () {
        window.updateBulkBar();
    };

    /* ==================== Filter Toggle ==================== */

    window.toggleFilters = function () {
        const section = document.getElementById('filterSection');
        if (section) section.style.display = section.style.display === 'block' ? 'none' : 'block';
    };

    /* ==================== Table Filter ==================== */

    window.filterTable = function () {
        const searchTerm = document.querySelector('.search-input')?.value?.toLowerCase() || '';
        const table = document.querySelector('.search-input')?.dataset?.target;
        if (!table) return;
        const rows = document.querySelectorAll(table + ' tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    };

    /* ==================== Sort Table ==================== */

    window.sortTable = function (field) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', field);
        const currentDir = url.searchParams.get('sort_dir') || 'desc';
        url.searchParams.set('sort_dir', currentDir === 'asc' ? 'desc' : 'asc');
        window.location = url.toString();
    };

    /* ==================== Reason Toggle ==================== */

    document.addEventListener('DOMContentLoaded', function () {
        const changeReason = document.getElementById('changeReason');
        if (changeReason) {
            changeReason.addEventListener('change', function () {
                const custom = document.getElementById('changeReasonCustom');
                if (custom) {
                    custom.style.display = this.value === 'Other' ? 'block' : 'none';
                    if (this.value !== 'Other') custom.value = '';
                }
            });
        }

        const checkAll = document.getElementById('checkAllPrices');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('.price-checkbox').forEach(cb => cb.checked = this.checked);
                window.updateBulkBar();
            });
        }

        const changeModal = document.getElementById('changePriceModal');
        if (changeModal) {
            changeModal.addEventListener('click', function (e) {
                if (e.target.classList.contains('dropdown-item')) return;
            });
        }

        const bulkOp = document.getElementById('bulkOperation');
        if (bulkOp) {
            bulkOp.addEventListener('change', function () {
                window.toggleBulkValueField();
                window.loadBulkPreviewOnBulkChange();
            });
        }

        const bulkValue = document.getElementById('bulkValue');
        if (bulkValue) {
            bulkValue.addEventListener('input', window.loadBulkPreviewOnBulkChange);
        }
    });

    window.loadBulkPreviewOnBulkChange = function () {
        const ids = document.getElementById('bulkProductIds')?.value;
        const op = document.getElementById('bulkOperation')?.value;
        const val = document.getElementById('bulkValue')?.value;
        const preview = document.getElementById('bulkPreview');
        const body = document.getElementById('bulkPreviewBody');

        if (!ids || !op) {
            if (preview) preview.style.display = 'none';
            return;
        }

        if (!val) {
            if (preview) preview.style.display = 'block';
            if (body) body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">Enter a value to see preview</td></tr>';
            return;
        }

        if (preview) preview.style.display = 'block';
        if (body) body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">Loading...</td></tr>';

        fetch('/price-management/bulk-update-preview', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCSRFToken(),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                product_ids: ids.split(',').map(Number),
                operation: op,
                value: parseFloat(val) || 0
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data.length) {
                    body.innerHTML = data.data.map(function (item) {
                        return '<tr><td>' + (item.product_name || '') + '</td>' +
                               '<td>' + window.formatCurrency(item.current_price) + '</td>' +
                               '<td>' + window.formatCurrency(item.new_price) + '</td></tr>';
                    }).join('');
                } else {
                    body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);">No prices available for selected products</td></tr>';
                }
            })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--danger);">Error loading preview</td></tr>';
            });
    };

    /* ==================== Init ==================== */

    window.toggleBulkValueField = window.toggleBulkValueField || function () {
        var op = document.getElementById('bulkOperation')?.value;
        var wrapper = document.getElementById('bulkValueField');
        var label = document.getElementById('bulkValueLabel');
        if (wrapper) wrapper.style.display = op ? 'block' : 'none';
        if (label) {
            switch (op) {
                case 'increase_percentage': label.textContent = 'Increase (%)'; break;
                case 'decrease_percentage': label.textContent = 'Decrease (%)'; break;
                case 'increase_fixed': label.textContent = 'Increase Amount (' + currencyDisplaySymbol() + ')'; break;
                case 'decrease_fixed': label.textContent = 'Decrease Amount (' + currencyDisplaySymbol() + ')'; break;
                case 'set_price': label.textContent = 'New Price (' + currencyDisplaySymbol() + ')'; break;
                default: label.textContent = 'Value';
            }
        }
        var bulkVal = document.getElementById('bulkValue');
        if (bulkVal) { bulkVal.value = ''; bulkVal.required = !!op; }
    };
})();
