<template>
    <card class="card relative" :style="cardStyles">
        <div class="p-0">
            <div class="bg-black px-8 py-4 rounded-t-lg">
                <h3 class="leading-tight text-base font-bold text-white text-center">{{ title }}</h3>
            </div>
            <div class="h-full flex flex-col">
                <div class="p-4">
                    <div class="w-full grid grid-cols-4 gap-1">
                        <button
                            v-for="period in periods"
                            :key="`switch-${period.key}`"
                            type="button"
                            class="w-full px-1.5 py-1.5 text-[11px] leading-none font-semibold rounded-md border-2 transition-colors text-center"
                            :class="period.key === selectedPeriod
                                ? 'bg-white text-gray-900'
                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100'"
                            :style="period.key === selectedPeriod ? activeTabStyle : null"
                            @click="selectedPeriod = period.key"
                        >
                            {{ period.label }}
                        </button>
                    </div>
                </div>

                <div v-if="activePeriod" class="flex-1 flex flex-col">
                    <div class="px-8 py-3 text-center" :style="totalsBlockStyle">
                        <div class="text-xs uppercase tracking-wide text-gray-500 font-bold">{{ totalLabel }}</div>
                        <div class="mt-1 text-3xl font-bold text-gray-900 leading-tight">{{ formatNumber(activePeriod.total || 0) }}</div>
                        <div class="mt-2 text-sm text-gray-500">{{ secondaryLine(activePeriod) }}</div>
                    </div>

                    <div class="px-8 pt-6 pb-8">
                        <ul class="space-y-2">
                            <li
                                v-for="(writer, index) in activePeriod.items"
                                :key="`${activePeriod.key}-${writer.id}`"
                                class="flex items-center justify-between border-b border-gray-100 pb-2"
                            >
                                <div class="flex items-center space-x-1">
                                    <span class="text-sm font-bold text-gray-900 w-5">{{ index + 1 }}</span>
                                    <span class="text-base font-medium text-gray-800">{{ writer.name }}</span>
                                </div>
                                <span class="text-xs font-semibold text-gray-700 tabular-nums">{{ writerStatsLine(writer) }}</span>
                            </li>
                            <li v-if="activePeriod.items.length === 0" class="text-sm text-gray-500">
                                {{ emptyLabel }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </card>
</template>

<script>
export default {
    props: {
        card: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            selectedPeriod: null,
            activeTabStyle: {
                borderColor: '#e54839',
                color: '#111827',
            },
            totalsBlockStyle: {
                borderTop: '1px solid #d1d5db',
                borderBottom: '1px solid #d1d5db',
            },
        };
    },
    created() {
        const fallbackPeriod = this.periods[0]?.key || null;
        this.selectedPeriod = this.card.defaultPeriod || fallbackPeriod;
    },
    computed: {
        cardStyles() {
            if (!this.card.isTallCard) {
                return null;
            }

            return {
                gridRow: 'span 4 / span 4',
                minHeight: '44rem',
            };
        },
        title() {
            return this.card.title;
        },
        totalLabel() {
            return this.card.totalLabel || '';
        },
        emptyLabel() {
            return this.card.emptyLabel || 'No data';
        },
        periods() {
            return this.card.periods || [];
        },
        activePeriod() {
            return this.periods.find((period) => period.key === this.selectedPeriod) || this.periods[0] || null;
        }
    },
    methods: {
        formatNumber(value) {
            const locale = this.card.locale || 'ru-RU';

            try {
                return new Intl.NumberFormat(locale).format(Number(value) || 0);
            } catch (e) {
                return String(value ?? 0);
            }
        },
        /**
         * ≥ 1 000 000 → 1M, 1.5M, 10M (Latin M).
         * ≥ 1 000 → 1K, 1.5K, 10K (Latin K).
         */
        formatCompact(value) {
            const n = Math.floor(Number(value) || 0);
            if (n < 1000) {
                return this.formatNumber(n);
            }
            if (n >= 1_000_000) {
                const m = n / 1_000_000;
                const s = m < 10
                    ? m.toFixed(1).replace(/\.0$/, '')
                    : String(Math.round(m));

                return `${s}M`;
            }
            const k = n / 1000;
            const s = k < 10
                ? k.toFixed(1).replace(/\.0$/, '')
                : String(Math.round(k));

            return `${s}K`;
        },
        secondaryLine(period) {
            const suffix = this.card.secondaryLineSuffix || '';
            const value = this.formatNumber(period?.secondary_total || 0);

            return `${value} ${suffix}`.trim();
        },
        writerStatsLine(writer) {
            const posts = writer.posts_count ?? writer.value ?? 0;
            const views = writer.views_count ?? 0;

            return `${this.formatCompact(posts)} • ${this.formatCompact(views)}`;
        }
    }
}
</script>
