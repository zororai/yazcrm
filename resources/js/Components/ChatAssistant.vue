<script setup>
import { ref, nextTick, watch } from 'vue';
import axios from 'axios';
import { ChatBubbleLeftRightIcon, XMarkIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';

const open        = ref(false);
const input       = ref('');
const sending     = ref(false);
const loading     = ref(false);
const error       = ref('');
const messages    = ref([]); // [{ role: 'user' | 'assistant', content }]
const scrollBox   = ref(null);
let   historyLoaded = false;

async function scrollToBottom() {
    await nextTick();
    if (scrollBox.value) scrollBox.value.scrollTop = scrollBox.value.scrollHeight;
}

async function loadHistory() {
    if (historyLoaded) return;
    historyLoaded = true;
    loading.value = true;
    try {
        const { data } = await axios.get('/api/assistant/chat');
        messages.value = (data.messages ?? []).map(m => ({ role: m.role, content: m.content }));
    } catch { /* ignore — start with an empty conversation */ }
    loading.value = false;
    scrollToBottom();
}

watch(open, (v) => { if (v) { loadHistory(); scrollToBottom(); } });

async function send() {
    const text = input.value.trim();
    if (!text || sending.value) return;

    messages.value.push({ role: 'user', content: text });
    input.value = '';
    error.value = '';
    sending.value = true;
    scrollToBottom();

    try {
        const { data } = await axios.post('/api/assistant/chat', { message: text });
        messages.value.push({ role: 'assistant', content: data.reply });
    } catch (e) {
        error.value = e.response?.data?.error ?? 'Something went wrong. Try again.';
    } finally {
        sending.value = false;
        scrollToBottom();
    }
}
</script>

<template>
    <div class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-2">

        <!-- Chat panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 scale-95"
        >
            <div v-if="open" class="bg-white rounded-2xl shadow-2xl ring-1 ring-black/10 w-80 sm:w-96 flex flex-col overflow-hidden mb-2" style="height: 480px;">

                <!-- Header -->
                <div class="bg-brand-600 px-4 py-3 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <ChatBubbleLeftRightIcon class="h-4 w-4 text-white" />
                        <span class="text-white text-sm font-semibold">Assistant</span>
                    </div>
                    <button @click="open = false" class="text-white/70 hover:text-white">
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>

                <!-- Messages -->
                <div ref="scrollBox" class="flex-1 overflow-y-auto px-4 py-3 space-y-3 bg-gray-50">
                    <div v-if="loading" class="text-center text-xs text-gray-400 mt-8">Loading conversation…</div>
                    <div v-else-if="!messages.length" class="text-center text-xs text-gray-400 mt-8">
                        Ask me anything — I don't have access to CRM data,<br />just general questions.
                    </div>
                    <div v-for="(m, i) in messages" :key="i" :class="['flex', m.role === 'user' ? 'justify-end' : 'justify-start']">
                        <div :class="[
                            'max-w-[85%] rounded-2xl px-3.5 py-2 text-sm whitespace-pre-wrap break-words',
                            m.role === 'user' ? 'bg-brand-600 text-white rounded-br-sm' : 'bg-white text-gray-800 ring-1 ring-gray-100 rounded-bl-sm',
                        ]">{{ m.content }}</div>
                    </div>
                    <div v-if="sending" class="flex justify-start">
                        <div class="bg-white ring-1 ring-gray-100 rounded-2xl rounded-bl-sm px-3.5 py-2.5 flex gap-1 items-center">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-300 animate-bounce" style="animation-delay: 0ms" />
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-300 animate-bounce" style="animation-delay: 150ms" />
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-300 animate-bounce" style="animation-delay: 300ms" />
                        </div>
                    </div>
                    <p v-if="error" class="text-xs text-red-500 text-center">{{ error }}</p>
                </div>

                <!-- Input -->
                <form @submit.prevent="send" class="flex-shrink-0 border-t border-gray-100 p-3 flex items-end gap-2">
                    <textarea
                        v-model="input"
                        rows="1"
                        class="flex-1 resize-none bg-gray-50 rounded-xl px-3 py-2 text-sm outline-none ring-1 ring-gray-200 focus:ring-brand-400 max-h-24"
                        placeholder="Type a question…"
                        @keydown.enter.exact.prevent="send"
                    />
                    <button type="submit" :disabled="!input.trim() || sending"
                        class="h-9 w-9 flex-shrink-0 rounded-full bg-brand-600 hover:bg-brand-700 disabled:opacity-40 disabled:cursor-not-allowed text-white flex items-center justify-center transition-colors">
                        <PaperAirplaneIcon class="h-4 w-4" />
                    </button>
                </form>
            </div>
        </Transition>

        <!-- FAB toggle -->
        <button
            @click="open = !open"
            class="h-14 w-14 rounded-full shadow-lg flex items-center justify-center bg-brand-600 hover:bg-brand-700 transition-colors"
        >
            <ChatBubbleLeftRightIcon v-if="!open" class="h-6 w-6 text-white" />
            <XMarkIcon v-else class="h-6 w-6 text-white" />
        </button>
    </div>
</template>
