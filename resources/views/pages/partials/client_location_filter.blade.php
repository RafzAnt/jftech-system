{{-- Branch, barangay, and client search fields. Render inside <form id="clientFilterForm">; styles: css/client_filter.css.
     Needs $branches, $branchBarangays, $selectedBranch, $selectedBarangays, $searchTerm, $scopeBranch (see App\Support\LocationFilter); optional $filterSearchLabel, $filterSearchPlaceholder. --}}
<div class="client-filter-controls">
    <div class="form-group client-filter-field client-branch-field">
        <label class="form-label" for="clientBranchInput">Branch</label>
        <input type="hidden" id="clientBranchFilter" name="branch" value="{{ $selectedBranch }}">
        <div class="client-barangay-picker">
            <input type="text" class="search-input client-barangay-input" id="clientBranchInput" placeholder="Branch" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="clientBranchSuggestions" aria-expanded="false" value="{{ $selectedBranch }}" {{ $scopeBranch !== null ? 'readonly' : '' }}>
            <div id="clientBranchSuggestions" class="location-suggestions client-filter-suggestions" role="listbox" hidden></div>
        </div>
    </div>
    <div class="form-group client-filter-field client-barangay-field">
        <label class="form-label" for="clientBarangayInput">Barangay</label>
        <div class="client-barangay-picker">
            <input type="text" class="search-input client-barangay-input" id="clientBarangayInput" placeholder="Barangay" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="clientBarangaySuggestions" aria-expanded="false" {{ $selectedBranch ? '' : 'disabled' }}>
            <div id="clientBarangaySuggestions" class="location-suggestions client-filter-suggestions" role="listbox" hidden></div>
        </div>
        <div class="client-barangay-chips" id="clientBarangayChips"></div>
        <div id="clientBarangayHiddenInputs"></div>
    </div>
    <div class="form-group client-filter-field client-search-field">
        <label class="form-label" for="searchInput">{{ $filterSearchLabel ?? 'Search Clients' }}</label>
        <input type="text" class="search-input client-filter-text-input" placeholder="{{ $filterSearchPlaceholder ?? 'Name, client code, or mobile number' }}" id="searchInput" name="search" value="{{ $searchTerm }}">
    </div>
    <div class="client-filter-submit">
        <button type="submit" class="btn btn-primary">Search</button>
    </div>
</div>

<script>
// Runs after jftech-main.js has loaded (createSuggestionList lives there).
document.addEventListener('DOMContentLoaded', function () {
    const barangaysByBranch = @json($branchBarangays, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const branchNames = @json(array_keys($branches), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    const selectedBarangays = @json($selectedBarangays, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    const branchFilter = document.getElementById('clientBranchFilter');
    const branchInput = document.getElementById('clientBranchInput');
    const barangayInput = document.getElementById('clientBarangayInput');
    const barangayHiddenInputs = document.getElementById('clientBarangayHiddenInputs');
    const barangayChips = document.getElementById('clientBarangayChips');

    function renderBarangays() {
        barangayHiddenInputs.replaceChildren();
        barangayChips.replaceChildren();
        selectedBarangays.forEach(name => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'barangays[]';
            hidden.value = name;
            barangayHiddenInputs.appendChild(hidden);

            const chip = document.createElement('span');
            chip.className = 'client-barangay-chip';
            chip.textContent = name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Remove ${name}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                selectedBarangays.splice(selectedBarangays.indexOf(name), 1);
                renderBarangays();
            });
            chip.appendChild(remove);
            barangayChips.appendChild(chip);
        });
    }

    function setBranch(name) {
        if (branchFilter.value !== name) {
            selectedBarangays.splice(0);
            barangayInput.value = '';
        }
        branchFilter.value = name;
        branchInput.value = name;
        barangayInput.disabled = !name;
        renderBarangays();
    }

    function addBarangay(name) {
        if (!selectedBarangays.some(item => item.toLocaleLowerCase() === name.toLocaleLowerCase())) {
            selectedBarangays.push(name);
            renderBarangays();
        }
        barangayInput.value = '';
        barangayInput.focus();
    }

    createSuggestionList(
        branchInput,
        document.getElementById('clientBranchSuggestions'),
        () => branchNames,
        name => {
            setBranch(name);
            barangayInput.focus();
        }
    );
    createSuggestionList(
        barangayInput,
        document.getElementById('clientBarangaySuggestions'),
        () => (barangaysByBranch[branchFilter.value] || [])
            .filter(name => !selectedBarangays.some(item => item.toLocaleLowerCase() === name.toLocaleLowerCase())),
        addBarangay
    );

    // Leaving the branch box with text that isn't a real branch resets it to the chosen one.
    branchInput.addEventListener('blur', () => setTimeout(() => {
        const typed = branchInput.value.trim();
        if (!typed) {
            setBranch('');
            return;
        }
        const match = branchNames.find(name => name.toLocaleLowerCase() === typed.toLocaleLowerCase());
        setBranch(match || branchFilter.value);
    }, 150));
    barangayInput.addEventListener('blur', () => setTimeout(() => { barangayInput.value = ''; }, 150));

    // A barangay typed in full but not yet picked still counts when searching.
    document.getElementById('clientFilterForm').addEventListener('submit', () => {
        const typed = barangayInput.value.trim().toLocaleLowerCase();
        const match = (barangaysByBranch[branchFilter.value] || []).find(name => name.toLocaleLowerCase() === typed);
        if (match && !selectedBarangays.includes(match)) {
            selectedBarangays.push(match);
            renderBarangays();
        }
    });
    renderBarangays();
});
</script>
