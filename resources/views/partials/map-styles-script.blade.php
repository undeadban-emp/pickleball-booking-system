<script>
    // Free, no-API-key tile providers. Shared by the admin location picker
    // and the homepage map so both ever offer exactly the same styles.
    window.MAP_STYLES = {
        standard: {
            label: 'Standard',
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        },
        light: {
            label: 'Light',
            url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 20,
        },
        dark: {
            label: 'Dark',
            url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 20,
        },
        satellite: {
            label: 'Satellite',
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            attribution: 'Tiles &copy; Esri',
            maxZoom: 19,
        },
        terrain: {
            label: 'Terrain',
            url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
            attribution: '&copy; <a href="https://opentopomap.org">OpenTopoMap</a> contributors',
            maxZoom: 17,
        },
    };
</script>
