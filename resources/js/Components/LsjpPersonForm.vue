<script setup>
// Fields for an LSJP register record — shared by "Add person" (Lsjp/Index)
// and "Edit details" (Lsjp/Show). `form` is an Inertia useForm object.
import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({ form: Object, provinceDistricts: Object });

const page = usePage();
const keyPops = computed(() => page.props.keyPops ?? []);
const projects = computed(() => page.props.projects ?? []);
const districts = computed(() => props.provinceDistricts[props.form.province] ?? []);

// A district outside the newly chosen province is cleared.
watch(() => props.form.province, () => {
    if (props.form.district && !districts.value.includes(props.form.district)) props.form.district = '';
});

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <div class="space-y-3">
        <div>
            <label class="label">Full name *</label>
            <input v-model="form.full_name" class="input" required />
            <p v-if="form.errors.full_name" class="mt-1 text-xs text-red-600">{{ form.errors.full_name }}</p>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="label">ID number</label>
                <input v-model="form.id_number" class="input" placeholder="e.g. 63-123456A78" />
                <p v-if="form.errors.id_number" class="mt-1 text-xs text-red-600">{{ form.errors.id_number }}</p>
            </div>
            <div>
                <label class="label">Phone</label>
                <input v-model="form.phone" class="input" placeholder="For follow-up calls" />
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="label">Age</label>
                <input v-model="form.age" type="number" min="10" max="100" class="input" />
                <p v-if="form.errors.age" class="mt-1 text-xs text-red-600">{{ form.errors.age }}</p>
            </div>
            <div>
                <label class="label">Sex</label>
                <select v-model="form.sex" class="input">
                    <option value="">—</option>
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="label">Province</label>
                <select v-model="form.province" class="input">
                    <option value="">—</option>
                    <option v-for="(_, p) in provinceDistricts" :key="p" :value="p">{{ p }}</option>
                </select>
            </div>
            <div>
                <label class="label">District</label>
                <select v-model="form.district" class="input" :disabled="!form.province">
                    <option value="">{{ form.province ? '—' : 'Pick a province first' }}</option>
                    <option v-for="d in districts" :key="d" :value="d">{{ d }}</option>
                </select>
            </div>
        </div>
        <div>
            <label class="label">Location (village / ward / area)</label>
            <input v-model="form.location" class="input" />
        </div>
        <div>
            <label class="label">Key population</label>
            <select v-model="form.key_population" class="input">
                <option value="">—</option>
                <option v-for="k in keyPops" :key="k" :value="k">{{ k }}</option>
                <option v-if="form.key_population && !keyPops.includes(form.key_population)" :value="form.key_population">{{ form.key_population }}</option>
            </select>
        </div>
        <div>
            <label class="label">Current business or activity</label>
            <input v-model="form.current_activity" class="input" placeholder="e.g. Tailoring from home, selling vegetables, unemployed" />
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="label">Skill taught</label>
                <input v-model="form.skill_trained" class="input" placeholder="e.g. Tailoring" />
            </div>
            <div>
                <label class="label">Project</label>
                <select v-model="form.project" class="input">
                    <option value="">—</option>
                    <option v-for="p in projects" :key="p" :value="p">{{ p }}</option>
                    <option v-if="form.project && !projects.includes(form.project)" :value="form.project">{{ form.project }}</option>
                </select>
            </div>
        </div>
        <div>
            <label class="label">Training completed on *</label>
            <input v-model="form.training_completed_on" type="date" :max="today" class="input" required />
            <p class="mt-1 text-xs text-gray-400">Check-ups fall due 1, 3 and 6 months after this date.</p>
            <p v-if="form.errors.training_completed_on" class="mt-1 text-xs text-red-600">{{ form.errors.training_completed_on }}</p>
        </div>
        <div>
            <label class="label">Notes</label>
            <textarea v-model="form.notes" class="input" rows="2"></textarea>
        </div>
    </div>
</template>
