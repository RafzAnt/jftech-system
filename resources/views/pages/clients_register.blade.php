<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JFTECH - Register Client</title>
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
                <h1 class="page-title">Register New Client</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem;">Add a new WiFi client to the system</p>
            </div>
            <a href="clients_browse.php" class="btn btn-secondary">Browse Clients</a>
        </div>

        @if ($success)
        <div class="alert alert-success">{{ $success }}</div>
        @endif
        @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <form method="POST" action="" class="card">
            @csrf
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-input" placeholder="Juan Dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" name="contact_number" class="form-input" placeholder="09123456789" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" placeholder="juan@email.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">WiFi Plan *</label>
                        <select name="internet_plan_id" class="form-select" id="wifiPlan" required>
                            <option value="">Select Plan</option>
                            @foreach ($plans as $plan)
                            <option value="{{ $plan['id'] }}" data-price="{{ $plan['monthly_price'] }}" data-installation-fee="{{ $plan['installation_fee'] }}">
                                {{ $plan['plan_name'] }} ({{ $plan['speed_mbps'] }} Mbps) - ₱{{ number_format($plan['monthly_price'], 2) }}/mo
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Rate (PHP)</label>
                        <input type="number" name="monthly_rate" class="form-input" id="monthlyRate" placeholder="500.00" step="0.01" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Installation Fee (PHP)</label>
                        <input type="number" class="form-input" id="installationFee" step="0.01" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Installation Date</label>
                        <input type="date" name="installation_date" class="form-input" value="{{ date('Y-m-d') }}">
                    </div>
                </div>

                <div class="section-title">Address Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Barangay *</label>
                        <select name="barangay_id" class="form-select" id="barangaySelect" required>
                            <option value="">Select Barangay</option>
                            @foreach ($barangays as $barangay)
                            <option value="{{ $barangay['id'] }}" data-lat="{{ $barangay['latitude'] }}" data-lng="{{ $barangay['longitude'] }}">
                                {{ $barangay['barangay_name'] }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Exact Address</label>
                        <input type="text" name="exact_address" class="form-input" placeholder="Purok 1, Sitio...">
                    </div>
                </div>

                <div class="section-title">Location on Map</div>
                <div class="form-group">
                    <label class="form-label">Marker Color</label>
                    <div class="marker-color-options">
                        <div class="color-option selected" style="background: #00BFFF;" data-color="#00BFFF"></div>
                        <div class="color-option" style="background: #00E676;" data-color="#00E676"></div>
                        <div class="color-option" style="background: #FF1744;" data-color="#FF1744"></div>
                        <div class="color-option" style="background: #FFAB00;" data-color="#FFAB00"></div>
                        <div class="color-option" style="background: #E040FB;" data-color="#E040FB"></div>
                        <div class="color-option" style="background: #2979FF;" data-color="#2979FF"></div>
                        <div class="color-option" style="background: #76FF03;" data-color="#76FF03"></div>
                        <div class="color-option" style="background: #FF4081;" data-color="#FF4081"></div>
                    </div>
                    <input type="hidden" name="marker_color" id="markerColor" value="#00BFFF">
                </div>
                 <div id="map" class="map-container" style="margin-bottom: 1rem;"></div>
                <input type="hidden" name="latitude" id="latitude" value="9.7562">
                <input type="hidden" name="longitude" id="longitude" value="123.9718">

                <div style="display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Client</button>
                </div>
            </div>
        </form>
    </div>

    <script>
    // Initialize map
    const map = L.map('map', {
        center: [9.7562, 123.9718],
        zoom: 14,
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

     let marker = null;
     let userLocationMarker = null;
     let barangayMarker = null;
     let directionsLayer = null;
     let currentUserCoords = null;

     // Do not allow an inaccurate IP/VPN location outside Bohol to move this map.
     const isWithinBohol = (lat, lng) => lat >= 9.35 && lat <= 10.20 && lng >= 123.55 && lng <= 124.75;

     // Map click handler
     map.on('click', function(e) {
         const lat = e.latlng.lat;
         const lng = e.latlng.lng;
         document.getElementById('latitude').value = lat;
         document.getElementById('longitude').value = lng;
         updateMarker(lat, lng);
     });

     function updateMarker(lat, lng) {
         const color = document.getElementById('markerColor').value;
         if (marker) map.removeLayer(marker);
         // Create custom location marker icon
         const markerIcon = L.divIcon({
             className: 'custom-location-marker',
             html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${color}" width="32" height="32" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                     <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                     <circle cx="12" cy="9" r="2.5" fill="white"/>
                    </svg>`,
             iconSize: [32, 32],
             iconAnchor: [16, 32],
             popupAnchor: [0, -32]
         });
         marker = L.marker([lat, lng], { icon: markerIcon }).addTo(map).bindPopup('Client Location');
     }

     function setClientLocation(lat, lng, label = '') {
         document.getElementById('latitude').value = lat;
         document.getElementById('longitude').value = lng;
         updateMarker(lat, lng);
         map.setView([lat, lng], 18);
         if (label) document.querySelector('[name="exact_address"]').value = label;
         if (currentUserCoords) drawRoute(currentUserCoords.lat, currentUserCoords.lng, lat, lng);
     }

     function clearDirections() {
         if (directionsLayer) {
             map.removeLayer(directionsLayer);
             directionsLayer = null;
         }
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

     function updateBarangayMarker(lat, lng, name) {
         if (barangayMarker) {
             map.removeLayer(barangayMarker);
         }

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

     async function drawRoute(startLat, startLng, endLat, endLng, color = '#00BFFF') {
         clearDirections();

         const url = `https://router.project-osrm.org/route/v1/driving/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson`;

         try {
             const response = await fetch(url);
             const data = await response.json();

             if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                 const routeCoords = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                 directionsLayer = L.polyline(routeCoords, {
                     color,
                     weight: 5,
                     opacity: 0.85
                 }).addTo(map);

                 const bounds = L.latLngBounds([[startLat, startLng], [endLat, endLng]]);
                 bounds.extend(directionsLayer.getBounds());
                 map.fitBounds(bounds, { padding: [50, 50] });
             }
         } catch (error) {
             console.error('Routing error:', error);
         }
     }

     function requestUserLocation() {
         if (!navigator.geolocation) {
             return;
         }

         navigator.geolocation.getCurrentPosition(
             (position) => {
                 const userLat = position.coords.latitude;
                 const userLng = position.coords.longitude;
                 if (!isWithinBohol(userLat, userLng)) {
                     console.warn('Browser location is outside Bohol; the map remains centered in Balilihan.');
                     return;
                 }
                 updateUserLocationMarker(userLat, userLng);

                 const barangaySelect = document.getElementById('barangaySelect');
                 const option = barangaySelect.options[barangaySelect.selectedIndex];
                 if (option && option.value) {
                     const lat = parseFloat(option.dataset.lat);
                     const lng = parseFloat(option.dataset.lng);
                     if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                         drawRoute(userLat, userLng, lat, lng);
                     }
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
            drawRoute(currentUserCoords.lat, currentUserCoords.lng, lat, lng);
        } else {
            map.setView([lat, lng], 14);
            requestUserLocation();
        }
    });

    // WiFi plan handler
    document.getElementById('wifiPlan').addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        document.getElementById('monthlyRate').value = option.dataset.price || 0;
        document.getElementById('installationFee').value = option.dataset.installationFee || 0;
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

    requestUserLocation();
    </script>
    <script src="{{ asset('js/jftech-main.js') }}?{{ $mainJsVersion }}"></script>
</body>
</html>
