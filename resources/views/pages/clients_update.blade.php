<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Update Client</title>
    <link rel="stylesheet" href="{{ asset('css/jftech-theme.css') }}?{{ $themeCssVersion }}">
    <link rel="stylesheet" href="{{ asset('css/forms.css') }}?{{ $formsCssVersion }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <div class="grid-overlay"></div>
    
    @include('layouts.sidebar')

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Update Client</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Search and update client information</p>
            </div>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <!-- Search Section -->
        @if (!$client)
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <span class="card-title">Search Client</span>
            </div>
            <div class="card-body">
                <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label">Search by name, code, or contact number</label>
                        <input type="text" name="search" class="form-input" placeholder="Enter search term..." value="{{ htmlspecialchars(request()->query('search') ?? '') }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>

                @if (request()->query('search') ?? '')
                <div class="section-title">Search Results</div>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Barangay</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($searchResults as $result)
                            <tr>
                                <td><strong>{{ $result['client_code'] }}</strong></td>
                                <td>{{ htmlspecialchars($result['full_name']) }}</td>
                                <td>{{ $result['barangay_name'] ?: 'N/A' }}</td>
                                <td>{{ $result['contact_number'] }}</td>
                                <td>
                                    <span class="badge badge-{{ $result['status'] === 'active' ? 'success' : ($result['status'] === 'inactive' ? 'warning' : 'danger') }}">
                                        {{ ucfirst($result['status']) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="?id={{ $result['id'] }}" class="btn btn-sm btn-primary">Edit</a>
                                </td>
                            </tr>
                            @endforeach
                            @if (empty($searchResults))
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No clients found</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Update Form -->
        @if ($client)
        <div class="card">
            <div class="card-header">
                <span class="card-title">Edit Client: {{ htmlspecialchars($client['full_name']) }}</span>
                <span class="badge badge-info">{{ $client['client_code'] }}</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
            @csrf
                    <input type="hidden" name="client_id" value="{{ $client['id'] }}">
                    <input type="hidden" name="update_client" value="1">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-input" value="{{ htmlspecialchars($client['full_name']) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number *</label>
                            <input type="text" name="contact_number" class="form-input" value="{{ htmlspecialchars($client['contact_number']) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" value="{{ htmlspecialchars($client['email'] ?? '') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">WiFi Plan *</label>
                            <select name="internet_plan_id" class="form-select" id="wifiPlan" required>
                                @foreach ($plans as $plan)
                                <option value="{{ $plan['id'] }}" data-price="{{ $plan['monthly_price'] }}" {{ ($client['internet_plan_id'] ?? null) == $plan['id'] ? 'selected' : '' }}>
                                    {{ $plan['plan_name'] }} ({{ $plan['speed_mbps'] }} Mbps) - ₱{{ number_format($plan['monthly_price'], 2) }}/mo{{ $plan['status'] !== 'active' ? ' — Inactive' : '' }}
                                </option>
                                @endforeach
                            </select>
                            <small style="display:block;margin-top:.4rem;color:var(--text-muted);">Changing the plan uses its current price. Saving other client details keeps the recorded installation price.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Monthly Rate (PHP)</label>
                            <input type="number" name="monthly_rate" class="form-input" id="monthlyRate" value="{{ $client['monthly_rate'] }}" step="0.01" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ $client['status'] === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $client['status'] === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="suspended" {{ $client['status'] === 'suspended' ? 'selected' : '' }}>Suspended</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-title">Address Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Barangay</label>
                            <select name="barangay_id" class="form-select" id="barangaySelect">
                                <option value="">Select Barangay</option>
                                @foreach ($barangays as $barangay)
                                <option value="{{ $barangay['id'] }}" data-lat="{{ $barangay['latitude'] }}" data-lng="{{ $barangay['longitude'] }}" {{ $client['barangay_id'] == $barangay['id'] ? 'selected' : '' }}>
                                    {{ $barangay['barangay_name'] }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Exact Address</label>
                            <input type="text" name="exact_address" class="form-input" value="{{ htmlspecialchars($client['exact_address'] ?? '') }}">
                        </div>
                    </div>

                    <div class="section-title">Location on Map</div>
                    <div class="form-group">
                        <label class="form-label">Marker Color</label>
                        <div class="marker-color-options">
                            @php
$colors = ['#00BFFF', '#00E676', '#FF1744', '#FFAB00', '#E040FB', '#2979FF', '#76FF03', '#FF4081'];
                            foreach ($colors as $color):
                                $isSelected = $client['marker_color'] === $color;
                            
@endphp
                            <div class="color-option {{ $isSelected ? 'selected' : '' }}" style="background: {{ $color }};" data-color="{{ $color }}"></div>
                            @endforeach
                        </div>
                        <input type="hidden" name="marker_color" id="markerColor" value="{{ $client['marker_color'] }}">
                    </div>
                     <div id="map" class="map-container" style="margin-bottom: 1rem;"></div>
                    <input type="hidden" name="latitude" id="latitude" value="{{ $client['latitude'] }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ $client['longitude'] }}">

                    <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                        <a href="clients_update.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>

    @if ($client)
    <script>
    const clientName = {{ json_encode($client['full_name']) }};
    const clientId = {{ $client['id'] }};
    let clientLat = parseFloat(document.getElementById('latitude').value) || 9.7562;
    let clientLng = parseFloat(document.getElementById('longitude').value) || 123.9718;
    const clientColor = document.getElementById('markerColor').value;

    const map = L.map('map', {
        center: [clientLat, clientLng],
        zoom: 15,
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

     // Directions layer and user marker
     let directionsLayer = null;
     let userLocationMarker = null;
     let barangayMarker = null;
     let currentUserCoords = null;
     const isWithinBohol = (lat, lng) => lat >= 9.35 && lat <= 10.20 && lng >= 123.55 && lng <= 124.75;

     // Create custom location marker icon
     const createMarkerIcon = (color) => L.divIcon({
         className: 'custom-location-marker',
         html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${color}" width="32" height="32" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                 <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                 <circle cx="12" cy="9" r="2.5" fill="white"/>
                </svg>`,
         iconSize: [32, 32],
         iconAnchor: [16, 32],
         popupAnchor: [0, -32]
     });

      let marker = L.marker([clientLat, clientLng], { icon: createMarkerIcon(clientColor) }).addTo(map);

     marker.bindPopup(`
         <div style="min-width: 200px;">
             <h4 style="margin: 0 0 8px 0; color: ${clientColor};">${clientName}</h4>
             <p style="margin: 4px 0; font-size: 12px;"><strong>Code:</strong> {{ $client['client_code'] }}</p>
             <p style="margin: 4px 0; font-size: 12px;"><strong>Barangay:</strong> {{ htmlspecialchars($client['barangay_name']) }}</p>
             <p style="margin: 4px 0; font-size: 12px;"><strong>Plan:</strong> {{ htmlspecialchars($client['wifi_plan']) }}</p>
             <p style="margin: 4px 0; font-size: 12px;"><strong>Rate:</strong> PHP {{ number_format($client['monthly_rate'], 2) }}</p>
             <p style="margin: 4px 0; font-size: 12px;"><strong>Status:</strong> {{ strtoupper($client['status']) }}</p>
             <button class="directions-btn" style="margin-top: 8px; padding: 6px 12px; background: var(--accent); color: white; border: none; border-radius: 4px; cursor: pointer; width: 100%;">
                 Get Directions
             </button>
             <div class="directions-info" id="directions-info" style="margin-top: 8px; font-size: 12px;"></div>
         </div>
     `);

      // Event delegation for directions button (inside popup)
      map.on('popupopen', function() {
          const btn = document.querySelector('.directions-btn');
          if (btn && !btn.hasAttribute('data-listener')) {
              btn.setAttribute('data-listener', 'true');
              btn.addEventListener('click', function() {
                  getDirections();
              });
          }
      });

     map.on('click', function(e) {
         const lat = e.latlng.lat;
         const lng = e.latlng.lng;
         document.getElementById('latitude').value = lat;
         document.getElementById('longitude').value = lng;
         updateMarker(lat, lng);
     });

     function updateMarker(lat, lng) {
         clientLat = lat;
         clientLng = lng;
         const color = document.getElementById('markerColor').value;
         if (marker) map.removeLayer(marker);
         marker = L.marker([lat, lng], { icon: createMarkerIcon(color) }).addTo(map);
     }

     function clearDirections() {
         if (directionsLayer) {
             map.removeLayer(directionsLayer);
             directionsLayer = null;
         }
     }

     function updateUserLocationMarker(lat, lng) {
         currentUserCoords = { lat, lng };
         if (userLocationMarker) map.removeLayer(userLocationMarker);
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

     function updateBarangayMarker(lat, lng, name) {
         if (barangayMarker) map.removeLayer(barangayMarker);
         barangayMarker = L.marker([lat, lng], {
             icon: L.divIcon({
                 className: 'barangay-marker',
                 html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#00BCD4" width="30" height="30" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.35));">
                         <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                         <circle cx="12" cy="9" r="3" fill="white"/>
                        </svg>`,
                 iconSize: [30, 30],
                 iconAnchor: [15, 30],
                 popupAnchor: [0, -30]
             })
         }).addTo(map).bindPopup(`${name} Barangay Center`);
     }

     async function drawRoute(startLat, startLng, endLat, endLng, color = clientColor) {
         clearDirections();

         const url = `https://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson`;

         try {
             const response = await fetch(url);
             const data = await response.json();

             if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                 const routeCoords = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                 directionsLayer = L.polyline(routeCoords, {
                     color,
                     weight: 5,
                     opacity: 0.8
                 }).addTo(map);

                 const bounds = L.latLngBounds([[startLat, startLng], [endLat, endLng]]);
                 bounds.extend(directionsLayer.getBounds());
                 map.fitBounds(bounds, { padding: [50, 50] });
             }
         } catch (err) {
             console.error('Routing error:', err);
         }
     }

     function requestUserLocationAndRoute(targetLat = clientLat, targetLng = clientLng) {
         if (!navigator.geolocation) {
             return;
         }

         navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                if (!isWithinBohol(userLat, userLng)) return;
                updateUserLocationMarker(userLat, userLng);
                drawRoute(userLat, userLng, targetLat, targetLng);
            },
            (err) => {
                console.error('Location error:', err);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
         );
     }

     // Get directions from user location to client
     async function getDirections() {
         if (!navigator.geolocation) {
             alert('Geolocation is not supported by your browser.');
             return;
         }

         const infoDiv = document.getElementById('directions-info');
         if (infoDiv) infoDiv.innerHTML = '<em>Getting your location...</em>';

         navigator.geolocation.getCurrentPosition(
            async (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                if (!isWithinBohol(userLat, userLng)) {
                    if (infoDiv) infoDiv.innerHTML = '<em>Your browser location is outside Bohol. Enable precise device location before requesting directions.</em>';
                    return;
                }

                updateUserLocationMarker(userLat, userLng);
                clearDirections();

                const url = `https://router.project-osrm.org/route/v1/driving/${userLng},${userLat};${clientLng},${clientLat}?overview=full&geometries=geojson`;

                try {
                    const response = await fetch(url);
                    const data = await response.json();

                    if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                        const route = data.routes[0];
                        const distance = (route.distance / 1000).toFixed(2);
                        const duration = Math.round(route.duration / 60);

                        const routeCoords = route.geometry.coordinates.map(c => [c[1], c[0]]);
                        directionsLayer = L.polyline(routeCoords, {
                            color: clientColor,
                            weight: 5,
                            opacity: 0.8
                        }).addTo(map);

                        const bounds = L.latLngBounds([[userLat, userLng], [clientLat, clientLng]]);
                        bounds.extend(directionsLayer.getBounds());
                        map.fitBounds(bounds, { padding: [50, 50] });

                        if (infoDiv) {
                            infoDiv.innerHTML = `
                                <div style="color: ${clientColor}; font-weight: bold;">
                                    📍 ${distance} km
                                </div>
                                <div>⏱️ ~${duration} min</div>
                            `;
                        }
                    } else {
                        if (infoDiv) infoDiv.innerHTML = '<em>No route found</em>';
                    }
                } catch (err) {
                    console.error('Routing error:', err);
                    if (infoDiv) infoDiv.innerHTML = '<em>Error getting directions</em>';
                }
            },
            (err) => {
                let msg = 'Unable to get your location.';
                if (err.code === 1) msg = 'Location access denied.';
                if (infoDiv) infoDiv.innerHTML = '<em>' + msg + '</em>';
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
         );
     }

     // Barangay selection handler
     document.getElementById('barangaySelect').addEventListener('change', function() {
         const option = this.options[this.selectedIndex];
         const lat = parseFloat(option.dataset.lat) || 9.7562;
         const lng = parseFloat(option.dataset.lng) || 123.9718;
         document.getElementById('latitude').value = lat;
         document.getElementById('longitude').value = lng;
         updateMarker(lat, lng);
         updateBarangayMarker(lat, lng, option.text);

         if (currentUserCoords) {
             drawRoute(currentUserCoords.lat, currentUserCoords.lng, lat, lng, '#00BCD4');
         } else {
             map.setView([lat, lng], 14);
             requestUserLocationAndRoute(lat, lng);
         }
     });

     // WiFi plan handler
     document.getElementById('wifiPlan').addEventListener('change', function() {
         const option = this.options[this.selectedIndex];
         document.getElementById('monthlyRate').value = option.dataset.price || 0;
     });

     // Color option handler
     document.querySelectorAll('.color-option').forEach(opt => {
         opt.addEventListener('click', function() {
             document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
             this.classList.add('selected');
             document.getElementById('markerColor').value = this.dataset.color;
             const lat = document.getElementById('latitude').value;
             const lng = document.getElementById('longitude').value;
             if (lat && lng) updateMarker(lat, lng);
         });
     });

     const initialBarangayOption = document.getElementById('barangaySelect').options[document.getElementById('barangaySelect').selectedIndex];
     if (initialBarangayOption && initialBarangayOption.value) {
         updateBarangayMarker(parseFloat(initialBarangayOption.dataset.lat), parseFloat(initialBarangayOption.dataset.lng), initialBarangayOption.text);
     }
     requestUserLocationAndRoute();
     </script>
     @endif
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
