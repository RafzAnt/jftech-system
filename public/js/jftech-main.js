/**
 * JFTECH WiFi Billing & Management System
 * Main JavaScript File
 */

function applyTheme(theme) {
    const nextTheme = theme === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', nextTheme);
    const themeMeta = document.querySelector('meta[name="theme-color"]');
    if (themeMeta) {
        themeMeta.setAttribute('content', nextTheme === 'light' ? '#eef6ff' : '#081426');
    }

    document.querySelectorAll('[data-theme-toggle]').forEach(toggle => {
        toggle.setAttribute('aria-pressed', nextTheme === 'light' ? 'true' : 'false');
        toggle.setAttribute('aria-label', nextTheme === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
        toggle.setAttribute('title', nextTheme === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
        const label = toggle.querySelector('[data-theme-label]');
        const icon = toggle.querySelector('[data-theme-icon]');

        if (label) label.textContent = nextTheme === 'light' ? 'Light Mode' : 'Dark Mode';
        if (icon) {
            icon.innerHTML = nextTheme === 'light'
                ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2.5"/><path d="M12 19.5V22"/><path d="M4.93 4.93l1.77 1.77"/><path d="M17.3 17.3l1.77 1.77"/><path d="M2 12h2.5"/><path d="M19.5 12H22"/><path d="M4.93 19.07l1.77-1.77"/><path d="M17.3 6.7l1.77-1.77"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3c-.02.26-.03.52-.03.79A7 7 0 0 0 18.21 14c.95 0 1.86-.19 2.79-.54z"/></svg>';
        }
    });
}

function initializeTheme() {
    // Dark is the default; a theme the user picked with the toggle is kept.
    const savedTheme = localStorage.getItem('jftech-theme') || 'dark';
    applyTheme(savedTheme);
}

initializeTheme();

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-theme-toggle]').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const nextTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            localStorage.setItem('jftech-theme', nextTheme);
            applyTheme(nextTheme);
        });
    });

    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSidebar = () => {
        if (sidebar) sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('active');
    };

    // Submenu toggle for sidebar
    document.querySelectorAll('[data-submenu-target]').forEach(item => {
        // The sidebar includes a small inline fallback for slow forwarded
        // connections. Do not attach a second handler when it is present.
        if (item.dataset.submenuReady === 'true') return;
        item.addEventListener('click', function() {
            const submenu = document.getElementById(this.dataset.submenuTarget);
            if (!submenu) return;

            const willOpen = !submenu.classList.contains('show');
            submenu.classList.toggle('show', willOpen);
            this.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    if (menuToggle && sidebar && menuToggle.dataset.menuReady !== 'true') {
        menuToggle.addEventListener('click', function() {
            const isOpen = sidebar.classList.toggle('open');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('active', isOpen);
            menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    document.querySelectorAll('.sidebar a, .sidebar .submenu-item').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1024) closeSidebar();
        });
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) closeSidebar();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });

    // Tab functionality
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            const tabContainer = this.closest('.tabs-container') || document;

            tabContainer.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            tabContainer.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            const targetContent = document.getElementById(tabId);
            if (targetContent) targetContent.classList.add('active');
        });
    });

    // Modal functionality
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('active');
    };

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });
    });

    // Search functionality
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const targetTable = document.querySelector(this.dataset.target);
            if (!targetTable) return;

            const rows = targetTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });

    // Confirm delete
    window.confirmDelete = function(message) {
        return confirm(message || 'Are you sure you want to delete this item?');
    };

    // Print functionality
    window.printPage = function() {
        window.print();
    };

    // Form validation: every required field gets a clear inline error message.
    const forms = document.querySelectorAll('form');
    const requiredFields = 'input[required]:not([type="hidden"]), select[required], textarea[required]';

    function fieldGroup(field) {
        return field.closest('.form-group') || field.parentElement;
    }

    function validationMessage(field) {
        const group = fieldGroup(field);
        if (!group) return;

        const invalid = !field.validity.valid;
        const existingMessage = group.querySelector('.required-field-message');
        if (!invalid) {
            group.classList.remove('form-field-invalid');
            if (existingMessage) existingMessage.remove();
            return;
        }

        let message = existingMessage;
        if (!message) {
            message = document.createElement('small');
            message.className = 'required-field-message';
            message.setAttribute('aria-live', 'polite');
            group.appendChild(message);
        }

        group.classList.add('form-field-invalid');
        message.textContent = field.validity.valueMissing ? 'Required field.' : (field.validationMessage || 'Please enter a valid value.');
    }

    forms.forEach(form => {
        form.addEventListener('submit', function() {
            form.classList.add('was-validated');
            form.querySelectorAll(requiredFields).forEach(validationMessage);
        });
    });

    // The browser prevents an invalid form from submitting before its submit
    // event runs, so listen during capture to show the inline feedback too.
    document.addEventListener('invalid', function(event) {
        const field = event.target;
        if (!field.matches || !field.matches(requiredFields)) return;

        const form = field.closest('form');
        if (form) form.classList.add('was-validated');
        validationMessage(field);
    }, true);

    document.addEventListener('input', function(event) {
        const field = event.target;
        if (!field.matches || !field.matches(requiredFields)) return;
        const form = field.closest('form');
        if (form && form.classList.contains('was-validated')) validationMessage(field);
    });

    document.addEventListener('change', function(event) {
        const field = event.target;
        if (!field.matches || !field.matches(requiredFields)) return;
        const form = field.closest('form');
        if (form && form.classList.contains('was-validated')) validationMessage(field);
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(100%)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Color picker for client markers
    const colorOptions = document.querySelectorAll('.color-option');
    colorOptions.forEach(option => {
        option.addEventListener('click', function() {
            colorOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            const colorInput = document.getElementById('markerColor');
            if (colorInput) colorInput.value = this.dataset.color;
        });
    });
});

// Format currency
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Format date
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-PH', options);
}

// Generate random color for markers
function getRandomColor() {
    const colors = ['#00BFFF', '#00E676', '#FF1744', '#FFAB00', '#E040FB', '#FF6D00', '#2979FF', '#76FF03', '#FF4081', '#18FFFF'];
    return colors[Math.floor(Math.random() * colors.length)];
}

// QR Code refresh timer
function startQRTimer(duration, display) {
    let timer = duration;
    const interval = setInterval(() => {
        const minutes = parseInt(timer / 60, 10);
        const seconds = parseInt(timer % 60, 10);
        display.textContent = (minutes < 10 ? '0' + minutes : minutes) + ':' + (seconds < 10 ? '0' + seconds : seconds);
        if (--timer < 0) {
            clearInterval(interval);
            location.reload();
        }
    }, 1000);
}

// Suggestion list: a text box with a dropdown of matches. Type to filter, then click
// or use the arrow keys + Enter to pick. Styles: .location-suggestions in jftech-theme.css.
// With acceptExactOnEnter, Enter on a fully typed option picks it instead of submitting the form.
function createSuggestionList(input, list, getOptions, onPick, { acceptExactOnEnter = true } = {}) {
    let active = -1;

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    }

    function open() {
        if (input.disabled) return;
        const query = input.value.trim().toLocaleLowerCase();
        const options = getOptions()
            .filter(name => !query || name.toLocaleLowerCase().includes(query))
            .slice(0, 30);
        list.replaceChildren();
        active = -1;
        options.forEach((name, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'location-suggestion-option';
            option.id = `${list.id}Option${index}`;
            option.setAttribute('role', 'option');
            option.textContent = name;
            option.addEventListener('mousedown', event => event.preventDefault());
            option.addEventListener('click', () => {
                close();
                onPick(name);
            });
            list.appendChild(option);
        });
        list.hidden = options.length === 0;
        input.setAttribute('aria-expanded', String(options.length > 0));
        if (!list.hidden) fitToContainer(list);
    }

    // A scrolling parent (a modal body) clips the list; shrink it to the room left below the input.
    function fitToContainer(menu) {
        menu.style.maxHeight = '';
        let limit = window.innerHeight;
        for (let parent = menu.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
            if (getComputedStyle(parent).overflowY !== 'visible') {
                limit = Math.min(limit, parent.getBoundingClientRect().bottom);
                break;
            }
        }
        const room = limit - menu.getBoundingClientRect().top - 8;
        if (room < menu.offsetHeight) menu.style.maxHeight = Math.max(120, room) + 'px';
    }

    input.addEventListener('input', open);
    input.addEventListener('focus', open);
    input.addEventListener('blur', () => setTimeout(close, 120));
    input.addEventListener('keydown', event => {
        const options = list.querySelectorAll('.location-suggestion-option');
        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (list.hidden) open();
            if (!options.length) return;
            event.preventDefault();
            active = active === -1
                ? (event.key === 'ArrowDown' ? 0 : options.length - 1)
                : (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
            input.setAttribute('aria-activedescendant', options[active].id);
        } else if (event.key === 'Enter') {
            if (!list.hidden && active >= 0) {
                event.preventDefault();
                options[active].click();
            } else if (acceptExactOnEnter) {
                const typed = input.value.trim().toLocaleLowerCase();
                const exact = getOptions().find(name => name.toLocaleLowerCase() === typed);
                if (exact) {
                    event.preventDefault();
                    close();
                    onPick(exact);
                }
            }
        }
    });
    return { close, open };
}

// Replace the browser's native <datalist> popup with the same suggestion list the
// client filters use, so every autocomplete looks and behaves alike.
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[list]').forEach(input => {
        const datalist = document.getElementById(input.getAttribute('list'));
        if (!datalist) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'location-field';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const list = document.createElement('div');
        list.id = `${datalist.id}Menu`;
        list.className = 'location-suggestions';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        wrapper.appendChild(list);

        input.removeAttribute('list');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');

        const suggestions = createSuggestionList(
            input,
            list,
            () => Array.from(datalist.options, option => option.value),
            name => {
                input.value = name;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                suggestions.close();
            },
            { acceptExactOnEnter: false }
        );
    });
});
