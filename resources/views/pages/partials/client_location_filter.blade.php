{{-- The one search box of a list page (see App\Support\LocationFilter). Render inside <form id="clientFilterForm">; styles: css/client_filter.css.
     Needs $searchTerm and $scopeBranch. Optional: $filterSubject (what is searched: Clients, Employees, ...), $filterSearchLabel,
     $filterIdPrefix (when a page has two of these), $filterWithBarangay (false for a list without barangays), $filterSuggest (what is
     suggested while typing: clients, employees, nap_boxes or users; taken from $filterSubject when left out),
     $filterDate = ['name' => 'attendance_date', 'label' => 'Date', 'value' => 'Y-m-d'] for a date field beside the search. --}}
@php
    $filterSubject = $filterSubject ?? 'Clients';
    $filterIdPrefix = $filterIdPrefix ?? 'client';
    $filterPlaceholder = \App\Support\LocationFilter::placeholder($scopeBranch, $filterSubject, $filterWithBarangay ?? true);
    $filterSuggest = $filterSuggest ?? (['Employees' => 'employees', 'NAP Boxes' => 'nap_boxes'][$filterSubject] ?? 'clients');
@endphp
<div class="client-filter-controls {{ isset($filterDate) ? 'client-filter-controls-dated' : '' }}">
    <div class="form-group client-filter-field client-search-field">
        <label class="form-label" for="{{ $filterIdPrefix }}SearchInput">{{ $filterSearchLabel ?? 'Search ' . $filterSubject }}</label>
        <input type="text" class="search-input client-filter-text-input" placeholder="{{ $filterPlaceholder }}" id="{{ $filterIdPrefix }}SearchInput" name="search" value="{{ $searchTerm }}" autocomplete="off" data-suggest="{{ $filterSuggest }}" data-suggest-url="{{ route('search.suggestions') }}">
    </div>
    @isset($filterDate)
    <div class="form-group client-filter-field client-date-field">
        <label class="form-label" for="{{ $filterIdPrefix }}FilterDate">{{ $filterDate['label'] }}</label>
        <input type="date" class="search-input client-filter-text-input" id="{{ $filterIdPrefix }}FilterDate" name="{{ $filterDate['name'] }}" value="{{ $filterDate['value'] }}">
    </div>
    @endisset
    <div class="client-filter-submit">
        <button type="submit" class="btn btn-primary">Search</button>
    </div>
</div>
@once
<script src="{{ asset('js/search_suggest.js') }}?{{ @filemtime(public_path('js/search_suggest.js')) ?: 1 }}"></script>
@endonce
