/**
 * The NAP box and port picker of the admin pages (Register New Client, confirming an installation report).
 *
 * After a barangay is chosen it lists that barangay's NAP boxes with their ports: a free port can be picked, a used port says
 * which client is plugged in, a faulty or reserved one cannot be picked. The chosen port goes in a hidden field (nap_port_id).
 *
 *   const picker = NapPicker.create({
 *       root: element,            // where the picker is drawn
 *       input: hiddenInput,       // receives the port id
 *       lookupUrl: '/nap-boxes/lookup',
 *       getBarangay: () => 'Boctol' or '' when there is none yet,
 *       getExcept: () => installation id or 0 (its own reserved port can be picked),
 *       selectedPortId: 12,       // optional, the port that is chosen from the start
 *       optional: true,           // the person may leave without a port
 *       onPick: (box, port) => {},// called when a port is picked (or both null when it is cleared); the box has its coordinates
 *   });
 *   picker.refresh();             // call again when the barangay changes
 */
(function () {
    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function create(options) {
        const root = options.root;
        const input = options.input;
        let boxes = [];
        let selected = Number(options.selectedPortId || input.value || 0) || 0;
        let requestId = 0;
        let message = '';

        function chosenLabel() {
            for (const box of boxes) {
                for (const port of box.ports) {
                    if (port.id === selected) return box.name + ' · Port ' + port.port_number;
                }
            }
            return '';
        }

        function choose(portId) {
            selected = portId;
            input.value = portId ? String(portId) : '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
            render();
            if (options.onPick) {
                const box = boxes.find(candidate => candidate.ports.some(port => port.id === portId));
                options.onPick(box || null, box ? box.ports.find(port => port.id === portId) : null);
            }
        }

        function render() {
            root.replaceChildren();

            const summary = element('div', 'nap-picker-summary');
            const label = chosenLabel();
            if (label) {
                summary.appendChild(element('strong', null, 'Connected to: ' + label));
                if (options.optional) {
                    const clear = element('button', 'nap-picker-clear', 'Clear');
                    clear.type = 'button';
                    clear.addEventListener('click', () => choose(0));
                    summary.appendChild(clear);
                }
            } else {
                summary.appendChild(element('span', 'nap-picker-hint', options.optional ? 'No NAP port chosen yet (optional).' : 'Choose a NAP port.'));
            }
            root.appendChild(summary);

            if (message) {
                root.appendChild(element('p', 'nap-picker-message', message));
                return;
            }

            boxes.forEach(box => {
                const card = element('div', 'nap-picker-box');
                const head = element('div', 'nap-picker-head');
                head.appendChild(element('strong', null, box.name));
                const free = box.available_ports;
                head.appendChild(element('span', 'nap-picker-count' + (free > 0 ? '' : ' is-full'), free + ' of ' + box.total_ports + ' ports free'));
                card.appendChild(head);

                const place = element('div', 'nap-picker-place');
                if (box.location) place.appendChild(element('span', null, box.location));
                if (box.map_url) {
                    const link = element('a', null, box.coordinates);
                    link.href = box.map_url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.title = 'Open in Google Maps';
                    place.appendChild(link);
                }
                if (place.childNodes.length) card.appendChild(place);

                const grid = element('div', 'nap-port-grid');
                box.ports.forEach(port => {
                    const state = port.status === 'occupied' ? 'used' : (port.status === 'faulty' ? 'faulty' : (port.reserved ? 'reserved' : 'free'));
                    const button = element('button', 'nap-port is-' + state + (port.id === selected ? ' is-selected' : ''));
                    button.type = 'button';
                    button.appendChild(element('span', 'nap-port-number', String(port.port_number)));
                    if (state === 'used') {
                        button.appendChild(element('span', 'nap-port-note', port.client_name || 'In use'));
                        button.title = 'Port ' + port.port_number + ' is connected to ' + (port.client_name || 'a client') + (port.client_code ? ' (' + port.client_code + ')' : '');
                        button.disabled = true;
                    } else if (state === 'faulty') {
                        button.appendChild(element('span', 'nap-port-note', 'Faulty'));
                        button.title = 'Port ' + port.port_number + ' is faulty';
                        button.disabled = true;
                    } else if (state === 'reserved') {
                        button.appendChild(element('span', 'nap-port-note', 'Reserved'));
                        button.title = 'Port ' + port.port_number + ' is used in an installation report that is waiting for the admin';
                        button.disabled = true;
                    } else {
                        button.appendChild(element('span', 'nap-port-note', port.id === selected ? 'Selected' : 'Free'));
                        button.title = 'Connect to port ' + port.port_number;
                        button.addEventListener('click', () => choose(port.id === selected && options.optional ? 0 : port.id));
                    }
                    grid.appendChild(button);
                });
                card.appendChild(grid);
                root.appendChild(card);
            });
        }

        async function refresh() {
            const barangay = (options.getBarangay() || '').trim();
            const ticket = ++requestId;
            if (barangay === '') {
                boxes = [];
                selected = 0;
                input.value = '';
                message = 'Choose a barangay to see its NAP boxes.';
                render();
                return;
            }

            message = 'Loading the NAP boxes of ' + barangay + '…';
            render();
            try {
                const url = new URL(options.lookupUrl, window.location.href);
                url.searchParams.set('barangay', barangay);
                const except = options.getExcept ? Number(options.getExcept()) : 0;
                if (except) url.searchParams.set('except', String(except));
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('lookup failed');
                const data = await response.json();
                if (ticket !== requestId) return;
                boxes = Array.isArray(data.boxes) ? data.boxes : [];
                message = boxes.length ? '' : 'There are no NAP boxes in ' + barangay + ' yet. Add them in Inventory · NAP Boxes first.';
                // A port that is no longer offered (another barangay, or taken meanwhile) is let go.
                if (selected && !chosenLabel()) {
                    selected = 0;
                    input.value = '';
                } else if (selected) {
                    // The chosen port stays only while it can still be taken.
                    const stillFree = boxes.some(box => box.ports.some(port => port.id === selected && port.status === 'available' && !port.reserved));
                    if (!stillFree) {
                        selected = 0;
                        input.value = '';
                    }
                }
            } catch (error) {
                if (ticket !== requestId) return;
                boxes = [];
                message = 'The NAP boxes could not be loaded. Check the connection and choose the barangay again.';
            }
            render();
        }

        refresh();
        return { refresh, choose };
    }

    window.NapPicker = { create };
})();
