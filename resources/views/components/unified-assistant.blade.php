@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $aiEnabled = config('ai.enabled') && $tenant && app(\App\Services\AI\KeyResolver::class)->isEnabled($tenant);

    // Build URLs for help links
    $documentationUrl = $tenant ? \App\Filament\Pages\Documentation::getUrl(tenant: $tenant) : '#';
    $serversUrl = $tenant ? \App\Filament\Resources\ServerResource::getUrl(tenant: $tenant) : '#';
    $webAppsUrl = $tenant ? \App\Filament\Resources\WebAppResource::getUrl(tenant: $tenant) : '#';
    $databasesUrl = $tenant ? \App\Filament\Resources\DatabaseResource::getUrl(tenant: $tenant) : '#';
    $teamSettingsUrl = $tenant ? route('filament.app.tenant.profile', ['tenant' => $tenant->id]) : '#';
@endphp

<div id="unified-assistant-app"
    data-ai-enabled="{{ $aiEnabled ? 'true' : 'false' }}"
    data-documentation-url="{{ $documentationUrl }}"
    data-servers-url="{{ $serversUrl }}"
    data-webapps-url="{{ $webAppsUrl }}"
    data-databases-url="{{ $databasesUrl }}"
    data-team-settings-url="{{ $teamSettingsUrl }}"
    data-csrf-token="{{ csrf_token() }}"
    data-default-provider="{{ config('ai.default_provider', 'anthropic') }}"
></div>

{{-- Global functions defined immediately so they're available before Vue loads --}}
<script>
    (function() {
        console.log('[SiteKit AI] Registering global openAiChat function');
        window._assistantQueue = window._assistantQueue || [];
        window.openAiChat = function(message, context) {
            console.log('[SiteKit AI] openAiChat called with:', message?.substring?.(0, 50) || message);
            window._assistantQueue.push({ message, context });
            window.dispatchEvent(new CustomEvent('open-ai-chat', { detail: { message, context } }));
        };
    })();
</script>

@verbatim
<script type="module">
    import { createApp, ref, computed, watch, nextTick } from 'https://cdn.jsdelivr.net/npm/vue@3/dist/vue.esm-browser.js'

    const UnifiedAssistant = {
        template: `
            <div>
                <!-- Floating Button -->
                <button
                    v-if="!isOpen"
                    @click="isOpen = true"
                    class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-12 h-12 bg-primary-600 hover:bg-primary-700 text-white rounded-full shadow-lg transition-all duration-200 hover:scale-105 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    :title="aiEnabled ? 'Help & AI Assistant (Cmd+/)' : 'Help & Support'"
                >
                    <!-- Question mark icon -->
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>

                <!-- Panel -->
                <transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 translate-y-4 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-4 scale-95"
                >
                    <div
                        v-if="isOpen"
                        class="fixed bottom-6 right-6 z-50 w-[400px] bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden"
                    >
                        <!-- Header -->
                        <div class="px-4 py-3 bg-primary-600 flex items-center justify-between">
                            <div class="text-white">
                                <h3 class="text-base font-semibold">{{ aiEnabled ? 'Help & AI Assistant' : 'Help & Support' }}</h3>
                                <p class="text-xs text-primary-100">How can we help you?</p>
                            </div>
                            <button @click="isOpen = false" class="text-white/70 hover:text-white p-1 rounded hover:bg-white/10 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Tabs -->
                        <div class="flex border-b border-gray-200 dark:border-gray-700">
                            <button
                                @click="activeTab = 'help'"
                                :class="activeTab === 'help' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                                class="flex-1 px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                            >
                                Quick Help
                            </button>
                            <button
                                @click="activeTab = 'faq'"
                                :class="activeTab === 'faq' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                                class="flex-1 px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                            >
                                FAQ
                            </button>
                            <button
                                v-if="aiEnabled"
                                @click="activeTab = 'ai'"
                                :class="activeTab === 'ai' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                                class="flex-1 px-4 py-2 text-sm font-medium border-b-2 transition-colors flex items-center justify-center gap-1"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                                AI Chat
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="max-h-96 overflow-y-auto">
                            <!-- Help Tab -->
                            <div v-show="activeTab === 'help'" class="p-4 space-y-3">
                                <a :href="urls.documentation" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600">Getting Started</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Learn the basics of SiteKit</p>
                                    </div>
                                </a>

                                <a :href="urls.servers" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600">Connect a Server</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Add your first server</p>
                                    </div>
                                </a>

                                <a :href="urls.webapps" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600">Deploy an App</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Create and deploy web apps</p>
                                    </div>
                                </a>

                                <a :href="urls.databases" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                                    <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 dark:bg-yellow-900 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-primary-600">Create a Database</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Manage MySQL, MariaDB, PostgreSQL</p>
                                    </div>
                                </a>
                            </div>

                            <!-- FAQ Tab -->
                            <div v-show="activeTab === 'faq'" class="p-4 space-y-2">
                                <div v-for="(faq, index) in faqs" :key="index" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <button
                                        @click="toggleFaq(index)"
                                        class="w-full flex items-center justify-between p-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ faq.question }}</span>
                                        <svg
                                            class="w-4 h-4 text-gray-500 transition-transform"
                                            :class="{ 'rotate-180': openFaqs.includes(index) }"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <div v-show="openFaqs.includes(index)" class="px-3 pb-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ faq.answer }}
                                    </div>
                                </div>
                            </div>

                            <!-- AI Chat Tab -->
                            <div v-show="activeTab === 'ai' && aiEnabled" class="flex flex-col" style="height: 384px;">
                                <!-- Clear Chat Button -->
                                <div v-if="messages.length > 0" class="flex justify-end px-4 pt-2">
                                    <button
                                        @click="clearChat"
                                        class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 flex items-center gap-1 transition-colors"
                                        title="Clear chat history"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Clear
                                    </button>
                                </div>
                                <!-- Messages -->
                                <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-3">
                                    <!-- Welcome message -->
                                    <div v-if="messages.length === 0" class="text-center py-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-primary-100 to-indigo-100 dark:from-primary-900/30 dark:to-indigo-900/30 rounded-xl flex items-center justify-center mx-auto mb-3">
                                            <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                            </svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">Ask me anything!</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">I can help with server issues, configs, and more</p>

                                        <!-- Quick suggestions -->
                                        <div class="mt-4 space-y-2">
                                            <button
                                                v-for="suggestion in suggestions"
                                                :key="suggestion"
                                                @click="sendMessage(suggestion)"
                                                class="w-full p-2 text-left text-xs text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                            >
                                                {{ suggestion }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Message list -->
                                    <template v-for="(msg, index) in messages" :key="index">
                                        <!-- User message -->
                                        <div v-if="msg.role === 'user'" class="flex justify-end">
                                            <div class="max-w-[80%] px-3 py-2 bg-primary-500 text-white rounded-xl rounded-br-sm text-sm">
                                                {{ msg.content }}
                                            </div>
                                        </div>
                                        <!-- AI message -->
                                        <div v-else class="flex gap-2">
                                            <div class="flex-shrink-0 w-6 h-6 bg-gradient-to-br from-primary-100 to-indigo-100 dark:from-primary-900/50 dark:to-indigo-900/50 rounded-full flex items-center justify-center">
                                                <svg class="w-3 h-3 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                                </svg>
                                            </div>
                                            <div class="max-w-[85%]">
                                                <div v-if="msg.error" class="px-3 py-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl rounded-tl-sm text-sm text-red-700 dark:text-red-300">
                                                    {{ msg.error }}
                                                    <a v-if="msg.settingsUrl" :href="msg.settingsUrl" class="block mt-1 text-red-600 dark:text-red-400 underline text-xs">Configure AI Settings</a>
                                                </div>
                                                <div v-else class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-xl rounded-tl-sm text-sm text-gray-900 dark:text-white whitespace-pre-wrap">{{ msg.content }}</div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Typing indicator -->
                                    <div v-if="isTyping" class="flex gap-2">
                                        <div class="flex-shrink-0 w-6 h-6 bg-gradient-to-br from-primary-100 to-indigo-100 dark:from-primary-900/50 dark:to-indigo-900/50 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                            </svg>
                                        </div>
                                        <div class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-xl rounded-tl-sm">
                                            <div class="flex gap-1">
                                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                                <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Input -->
                                <div class="border-t border-gray-200 dark:border-gray-700 p-3">
                                    <form @submit.prevent="sendMessage()" class="flex gap-2">
                                        <input
                                            ref="inputRef"
                                            v-model="input"
                                            type="text"
                                            placeholder="Ask anything..."
                                            class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-700 border-0 rounded-lg text-sm text-gray-900 dark:text-white placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:outline-none"
                                            :disabled="isTyping"
                                        />
                                        <button
                                            type="submit"
                                            :disabled="!input.trim() || isTyping"
                                            class="px-3 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 bg-gray-50 dark:bg-gray-900">
                            <div class="flex items-center justify-between text-sm">
                                <a href="mailto:support@sitekit.dev" class="text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    Support
                                </a>
                                <span v-if="aiEnabled" class="text-xs text-gray-400">Cmd+/ to toggle AI</span>
                                <a href="https://github.com/avansaber/sitekit-feedback" target="_blank" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                    </svg>
                                    Feedback
                                </a>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        `,
        setup() {
            const container = document.getElementById('unified-assistant-app')
            const aiEnabled = container?.dataset?.aiEnabled === 'true'
            const csrfToken = container?.dataset?.csrfToken || ''

            const urls = {
                documentation: container?.dataset?.documentationUrl || '#',
                servers: container?.dataset?.serversUrl || '#',
                webapps: container?.dataset?.webappsUrl || '#',
                databases: container?.dataset?.databasesUrl || '#',
                teamSettings: container?.dataset?.teamSettingsUrl || '#',
            }

            const isOpen = ref(false)
            const activeTab = ref('help')
            const openFaqs = ref([])

            // AI Chat state
            const input = ref('')
            const isTyping = ref(false)
            const messagesContainer = ref(null)
            const inputRef = ref(null)

            // Load messages from localStorage
            const storageKey = 'sitekit_ai_chat_history'
            const loadMessages = () => {
                try {
                    const saved = localStorage.getItem(storageKey)
                    return saved ? JSON.parse(saved) : []
                } catch (e) {
                    return []
                }
            }
            const messages = ref(loadMessages())

            // Save messages to localStorage whenever they change
            watch(messages, (newMessages) => {
                try {
                    // Keep only last 50 messages to avoid storage limits
                    const toSave = newMessages.slice(-50)
                    localStorage.setItem(storageKey, JSON.stringify(toSave))
                } catch (e) {
                    console.warn('Failed to save chat history:', e)
                }
            }, { deep: true })

            const faqs = [
                { question: 'How do I connect a server?', answer: 'Click "Add Server", enter your server details, then run the provisioning command on your VPS via SSH.' },
                { question: 'What PHP versions are supported?', answer: 'PHP 8.1, 8.2, 8.3, 8.4, and 8.5 are supported. PHP 8.3 is the recommended default.' },
                { question: 'How do I set up SSL?', answer: 'Go to your web app, click "Issue SSL Certificate". We use Let\'s Encrypt for free SSL certificates.' },
                { question: 'How do deployments work?', answer: 'Connect your Git repository, configure your deploy script, then click "Deploy" or enable auto-deploy for automatic deployments on push.' },
                { question: 'Can I use MySQL and MariaDB together?', answer: 'No, MySQL and MariaDB cannot run simultaneously as they conflict. Starting one will stop the other.' },
            ]

            const suggestions = [
                '💾 What is using disk space?',
                '⚡ How do I optimize MySQL?',
                '🔒 Run a security audit',
            ]

            const toggleFaq = (index) => {
                if (openFaqs.value.includes(index)) {
                    openFaqs.value = openFaqs.value.filter(i => i !== index)
                } else {
                    openFaqs.value.push(index)
                }
            }

            const scrollToBottom = () => {
                nextTick(() => {
                    if (messagesContainer.value) {
                        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
                    }
                })
            }

            const sendMessage = async (text) => {
                const content = text || input.value.trim()
                if (!content) return

                messages.value.push({ role: 'user', content })
                input.value = ''
                isTyping.value = true
                scrollToBottom()

                try {
                    const response = await fetch('/ai/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ message: content }),
                    })

                    const data = await response.json()

                    if (data.success) {
                        messages.value.push({
                            role: 'assistant',
                            content: data.message,
                        })
                    } else {
                        messages.value.push({
                            role: 'assistant',
                            error: data.error || 'Failed to get response',
                            settingsUrl: data.settings_url,
                        })
                    }
                } catch (error) {
                    console.error('AI Chat Error:', error)
                    messages.value.push({
                        role: 'assistant',
                        error: 'Network error. Please try again.',
                    })
                }

                isTyping.value = false
                scrollToBottom()
            }

            const clearChat = () => {
                messages.value = []
                localStorage.removeItem(storageKey)
            }

            // Keyboard shortcut (Cmd+/ or Ctrl+/)
            document.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === '/') {
                    e.preventDefault()
                    if (aiEnabled) {
                        isOpen.value = !isOpen.value
                        if (isOpen.value) {
                            activeTab.value = 'ai'
                            nextTick(() => inputRef.value?.focus())
                        }
                    }
                }
                if (e.key === 'Escape' && isOpen.value) {
                    isOpen.value = false
                }
            })

            // Helper to open AI chat with a message
            const openChat = (message) => {
                if (aiEnabled && message) {
                    isOpen.value = true
                    activeTab.value = 'ai'
                    nextTick(() => sendMessage(message))
                }
            }

            // Listen for window custom events (from global openAiChat function)
            window.addEventListener('open-ai-chat', (e) => {
                console.log('[SiteKit AI] Vue received open-ai-chat event:', e.detail)
                const message = e.detail?.message
                if (message) {
                    openChat(message)
                }
            })

            // Listen for Livewire events to open AI chat (with guard to prevent duplicate setup)
            let livewireListenerSetup = false
            const setupLivewireListener = () => {
                if (livewireListenerSetup || typeof Livewire === 'undefined') return
                livewireListenerSetup = true
                console.log('[SiteKit AI] Setting up Livewire listener (once)')
                Livewire.on('open-ai-chat', (data) => {
                    console.log('[SiteKit AI] Livewire event received:', data)
                    // Livewire 3 passes data as array, first element contains the params
                    const message = data?.message || data?.[0]?.message || (Array.isArray(data) && data[0])
                    if (message) {
                        openChat(typeof message === 'string' ? message : message.message)
                    }
                })
            }

            // Try to set up immediately if Livewire is already loaded
            if (typeof Livewire !== 'undefined') {
                setupLivewireListener()
            } else {
                // Otherwise wait for Livewire to initialize (use both events as fallback)
                document.addEventListener('livewire:init', setupLivewireListener)
                document.addEventListener('livewire:initialized', setupLivewireListener)
            }

            // Process any queued messages from before Vue was ready
            if (window._assistantQueue && window._assistantQueue.length > 0) {
                const queued = window._assistantQueue.shift()
                if (queued?.message) {
                    nextTick(() => openChat(queued.message))
                }
            }

            // Override global function now that Vue is ready
            window.openAiChat = (message, context = {}) => {
                if (aiEnabled) {
                    isOpen.value = true
                    activeTab.value = 'ai'
                    if (message) {
                        nextTick(() => sendMessage(message))
                    }
                }
            }

            // Focus input when switching to AI tab
            watch(activeTab, (tab) => {
                if (tab === 'ai') {
                    nextTick(() => inputRef.value?.focus())
                }
            })

            return {
                aiEnabled,
                urls,
                isOpen,
                activeTab,
                openFaqs,
                faqs,
                suggestions,
                toggleFaq,
                // AI Chat
                input,
                messages,
                isTyping,
                messagesContainer,
                inputRef,
                sendMessage,
                clearChat,
            }
        }
    }

    createApp(UnifiedAssistant).mount('#unified-assistant-app')
</script>
@endverbatim
