<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Browse Clients</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/clients_browse.css') }}?{{ $clientsBrowseCssVersion }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Browse Clients</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">View and manage all registered clients</p>
            </div>
            <a href="clients_register.php" class="btn btn-success">+ Register New Client</a>
        </div>

<!-- Tabs -->
<div class="tabs-container">
    <div class="tabs">
        <a href="clients_browse.php" class="tab-btn {{ !$showArchived ? 'active' : '' }}">Active Clients</a>
        <a href="clients_browse.php?view=archived" class="tab-btn {{ $showArchived ? 'active' : '' }}">Archived ({{ $archivedCount }})</a>
    </div>
</div>

<!-- Barangay Selector & Search -->
<div class="filter-bar card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <div class="form-group" style="margin: 0; min-width: 250px;">
            <label class="form-label" style="margin-bottom: 0.35rem;">Select Barangay</label>
            <select class="form-select" id="barangayFilter" onchange="filterByBarangay(this.value)">
                <option value="">All Barangays</option>
                @foreach ($barangays as $barangay)
                <option value="{{ $barangay['id'] }}" data-lat="{{ $barangay['latitude'] }}" data-lng="{{ $barangay['longitude'] }}" {{ $selectedBarangay == $barangay['id'] ? 'selected' : '' }}>
                    {{ $barangay['barangay_name'] }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="search-box" style="margin: 0; margin-top: 1.25rem;">
            <input type="text" class="search-input" placeholder="Search clients..." id="searchInput" value="{{ htmlspecialchars($searchTerm) }}" onkeyup="searchClients()">
        </div>
        <div style="margin-top: 1.25rem;">
            <span class="badge badge-info">{{ count($clients) }} Client(s) Found</span>
        </div>
    </div>
</div>

<!-- Map Section -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <span class="card-title">Client Locations Map</span>
        @if ($selectedBarangayData)
        <span class="badge badge-success">{{ $selectedBarangayData['barangay_name'] }}</span>
        @else
        <span class="badge badge-info">All Barangays</span>
        @endif
    </div>
    <div id="map" class="map-container"></div>
    <div class="card-footer" style="font-size: 0.8rem; color: var(--text-muted);">
        Each client is marked with their assigned color. Click on a marker to view client details.
    </div>
</div>

<!-- Clients Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <span class="card-title">{{ $showArchived ? 'Archived Clients' : 'Client List' }}</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-container">
            <table class="data-table" id="clientsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Barangay</th>
                        <th>Contact</th>
                        <th>Plan</th>
                        <th>Rate</th>
                        <th>Installed</th>
                        <th>Next Due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clients as $client)
                    <tr data-lat="{{ $client['latitude'] }}" data-lng="{{ $client['longitude'] }}" data-name="{{ htmlspecialchars($client['full_name']) }}">
                        <td><strong>{{ $client['client_code'] }}</strong></td>
                        <td>{{ htmlspecialchars($client['full_name']) }}</td>
                        <td>{{ $client['barangay_name'] ?: 'N/A' }}</td>
                        <td>{{ $client['contact_number'] }}</td>
                        <td>{{ $client['wifi_plan'] }}</td>
                        <td style="color: var(--success); font-weight: 600;">PHP {{ number_format($client['monthly_rate'], 2) }}</td>
                        <td>{{ $client['installation_date'] ? date('M d, Y', strtotime($client['installation_date'])) : '-' }}</td>
                        <td>
                            @if (!empty($client['next_due_date']))
                                @if ($client['next_due_status'] === 'overdue')
                                    <span style="color: var(--danger); font-weight: 700;">{{ date('M d, Y', strtotime($client['next_due_date'])) }} <small class="badge badge-danger">Overdue</small></span>
                                @else
                                    <span>{{ date('M d, Y', strtotime($client['next_due_date'])) }}</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if ($client['status'] === 'active')
                                <span class="badge badge-success">Active</span>
                            @elseif ($client['status'] === 'inactive')
                                <span class="badge badge-warning">Inactive</span>
                            @else
                                <span class="badge badge-danger">Suspended</span>
                            @endif
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                @if ($showArchived)
                                    <form method="post" action="" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                                        <button type="submit" class="btn btn-sm btn-success" title="Restore">Restore</button>
                                    </form>
                                    <form method="post" action="" style="display:inline;" onsubmit="return confirm('Permanently delete this client? This cannot be undone.');">
                                        @csrf
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Permanently delete">Delete</button>
                                    </form>
                                @else
                                    <a href="clients_update.php?id={{ $client['id'] }}" class="btn btn-sm btn-primary btn-icon" title="Edit">Edit</a>
                                    <button type="button" class="btn btn-sm btn-secondary btn-icon" onclick="viewClient({{ $client['id'] }})" title="View client details">View</button>
                                    <form method="post" action="" style="display:inline;margin-left:.35rem;">
                                        @csrf
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                                        <button type="submit" class="btn btn-sm btn-outline" title="Archive">Archive</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                    @if (empty($clients))
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            {{ $showArchived ? 'No archived clients.' : 'No clients found. <a href="clients_register.php" style="color: var(--accent);">Register a new client</a>' }}
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- Client details are rendered dynamically via viewClient() (AJAX) to always reflect live billing/payment status -->

    <!-- Client Details Modal -->
    <div class="modal-overlay" id="clientModal" onclick="if (event.target === this) this.classList.remove('active');">
        <div class="modal-content cd-modal modal-large">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Client Details</h3>
                </div>
                <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div id="clientDetails" class="cd-body"></div>
        </div>
    </div>

    <!-- Receipt Print Modal -->
    <div class="modal-overlay" id="receiptModal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <div style="display:flex;align-items:center;gap:.5rem">
                    @include('partials.modal_logo')
                    <h3 class="modal-title">Client Receipt</h3>
                </div>
                <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div id="receiptContent" style="background: white; color: black; padding: 1.5rem; border-radius: 8px;"></div>
            <div style="text-align: center; margin-top: 1rem;">
                <button class="btn btn-primary" onclick="window.print()">Print Receipt</button>
            </div>
        </div>
    </div>

    <script>
    // Client data for map
    const clients = {!! json_encode(array_map(function($c) {
        return [
            'id' => $c['id'],
            'name' => $c['full_name'],
            'code' => $c['client_code'],
            'lat' => (float)$c['latitude'],
            'lng' => (float)$c['longitude'],
            'color' => $c['marker_color'],
            'barangay' => $c['barangay_name'],
            'contact' => $c['contact_number'],
            'plan' => $c['wifi_plan'],
            'rate' => $c['monthly_rate'],
            'status' => $c['status']
        ];
    }, $clients), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    const barangays = {!! json_encode(array_map(function($b) {
        return [
            'id' => $b['id'],
            'name' => $b['barangay_name'],
            'lat' => (float)$b['latitude'],
            'lng' => (float)$b['longitude']
        ];
    }, $barangays), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    const selectedBarangayId = '{{ $selectedBarangay }}';

    // Initialize map
    let mapCenter = [9.7562, 123.9718];
    let mapZoom = 13;

    if (selectedBarangayId) {
        const barangay = barangays.find(b => b.id == selectedBarangayId);
        if (barangay) {
            mapCenter = [barangay.lat, barangay.lng];
            mapZoom = 15;
        }
    }

    let map = null;
    const markers = {};

    if (window.L) {
    map = L.map('map', {
        center: mapCenter,
        zoom: mapZoom,
        zoomControl: true,
        scaleControl: true
    });

    // Define base tile layers
    const osmStandard = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    });

    const esriSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri',
        maxZoom: 19,
        maxNativeZoom: 16
    });

    const esriStreets = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri',
        maxZoom: 19,
        maxNativeZoom: 18
    });

    // Add default layer
    osmStandard.addTo(map);

    // Add layer control
    L.control.layers({
        'OpenStreetMap': osmStandard,
        'Esri Streets': esriStreets,
        'Satellite': esriSatellite
    }).addTo(map);

    // Add barangay markers with distinct pins
    barangays.forEach(barangay => {
        if (!selectedBarangayId || barangay.id == selectedBarangayId) {
            // Create barangay marker with teal/cyan pin
            const barangayIcon = L.divIcon({
                className: 'barangay-marker',
                html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#00BCD4" width="30" height="30" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                        <circle cx="12" cy="9" r="3" fill="white"/>
                       </svg>`,
                iconSize: [30, 30],
                iconAnchor: [15, 30],
                popupAnchor: [0, -30]
            });

            L.marker([barangay.lat, barangay.lng], { icon: barangayIcon })
                .addTo(map)
                .bindPopup('<strong>' + barangay.name + '</strong><br>Barangay Center');
        }
    });

    // Directions layer (route polyline and markers)
    let directionsLayer = null;
    let userLocationMarker = null;
    let currentUserCoords = null;
    let selectedBarangayMarker = null;
    const isWithinBohol = (lat, lng) => lat >= 9.35 && lat <= 10.20 && lng >= 123.55 && lng <= 124.75;

    // Add client markers with location icons and directions button
    clients.forEach(client => {
        if (client.lat && client.lng) {
            // Create custom location marker icon with client color
            const markerIcon = L.divIcon({
                className: 'custom-location-marker',
                html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${client.color}" width="32" height="32" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                        <circle cx="12" cy="9" r="2.5" fill="white"/>
                       </svg>`,
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });

            const marker = L.marker([client.lat, client.lng], { icon: markerIcon }).addTo(map);

            marker.bindPopup(`
                <div style="min-width: 200px;">
                    <h4 style="margin: 0 0 8px 0; color: ${client.color};">${client.name}</h4>
                    <p style="margin: 4px 0; font-size: 12px;"><strong>Code:</strong> ${client.code}</p>
                    <p style="margin: 4px 0; font-size: 12px;"><strong>Barangay:</strong> ${client.barangay}</p>
                    <p style="margin: 4px 0; font-size: 12px;"><strong>Plan:</strong> ${client.plan}</p>
                    <p style="margin: 4px 0; font-size: 12px;"><strong>Rate:</strong> PHP ${parseFloat(client.rate).toFixed(2)}</p>
                    <p style="margin: 4px 0; font-size: 12px;"><strong>Status:</strong> ${client.status.toUpperCase()}</p>
                    <button class="directions-btn" data-lat="${client.lat}" data-lng="${client.lng}" data-name="${client.name}" data-color="${client.color}" data-id="${client.id}" style="margin-top: 8px; padding: 6px 12px; background: var(--accent); color: white; border: none; border-radius: 4px; cursor: pointer; width: 100%;">
                        Get Directions
                    </button>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=${client.lat},${client.lng}&travelmode=driving" target="_blank" rel="noopener" style="display:block;text-align:center;margin-top:6px;font-size:12px;">Open turn-by-turn navigation</a>
                    <div class="directions-info" id="directions-info-${client.id}" style="margin-top: 8px; font-size: 12px;"></div>
                </div>
            `);

            markers[client.id] = marker;
        }
    });

    // Event delegation for directions buttons
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('directions-btn')) {
            const btn = e.target;
            const lat = parseFloat(btn.dataset.lat);
            const lng = parseFloat(btn.dataset.lng);
            const name = btn.dataset.name;
            const color = btn.dataset.color;
            const id = btn.dataset.id;
            getDirections(lat, lng, name, color, id);
        }
    });

    // Get directions from user's location to client
    async function getDirections(destLat, destLng, destName, destColor, clientId) {
        // Request user location
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        const infoDiv = document.getElementById('directions-info-' + clientId);
        if (infoDiv) {
            infoDiv.innerHTML = '<em>Getting your location...</em>';
        }

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                if (!isWithinBohol(userLat, userLng)) {
                    if (infoDiv) infoDiv.innerHTML = '<em>Your browser location is outside Bohol. Enable precise device location before requesting directions.</em>';
                    return;
                }
                currentUserCoords = { lat: userLat, lng: userLng };

                // Add/update user location marker
                if (userLocationMarker) {
                    map.removeLayer(userLocationMarker);
                }
                userLocationMarker = L.marker([userLat, userLng], {
                    icon: L.divIcon({
                        className: 'user-location-marker',
                        html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#4285F4" width="28" height="28" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));">
                                <circle cx="12" cy="12" r="10"/>
                                <circle cx="12" cy="12" r="4" fill="white"/>
                               </svg>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14]
                    })
                }).addTo(map).bindPopup('Your Location');

                // Remove previous route
                if (directionsLayer) {
                    map.removeLayer(directionsLayer);
                }

                // Get route from OSRM
                const url = `https://router.project-osrm.org/route/v1/driving/${userLng},${userLat};${destLng},${destLat}?overview=full&geometries=geojson`;
                
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    
                    if (data.code === 'Ok' && data.routes.length > 0) {
                        const route = data.routes[0];
                        const distance = (route.distance / 1000).toFixed(2); // km
                        const duration = Math.round(route.duration / 60); // minutes
                        
                        // Draw route
                        const routeCoordinates = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);
                        directionsLayer = L.polyline(routeCoordinates, {
                            color: destColor,
                            weight: 5,
                            opacity: 0.8
                        }).addTo(map);

                        // Fit map to show entire route
                        map.fitBounds(directionsLayer.getBounds(), { padding: [50, 50] });

                        // Update info
                        if (infoDiv) {
                            infoDiv.innerHTML = `
                                <div style="color: ${destColor}; font-weight: bold;">
                                    📍 ${distance} km
                                </div>
                                <div>
                                    ⏱️ ~${duration} min
                                </div>
                            `;
                        }
                    } else {
                        if (infoDiv) {
                            infoDiv.innerHTML = '<em>No route found</em>';
                        }
                    }
                } catch (error) {
                    console.error('Routing error:', error);
                    if (infoDiv) {
                        infoDiv.innerHTML = '<em>Error getting directions</em>';
                    }
                }
            },
            (error) => {
                let msg = 'Unable to get your location.';
                if (error.code === 1) msg = 'Location access denied.';
                if (infoDiv) {
                    infoDiv.innerHTML = '<em>' + msg + '</em>';
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    function updateUserLocationMarker(lat, lng) {
        currentUserCoords = { lat, lng };
        if (userLocationMarker) {
            map.removeLayer(userLocationMarker);
        }

        userLocationMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'user-location-marker',
                html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#4285F4" width="28" height="28" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));">
                        <circle cx="12" cy="12" r="10"/>
                        <circle cx="12" cy="12" r="4" fill="white"/>
                       </svg>`,
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            })
        }).addTo(map).bindPopup('Your Location');
    }

    function updateSelectedBarangayMarker(barangay) {
        if (selectedBarangayMarker) {
            map.removeLayer(selectedBarangayMarker);
        }

        selectedBarangayMarker = L.marker([barangay.lat, barangay.lng], {
            icon: L.divIcon({
                className: 'barangay-marker',
                html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#00BCD4" width="34" height="34" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                        <circle cx="12" cy="9" r="3" fill="white"/>
                       </svg>`,
                iconSize: [34, 34],
                iconAnchor: [17, 34],
                popupAnchor: [0, -34]
            })
        }).addTo(map).bindPopup(`<strong>${barangay.name}</strong><br>Barangay Center`);
    }

    async function drawBarangayRoute(barangay) {
        if (!navigator.geolocation) {
            return;
        }

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                if (!isWithinBohol(userLat, userLng)) return;

                updateUserLocationMarker(userLat, userLng);
                updateSelectedBarangayMarker(barangay);

                if (directionsLayer) {
                    map.removeLayer(directionsLayer);
                }

                const url = `https://router.project-osrm.org/route/v1/driving/${userLng},${userLat};${barangay.lng},${barangay.lat}?overview=full&geometries=geojson`;

                try {
                    const response = await fetch(url);
                    const data = await response.json();

                    if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                        const routeCoords = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                        directionsLayer = L.polyline(routeCoords, {
                            color: '#00BCD4',
                            weight: 5,
                            opacity: 0.85
                        }).addTo(map);

                        const bounds = L.latLngBounds([[userLat, userLng], [barangay.lat, barangay.lng]]);
                        bounds.extend(directionsLayer.getBounds());
                        map.fitBounds(bounds, { padding: [50, 50] });
                    }
                } catch (error) {
                    console.error('Routing error:', error);
                }
            },
            (error) => {
                console.error('Location error:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    if (selectedBarangayId) {
        const selectedBarangay = barangays.find(b => b.id == selectedBarangayId);
        if (selectedBarangay) {
            drawBarangayRoute(selectedBarangay);
        }
    }
    } else {
        document.getElementById('map').innerHTML = '<div class="map-unavailable">Map is unavailable while Leaflet is offline. Client View and Print remain available.</div>';
    }

    // Filter by barangay
    function filterByBarangay(barangayId) {
        const url = new URL(window.location.href);
        if (barangayId) {
            url.searchParams.set('barangay', barangayId);
        } else {
            url.searchParams.delete('barangay');
        }
        url.searchParams.delete('search');
        window.location.href = url.toString();
    }

    // Search clients
    function searchClients() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('clientsTable');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName('td');
            let found = false;
            for (let j = 0; j < td.length; j++) {
                if (td[j] && td[j].textContent.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            tr[i].style.display = found ? '' : 'none';
        }
    }

    // View client details
    function formatDate(dateString) {
        if (!dateString) return '-';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-US', options);
    }

    function viewClient(clientId) {
        const client = clients.find(c => String(c.id) === String(clientId));
        if (!client) return;

        fetch('../backend/api/get_client_billing.php?client_id=' + clientId)
            .then(r => r.json())
            .then(data => {
                const bills = data.bills || [];
                const payments = data.payments || [];

                let billsHTML = '';
                if (bills.length > 0) {
                    billsHTML = bills.map(bill => `
                        <tr>
                            <td class="col-bill"><strong>${bill.bill_number}</strong></td>
                            <td class="col-period">${formatDate(bill.billing_period_start)} - ${formatDate(bill.billing_period_end)}</td>
                            <td class="col-amount">PHP ${parseFloat(bill.amount).toFixed(2)}</td>
                            <td class="col-due">${formatDate(bill.due_date)}</td>
                            <td class="col-status"><span class="badge badge-${bill.status === 'paid' ? 'success' : bill.status === 'pending' ? 'warning' : 'danger'}">${bill.status.toUpperCase()}</span></td>
                        </tr>
                    `).join('');
                } else {
                    billsHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1rem;">No billing records</td></tr>';
                }

                let paymentsHTML = '';
                if (payments.length > 0) {
                    paymentsHTML = payments.map(p => `
                        <tr>
                            <td class="col-date">${formatDate(p.payment_date)}</td>
                            <td class="col-bill">${p.bill_number || '-'}</td>
                            <td class="col-amount">PHP ${(p.bill_amount ? parseFloat(p.bill_amount) : 0).toFixed(2)}</td>
                            <td class="col-amount" style="color: var(--success); font-weight: 600;">PHP ${parseFloat(p.amount).toFixed(2)}</td>
                            <td class="col-amount" style="color: var(--warning); font-weight: 600;">PHP ${parseFloat(p.balance || 0).toFixed(2)}</td>
                            <td class="col-method"><span class="badge badge-info">${p.payment_method.toUpperCase()}</span></td>
                        </tr>
                    `).join('');
                 } else {
                     paymentsHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1rem;">No payment records</td></tr>';
                }

                const details = `
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <div style="width: 60px; height: 60px; border-radius: 50%; background: ${client.color}; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; font-weight: bold;">
                            ${client.name.charAt(0).toUpperCase()}
                        </div>
                        <h3 style="margin: 0.5rem 0 0.25rem 0;">${client.name}</h3>
                        <span class="badge badge-${client.status === 'active' ? 'success' : client.status === 'inactive' ? 'warning' : 'danger'}">${client.status.toUpperCase()}</span>
                    </div>
                    <div class="client-details-grid">
                        <div class="info-box">
                            <span class="info-label">CLIENT CODE</span>
                            <p class="info-value">${client.code}</p>
                        </div>
                        <div class="info-box">
                            <span class="info-label">BARANGAY</span>
                            <p class="info-value">${client.barangay}</p>
                        </div>
                        <div class="info-row">
                            <div class="info-box">
                                <span class="info-label">CONTACT</span>
                                <p class="info-value">${client.contact}</p>
                            </div>
                            <div class="info-box">
                                <span class="info-label">PLAN</span>
                                <p class="info-value">${client.plan}</p>
                            </div>
                        </div>
                        <div class="rate-box">
                            <span class="info-label">MONTHLY RATE</span>
                            <p class="rate-value">PHP ${parseFloat(client.rate).toFixed(2)}</p>
                        </div>
                    </div>
                    <div style="margin-bottom: 0.5rem; font-weight: 600; color: var(--accent);">BILLING HISTORY</div>
                    <div style="max-height: 200px; overflow-y: auto; margin-bottom: 1rem;">
                        <table class="client-modal-table" style="width: 100%; font-size: 0.8rem; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <th class="col-bill">Bill #</th>
                                    <th class="col-period">Period</th>
                                    <th class="col-amount">Amount</th>
                                    <th class="col-status">Status</th>
                                </tr>
                            </thead>
                            <tbody>${billsHTML}</tbody>
                        </table>
                    </div>
                    <div style="margin-bottom: 0.5rem; font-weight: 600; color: var(--success);">PAYMENT HISTORY</div>
                    <div style="max-height: 200px; overflow-y: auto; margin-bottom: 1rem;">
                        <table class="client-modal-table" style="width: 100%; font-size: 0.8rem; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <th class="col-date">Date</th>
                                    <th class="col-bill">Bill</th>
                                    <th class="col-amount">Bill Amount</th>
                                    <th class="col-amount">Paid Amount</th>
                                    <th class="col-amount">Balance</th>
                                    <th class="col-method">Method</th>
                                </tr>
                            </thead>
                            <tbody>${paymentsHTML}</tbody>
                        </table>
                    </div>
                    <div style="text-align: center; margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: center;">
                        <a href="bill_inquiry.php?client_id=${client.id}" class="btn btn-primary">View Records</a>
                        <a href="payment_history.php?client_id=${client.id}" class="btn btn-success">Record Payment</a>
                    </div>
                `;

                document.getElementById('clientDetails').innerHTML = details;
                openModal('clientModal');

                // Center map on client
                if (map && markers[client.id]) {
                    map.setView([client.lat, client.lng], 16);
                    markers[client.id].openPopup();
                }
            })
            .catch(err => {
                console.error('Error loading client billing:', err);
                // Still show basic client details on error
                const details = `
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <div style="width: 60px; height: 60px; border-radius: 50%; background: ${client.color}; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; font-weight: bold;">
                            ${client.name.charAt(0).toUpperCase()}
                        </div>
                        <h3 style="margin: 0.5rem 0 0.25rem 0;">${client.name}</h3>
                        <span class="badge badge-${client.status === 'active' ? 'success' : client.status === 'inactive' ? 'warning' : 'danger'}">${client.status.toUpperCase()}</span>
                    </div>
                    <div style="display: grid; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="background: rgba(0,212,255,0.05); padding: 0.75rem; border-radius: 8px;">
                            <span style="color: var(--text-muted); font-size: 0.75rem;">CLIENT CODE</span>
                            <p style="margin: 0; font-weight: 600;">${client.code}</p>
                        </div>
                        <div style="background: rgba(0,212,255,0.05); padding: 0.75rem; border-radius: 8px;">
                            <span style="color: var(--text-muted); font-size: 0.75rem;">BARANGAY</span>
                            <p style="margin: 0; font-weight: 600;">${client.barangay}</p>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div style="background: rgba(0,212,255,0.05); padding: 0.75rem; border-radius: 8px;">
                                <span style="color: var(--text-muted); font-size: 0.75rem;">CONTACT</span>
                                <p style="margin: 0; font-weight: 600;">${client.contact}</p>
                            </div>
                            <div style="background: rgba(0,212,255,0.05); padding: 0.75rem; border-radius: 8px;">
                                <span style="color: var(--text-muted); font-size: 0.75rem;">PLAN</span>
                                <p style="margin: 0; font-weight: 600;">${client.plan}</p>
                            </div>
                        </div>
                        <div style="background: rgba(0,230,118,0.1); padding: 0.75rem; border-radius: 8px; text-align: center;">
                            <span style="color: var(--text-muted); font-size: 0.75rem;">MONTHLY RATE</span>
                            <p style="margin: 0; font-weight: 700; font-size: 1.25rem; color: var(--success);">PHP ${parseFloat(client.rate).toFixed(2)}</p>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="bill_inquiry.php?client_id=${client.id}" class="btn btn-primary">View Records</a>
                    </div>
                `;
                document.getElementById('clientDetails').innerHTML = details;
                openModal('clientModal');
                if (map && markers[client.id]) {
                    map.setView([client.lat, client.lng], 16);
                    markers[client.id].openPopup();
                }
            });
    }

    // Print receipt (open thermal print page)
    function printReceipt(clientId) {
        const url = '/pages/print_client.php?id=' + encodeURIComponent(clientId) + '&format=thermal';
        window.open(url, '_blank');
    }

    // Keep the row actions available even when optional map resources are offline.
    window.viewClient = viewClient;
    window.printReceipt = printReceipt;
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
