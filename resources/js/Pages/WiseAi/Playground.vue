<template>
    <AuthenticatedLayout title="Wise AI — Playground">
        <div class="space-y-5">
            <PageHeader
                title="Playground"
                description="আসল /decide API দিয়ে টেস্ট — বাইরের অ্যাপ যেমন কল করে, একইভাবে"
                icon="PhFlask"
                icon-bg-class="bg-fuchsia-50 dark:bg-fuchsia-500/15"
                icon-class="text-fuchsia-600 dark:text-fuchsia-400"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusBadge
                            v-if="apiKey && lastTrace"
                            :label="lastTrace.brain_version"
                            variant="neutral"
                            format="none"
                        />
                        <Button
                            label="Reset"
                            icon="pi pi-refresh"
                            size="small"
                            severity="secondary"
                            outlined
                            :disabled="!messages.length"
                            @click="resetConversation"
                        />
                    </div>
                </template>
            </PageHeader>

            <WiseAiSubNav />

            <WiseAiHowTo
                title="Playground কীভাবে ব্যবহার করবেন"
                subtitle="Key connect → মেসেজ পাঠান → Decision দেখুন"
                badge="টেস্ট"
                storage-key="playground"
                :steps="howToSteps"
                :tips="howToTips"
            />

            <!-- Connect gate -->
            <PageCard v-if="!apiKey">
                <div class="mx-auto max-w-xl py-4 text-center sm:py-8">
                    <span
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-fuchsia-50 text-fuchsia-500 dark:bg-fuchsia-500/15 dark:text-fuchsia-400"
                    >
                        <Icon name="PhKey" class="text-3xl" />
                    </span>
                    <h2 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white">
                        Connect with an API key
                    </h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Config থেকে তৈরি করা Wise AI API key এখানে পেস্ট করুন — বাইরের অ্যাপ যেমন করে auth করে, একইভাবে।
                        Key নেই?
                        <Link
                            :href="route('wiseAi.config')"
                            class="font-medium text-fuchsia-600 hover:underline dark:text-fuchsia-400"
                        >
                            Config → Generate Key
                        </Link>.
                    </p>
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <input
                            v-model="keyDraft"
                            type="password"
                            placeholder="wise_…"
                            class="h-11 min-w-0 flex-1 rounded-xl border border-gray-200 bg-white px-4 font-mono text-sm text-gray-800 outline-none transition focus:border-fuchsia-400 focus:ring-2 focus:ring-fuchsia-100 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-100 dark:focus:ring-fuchsia-500/20"
                            @keyup.enter="saveKey"
                        />
                        <Button
                            label="Connect"
                            icon="pi pi-link"
                            :disabled="!keyDraft.trim()"
                            @click="saveKey"
                        />
                    </div>
                </div>
            </PageCard>

            <!-- Main workspace -->
            <div v-else class="grid grid-cols-1 gap-5 xl:grid-cols-5">
                <!-- Chat column -->
                <div class="flex flex-col gap-4 xl:col-span-3">
                    <PageCard class="flex min-h-0 flex-1 flex-col" no-padding>
                        <template #header>
                            <div class="flex min-w-0 items-start gap-3">
                                <span
                                    class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-500/15 dark:text-fuchsia-400"
                                >
                                    <Icon name="PhChatCircleDots" class="text-lg" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">
                                        Test Conversation
                                    </h2>
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                        কাস্টমারের মতো মেসেজ পাঠান — brain কী সাজেস্ট করে দেখুন
                                    </p>
                                </div>
                            </div>
                        </template>
                        <template #actions>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                :title="apiKey"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                {{ keyPreview }}
                            </span>
                            <Button
                                icon="pi pi-sign-out"
                                size="small"
                                severity="secondary"
                                text
                                aria-label="Disconnect key"
                                title="Disconnect key"
                                @click="disconnectKey"
                            />
                        </template>

                        <!-- Compact context toolbar -->
                        <div
                            class="flex items-center gap-2 border-b border-gray-100 px-4 py-2.5 dark:border-gray-800 sm:px-5"
                        >
                            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 text-[11px] font-semibold text-gray-600 transition hover:border-fuchsia-200 hover:text-fuchsia-700 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-300 dark:hover:border-fuchsia-500/40 dark:hover:text-fuchsia-300"
                                    :class="showAdvancedContext ? 'border-fuchsia-300 text-fuchsia-700 dark:border-fuchsia-500/50 dark:text-fuchsia-300' : ''"
                                    @click="showAdvancedContext = !showAdvancedContext"
                                >
                                    <Icon name="PhSlidersHorizontal" :size="14" />
                                    Context
                                    <span
                                        v-if="activeContextCount"
                                        class="rounded-md bg-fuchsia-100 px-1.5 py-0.5 text-[10px] text-fuchsia-700 dark:bg-fuchsia-500/20 dark:text-fuchsia-300"
                                    >
                                        {{ activeContextCount }}
                                    </span>
                                </button>
                                <span
                                    class="inline-flex h-8 items-center gap-1 rounded-lg bg-gray-50 px-2.5 text-[11px] text-gray-500 dark:bg-slate-800/60 dark:text-gray-400"
                                >
                                    <Icon name="PhBroadcast" :size="14" />
                                    {{ decideChannel }}
                                </span>
                                <span
                                    v-if="ctxProductId.trim()"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg bg-amber-50 px-2.5 font-mono text-[11px] text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
                                >
                                    product {{ ctxProductId.trim() }}
                                </span>
                                <span
                                    v-if="ctxVoiceProfile"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg bg-cyan-50 px-2.5 text-[11px] font-semibold text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-300"
                                >
                                    voice
                                </span>
                            </div>
                            <span class="shrink-0 whitespace-nowrap text-[11px] leading-8 text-gray-400">
                                {{ messages.length }} msg · turn #{{ lastTrace?.turn_id || "—" }}
                            </span>
                        </div>

                        <div
                            v-if="showAdvancedContext"
                            class="grid grid-cols-1 gap-4 border-b border-gray-100 bg-gray-50/50 px-4 py-4 dark:border-gray-800 dark:bg-slate-800/30 sm:grid-cols-2 sm:px-5 lg:grid-cols-3"
                        >
                            <div>
                                <label class="mb-1.5 block text-[11px] font-medium text-gray-500">channel</label>
                                <Select
                                    v-model="decideChannel"
                                    :options="channelOptions"
                                    option-label="label"
                                    option-value="value"
                                    class="w-full"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-medium text-gray-500">product_id</label>
                                <input
                                    v-model="ctxProductId"
                                    type="text"
                                    class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 font-mono text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                                    placeholder="45 or svc-1"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-medium text-gray-500">offer_kind</label>
                                <Select
                                    v-model="ctxOfferKind"
                                    :options="offerKindOptions"
                                    option-label="label"
                                    option-value="value"
                                    show-clear
                                    placeholder="optional"
                                    class="w-full"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-medium text-gray-500">platform</label>
                                <input
                                    v-model="ctxPlatform"
                                    type="text"
                                    class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                                    placeholder="woocommerce"
                                />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[11px] font-medium text-gray-500">product_name</label>
                                <input
                                    v-model="ctxProductName"
                                    type="text"
                                    class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm outline-none focus:border-fuchsia-400 dark:border-gray-700 dark:bg-slate-900"
                                    placeholder="optional"
                                />
                            </div>
                            <div class="flex min-h-10 items-end">
                                <label class="inline-flex h-10 items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                    <input v-model="ctxVoiceProfile" type="checkbox" class="rounded border-gray-300" />
                                    output_profile=voice
                                </label>
                            </div>
                            <p class="text-[11px] text-gray-400 sm:col-span-2 lg:col-span-3">
                                খালি রাখলে bare price → clarify। product_id দিয়ে S4/S6 টেস্ট করুন।
                            </p>
                        </div>

                        <div class="flex h-[min(560px,70vh)] flex-col px-4 py-4 sm:px-5">
                            <div
                                ref="threadEl"
                                class="min-h-0 flex-1 space-y-3 overflow-y-auto rounded-2xl border border-gray-100 bg-gradient-to-b from-gray-50/80 to-white p-4 dark:border-gray-800 dark:from-slate-800/50 dark:to-slate-900/40"
                            >
                                <div
                                    v-if="!messages.length"
                                    class="flex h-full flex-col items-center justify-center gap-3 px-2 text-center"
                                >
                                    <span
                                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-fuchsia-50 text-fuchsia-500 dark:bg-fuchsia-500/15 dark:text-fuchsia-400"
                                    >
                                        <Icon name="PhChatCircleDots" class="text-3xl" />
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                            টেস্ট কথোপকথন শুরু করুন
                                        </p>
                                        <p class="mt-1 max-w-md text-xs text-gray-500 dark:text-gray-400">
                                            প্রথমে সামাজিক (“hi”) — তারপর knowledge (“ডেলিভারি চার্জ কত?”)।
                                            Knowledge না থাকলে Action = needs_human।
                                        </p>
                                    </div>
                                    <div class="mt-1 flex max-w-lg flex-wrap justify-center gap-2">
                                        <button
                                            v-for="chip in quickStarts"
                                            :key="chip"
                                            type="button"
                                            class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-fuchsia-300 hover:bg-fuchsia-50 hover:text-fuchsia-700 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-200 dark:hover:border-fuchsia-500/40 dark:hover:bg-fuchsia-500/10 dark:hover:text-fuchsia-300"
                                            @click="useQuickStart(chip)"
                                        >
                                            {{ chip }}
                                        </button>
                                    </div>
                                </div>

                                <template v-for="msg in messages" :key="msg.id">
                                    <div
                                        class="flex"
                                        :class="msg.role === 'customer' ? 'justify-end' : 'justify-start'"
                                    >
                                        <div
                                            class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm shadow-sm sm:max-w-[75%]"
                                            :class="
                                                msg.role === 'customer'
                                                    ? 'rounded-br-md bg-fuchsia-600 text-white'
                                                    : 'rounded-bl-md border border-gray-200/80 bg-white text-gray-700 dark:border-gray-700 dark:bg-slate-900 dark:text-gray-200'
                                            "
                                        >
                                            <p
                                                v-if="msg.role === 'brain'"
                                                class="mb-1.5 flex flex-wrap items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-fuchsia-500 dark:text-fuchsia-400"
                                            >
                                                <Icon name="PhBrain" class="text-xs" />
                                                Wise AI
                                                <span
                                                    v-if="msg.meta"
                                                    class="normal-case tracking-normal text-gray-400"
                                                >
                                                    · {{ msg.meta }}
                                                </span>
                                            </p>
                                            <p class="whitespace-pre-wrap leading-relaxed">{{ msg.text }}</p>
                                            <button
                                                v-if="msg.role === 'brain' && msg.turn_id && !msg.taught"
                                                type="button"
                                                class="mt-2 text-[11px] font-semibold text-fuchsia-700 hover:underline dark:text-fuchsia-300"
                                                @click="startTeachFromMessage(msg)"
                                            >
                                                রিপ্লাই ঠিক করে brain-এ শেখান →
                                            </button>
                                            <p
                                                v-else-if="msg.role === 'brain' && msg.taught && msg.taughtPublished"
                                                class="mt-2 text-[11px] font-medium text-emerald-600 dark:text-emerald-300"
                                            >
                                                Published — একই প্রশ্ন আবার পাঠালে knowledge hit হওয়া উচিত
                                            </p>
                                            <p
                                                v-else-if="msg.role === 'brain' && msg.taught"
                                                class="mt-2 text-[11px] font-medium text-amber-600 dark:text-amber-300"
                                            >
                                                Draft only — Publish না করলে decide আবার Gap দেখাবে
                                            </p>
                                        </div>
                                    </div>
                                </template>

                                <div v-if="sending" class="flex justify-start">
                                    <div
                                        class="rounded-2xl rounded-bl-md border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-slate-900"
                                    >
                                        <span class="flex gap-1">
                                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-fuchsia-400" />
                                            <span
                                                class="h-1.5 w-1.5 animate-bounce rounded-full bg-fuchsia-400 [animation-delay:120ms]"
                                            />
                                            <span
                                                class="h-1.5 w-1.5 animate-bounce rounded-full bg-fuchsia-400 [animation-delay:240ms]"
                                            />
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <form class="mt-3" @submit.prevent="sendMessage">
                                <div class="flex items-center gap-2">
                                    <div class="min-w-0 flex-1">
                                        <BanglaField
                                            v-model="draft"
                                            placeholder="Type a customer message…"
                                            :disabled="sending"
                                            class="px-4 focus:ring-2 focus:ring-fuchsia-100 dark:focus:ring-fuchsia-500/20"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        icon="pi pi-send"
                                        label="Send"
                                        class="!h-11 shrink-0"
                                        :disabled="!draft.trim() || sending"
                                        :loading="sending"
                                        aria-label="Send"
                                    />
                                </div>
                                <p class="mt-1.5 text-[10px] text-gray-400">
                                    Enter পাঠান · Ctrl+M বাংলা টগল · বাং মোডে Space দিয়ে convert
                                </p>
                            </form>
                        </div>
                    </PageCard>
                </div>

                <!-- Inspector column -->
                <div class="xl:col-span-2 xl:sticky xl:top-4 xl:self-start">
                    <PageCard title="Inspector" description="Last decide turn — sealed trace">
                        <div class="max-h-[calc(100vh-8rem)] space-y-0 overflow-y-auto pr-0.5">
                        <!-- Live decision strip: 2×2 keeps cards equal in a narrow column -->
                        <div
                            v-if="lastTrace"
                            class="mb-4 grid grid-cols-2 gap-2"
                        >
                            <div
                                class="min-w-0 rounded-xl border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-gray-800 dark:bg-slate-800/40"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                                    Intent
                                </p>
                                <p
                                    class="mt-1 truncate text-sm font-semibold text-gray-800 dark:text-gray-100"
                                    :title="lastTrace.intent"
                                >
                                    {{ lastTrace.intent }}
                                </p>
                            </div>
                            <div
                                class="min-w-0 rounded-xl border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-gray-800 dark:bg-slate-800/40"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                                    Action
                                </p>
                                <div class="mt-1 min-w-0">
                                    <span
                                        class="inline-flex max-w-full items-center truncate rounded-full px-2 py-0.5 text-[11px] font-semibold leading-5"
                                        :class="actionChipClass(lastTrace.action)"
                                        :title="lastTrace.action"
                                    >
                                        {{ lastTrace.action }}
                                    </span>
                                </div>
                            </div>
                            <div
                                class="min-w-0 rounded-xl border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-gray-800 dark:bg-slate-800/40"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                                    Confidence
                                </p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">
                                    {{ lastTrace.confidence }}%
                                </p>
                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        class="h-full rounded-full bg-fuchsia-500 transition-all"
                                        :style="{ width: `${Math.min(100, Math.max(0, lastTrace.confidence))}%` }"
                                    />
                                </div>
                            </div>
                            <div
                                class="min-w-0 rounded-xl border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-gray-800 dark:bg-slate-800/40"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                                    Latency
                                </p>
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">
                                    {{ lastTrace.latency_ms }}
                                    <span class="text-xs font-medium text-gray-400">ms</span>
                                </p>
                            </div>
                        </div>

                        <div
                            class="mb-4 grid grid-cols-5 gap-0.5 rounded-xl border border-gray-200 bg-gray-50/80 p-1 dark:border-gray-700 dark:bg-slate-800/50"
                        >
                            <button
                                v-for="tab in suiteTabs"
                                :key="tab.id"
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-1 rounded-lg px-1 text-[10px] font-semibold leading-none transition-colors sm:text-[11px]"
                                :class="
                                    activeTab === tab.id
                                        ? 'bg-white text-fuchsia-700 shadow-sm dark:bg-slate-900 dark:text-fuchsia-300'
                                        : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'
                                "
                                :title="tab.label"
                                @click="activeTab = tab.id"
                            >
                                <Icon :name="tab.icon" :size="14" class="shrink-0" />
                                <span class="truncate">{{ tab.short }}</span>
                            </button>
                        </div>

                        <div v-if="!lastTrace && activeTab !== 'knowledge'" class="py-8">
                            <EmptyState
                                icon="PhCircuitry"
                                title="No turn yet"
                                description="Send a chat message — tabs fill from the public decide/explain APIs"
                            />
                        </div>

                        <div v-else-if="activeTab === 'decision' && lastTrace" class="space-y-3">
                            <div
                                class="rounded-xl border border-gray-100 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-slate-800/40"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                                    Input
                                </p>
                                <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-100">
                                    “{{ lastTrace.input }}”
                                </p>
                            </div>
                            <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <div class="flex items-center justify-between gap-4 py-2.5 first:pt-0">
                                    <dt class="text-gray-500">Source</dt>
                                    <dd class="font-medium text-gray-800 dark:text-gray-100">{{ lastTrace.source }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 py-2.5">
                                    <dt class="text-gray-500">Turn / Brain</dt>
                                    <dd class="font-mono text-xs text-gray-700 dark:text-gray-200">
                                        #{{ lastTrace.turn_id }} · {{ lastTrace.brain_version }}
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 py-2.5">
                                    <dt class="text-gray-500">Knowledge gap</dt>
                                    <dd>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold leading-5"
                                            :class="
                                                lastTrace.gap
                                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'
                                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                                            "
                                            :title="lastTrace.gap ? 'No published evidence for this business question' : 'Evidence found or social turn'"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="lastTrace.gap ? 'bg-rose-500' : 'bg-emerald-500'"
                                            />
                                            {{ lastTrace.gap ? "Gap detected" : "Covered" }}
                                        </span>
                                    </dd>
                                </div>
                                <div
                                    v-if="lastTrace.missing_context"
                                    class="flex items-center justify-between gap-4 py-2.5"
                                >
                                    <dt class="text-gray-500">Missing</dt>
                                    <dd class="font-medium text-amber-700 dark:text-amber-300">
                                        {{ lastTrace.missing_context }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="lastTrace.voice"
                                class="rounded-xl border border-cyan-100 bg-cyan-50/50 p-2.5 text-[11px] dark:border-cyan-500/20 dark:bg-cyan-500/10"
                            >
                                <p class="font-semibold text-cyan-800 dark:text-cyan-300">Voice contract</p>
                                <p class="mt-0.5 text-gray-600 dark:text-gray-300">
                                    {{ lastTrace.voice.next_action }}
                                    <span v-if="lastTrace.voice.slot_to_ask">
                                        · slot {{ lastTrace.voice.slot_to_ask }}
                                    </span>
                                    <span v-if="lastTrace.voice.gap"> · gap</span>
                                </p>
                                <p class="mt-1 text-gray-700 dark:text-gray-200">
                                    {{ lastTrace.voice.speak_text }}
                                </p>
                                <p class="mt-1 text-[10px] text-gray-400">
                                    Prep only — no telephony / TTS in brain
                                </p>
                            </div>

                            <div
                                v-if="lastEvidence"
                                class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-2.5 text-[11px] dark:border-emerald-500/20 dark:bg-emerald-500/10"
                            >
                                <p class="font-semibold text-emerald-700 dark:text-emerald-300">Evidence</p>
                                <p class="mt-0.5 text-gray-600 dark:text-gray-300">
                                    {{ lastEvidence.title || "knowledge" }}
                                    <span v-if="lastEvidence.knowledge_scope">
                                        · {{ lastEvidence.knowledge_scope }}
                                    </span>
                                    <span v-if="lastEvidence.match_score != null">
                                        · score {{ lastEvidence.match_score }}
                                    </span>
                                </p>
                            </div>

                            <div
                                v-if="lastTrace.psych"
                                class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-2.5 text-[11px] dark:border-indigo-500/20 dark:bg-indigo-500/10"
                            >
                                <p class="font-semibold text-indigo-700 dark:text-indigo-300">
                                    Assist side-channel
                                </p>
                                <p class="mt-0.5 text-gray-600 dark:text-gray-300">
                                    {{ lastTrace.psych.emotion }} · {{ lastTrace.psych.journey }} · priority
                                    {{ lastTrace.psych.priority }} · {{ lastTrace.psych.style_hint }}
                                </p>
                                <ul
                                    v-if="lastTrace.opportunities?.length"
                                    class="mt-1 list-inside list-disc text-indigo-700 dark:text-indigo-300"
                                >
                                    <li v-for="op in lastTrace.opportunities" :key="op.id">{{ op.title }}</li>
                                </ul>
                                <p class="mt-1 text-[10px] text-gray-400">
                                    Never changes sealed facts or Auto-send
                                </p>
                            </div>

                            <div
                                class="space-y-3 rounded-xl border border-violet-100 bg-violet-50/40 p-3.5 dark:border-violet-500/20 dark:bg-violet-500/10"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-[11px] font-semibold text-violet-800 dark:text-violet-200">
                                            LLM Coach
                                        </p>
                                        <p class="mt-0.5 text-[10px] leading-relaxed text-gray-500 dark:text-gray-400">
                                            Turn পড়ে FAQ / abbrev / noop propose করে — edit করে Approve। Silent publish নেই।
                                        </p>
                                    </div>
                                    <StatusBadge
                                        v-if="!llm_ready"
                                        label="LLM off"
                                        variant="warning"
                                        format="none"
                                    />
                                </div>

                                <Button
                                    v-if="!coachProposal"
                                    label="Coach this turn"
                                    icon="pi pi-sparkles"
                                    size="small"
                                    severity="help"
                                    fluid
                                    :loading="coachLoading"
                                    :disabled="!lastTrace?.turn_id || coachLoading || !can_edit"
                                    @click="runCoach"
                                />

                                <template v-else>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                            :class="coachCategoryChipClass"
                                        >
                                            {{ coachCategory }}
                                        </span>
                                        <span class="text-[10px] text-gray-500">
                                            {{ coachProposal.confidence }}% · {{ coachProposal.latency_ms }}ms
                                            <span v-if="coachProposal.hint && coachProposal.hint !== coachCategory">
                                                · LLM hint {{ coachProposal.hint }}
                                            </span>
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-gray-600 dark:text-gray-300">
                                        {{ coachProposal.rationale }}
                                    </p>
                                    <ul
                                        v-if="coachProposal.warnings?.length"
                                        class="list-inside list-disc text-[10px] text-amber-700 dark:text-amber-300"
                                    >
                                        <li v-for="(w, i) in coachProposal.warnings" :key="i">{{ w }}</li>
                                    </ul>

                                    <div>
                                        <p class="mb-1 text-[10px] font-semibold text-violet-700 dark:text-violet-300">
                                            Action category (override OK)
                                        </p>
                                        <Select
                                            v-model="coachCategory"
                                            :options="coachCategoryOptions"
                                            option-label="label"
                                            option-value="value"
                                            size="small"
                                            class="w-full"
                                            :disabled="coachApplying || coachDone"
                                            @update:model-value="onCoachCategoryChange"
                                        />
                                    </div>

                                    <template v-if="coachCategory === 'knowledge_faq'">
                                        <BanglaField
                                            v-model="coachKnowledge.title"
                                            placeholder="Title"
                                            :disabled="coachApplying || coachDone"
                                        />
                                        <BanglaField
                                            v-model="coachKnowledge.question"
                                            placeholder="Question"
                                            :disabled="coachApplying || coachDone"
                                        />
                                        <BanglaField
                                            v-model="coachKnowledge.answer"
                                            multiline
                                            :rows="3"
                                            placeholder="Answer — no invented fees"
                                            :disabled="coachApplying || coachDone"
                                        />
                                    </template>

                                    <template v-else-if="coachCategory === 'language_abbrev'">
                                        <Select
                                            v-model="coachLanguage.type"
                                            :options="coachLangTypeOptions"
                                            option-label="label"
                                            option-value="value"
                                            size="small"
                                            class="w-full"
                                            :disabled="coachApplying || coachDone"
                                        />
                                        <BanglaField
                                            v-model="coachLanguage.from"
                                            placeholder="from (e.g. pp)"
                                            :disabled="coachApplying || coachDone"
                                        />
                                        <BanglaField
                                            v-model="coachLanguage.to"
                                            placeholder="to (e.g. দাম কত)"
                                            :disabled="coachApplying || coachDone"
                                        />
                                    </template>

                                    <p
                                        v-else
                                        class="text-[10px] text-gray-500"
                                    >
                                        Noop = brain আপডেট নেই। FAQ বা abbrev বেছে Save draft / Approve & Publish করুন।
                                    </p>

                                    <div v-if="!coachDone" class="flex flex-col gap-2">
                                        <Button
                                            v-if="coachCategory !== 'noop' && can_edit"
                                            label="Save draft"
                                            icon="pi pi-save"
                                            size="small"
                                            severity="secondary"
                                            fluid
                                            :loading="coachApplying && !coachPublishing"
                                            :disabled="coachApplying"
                                            @click="applyCoach(false)"
                                        />
                                        <Button
                                            v-if="coachCategory !== 'noop' && can_publish"
                                            label="Approve & Publish"
                                            icon="pi pi-check"
                                            size="small"
                                            severity="success"
                                            fluid
                                            :loading="coachPublishing"
                                            :disabled="coachApplying"
                                            @click="applyCoach(true)"
                                        />
                                        <Button
                                            label="Dismiss"
                                            size="small"
                                            text
                                            severity="secondary"
                                            fluid
                                            :disabled="coachApplying"
                                            @click="dismissCoach"
                                        />
                                    </div>
                                    <p
                                        v-if="coachResult"
                                        class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                    >
                                        {{ coachResult }}
                                    </p>
                                </template>
                            </div>

                            <div
                                class="space-y-3 rounded-xl border border-fuchsia-100 bg-fuchsia-50/40 p-3.5 dark:border-fuchsia-500/20 dark:bg-fuchsia-500/10"
                            >
                                <div>
                                    <p class="text-[11px] font-semibold text-fuchsia-800 dark:text-fuchsia-200">
                                        Teach brain
                                    </p>
                                    <p class="mt-0.5 text-[10px] leading-relaxed text-gray-500 dark:text-gray-400">
                                        ভুল suggest ঠিক করে Save & Publish করুন। Draft alone decide-এ match হয় না — silent auto-learn নেই।
                                    </p>
                                </div>
                                <BanglaField
                                    v-model="teachReply"
                                    multiline
                                    :rows="4"
                                    placeholder="সঠিক উত্তর লিখুন — fee digit আন্দাজ করবেন না"
                                    :disabled="!lastTrace?.turn_id || teachDone"
                                />
                                <div
                                    v-if="can_edit"
                                    class="flex flex-col gap-2"
                                >
                                    <Button
                                        label="Save to Knowledge"
                                        icon="pi pi-save"
                                        size="small"
                                        severity="help"
                                        fluid
                                        :loading="teachLoading && !teachPublishing"
                                        :disabled="!canTeach || teachLoading || teachDone || teachDraftItemId !== null"
                                        @click="teachToBrain(false)"
                                    />
                                    <Button
                                        v-if="can_publish"
                                        :label="teachDraftItemId ? 'Publish draft' : 'Save & Publish'"
                                        icon="pi pi-check"
                                        size="small"
                                        severity="success"
                                        fluid
                                        :loading="teachPublishing"
                                        :disabled="!canTeach || teachLoading || teachDone"
                                        @click="teachToBrain(true)"
                                    />
                                </div>
                                <p
                                    v-else
                                    class="text-[10px] text-amber-700 dark:text-amber-300"
                                >
                                    Editor permission লাগবে Knowledge-এ সেভ করতে।
                                </p>
                                <p
                                    v-if="teachResult"
                                    class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                >
                                    {{ teachResult }}
                                </p>
                            </div>

                            <div
                                class="space-y-3 rounded-xl border border-gray-100 bg-white p-3.5 dark:border-gray-800 dark:bg-slate-900/40"
                            >
                                <div>
                                    <p class="text-[11px] font-semibold text-gray-700 dark:text-gray-200">
                                        Human feedback
                                    </p>
                                    <p
                                        v-if="!feedbackOutcome"
                                        class="mt-0.5 text-[10px] text-gray-400"
                                    >
                                        Approve = ঠিক আছে · Reject = reason লাগবে
                                    </p>
                                </div>
                                <p
                                    v-if="feedbackOutcome"
                                    class="rounded-lg px-2.5 py-1.5 text-[11px] font-medium"
                                    :class="
                                        feedbackOutcome === 'approved'
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                            : feedbackOutcome === 'edited'
                                              ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
                                              : 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'
                                    "
                                >
                                    Logged: {{ feedbackOutcome }}
                                    <span v-if="feedbackOutcome === 'rejected'"> · {{ rejectReason }}</span>
                                </p>
                                <template v-else>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] text-gray-500">
                                            Reject reason
                                        </label>
                                        <Select
                                            v-model="rejectReason"
                                            :options="rejectReasonOptions"
                                            option-label="label"
                                            option-value="value"
                                            class="w-full"
                                        />
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <Button
                                            label="Approve"
                                            icon="pi pi-check"
                                            size="small"
                                            severity="success"
                                            fluid
                                            :loading="feedbackLoading"
                                            :disabled="!lastTrace?.turn_id || !apiKey"
                                            @click="sendFeedback('approved')"
                                        />
                                        <Button
                                            label="Reject"
                                            icon="pi pi-times"
                                            size="small"
                                            severity="danger"
                                            outlined
                                            fluid
                                            :loading="feedbackLoading"
                                            :disabled="!lastTrace?.turn_id || !apiKey"
                                            @click="sendFeedback('rejected')"
                                        />
                                    </div>
                                </template>
                                <Button
                                    v-if="lastTrace.turn_id"
                                    label="Open Replay"
                                    icon="pi pi-replay"
                                    size="small"
                                    text
                                    severity="help"
                                    fluid
                                    @click="openReplay(lastTrace.turn_id)"
                                />
                            </div>
                        </div>

                        <div v-else-if="activeTab === 'language' && lastTrace" class="space-y-3 text-sm">
                            <div
                                class="rounded-xl border border-fuchsia-100 bg-fuchsia-50/50 p-3 dark:border-fuchsia-500/20 dark:bg-fuchsia-500/10"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-fuchsia-500">
                                    Canonical
                                </p>
                                <p class="mt-1 font-medium">
                                    “{{ lastTrace.canonical || lastTrace.input }}”
                                </p>
                                <p
                                    v-if="lastTrace.dict_version"
                                    class="mt-1 font-mono text-[10px] text-gray-400"
                                >
                                    {{ lastTrace.dict_version }}
                                </p>
                            </div>
                            <p v-if="lastTrace.language_rules" class="text-xs text-gray-600 dark:text-gray-300">
                                Rules: {{ lastTrace.language_rules }}
                            </p>
                            <div v-if="lastLanguage?.unknown_tokens?.length" class="text-xs">
                                <p class="font-semibold text-amber-700 dark:text-amber-300">Unknown tokens</p>
                                <p class="mt-1 font-mono text-gray-600">
                                    {{ lastLanguage.unknown_tokens.join(", ") }}
                                </p>
                            </div>
                            <div v-if="lastLanguage?.ambiguous?.length" class="text-xs">
                                <p class="font-semibold text-gray-500">Ambiguous (left untouched)</p>
                                <p class="mt-1 font-mono">{{ lastLanguage.ambiguous.join(", ") }}</p>
                                <p class="mt-1.5 text-[10px] text-violet-700 dark:text-violet-300">
                                    Decision ট্যাবে Coach this turn → merchant abbrev Approve করুন (e.g. pp → দাম কত)।
                                </p>
                            </div>
                            <p
                                v-if="!lastTrace.language_rules && !lastLanguage?.unknown_tokens?.length"
                                class="text-xs text-gray-400"
                            >
                                No language transforms on this turn.
                            </p>
                        </div>

                        <div v-else-if="activeTab === 'explain' && lastTrace" class="space-y-3">
                            <template v-if="explain">
                                <div class="flex flex-wrap items-center gap-2">
                                    <StatusBadge label="replay-safe" variant="success" format="none" />
                                    <Button
                                        v-if="lastTrace.turn_id"
                                        label="Open Replay"
                                        size="small"
                                        text
                                        severity="help"
                                        @click="openReplay(lastTrace.turn_id)"
                                    />
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-300">{{ explain.summary }}</p>
                                <ol class="space-y-2">
                                    <li
                                        v-for="(step, idx) in explain.timeline"
                                        :key="step.step"
                                        class="rounded-lg border border-gray-100 px-2.5 py-2 dark:border-gray-800"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-fuchsia-50 font-mono text-[10px] font-bold text-fuchsia-600 dark:bg-fuchsia-500/15 dark:text-fuchsia-300"
                                            >
                                                {{ idx + 1 }}
                                            </span>
                                            <span class="text-xs font-semibold">{{ step.title }}</span>
                                            <StatusBadge
                                                :label="step.status"
                                                variant="neutral"
                                                format="none"
                                                class="ml-auto"
                                            />
                                        </div>
                                        <p class="mt-1 pl-7 text-[11px] text-gray-500">{{ step.detail }}</p>
                                    </li>
                                </ol>
                                <div
                                    v-if="explainCorpusPacks.length || explain.answers?.why_corpus"
                                    class="rounded-lg border border-fuchsia-100 bg-fuchsia-50/50 p-2.5 dark:border-fuchsia-900/40 dark:bg-fuchsia-950/20"
                                >
                                    <p class="text-[11px] font-semibold text-fuchsia-700 dark:text-fuchsia-300">
                                        Sealed language corpus
                                    </p>
                                    <ul
                                        v-if="explainCorpusPacks.length"
                                        class="mt-1 space-y-0.5 font-mono text-[10px] text-gray-600 dark:text-gray-300"
                                    >
                                        <li v-for="(pack, i) in explainCorpusPacks" :key="i">
                                            {{ pack.slug }}@{{ pack.version }} ·
                                            {{ String(pack.artifact_hash || "").slice(0, 8) }}
                                        </li>
                                    </ul>
                                    <p
                                        v-if="explain.answers?.why_corpus"
                                        class="mt-1 text-[11px] text-gray-500"
                                    >
                                        {{ explain.answers.why_corpus }}
                                    </p>
                                </div>
                            </template>
                            <EmptyState
                                v-else
                                icon="PhPath"
                                title="Explain unavailable"
                                description="GET /turns/{id}/explain failed for this turn"
                            />
                        </div>

                        <div v-else-if="activeTab === 'memory'" class="space-y-3 text-sm">
                            <dl class="space-y-2 text-xs">
                                <div class="flex justify-between gap-2">
                                    <dt class="text-gray-500">conversation_id</dt>
                                    <dd class="max-w-[60%] truncate font-mono" :title="conversationId">
                                        {{ conversationId }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-gray-500">Thread messages</dt>
                                    <dd class="font-medium">{{ messages.length }}</dd>
                                </div>
                                <div v-if="lastTrace" class="flex justify-between gap-2">
                                    <dt class="text-gray-500">Last memory_used</dt>
                                    <dd>
                                        <StatusBadge
                                            :label="lastTrace.memory_used ? 'yes' : 'no'"
                                            :variant="lastTrace.memory_used ? 'success' : 'neutral'"
                                            format="none"
                                        />
                                    </dd>
                                </div>
                                <div v-if="lastTrace?.product_subject" class="flex justify-between gap-2">
                                    <dt class="text-gray-500">Active offer</dt>
                                    <dd
                                        class="truncate font-medium"
                                        :title="lastTrace.product_subject"
                                    >
                                        {{ lastTrace.product_subject }}
                                    </dd>
                                </div>
                            </dl>
                            <p class="text-[11px] text-gray-400">
                                Memory is sealed on the turn (same conversation_id). Follow-ups reuse prior
                                business intent — never invents facts.
                            </p>
                            <ol class="max-h-56 space-y-1.5 overflow-y-auto text-[11px]">
                                <li
                                    v-for="msg in messages"
                                    :key="'mem-' + msg.id"
                                    class="rounded-lg border border-gray-100 px-2 py-1.5 dark:border-gray-800"
                                >
                                    <span class="font-semibold uppercase text-gray-400">{{ msg.role }}</span>
                                    — {{ msg.text }}
                                </li>
                            </ol>
                        </div>

                        <div v-else-if="activeTab === 'knowledge'" class="space-y-3">
                            <p class="text-[11px] text-gray-500">
                                Sandbox probe — same public decide API (separate conversation so chat memory
                                stays clean).
                            </p>
                            <BanglaField
                                v-model="probeText"
                                multiline
                                :rows="2"
                                placeholder="e.g. delivery charge koto?"
                            />
                            <Button
                                label="Probe knowledge"
                                icon="pi pi-search"
                                size="small"
                                class="w-full"
                                :loading="probeLoading"
                                :disabled="!probeText.trim()"
                                @click="runKnowledgeProbe"
                            />
                            <div
                                v-if="probeResult"
                                class="space-y-2 rounded-xl border border-gray-100 p-3 text-xs dark:border-gray-800"
                            >
                                <div class="flex flex-wrap gap-2">
                                    <StatusBadge :label="probeResult.intent" variant="neutral" format="none" />
                                    <StatusBadge
                                        :label="probeResult.action"
                                        :variant="actionVariant(probeResult.action)"
                                        format="none"
                                    />
                                    <StatusBadge :label="probeResult.source" variant="info" format="none" />
                                </div>
                                <p v-if="probeResult.reply" class="text-sm text-gray-800 dark:text-gray-100">
                                    {{ probeResult.reply }}
                                </p>
                                <p v-if="probeResult.evidence_title" class="text-gray-500">
                                    Hit: {{ probeResult.evidence_title }}
                                    <span v-if="probeResult.score != null"> · score {{ probeResult.score }}</span>
                                </p>
                                <p v-if="probeResult.gap" class="font-medium text-rose-600">Knowledge gap</p>
                            </div>
                        </div>
                        </div>
                    </PageCard>
                </div>
            </div>
        </div>

        <TurnReplayDialog v-model:visible="replayOpen" :turn-id="replayTurnId" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from "vue";
import axios from "axios";
import { Link } from "@inertiajs/vue3";
import Button from "primevue/button";
import { useToast } from "primevue/usetoast";
import { AuthenticatedLayout } from "@/layouts";
import { Icon } from "@/plugins";
import PageHeader from "@/Pages/Users/fragments/PageHeader.vue";
import PageCard from "@/Pages/Users/fragments/PageCard.vue";
import StatusBadge from "@/Pages/Users/fragments/StatusBadge.vue";
import EmptyState from "@/Pages/Users/fragments/EmptyState.vue";
import Select from "primevue/select";
import TurnReplayDialog from "./fragments/TurnReplayDialog.vue";
import WiseAiSubNav from "./fragments/WiseAiSubNav.vue";
import WiseAiHowTo from "./fragments/WiseAiHowTo.vue";
import BanglaField from "@/components/BanglaField.vue";
import { feeInvented } from "@/utils/wiseFeeGuard";

const offerKindOptions = [
    { label: "physical", value: "physical" },
    { label: "digital", value: "digital" },
    { label: "service", value: "service" },
    { label: "subscription", value: "subscription" },
    { label: "other", value: "other" },
];

const channelOptions = [
    { label: "playground", value: "playground" },
    { label: "voice (sim)", value: "voice" },
    { label: "messenger", value: "messenger" },
];

const rejectReasonOptions = [
    { value: "wrong_fact", label: "Wrong fact / answer" },
    { value: "wrong_offer", label: "Wrong offer / product" },
    { value: "missing_knowledge", label: "Missing knowledge" },
    { value: "outdated", label: "Outdated knowledge" },
    { value: "tone", label: "Tone / voice wrong" },
    { value: "language", label: "Language / wording wrong" },
    { value: "policy", label: "Policy / safety" },
    { value: "other", label: "Other" },
];

const howToSteps = [
    {
        title: "API key Connect করুন",
        detail: "Config-এ Generate Key → Copy → এখানে Paste → Connect। Key ব্রাউজার localStorage-এ থাকে।",
    },
    {
        title: "কাস্টমারের মতো মেসেজ লিখুন",
        detail: "উদাহরণ: hi · দাম কত? · delivery charge koto? · অর্ডার কই?",
    },
    {
        title: "ডান পাশে Decision Trace পড়ুন",
        detail: "Intent, Confidence, Action, Source, Gap — brain কেন এমন সিদ্ধান্ত নিল তা এখানে।",
    },
    {
        title: "LLM Coach চালান (optional)",
        detail: "Coach this turn → FAQ / abbrev / noop propose → edit → Approve & Publish। Silent learn নেই।",
    },
    {
        title: "Approve বা Reject (reason সহ) দিন",
        detail: "Reject-এ Learning taxonomy reason বাছুন — silent reject নেই।",
    },
];

const howToTips = [
    "শুধু ‘price koto?’ → clarify: নাম বা ছবি চায়।",
    "উপরের context.product_id দিয়ে S4/S6 টেস্ট: id আছে + ambiguity → দাম বা gap (ভুল FAQ নয়)।",
    "Offer knowledge Publish করে একই external_id দিন।",
    "channel=voice বা output_profile=voice → decision.voice (speak / next_action) — telephony নয়।",
    "ভুল রিপ্লাই → Teach brain → Save & Publish (draft মানে এখনও Gap)।",
    "pp এর মতো ambiguous → Coach → language_abbrev → Approve & Publish (merchant only)।",
];

const quickStarts = [
    "hi",
    "ধন্যবাদ",
    "দাম কত?",
    "delivery charge koto?",
    "অর্ডার কই?",
];

type PlaygroundMessage = {
    id: number;
    role: "customer" | "brain";
    text: string;
    meta?: string;
    turn_id?: number;
    taught?: boolean;
    /** false = Knowledge draft (not grounded by decide); true = published */
    taughtPublished?: boolean;
};

type DecisionTrace = {
    input: string;
    canonical: string | null;
    dict_version: string | null;
    language_rules: string | null;
    intent: string;
    confidence: number;
    action: string;
    source: string;
    latency_ms: number;
    turn_id: number;
    brain_version: string;
    gap: boolean;
    memory_used: boolean;
    missing_context: string | null;
    product_subject: string | null;
    voice?: {
        speak_text?: string;
        next_action?: string;
        slot_to_ask?: string | null;
        gap?: boolean;
    } | null;
    psych?: {
        emotion?: string;
        journey?: string;
        priority?: string;
        style_hint?: string;
    } | null;
    opportunities?: { id: string; title: string }[];
};

type ExplainPayload = {
    summary: string;
    timeline: Array<{ step: string; title: string; detail: string; status: string }>;
    answers: Record<string, string>;
    sealed?: {
        language_corpus_snapshot?: {
            packs?: { slug?: string; version?: string; artifact_hash?: string }[];
        };
    };
};

type LanguageSnap = {
    unknown_tokens?: string[];
    ambiguous?: string[];
    dict_version?: string | null;
};

type EvidenceSnap = {
    title?: string;
    knowledge_scope?: string;
    match_score?: number;
    knowledge_id?: number;
};

const STORAGE_KEY = "wise_ai_playground_key";

const props = defineProps<{
    can_edit?: boolean;
    can_publish?: boolean;
    llm_ready?: boolean;
}>();

const can_edit = computed(() => props.can_edit !== false);
const can_publish = computed(() => Boolean(props.can_publish));
const llm_ready = computed(() => Boolean(props.llm_ready));

const toast = useToast();

const apiKey = ref(localStorage.getItem(STORAGE_KEY) || "");
const keyDraft = ref("");
const keyMeta = ref<{ id: number; name: string } | null>(null);
const messages = ref<PlaygroundMessage[]>([]);
const draft = ref("");
const sending = ref(false);
const feedbackLoading = ref(false);
const feedbackOutcome = ref<"approved" | "rejected" | "edited" | null>(null);
const rejectReason = ref("tone");
const teachReply = ref("");
const teachLoading = ref(false);
const teachPublishing = ref(false);
const teachDone = ref(false);
/** After Save to Knowledge — Publish uses this id (gap draft already closes the turn). */
const teachDraftItemId = ref<number | null>(null);
const teachResult = ref<string | null>(null);

type CoachProposal = {
    category: "knowledge_faq" | "language_abbrev" | "noop";
    confidence: number;
    rationale: string;
    knowledge: { title: string; question: string; answer: string; keywords: string[] };
    language: { type: string; from: string; to: string };
    warnings: string[];
    model?: string;
    latency_ms?: number;
    hint?: string;
};

const coachLoading = ref(false);
const coachApplying = ref(false);
const coachPublishing = ref(false);
const coachDone = ref(false);
const coachResult = ref<string | null>(null);
const coachProposal = ref<CoachProposal | null>(null);
const coachCategory = ref<CoachProposal["category"]>("noop");
const coachKnowledge = ref({ title: "", question: "", answer: "", keywords: [] as string[] });
const coachLanguage = ref({ type: "abbrev", from: "", to: "" });
const coachCategoryOptions = [
    { label: "FAQ → Knowledge", value: "knowledge_faq" },
    { label: "Abbrev → Language", value: "language_abbrev" },
    { label: "Noop (dismiss)", value: "noop" },
];
const coachLangTypeOptions = [
    { label: "abbrev", value: "abbrev" },
    { label: "banglish", value: "banglish" },
    { label: "sms", value: "sms" },
    { label: "commerce", value: "commerce" },
    { label: "messenger", value: "messenger" },
];

const coachCategoryChipClass = computed(() => {
    const c = coachCategory.value;
    if (c === "knowledge_faq") {
        return "bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-500/20 dark:text-fuchsia-200";
    }
    if (c === "language_abbrev") {
        return "bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200";
    }
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300";
});

const seedCoachFieldsForCategory = (category: CoachProposal["category"], force = false) => {
    if (category === "knowledge_faq") {
        const customer = (lastTrace.value?.input || lastTrace.value?.canonical || "").trim();
        if (customer) {
            // Publishable FAQ must ask what the customer actually typed.
            coachKnowledge.value.question = customer;
            if (force || !coachKnowledge.value.title.trim()) {
                coachKnowledge.value.title = customer.slice(0, 80);
            }
        } else if (force || !coachKnowledge.value.title.trim()) {
            coachKnowledge.value.title = "Playground FAQ";
        }
        if (!coachKnowledge.value.answer.trim()) {
            coachKnowledge.value.answer =
                teachReply.value.trim() ||
                "কোন প্রোডাক্ট/সার্ভিসের বিস্তারিত জানতে চান? নাম বা ছবি পাঠালে দেখে বলছি — আন্দাজ করে দাম/চার্জ বলব না।";
        }
        if (!coachKnowledge.value.keywords.length && lastTrace.value?.intent) {
            coachKnowledge.value.keywords = [lastTrace.value.intent];
        }
    }
    if (category === "language_abbrev") {
        if (force || !coachLanguage.value.from.trim()) {
            const amb = lastLanguage.value?.ambiguous?.[0];
            const unk = lastLanguage.value?.unknown_tokens?.[0];
            coachLanguage.value.from = (amb || unk || lastTrace.value?.input || "").trim().slice(0, 80);
        }
        if (force || !coachLanguage.value.to.trim()) {
            coachLanguage.value.to = "দাম কত";
        }
    }
};

const onCoachCategoryChange = (value: CoachProposal["category"]) => {
    coachCategory.value = value;
    if (coachProposal.value) {
        coachProposal.value = { ...coachProposal.value, category: value };
    }
    seedCoachFieldsForCategory(value);
};

const resetCoach = () => {
    coachProposal.value = null;
    coachCategory.value = "noop";
    coachDone.value = false;
    coachResult.value = null;
    coachKnowledge.value = { title: "", question: "", answer: "", keywords: [] };
    coachLanguage.value = { type: "abbrev", from: "", to: "" };
};
const lastTrace = ref<DecisionTrace | null>(null);
const lastLanguage = ref<LanguageSnap | null>(null);
const lastEvidence = ref<EvidenceSnap | null>(null);
const explain = ref<ExplainPayload | null>(null);
const explainCorpusPacks = computed(
    () => explain.value?.sealed?.language_corpus_snapshot?.packs ?? [],
);
const replayOpen = ref(false);
const replayTurnId = ref<number | null>(null);
const openReplay = (turnId: number) => {
    replayTurnId.value = turnId;
    replayOpen.value = true;
};
const threadEl = ref<HTMLElement | null>(null);
const conversationId = `playground-${Date.now()}`;
const showAdvancedContext = ref(false);
const decideChannel = ref("playground");
const ctxProductId = ref("");
const ctxOfferKind = ref<string | null>(null);
const ctxPlatform = ref("");
const ctxProductName = ref("");
const ctxVoiceProfile = ref(false);
const activeTab = ref<"decision" | "language" | "explain" | "memory" | "knowledge">("decision");
const probeText = ref("");
const probeLoading = ref(false);
const probeResult = ref<{
    intent: string;
    action: string;
    source: string;
    reply: string | null;
    evidence_title: string | null;
    score: number | null;
    gap: boolean;
} | null>(null);
let nextId = 1;

const suiteTabs = [
    { id: "decision" as const, label: "Decision", short: "Decide", icon: "PhScales" },
    { id: "language" as const, label: "Language", short: "Lang", icon: "PhTranslate" },
    { id: "explain" as const, label: "Explain", short: "Explain", icon: "PhPath" },
    { id: "memory" as const, label: "Memory", short: "Memory", icon: "PhBrain" },
    { id: "knowledge" as const, label: "Knowledge", short: "Know", icon: "PhBookOpen" },
];

const activeContextCount = computed(() => {
    let n = 0;
    if (decideChannel.value !== "playground") n++;
    if (ctxProductId.value.trim()) n++;
    if (ctxOfferKind.value) n++;
    if (ctxPlatform.value.trim()) n++;
    if (ctxProductName.value.trim()) n++;
    if (ctxVoiceProfile.value) n++;
    return n;
});

const buildContext = (): Record<string, string> => {
    const context: Record<string, string> = {};
    if (ctxProductId.value.trim()) context.product_id = ctxProductId.value.trim();
    if (ctxOfferKind.value) context.offer_kind = ctxOfferKind.value;
    if (ctxPlatform.value.trim()) context.platform = ctxPlatform.value.trim();
    if (ctxProductName.value.trim()) context.product_name = ctxProductName.value.trim();
    if (ctxVoiceProfile.value) context.output_profile = "voice";
    return context;
};

const keyPreview = computed(() => `${apiKey.value.slice(0, 13)}…`);

const canTeach = computed(
    () =>
        Boolean(lastTrace.value?.turn_id) &&
        teachReply.value.trim().length > 0 &&
        !teachDone.value &&
        can_edit.value,
);

const refreshKeyMeta = async () => {
    if (!apiKey.value) {
        keyMeta.value = null;
        return;
    }
    try {
        const { data } = await axios.get("/api/wise/v1/ping", {
            headers: { Authorization: `Bearer ${apiKey.value}` },
        });
        keyMeta.value = data?.key?.id
            ? { id: Number(data.key.id), name: String(data.key.name || "") }
            : null;
    } catch {
        keyMeta.value = null;
    }
};

const saveKey = async () => {
    const key = keyDraft.value.trim();
    if (!key) return;
    apiKey.value = key;
    localStorage.setItem(STORAGE_KEY, key);
    keyDraft.value = "";
    await refreshKeyMeta();
};

const disconnectKey = () => {
    apiKey.value = "";
    keyMeta.value = null;
    localStorage.removeItem(STORAGE_KEY);
    resetConversation();
};

const scrollToBottom = async () => {
    await nextTick();
    threadEl.value?.scrollTo({ top: threadEl.value.scrollHeight, behavior: "smooth" });
};

const useQuickStart = async (text: string) => {
    if (sending.value) return;
    draft.value = text;
    await nextTick();
    await sendMessage();
};

const sendMessage = async () => {
    const text = draft.value.trim();
    if (!text || sending.value) return;

    messages.value.push({ id: nextId++, role: "customer", text });
    draft.value = "";
    sending.value = true;
    await scrollToBottom();

    try {
        const context = buildContext();

        const { data } = await axios.post(
            "/api/wise/v1/decide",
            {
                text,
                channel: decideChannel.value || "playground",
                conversation_id: conversationId,
                ...(Object.keys(context).length ? { context } : {}),
            },
            {
                headers: { Authorization: `Bearer ${apiKey.value}` },
            },
        );

        const decision = data.decision;

        const lang = decision.language || {};
        const rules = Array.isArray(lang.rules_applied)
            ? lang.rules_applied.map((r: { from: string; to: string }) => `${r.from}→${r.to}`).join(" · ")
            : null;

        lastLanguage.value = {
            unknown_tokens: lang.unknown_tokens || [],
            ambiguous: lang.ambiguous || [],
            dict_version: lang.dict_version || null,
        };
        lastEvidence.value = data.evidence || decision.evidence || null;
        feedbackOutcome.value = null;
        teachDone.value = false;
        teachDraftItemId.value = null;
        teachResult.value = null;
        teachReply.value = (decision.suggested_reply || "").trim();
        resetCoach();

        lastTrace.value = {
            input: text,
            canonical: lang.canonical || null,
            dict_version: lang.dict_version || null,
            language_rules: rules,
            intent: decision.intent,
            confidence: decision.confidence,
            action: decision.action,
            source: decision.source,
            latency_ms: data.latency_ms,
            turn_id: data.turn_id,
            brain_version: decision.brain_version,
            gap: Boolean(data.gap ?? decision.gap),
            memory_used: Boolean(decision.memory_used),
            missing_context: decision.missing_context || null,
            product_subject: decision.product_subject?.title || null,
            voice: decision.voice || null,
            psych: decision.psych || null,
            opportunities: Array.isArray(decision.opportunities?.items)
                ? decision.opportunities.items
                : [],
        };
        activeTab.value = "decision";

        try {
            const { data: expl } = await axios.get(`/api/wise/v1/turns/${data.turn_id}/explain`, {
                headers: { Authorization: `Bearer ${apiKey.value}` },
            });
            explain.value = expl.explain || null;
        } catch {
            explain.value = null;
        }

        const replyText = decision.suggested_reply
            || (decision.gap
                ? `Knowledge gap — intent “${decision.intent}”. Publish knowledge, then try again.`
                : `No suggested reply — intent “${decision.intent}” → ${decision.action}.`);

        messages.value.push({
            id: nextId++,
            role: "brain",
            text: replyText,
            meta: `${decision.intent} · ${decision.confidence}% · ${decision.source}`,
            turn_id: data.turn_id,
        });
    } catch (error: unknown) {
        const status = axios.isAxiosError(error) ? error.response?.status : null;

        if (status === 401) {
            toast.add({
                severity: "error",
                summary: "Invalid API key",
                detail: "The key was rejected — it may be revoked. Reconnect with a valid key.",
                life: 4500,
                group: "br",
            });
            disconnectKey();
        } else {
            messages.value.push({
                id: nextId++,
                role: "brain",
                text: "Request failed — check that the hub API is reachable and try again.",
            });
        }
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
};

const startTeachFromMessage = (msg: PlaygroundMessage) => {
    teachReply.value = msg.text;
    teachDone.value = false;
    teachDraftItemId.value = null;
    teachResult.value = null;
    activeTab.value = "decision";
    toast.add({
        severity: "info",
        summary: "Teach panel খুলুন",
        detail: "ডান পাশে সঠিক উত্তর লিখে Save & Publish চাপুন — draft alone decide-এ লাগে না।",
        life: 3200,
        group: "br",
    });
};

const runCoach = async () => {
    if (!lastTrace.value?.turn_id || coachLoading.value || !can_edit.value) return;
    if (!llm_ready.value) {
        toast.add({
            severity: "warn",
            summary: "LLM not ready",
            detail: "Config → LLM Language enable + API key লাগবে।",
            life: 4000,
            group: "br",
        });
        return;
    }
    coachLoading.value = true;
    coachDone.value = false;
    coachResult.value = null;
    try {
        const { data } = await axios.post(route("wiseAi.playground.coach"), {
            turn_id: lastTrace.value.turn_id,
            messages: messages.value.map((m) => ({ role: m.role, text: m.text })),
        });
        const p = data?.proposal as CoachProposal | undefined;
        if (!p?.category) {
            throw new Error("Empty coach proposal");
        }
        coachProposal.value = p;
        coachCategory.value = p.category;
        coachKnowledge.value = {
            title: p.knowledge?.title || "",
            question: p.knowledge?.question || "",
            answer: p.knowledge?.answer || "",
            keywords: Array.isArray(p.knowledge?.keywords) ? [...p.knowledge.keywords] : [],
        };
        coachLanguage.value = {
            type: p.language?.type || "abbrev",
            from: p.language?.from || "",
            to: p.language?.to || "",
        };
        // Anchor FAQ to customer utterance + ensure answer is never blank for Publish.
        seedCoachFieldsForCategory(p.category, true);
    } catch (e: unknown) {
        const msg =
            typeof e === "object" && e && "response" in e
                ? (e as { response?: { data?: { message?: string } } }).response?.data?.message
                : e instanceof Error
                  ? e.message
                  : null;
        toast.add({
            severity: "error",
            summary: msg || "Coach failed",
            life: 4500,
            group: "br",
        });
    } finally {
        coachLoading.value = false;
    }
};

const dismissCoach = async () => {
    if (coachCategory.value === "noop" && lastTrace.value?.turn_id && keyMeta.value?.id) {
        try {
            await axios.post(route("wiseAi.playground.coachApply"), {
                turn_id: lastTrace.value.turn_id,
                wise_api_key_id: keyMeta.value.id,
                category: "noop",
                publish_now: false,
            });
        } catch {
            // client dismiss is enough
        }
    }
    resetCoach();
};

const applyCoach = async (publishNow: boolean) => {
    if (!lastTrace.value?.turn_id || !coachProposal.value || coachApplying.value || coachDone.value) return;
    if (!can_edit.value) return;
    if (publishNow && !can_publish.value) {
        toast.add({
            severity: "warn",
            summary: "Publisher role required",
            life: 3000,
            group: "br",
        });
        return;
    }

    const category = coachCategory.value;
    if (category === "noop") {
        await dismissCoach();
        return;
    }

    if (category === "knowledge_faq" && !coachKnowledge.value.answer.trim()) {
        toast.add({
            severity: "warn",
            summary: "Answer লাগবে",
            detail: "FAQ publish করতে উত্তর লিখুন।",
            life: 3000,
            group: "br",
        });
        return;
    }
    if (category === "knowledge_faq" && feeInvented(coachKnowledge.value.answer)) {
        toast.add({
            severity: "warn",
            summary: "Invented fee blocked",
            detail: "digit / টাকা / phone / % সরিয়ে নিন — Evidence First।",
            life: 4000,
            group: "br",
        });
        return;
    }
    if (category === "language_abbrev" && feeInvented(coachLanguage.value.to)) {
        toast.add({
            severity: "warn",
            summary: "Invented fee blocked",
            detail: "language expansion-এ fee/phone/% রাখবেন না।",
            life: 4000,
            group: "br",
        });
        return;
    }

    if (!keyMeta.value?.id) {
        await refreshKeyMeta();
    }
    if (!keyMeta.value?.id) {
        toast.add({
            severity: "error",
            summary: "API key id missing — reconnect key",
            life: 3500,
            group: "br",
        });
        return;
    }

    coachApplying.value = true;
    coachPublishing.value = publishNow;
    try {
        const { data } = await axios.post(route("wiseAi.playground.coachApply"), {
            turn_id: lastTrace.value.turn_id,
            wise_api_key_id: keyMeta.value.id,
            category,
            publish_now: publishNow,
            knowledge: coachKnowledge.value,
            language: coachLanguage.value,
        });

        coachDone.value = publishNow;
        const turnId = lastTrace.value.turn_id;

        if (category === "language_abbrev") {
            coachResult.value = publishNow
                ? `Language map published: ${coachLanguage.value.from} → ${coachLanguage.value.to} — আবার পাঠিয়ে দেখুন।`
                : `Language draft সেভ: ${coachLanguage.value.from} → ${coachLanguage.value.to} — নিচে Approve & Publish চাপুন।`;
            messages.value = messages.value.map((m) =>
                m.turn_id === turnId
                    ? {
                          ...m,
                          taught: true,
                          taughtPublished: publishNow,
                          meta: `${m.meta || ""} · coach · abbrev${publishNow ? " · published" : " · draft"}`,
                      }
                    : m,
            );
        } else {
            coachResult.value = publishNow
                ? "FAQ published — একই প্রশ্ন আবার পাঠিয়ে দেখুন।"
                : "FAQ draft সেভ — নিচে Approve & Publish চাপুন (Teach Publish draft ব্যবহার করবেন না)।";
            const answer = coachKnowledge.value.answer;
            messages.value = messages.value.map((m) =>
                m.turn_id === turnId
                    ? {
                          ...m,
                          text: answer || m.text,
                          taught: true,
                          taughtPublished: publishNow,
                          meta: `${m.meta || ""} · coach · faq${publishNow ? " · published" : " · draft"}`,
                      }
                    : m,
            );
            // Do not set teachDraftItemId — Teach "Publish draft" would overwrite with teachReply (old assist).
            if (publishNow) {
                teachDone.value = true;
            }
            teachDraftItemId.value = null;
            teachReply.value = answer || teachReply.value;
        }

        toast.add({
            severity: "success",
            summary: publishNow ? "Coach approved & published" : "Coach draft saved",
            life: 3000,
            group: "br",
        });
    } catch (e: unknown) {
        const msg =
            typeof e === "object" && e && "response" in e
                ? (e as { response?: { data?: { message?: string } } }).response?.data?.message
                : e instanceof Error
                  ? e.message
                  : null;
        toast.add({
            severity: "error",
            summary: msg || "Coach apply failed",
            life: 4500,
            group: "br",
        });
    } finally {
        coachApplying.value = false;
        coachPublishing.value = false;
    }
};

/** Keep Banglish surface forms (koto) in keywords when question is stored as canonical (কত). */
const teachAliasKeywords = (raw: string | null | undefined, canonical: string | null | undefined): string[] => {
    const out: string[] = [];
    const rawNorm = (raw || "").toLowerCase();
    const canNorm = (canonical || "").toLowerCase();
    if (/\bkoto\b/u.test(rawNorm) && !/\bkoto\b/u.test(canNorm)) {
        out.push("koto");
    }
    return out;
};

const teachToBrain = async (publishNow: boolean) => {
    if (!lastTrace.value?.turn_id || !canTeach.value || teachLoading.value) return;
    if (!publishNow && teachDraftItemId.value !== null) return;
    const answer = teachReply.value.trim();
    if (feeInvented(answer)) {
        toast.add({
            severity: "warn",
            summary: "Invented fee blocked",
            detail: "digit + টাকা/tk সরিয়ে নিন — Evidence First।",
            life: 4000,
            group: "br",
        });
        return;
    }

    teachLoading.value = true;
    teachPublishing.value = publishNow;
    try {
        // Prefer language canonical so Banglish (koto) and Bangla (কত) share match_text.
        const question =
            (lastTrace.value.canonical || lastTrace.value.input || "Playground FAQ").slice(0, 2000);
        const title = question.slice(0, 80);
        const keywords = [
            ...(lastTrace.value.intent ? [lastTrace.value.intent] : []),
            ...teachAliasKeywords(lastTrace.value.input, lastTrace.value.canonical),
        ];

        let itemId = teachDraftItemId.value;

        if (itemId !== null && publishNow) {
            // Draft already closed the gap turn — only update + publish the item.
            await axios.post(route("wiseAi.knowledge.update", { item: itemId }), {
                answer,
                title,
                question,
                keywords,
            });
            await axios.post(route("wiseAi.knowledge.publish", { item: itemId }));
        } else if (lastTrace.value.gap) {
            const { data } = await axios.post(route("wiseAi.gaps.draft", { turn: lastTrace.value.turn_id }), {
                type: "faq",
                scope: "merchant",
                title,
                question,
                answer,
                keywords,
                publish_now: publishNow,
            });
            itemId = Number(data?.item?.id) || null;
        } else {
            if (!keyMeta.value?.id) {
                await refreshKeyMeta();
            }
            if (!keyMeta.value?.id) {
                throw new Error("API key id missing — reconnect key, then retry.");
            }
            const { data } = await axios.post(route("wiseAi.knowledge.store"), {
                wise_api_key_id: keyMeta.value.id,
                type: "faq",
                scope: "merchant",
                title,
                question,
                answer,
                keywords,
            });
            itemId = Number(data?.item?.id) || null;
            if (publishNow && itemId) {
                await axios.post(route("wiseAi.knowledge.publish", { item: itemId }));
            }
        }

        // Experience signal: edited assist (does not invent knowledge by itself).
        try {
            await axios.post(
                "/api/wise/v1/feedback",
                {
                    turn_id: lastTrace.value.turn_id,
                    outcome: "edited",
                    reason_code: "playground_approve",
                    edited_reply: answer,
                },
                { headers: { Authorization: `Bearer ${apiKey.value}` } },
            );
            feedbackOutcome.value = "edited";
        } catch {
            // Knowledge save already succeeded — feedback is optional soft signal.
        }

        const turnId = lastTrace.value.turn_id;

        if (publishNow) {
            teachDone.value = true;
            teachDraftItemId.value = null;
            teachResult.value = "Published to Knowledge — একই প্রশ্ন আবার পাঠিয়ে দেখুন।";
            messages.value = messages.value.map((m) =>
                m.turn_id === turnId
                    ? {
                          ...m,
                          text: answer,
                          taught: true,
                          taughtPublished: true,
                          meta: `${m.meta || ""} · taught · published`,
                      }
                    : m,
            );
            toast.add({
                severity: "success",
                summary: "Saved & published",
                life: 3000,
                group: "br",
            });
        } else {
            teachDraftItemId.value = itemId;
            teachDone.value = false;
            teachResult.value = itemId
                ? "Draft সেভ হয়েছে — নিচে Publish draft চাপুন (তাছাড়া decide আবার Gap দেখাবে)।"
                : "Knowledge draft সেভ — Publish করে লাইভ করুন।";
            messages.value = messages.value.map((m) =>
                m.turn_id === turnId
                    ? {
                          ...m,
                          text: answer,
                          taught: true,
                          taughtPublished: false,
                          meta: `${m.meta || ""} · taught · draft`,
                      }
                    : m,
            );
            toast.add({
                severity: "success",
                summary: "Saved as knowledge draft",
                detail: "Publish draft চাপুন লাইভ করতে",
                life: 3500,
                group: "br",
            });
        }
    } catch (e: unknown) {
        const msg =
            typeof e === "object" && e && "response" in e
                ? (e as { response?: { data?: { message?: string } } }).response?.data?.message
                : e instanceof Error
                  ? e.message
                  : null;
        toast.add({
            severity: "error",
            summary: msg || "Teach brain failed",
            life: 4500,
            group: "br",
        });
    } finally {
        teachLoading.value = false;
        teachPublishing.value = false;
    }
};

const sendFeedback = async (outcome: "approved" | "rejected") => {
    if (!lastTrace.value || !apiKey.value || feedbackLoading.value || feedbackOutcome.value) return;
    if (!lastTrace.value.turn_id) {
        toast.add({ severity: "warn", summary: "No turn to review yet", life: 2500, group: "br" });
        return;
    }
    if (outcome === "rejected" && !rejectReason.value) {
        toast.add({ severity: "warn", summary: "Pick a reject reason", life: 2500, group: "br" });
        return;
    }
    feedbackLoading.value = true;
    try {
        await axios.post(
            "/api/wise/v1/feedback",
            {
                turn_id: lastTrace.value.turn_id,
                outcome,
                reason_code: outcome === "rejected" ? rejectReason.value : "playground_approve",
            },
            { headers: { Authorization: `Bearer ${apiKey.value}` } },
        );
        feedbackOutcome.value = outcome;
        toast.add({
            severity: "success",
            summary: outcome === "approved" ? "Feedback: approved" : `Rejected · ${rejectReason.value}`,
            life: 2500,
            group: "br",
        });
    } catch (e: unknown) {
        const msg =
            typeof e === "object" && e && "response" in e
                ? (e as { response?: { data?: { message?: string; error?: string } } }).response?.data
                      ?.message ||
                  (e as { response?: { data?: { error?: string } } }).response?.data?.error
                : null;
        toast.add({
            severity: "error",
            summary: msg || "Feedback failed — check API key owns this turn",
            life: 4000,
            group: "br",
        });
    } finally {
        feedbackLoading.value = false;
    }
};

const runKnowledgeProbe = async () => {
    const text = probeText.value.trim();
    if (!text || !apiKey.value || probeLoading.value) return;
    probeLoading.value = true;
    probeResult.value = null;
    try {
        const context = buildContext();
        const { data } = await axios.post(
            "/api/wise/v1/decide",
            {
                text,
                channel: "playground_probe",
                conversation_id: `probe-${Date.now()}`,
                ...(Object.keys(context).length ? { context } : {}),
            },
            { headers: { Authorization: `Bearer ${apiKey.value}` } },
        );
        const decision = data.decision || {};
        const evidence = data.evidence || {};
        probeResult.value = {
            intent: decision.intent || "unknown",
            action: decision.action || "—",
            source: decision.source || "—",
            reply: decision.suggested_reply || null,
            evidence_title: evidence.title || null,
            score: evidence.match_score ?? null,
            gap: Boolean(data.gap ?? decision.gap),
        };
    } catch {
        toast.add({ severity: "error", summary: "Probe failed", life: 3000, group: "br" });
    } finally {
        probeLoading.value = false;
    }
};

const resetConversation = () => {
    messages.value = [];
    lastTrace.value = null;
    feedbackOutcome.value = null;
    teachReply.value = "";
    teachDone.value = false;
    teachDraftItemId.value = null;
    teachResult.value = null;
    resetCoach();
    lastLanguage.value = null;
    lastEvidence.value = null;
    explain.value = null;
    probeResult.value = null;
    draft.value = "";
    activeTab.value = "decision";
};

const actionVariant = (action: string) => {
    if (action === "needs_human") return "warning";
    if (action === "clarify") return "info";
    return "success";
};

const actionChipClass = (action: string) => {
    if (action === "needs_human") {
        return "bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300";
    }
    if (action === "clarify") {
        return "bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300";
    }
    return "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300";
};

onMounted(() => {
    void refreshKeyMeta();
});
</script>
