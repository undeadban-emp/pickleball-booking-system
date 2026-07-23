<x-layouts.admin :title="'Location'">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950 dark:text-white">Location</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
        Click anywhere on the map (or drag the pin) to set the exact spot shown on the homepage map.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @php
        $__lat = $settings->map_lat ?? 14.5995;
        $__lng = $settings->map_lng ?? 120.9842;
        $__styles = [
            'standard' => ['label' => 'Standard', 'icon' => 'ph-map-trifold'],
            'light' => ['label' => 'Light', 'icon' => 'ph-sun'],
            'dark' => ['label' => 'Dark', 'icon' => 'ph-moon'],
            'satellite' => ['label' => 'Satellite', 'icon' => 'ph-planet'],
            'terrain' => ['label' => 'Terrain', 'icon' => 'ph-mountains'],
        ];
    @endphp

    <form
        method="POST"
        action="{{ route('admin.settings.location.update') }}"
        class="mt-6"
        x-data="{
            lat: {{ $__lat }},
            lng: {{ $__lng }},
            style: '{{ old('map_style', $settings->map_style ?? 'satellite') }}',
            picker: null,
        }"
        x-init="picker = window.initLocationPicker($refs.map, lat, lng, style, (newLat, newLng) => { lat = newLat; lng = newLng; })"
    >
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-ink-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <div class="flex flex-col gap-1.5 sm:w-96">
                <label for="map_location" class="text-xs font-medium text-ink-500 dark:text-ink-400">Label (shown above the map on the homepage)</label>
                <input
                    id="map_location"
                    name="map_location"
                    type="text"
                    value="{{ old('map_location', $settings->map_location) }}"
                    placeholder="e.g. Kitchen Line Pickleball Courts, Quezon City"
                    class="rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-200 focus:outline-none dark:border-ink-700 dark:bg-ink-950 dark:text-ink-100"
                >
                @error('map_location')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4 flex flex-col gap-1.5">
                <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Map style</label>
                <div class="flex flex-wrap gap-2">
                    @foreach ($__styles as $value => $meta)
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors has-checked:border-accent-500 has-checked:bg-accent-50 dark:border-ink-700 dark:has-checked:bg-accent-950">
                            <input
                                type="radio"
                                name="map_style"
                                value="{{ $value }}"
                                x-model="style"
                                @change="picker.setStyle(style)"
                                class="text-accent-600 focus:ring-accent-500"
                            >
                            <i class="ph {{ $meta['icon'] }} text-sm"></i>
                            {{ $meta['label'] }}
                        </label>
                    @endforeach
                </div>
                @error('map_style')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div x-ref="map" class="mt-4 h-96 w-full rounded-xl border border-ink-200 dark:border-ink-800"></div>

            <div class="mt-3 grid grid-cols-2 gap-3 sm:w-96">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Latitude</label>
                    <input name="map_lat" type="text" readonly :value="lat.toFixed(7)" class="rounded-lg border border-ink-200 bg-ink-50 px-3 py-2 font-mono text-xs text-ink-600 dark:border-ink-700 dark:bg-ink-950 dark:text-ink-300">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-ink-500 dark:text-ink-400">Longitude</label>
                    <input name="map_lng" type="text" readonly :value="lng.toFixed(7)" class="rounded-lg border border-ink-200 bg-ink-50 px-3 py-2 font-mono text-xs text-ink-600 dark:border-ink-700 dark:bg-ink-950 dark:text-ink-300">
                </div>
            </div>
            @error('map_lat')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            <button type="submit" class="mt-4 w-fit rounded-full bg-ink-950 px-6 py-2.5 text-sm font-semibold text-white transition-transform active:scale-[0.98] hover:bg-ink-800 dark:bg-accent-500 dark:text-ink-950 dark:hover:bg-accent-400">
                Save location
            </button>
        </div>
    </form>

    @include('partials.map-styles-script')

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        window.initLocationPicker = function (el, lat, lng, style, onPick) {
            const map = L.map(el).setView([lat, lng], 15);
            let tileLayer = L.tileLayer(window.MAP_STYLES[style].url, window.MAP_STYLES[style]).addTo(map);

            const marker = L.marker([lat, lng], { draggable: true }).addTo(map);

            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                onPick(pos.lat, pos.lng);
            });

            map.on('click', (e) => {
                marker.setLatLng(e.latlng);
                onPick(e.latlng.lat, e.latlng.lng);
            });

            return {
                setStyle(newStyle) {
                    map.removeLayer(tileLayer);
                    tileLayer = L.tileLayer(window.MAP_STYLES[newStyle].url, window.MAP_STYLES[newStyle]).addTo(map);
                },
            };
        };
    </script>

</x-layouts.admin>
