<script setup>
import { computed, ref } from 'vue';
import { ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    service:              { type: String, default: '' },
    modelValue:           { type: Object, default: () => ({}) },
    serviceCategories:    { type: Object, default: () => ({}) },
    psychosocialType:     { type: String, default: '' },  // bound to ticket.psychosocial_type
});
const emit = defineEmits(['update:modelValue', 'update:psychosocialType']);

const data = computed({
    get: () => props.modelValue ?? {},
    set: (v) => emit('update:modelValue', v),
});

function persistCategories(patch = {}) {
    emit('update:modelValue', { ...data.value, ...patch, _categories: allSelected.value });
}

function set(key, val) {
    persistCategories({ [key]: val });
}
function get(key) { return data.value?.[key] ?? ''; }

// ── Category definitions ────────────────────────────────────────────────────
const CATEGORIES = [
  {
    key: 'household_social', label: 'Household & Social Determinants',
    keywords: ['household', 'social', 'food', 'poverty', 'shelter', 'cash transfer', 'displacement', 'disaster', 'climate', 'living', 'community', 'psycho', 'psychosocial'],
    fields: [
      { key: 'psychosocial_type', label: 'Psycho-social Support Type', type: 'psychosocial', options: ['Awareness Raising', 'Helpline Marketing', 'Counselling'] },
      { key: 'financial_dependence_level', label: 'Financial Dependence Level', type: 'select', options: ['Low', 'Medium', 'High', 'Very High'] },
      { key: 'food_insecurity', label: 'Food Insecurity', type: 'yesno' },
      { key: 'children_under_5', label: 'Children Under 5-18', type: 'number' },
      { key: 'household_composition', label: 'Household Composition', type: 'text' },
      { key: 'living_situation', label: 'Living Situation', type: 'select', options: ['Owned', 'Rented', 'Shared', 'Homeless', 'Shelter', 'Other'] },
      { key: 'social_support_level', label: 'Social Support Level', type: 'select', options: ['Strong', 'Moderate', 'Weak', 'None'] },
      { key: 'loneliness_isolation_duration', label: 'Loneliness / Isolation Duration', type: 'text' },
      { key: 'climate_event_type', label: 'Climate Event Type', type: 'text' },
      { key: 'disaster_affected', label: 'Disaster Affected', type: 'yesno' },
      { key: 'displacement_status', label: 'Displacement Status', type: 'select', options: ['Not Displaced', 'Internally Displaced', 'Refugee', 'Returnee'] },
      { key: 'poverty_cash_transfer_need', label: 'Poverty / Cash Transfer Need', type: 'yesno' },
    ],
  },
  {
    key: 'hiv', label: 'HIV Services',
    keywords: ['hiv', 'art', 'antiretroviral', 'viral load', 'cd4', 'adherence'],
    fields: [
      { key: 'ever_tested_hiv', label: 'Ever Tested HIV', type: 'yesno' },
      { key: 'tested_last_12_months', label: 'Tested in Last 12 Months', type: 'yesno' },
      { key: 'hiv_status', label: 'HIV Status', type: 'select', options: ['Positive', 'Negative', 'Unknown', 'Declined'] },
      { key: 'test_result_disclosed', label: 'Test Result Disclosed', type: 'yesno' },
      { key: 'referred_hiv_testing', label: 'Referred for HIV Testing', type: 'yesno' },
      { key: 'art_status', label: 'ART Status', type: 'select', options: ['On ART', 'Not on ART', 'Defaulted', 'Never Started'] },
      { key: 'art_start_date', label: 'ART Start Date', type: 'date' },
      { key: 'last_art_refill_date', label: 'Last ART Refill Date', type: 'date' },
      { key: 'viral_load_status', label: 'Viral Load Status', type: 'select', options: ['Suppressed', 'Unsuppressed', 'Unknown', 'Pending'] },
      { key: 'viral_load_result', label: 'Viral Load Result', type: 'text' },
      { key: 'adherence_issues', label: 'Adherence Issues', type: 'yesno' },
    ],
  },
  {
    key: 'pep', label: 'PEP',
    keywords: ['pep', 'post-exposure', 'exposure'],
    fields: [
      { key: 'exposure_type', label: 'Exposure Type', type: 'select', options: ['Sexual', 'Needlestick', 'Other'] },
      { key: 'exposure_date', label: 'Exposure Date', type: 'date' },
      { key: 'hours_since_exposure', label: 'Hours Since Exposure', type: 'number' },
      { key: 'pep_eligible', label: 'PEP Eligible', type: 'yesno' },
      { key: 'pep_facility', label: 'PEP Facility', type: 'text' },
    ],
  },
  {
    key: 'prep', label: 'PrEP',
    keywords: ['prep', 'pre-exposure'],
    fields: [
      { key: 'prep_naive', label: 'PrEP Naive', type: 'yesno' },
      { key: 'prep_adherence_issues', label: 'PrEP Adherence Issues', type: 'yesno' },
    ],
  },
  {
    key: 'srhr', label: 'Sexual & Reproductive Health',
    keywords: ['sexual', 'reproductive', 'srhr', 'family planning', 'contraception', 'sti', 'cervical', 'pregnancy'],
    fields: [
      { key: 'last_cervical_screen_date', label: 'Last Cervical Screen Date', type: 'date' },
      { key: 'partner_notified', label: 'Partner Notified', type: 'yesno' },
    ],
  },
  {
    key: 'general_health', label: 'General Health',
    keywords: ['health', 'medical', 'cholera', 'covid', 'vaccination', 'outbreak', 'medication'],
    fields: [
      { key: 'general_health_topic', label: 'General Health Topic', type: 'text' },
    ],
  },
  {
    key: 'mental_health', label: 'Mental Health & Psychosocial Support',
    keywords: ['mental', 'psychosocial', 'counselling', 'depression', 'anxiety', 'trauma', 'suicide', 'distress', 'mhpss'],
    fields: [
      { key: 'assist', label: 'ASSIST', type: 'text' },
      { key: 'distress_level', label: 'Distress Level', type: 'select', options: ['None', 'Mild', 'Moderate', 'Severe'] },
      { key: 'depressive_symptoms', label: 'Depressive Symptoms', type: 'yesno' },
      { key: 'anxiety_symptoms', label: 'Anxiety Symptoms', type: 'yesno' },
      { key: 'trauma_type', label: 'Trauma Type', type: 'text' },
      { key: 'aces_disclosed', label: 'ACEs Disclosed', type: 'yesno' },
      { key: 'ptsd_screening_needed', label: 'PTSD Screening Needed', type: 'yesno' },
      { key: 'suicidal_ideation', label: 'Suicidal Ideation', type: 'yesno' },
      { key: 'ideation_type', label: 'Ideation Type', type: 'select', options: ['Passive', 'Active', 'With Plan', 'None'] },
      { key: 'suicide_plan_disclosed', label: 'Suicide Plan Disclosed', type: 'yesno' },
      { key: 'means_access', label: 'Means Access', type: 'yesno' },
      { key: 'immediate_escalation_required', label: 'Immediate Escalation Required', type: 'yesno' },
    ],
  },
  {
    key: 'substance_use', label: 'Substance Use',
    keywords: ['substance', 'drug', 'alcohol', 'addiction', 'relapse', 'sober'],
    fields: [
      { key: 'substances_used', label: 'Substances Used', type: 'text' },
      { key: 'frequency', label: 'Frequency', type: 'select', options: ['Daily', 'Weekly', 'Monthly', 'Occasionally'] },
      { key: 'risk_level', label: 'Risk Level', type: 'select', options: ['Low', 'Medium', 'High'] },
      { key: 'days_sober', label: 'Days Sober', type: 'number' },
      { key: 'trigger_identified', label: 'Trigger Identified', type: 'yesno' },
      { key: 'coping_plan_developed', label: 'Coping Plan Developed', type: 'yesno' },
      { key: 'motivational_interviewing', label: 'Motivational Interviewing Session', type: 'yesno' },
      { key: 'relapse_warning_signs', label: 'Relapse Warning Signs', type: 'text' },
      { key: 'emergency_relapse_plan', label: 'Emergency Relapse Plan', type: 'yesno' },
    ],
  },
  {
    key: 'family_relationships', label: 'Family & Relationships',
    keywords: ['family', 'relationship', 'domestic', 'mediation', 'partner'],
    fields: [
      { key: 'relationship_type', label: 'Relationship Type', type: 'select', options: ['Partner', 'Spouse', 'Parent', 'Sibling', 'Friend', 'Other'] },
      { key: 'family_dynamics', label: 'Family Dynamics', type: 'text' },
      { key: 'relationship_issue_type', label: 'Relationship Issue Type', type: 'text' },
      { key: 'mediation_offered', label: 'Mediation Offered', type: 'yesno' },
    ],
  },
  {
    key: 'gbv', label: 'GBV',
    keywords: ['gbv', 'gender-based violence', 'abuse', 'assault', 'rape', 'violence', 'sexual assault'],
    fields: [
      { key: 'perpetrator_known', label: 'Perpetrator Known', type: 'yesno' },
      { key: 'relationship_to_perpetrator', label: 'Relationship to Perpetrator', type: 'text' },
      { key: 'gbv_type', label: 'GBV Type', type: 'select', options: ['Physical', 'Sexual', 'Emotional', 'Economic', 'Other'] },
      { key: 'gbv_sub_type', label: 'Please specify GBV Type', type: 'text', showWhen: { key: 'gbv_type', value: 'Other' }, required: true },
      { key: 'incident_date', label: 'Incident Date', type: 'date' },
      { key: 'incident_location', label: 'Incident Location', type: 'text' },
      { key: 'prior_incidents', label: 'Prior Incidents', type: 'yesno' },
      { key: 'physical_injury', label: 'Physical Injury', type: 'yesno' },
      { key: 'medical_care_sought', label: 'Medical Care Sought', type: 'yesno' },
      { key: 'safety_assessment', label: 'Safety Assessment', type: 'select', options: ['Safe', 'At Risk', 'High Risk', 'In Danger'] },
      { key: 'safety_plan', label: 'Safety Plan', type: 'yesno' },
      { key: 'police_report', label: 'Police Report', type: 'yesno' },
      { key: 'evidence_preserved', label: 'Evidence Preserved', type: 'yesno' },
      { key: 'emergency_rescue_needed', label: 'Emergency Rescue Needed', type: 'yesno' },
    ],
  },
  {
    key: 'child_protection', label: 'Child Protection',
    keywords: ['child', 'minor', 'protection', 'dssd', 'childline'],
    fields: [
      { key: 'child_age', label: 'Child Age', type: 'number' },
      { key: 'child_gender', label: 'Child Gender', type: 'select', options: ['Male', 'Female', 'Other'] },
      { key: 'visible_injury', label: 'Visible Injury', type: 'yesno' },
      { key: 'dssd_report', label: 'DSSD Report', type: 'yesno' },
      { key: 'childline_contacted', label: 'Childline Contacted', type: 'select', options: ['Yes', 'No', 'Other'] },
      { key: 'childline_contacted_other', label: 'Specification', type: 'text', showWhen: { key: 'childline_contacted', value: 'Other' }, required: true },
    ],
  },
  {
    key: 'legal', label: 'Legal & Protection Services',
    keywords: ['legal', 'law', 'court', 'police', 'shelter', 'protection order'],
    fields: [
      { key: 'legal_issue_type', label: 'Legal Issue Type', type: 'text' },
      { key: 'legal_information_provided', label: 'Legal Information Provided', type: 'yesno' },
      { key: 'police_report_number', label: 'Police Report Number', type: 'text' },
      { key: 'court_date', label: 'Court Date', type: 'date' },
      { key: 'legal_representative_secured', label: 'Legal Representative Secured', type: 'yesno' },
      { key: 'emergency_shelter_needed', label: 'Emergency Shelter Needed', type: 'yesno' },
      { key: 'safe_currently', label: 'Safe Currently', type: 'yesno' },
    ],
  },
  {
    key: 'education', label: 'Education',
    keywords: ['education', 'school', 'dropout', 'beam', 'study', 'college', 'learning'],
    fields: [
      { key: 'last_grade_completed', label: 'Last Grade Completed', type: 'text' },
      { key: 'reason_for_dropout', label: 'Reason for Dropout', type: 'text' },
      { key: 'beam_eligibility', label: 'BEAM Eligibility', type: 'yesno' },
      { key: 'institution_type', label: 'Institution Type', type: 'select', options: ['Primary', 'Secondary', 'Tertiary', 'TVET', 'Other'] },
      { key: 'education_query_type', label: 'Education Query Type', type: 'text' },
    ],
  },
  {
    key: 'livelihood', label: 'Education & Livelihoods',
    keywords: ['livelihood', 'career', 'employment', 'job', 'entrepreneurship', 'business', 'income', 'savings', 'vsla', 'grant'],
    fields: [
      { key: 'interest_areas', label: 'Interest Areas', type: 'text' },
      { key: 'career_goal', label: 'Career Goal', type: 'text' },
      { key: 'career_plan_developed', label: 'Career Plan Developed', type: 'yesno' },
      { key: 'cv_developed', label: 'CV Developed', type: 'yesno' },
      { key: 'employment_exchange_referral', label: 'Employment Exchange Referral', type: 'yesno' },
      { key: 'business_idea', label: 'Business Idea', type: 'text' },
      { key: 'income_diversified', label: 'Income Diversified', type: 'yesno' },
      { key: 'grant_referral', label: 'Grant Referral', type: 'yesno' },
      { key: 'savings_goal', label: 'Savings Goal', type: 'text' },
      { key: 'vsla_joined', label: 'VSLA Joined', type: 'yesno' },
    ],
  },
  {
    key: 'youth_empowerment', label: 'Youth Empowerment & Coaching',
    keywords: ['youth', 'empowerment', 'coaching', 'tvet', 'goal', 'leadership', 'volunteer'],
    fields: [
      { key: 'tvet_college_identified', label: 'TVET College Identified', type: 'text' },
      { key: 'personal_agency_plan', label: 'Personal Agency Plan', type: 'yesno' },
      { key: '90_day_goal', label: '90-Day Goal', type: 'text' },
      { key: 'accountability_partner', label: 'Accountability Partner', type: 'yesno' },
      { key: 'smart_goal', label: 'SMART Goal', type: 'text' },
      { key: 'action_steps_agreed', label: 'Action Steps Agreed', type: 'yesno' },
      { key: 'mood_rating_before', label: 'Mood Rating Before (1-10)', type: 'number' },
      { key: 'mood_rating_after', label: 'Mood Rating After (1-10)', type: 'number' },
      { key: 'leadership_volunteer_interest', label: 'Leadership / Volunteer Interest', type: 'yesno' },
    ],
  },
  {
    key: 'case_resolution', label: 'Case Resolution & Referral',
    keywords: ['referral', 'resolution', 'follow-up', 'closure', 'case'],
    fields: [
      { key: 'information_provided', label: 'Information Provided', type: 'text' },
      { key: 'action_taken', label: 'Action Taken', type: 'text' },
      { key: 'warm_referral', label: 'Warm Referral', type: 'yesno' },
      { key: 'referral_completed', label: 'Referral Completed', type: 'yesno' },
      { key: 'services_accessed', label: 'Services Accessed', type: 'text' },
      { key: 'follow_up_required', label: 'Follow-Up Required', type: 'yesno' },
      { key: 'follow_up_date', label: 'Follow-Up Date', type: 'date' },
      { key: 'follow_up_outcome', label: 'Follow-Up Outcome', type: 'text' },
      { key: 'case_status', label: 'Case Status', type: 'select', options: ['Open', 'In Progress', 'Resolved', 'Closed', 'Lost to Follow-Up'] },
      { key: 'case_closure_date', label: 'Case Closure Date', type: 'date' },
    ],
  },
  {
    key: 'cyberbullying', label: 'Cyberbullying',
    keywords: ['cyberbullying', 'cyber', 'online harassment', 'online abuse', 'social media', 'digital abuse', 'trolling', 'doxxing', 'sexting', 'revenge porn'],
    fields: [
      { key: 'platform', label: 'Platform', type: 'select', options: ['Facebook', 'WhatsApp', 'Instagram', 'TikTok', 'Twitter/X', 'Snapchat', 'YouTube', 'Online Gaming', 'Other'] },
      { key: 'cyberbullying_type', label: 'Cyberbullying Type', type: 'select', options: ['Harassment', 'Threats', 'Impersonation', 'Doxxing', 'Non-consensual Image Sharing', 'Hate Speech', 'Exclusion', 'Other'] },
      { key: 'cyberbullying_type_other', label: 'Please specify Cyberbullying Type', type: 'text', showWhen: { key: 'cyberbullying_type', value: 'Other' }, required: true },
      { key: 'perpetrator_known', label: 'Perpetrator Known', type: 'yesno' },
      { key: 'relationship_to_perpetrator', label: 'Relationship to Perpetrator', type: 'text' },
      { key: 'duration', label: 'Duration of Bullying', type: 'select', options: ['Once-off', 'Less than 1 week', '1–4 weeks', '1–6 months', 'More than 6 months'] },
      { key: 'evidence_available', label: 'Evidence Available (screenshots etc)', type: 'yesno' },
      { key: 'reported_to_platform', label: 'Reported to Platform', type: 'yesno' },
      { key: 'reported_to_police', label: 'Reported to Police', type: 'yesno' },
      { key: 'emotional_impact', label: 'Emotional Impact', type: 'select', options: ['None', 'Mild', 'Moderate', 'Severe'] },
      { key: 'safety_concern', label: 'Safety Concern', type: 'yesno' },
      { key: 'account_blocked_perpetrator', label: 'Perpetrator Blocked/Reported', type: 'yesno' },
    ],
  },
];

// Use stored categories from lookup item, fall back to keyword detection
const detectedCategories = computed(() => {
    if (!props.service) return [];

    // Check if we have stored categories for this service
    const stored = props.serviceCategories?.[props.service];
    if (stored && stored.length > 0) {
        return CATEGORIES.filter(cat => stored.includes(cat.key));
    }

    // Fallback: keyword matching
    const svc = props.service.toLowerCase();
    return CATEGORIES.filter(cat =>
        cat.keywords.some(kw => svc.includes(kw))
    );
});

// Manually selected additional categories — initialized from saved _categories
const manualCats = ref((props.modelValue?._categories ?? []).filter(
    k => !detectedCategories.value.map(c => c.key).includes(k)
));

const allSelected = computed(() => {
    const auto = detectedCategories.value.map(c => c.key);
    const manual = manualCats.value;
    return [...new Set([...auto, ...manual])];
});

const visibleCategories = computed(() =>
    CATEGORIES.filter(c => allSelected.value.includes(c.key))
);

const openSections = ref({});
function toggleSection(key) {
    openSections.value[key] = !openSections.value[key];
}
function isOpen(key) {
    return openSections.value[key] !== false; // default open
}

const yesNoOptions = ['Yes', 'No', 'Unknown'];
</script>

<template>
    <div class="space-y-4">
        <!-- Category selector -->
        <div>
            <label class="label">Classification Category</label>
            <p class="text-xs text-gray-400 mb-2">
                <span v-if="detectedCategories.length">
                    Auto-detected from service:
                    <strong v-for="c in detectedCategories" :key="c.key" class="text-brand-600 mr-1">{{ c.label }}</strong>
                </span>
                <span v-else>Select the category that applies to this case.</span>
            </p>
            <div class="grid grid-cols-2 gap-1.5 rounded-lg border border-gray-200 p-3 bg-gray-50 max-h-48 overflow-y-auto">
                <label v-for="cat in CATEGORIES" :key="cat.key"
                    class="flex items-center gap-2 cursor-pointer text-xs text-gray-700 py-0.5">
                    <input type="checkbox"
                        :checked="allSelected.includes(cat.key)"
                        :disabled="detectedCategories.map(c=>c.key).includes(cat.key)"
                        @change="e => {
                            if (e.target.checked) manualCats.push(cat.key);
                            else manualCats.splice(manualCats.indexOf(cat.key), 1);
                            persistCategories();
                        }"
                        class="rounded border-gray-300 text-brand-600" />
                    <span :class="detectedCategories.map(c=>c.key).includes(cat.key) ? 'text-brand-700 font-semibold' : ''">
                        {{ cat.label }}
                    </span>
                </label>
            </div>
        </div>

        <!-- Visible category sections -->
        <div v-for="cat in visibleCategories" :key="cat.key"
            class="border border-gray-200 rounded-xl overflow-hidden">
            <!-- Section header -->
            <button type="button"
                @click="toggleSection(cat.key)"
                class="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 hover:bg-gray-100 transition-colors text-left">
                <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">{{ cat.label }}</span>
                <ChevronDownIcon v-if="!isOpen(cat.key)" class="h-4 w-4 text-gray-400" />
                <ChevronUpIcon v-else class="h-4 w-4 text-gray-400" />
            </button>

            <!-- Fields -->
            <div v-if="isOpen(cat.key)" class="p-4 grid grid-cols-2 gap-3">
                <div v-for="field in cat.fields" :key="field.key"
                    v-show="!field.showWhen || get(field.showWhen.key) === field.showWhen.value"
                    :class="field.type === 'text' && !field.options ? 'col-span-2' : ''">
                    <label class="label text-xs">
                        {{ field.label }}
                        <span v-if="field.required && (!field.showWhen || get(field.showWhen.key) === field.showWhen.value)" class="text-red-500 ml-0.5">*</span>
                    </label>

                    <!-- Psychosocial type — writes to both JSON and dedicated DB column -->
                    <select v-if="field.type === 'psychosocial'"
                        :value="psychosocialType || get(field.key)"
                        @change="e => {
                            set(field.key, e.target.value);
                            emit('update:psychosocialType', e.target.value);
                        }"
                        class="input text-sm col-span-2">
                        <option value="">— select —</option>
                        <option v-for="o in field.options" :key="o" :value="o">{{ o }}</option>
                    </select>

                    <!-- Yes/No -->
                    <select v-else-if="field.type === 'yesno'"
                        :value="get(field.key)"
                        @change="set(field.key, $event.target.value)"
                        class="input text-sm">
                        <option value="">— select —</option>
                        <option v-for="o in yesNoOptions" :key="o" :value="o">{{ o }}</option>
                    </select>

                    <!-- Select with options -->
                    <select v-else-if="field.type === 'select'"
                        :value="get(field.key)"
                        @change="set(field.key, $event.target.value)"
                        class="input text-sm">
                        <option value="">— select —</option>
                        <option v-for="o in field.options" :key="o" :value="o">{{ o }}</option>
                    </select>

                    <!-- Date -->
                    <input v-else-if="field.type === 'date'"
                        type="date"
                        :value="get(field.key)"
                        @input="set(field.key, $event.target.value)"
                        class="input text-sm" />

                    <!-- Number -->
                    <input v-else-if="field.type === 'number'"
                        type="number"
                        :value="get(field.key)"
                        @input="set(field.key, $event.target.value)"
                        class="input text-sm" />

                    <!-- Text -->
                    <input v-else
                        type="text"
                        :value="get(field.key)"
                        @input="set(field.key, $event.target.value)"
                        :required="field.required && (!field.showWhen || get(field.showWhen.key) === field.showWhen.value)"
                        :placeholder="field.required ? 'Required' : ''"
                        class="input text-sm" />
                </div>
            </div>
        </div>

        <p v-if="!visibleCategories.length && !service" class="text-xs text-gray-400 text-center py-4 border border-dashed border-gray-200 rounded-xl">
            Select a service above to see relevant classification fields
        </p>
    </div>
</template>
