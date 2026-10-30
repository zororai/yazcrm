<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormTabs from '@/Components/DataCollection/FormTabs.vue';
import FilterBar from '@/Components/DataCollection/FilterBar.vue';
import { currentQuery } from '@/Components/DataCollection/answers';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

const props = defineProps({
    form: Object, can: Object, fields: Array, repeats: Array, enumerators: Array, filters: Object, statuses: Array,
    map: Object, geo: String, color: String,
});

const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#8b5cf6', '#e0a106', '#d03b6b', '#0e9db3', '#7c9a2a', '#b45309', '#6b7280'];
const NO_ANSWER = '#9ca3af';

const geoFields = computed(() => props.fields.filter(f => f.type === 'geopoint' && !f.hidden));
const colorFields = computed(() => props.fields.filter(f => f.type === 'select_one' && !f.repeat && !f.hidden));
const legend = computed(() => Object.entries(props.map.legend ?? {}).map(([value, label], i) => ({ value, label, color: PALETTE[i % PALETTE.length] })));
const colorOf = computed(() => Object.fromEntries(legend.value.map(l => [l.value, l.color])));

function setParam(params) {
    router.get(window.location.pathname, { ...currentQuery(), ...params }, { preserveScroll: true });
}

const el = ref(null);
let mapInstance = null;
let cluster = null;

function draw() {
    if (!mapInstance) return;
    cluster?.clearLayers();
    const markers = props.map.points.map(p => L.circleMarker([p.lat, p.lng], {
        radius: 7, weight: 1, color: '#fff', fillOpacity: 0.9,
        fillColor: p.color != null ? (colorOf.value[p.color] ?? NO_ANSWER) : (props.color ? NO_ANSWER : PALETTE[0]),
    }).bindPopup(`<a href="/data-collection/submissions/${p.id}">Submission #${p.id}</a>`));
    cluster.addLayers(markers);
    if (markers.length) mapInstance.fitBounds(cluster.getBounds(), { padding: [30, 30], maxZoom: 15 });
}

onMounted(() => {
    mapInstance = L.map(el.value).setView([-19.0, 29.8], 6); // Zimbabwe
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
    }).addTo(mapInstance);
    cluster = L.markerClusterGroup({ chunkedLoading: true });
    mapInstance.addLayer(cluster);
    draw();
});
watch(() => props.map, draw);
onBeforeUnmount(() => mapInstance?.remove());
</script>

<template>
    <AppLayout>
        <template #title>{{ form.name }}: map</template>

        <FormTabs :form="form" :can="can" active="map" :filters="filters" />
        <FilterBar :filters="filters" :fields="fields" :enumerators="enumerators" :statuses="statuses" />

        <div class="card mb-4 flex flex-wrap items-end gap-4">
            <div class="w-64">
                <label class="label">Location from</label>
                <select :value="geo ?? ''" class="input" @change="setParam({ geo: $event.target.value })">
                    <option v-for="f in geoFields" :key="f.path" :value="f.path">{{ f.label }}</option>
                    <option value="">Where the form was filled in (device GPS)</option>
                </select>
            </div>
            <div class="w-64">
                <label class="label">Colour by</label>
                <select :value="color ?? ''" class="input" @change="setParam({ color: $event.target.value || undefined })">
                    <option value="">One colour</option>
                    <option v-for="f in colorFields" :key="f.path" :value="f.path">{{ f.label }}</option>
                </select>
            </div>
            <p class="text-sm text-gray-500 ml-auto">{{ map.points.length.toLocaleString() }} points<template v-if="map.points.length >= 5000"> (first 5,000)</template></p>
        </div>

        <div class="card p-0 overflow-hidden">
            <div ref="el" class="h-[32rem] w-full z-0"></div>
            <div v-if="legend.length" class="flex flex-wrap gap-4 px-4 py-3 text-xs text-gray-600 border-t border-gray-100">
                <span v-for="l in legend" :key="l.value" class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-full" :style="{ background: l.color }"></span>{{ l.label }}
                </span>
                <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full" :style="{ background: NO_ANSWER }"></span>No answer</span>
            </div>
        </div>
    </AppLayout>
</template>
