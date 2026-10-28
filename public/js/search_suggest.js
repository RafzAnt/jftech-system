/**
 * The suggestions of the one search box of a list page (see App\Support\SearchSuggestions).
 *
 * Every <input data-suggest="clients|employees|nap_boxes|users" data-suggest-url="..."> suggests, while someone types, one kind at a
 * time in the order of its placeholder: first only branches, then only the barangays of that branch, then only the records (clients,
 * employees, ...) of that barangay. The server works out the step from the whole text of the box and answers for the part being
 * typed (what follows the last comma, or its last word when the whole part finds nothing). Picking one puts it in the box in place
 * of what was typed; a branch or a barangay is followed by ", " so the next step can be typed straight away. The box itself stays a
 * plain text box: pressing Enter without picking just searches.
 */
(function () {
    const DELAY = 150;

    // The look of the list comes with this script (css/search_suggest.css sits beside js/search_suggest.js).
    const script = document.currentScript;
    if (script && script.src && !document.querySelector('link[data-search-suggest-css]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = script.src.replace(/js\/search_suggest\.js.*$/, 'css/search_suggest.css');
        link.setAttribute('data-search-suggest-css', '');
        document.head.appendChild(link);
    }

    function node(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    }

    /** The text being typed: what comes after the last comma. */
    function segmentOf(value) {
        return value.slice(value.lastIndexOf(',') + 1).trim();
    }

    function init(input, number) {
        const url = input.dataset.suggestUrl;
        const type = input.dataset.suggest;
        if (!url || !type) return;

        const list = node('div', 'search-suggest');
        list.id = 'searchSuggest' + number;
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        document.body.appendChild(list);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('autocomplete', 'off');

        let items = [];
        let active = -1;
        let query = '';
        let timer = null;
        let ticket = 0;

        function place() {
            const box = input.getBoundingClientRect();
            list.style.left = box.left + window.scrollX + 'px';
            list.style.top = box.bottom + window.scrollY + 2 + 'px';
            list.style.width = box.width + 'px';
        }

        function close() {
            list.hidden = true;
            active = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function highlight(index) {
            active = index;
            list.querySelectorAll('.search-suggest-option').forEach((option, position) => {
                option.setAttribute('aria-selected', String(position === index));
                if (position === index) {
                    input.setAttribute('aria-activedescendant', option.id);
                    option.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function pick(item) {
            // What was typed (the last segment or last word) gives way to the suggestion.
            let head = input.value.replace(/\s+$/, '');
            if (query && head.toLowerCase().endsWith(query.toLowerCase())) head = head.slice(0, head.length - query.length);
            const goOn = item.group === 'Branch' || item.group === 'Barangay' ? ', ' : '';
            input.value = head + item.value + goOn;
            close();
            input.focus();
        }

        function render() {
            list.replaceChildren();
            if (items.length === 0) {
                close();
                return;
            }
            let group = '';
            items.forEach((item, index) => {
                if (item.group !== group) {
                    group = item.group;
                    list.appendChild(node('div', 'search-suggest-group', group));
                }
                const option = node('button', 'search-suggest-option');
                option.type = 'button';
                option.id = list.id + 'Option' + index;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.appendChild(node('span', 'search-suggest-label', item.label));
                if (item.detail) option.appendChild(node('span', 'search-suggest-detail', item.detail));
                option.addEventListener('mousedown', event => event.preventDefault());
                option.addEventListener('click', () => pick(item));
                list.appendChild(option);
            });
            place();
            list.hidden = false;
            active = -1;
            input.setAttribute('aria-expanded', 'true');
        }

        async function load() {
            const text = segmentOf(input.value);
            const mine = ++ticket;
            if (text === '') {
                items = [];
                render();
                return;
            }
            try {
                const request = new URL(url, window.location.href);
                request.searchParams.set('type', type);
                request.searchParams.set('q', input.value); // the whole text: what came before says which step this is
                const response = await fetch(request, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('suggestions failed');
                const data = await response.json();
                if (mine !== ticket) return; // a newer keystroke is already on its way
                query = data.query || text;
                items = Array.isArray(data.suggestions) ? data.suggestions : [];
            } catch (error) {
                if (mine !== ticket) return;
                items = [];
            }
            render();
        }

        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(load, DELAY);
        });
        input.addEventListener('focus', () => {
            if (segmentOf(input.value) !== '') load();
        });
        input.addEventListener('blur', () => setTimeout(close, 120));
        input.addEventListener('keydown', event => {
            if (list.hidden) return;
            const options = list.querySelectorAll('.search-suggest-option');
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const next = active === -1 ? (event.key === 'ArrowDown' ? 0 : options.length - 1) : (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                highlight(next);
            } else if (event.key === 'Enter' && active >= 0) {
                event.preventDefault(); // pick it instead of searching
                pick(items[active]);
            } else if (event.key === 'Escape') {
                close();
            }
        });
        if (input.form) input.form.addEventListener('submit', () => { clearTimeout(timer); ticket++; close(); });
        window.addEventListener('resize', () => { if (!list.hidden) place(); });
        window.addEventListener('scroll', () => { if (!list.hidden) place(); }, true);
    }

    function start() {
        document.querySelectorAll('input[data-suggest]').forEach(init);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
