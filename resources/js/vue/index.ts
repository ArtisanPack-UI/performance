/**
 * Barrel export for the Performance package's Vue companion components.
 *
 * @since 1.0.0
 */

export { default as LazyImage } from './LazyImage.vue';
export type { LazyImageFetchPriority, LazyImagePlaceholder } from './LazyImage.vue';

export { default as ResponsiveImage } from './ResponsiveImage.vue';

export { default as PerfEmbed } from './PerfEmbed.vue';
export type { PerfEmbedFacade, PerfEmbedMode, PerfEmbedProvider } from './PerfEmbed.vue';

export { default as PerfPrefetch } from './PerfPrefetch.vue';

export { default as SpeculativeRules } from './SpeculativeRules.vue';

export { default as PerformanceDashboard } from './PerformanceDashboard.vue';
export type { PerformanceDashboardTab } from './PerformanceDashboard.vue';

export { default as MetricsChart } from './MetricsChart.vue';

export { default as CacheManager } from './CacheManager.vue';

export { default as QueryAnalyzer } from './QueryAnalyzer.vue';

export { default as RecommendationsPanel } from './RecommendationsPanel.vue';

export { usePerformance } from './usePerformance';
export type { UsePerformanceOptions, UsePerformanceResult } from './usePerformance';

export type {
	CacheActionResult,
	CachePayload,
	CacheSummary,
	ChartDataset,
	ChartPayload,
	ChartRangeKey,
	DashboardPayload,
	DateRangeKey,
	FragmentTag,
	OverviewRow,
	PageEntry,
	PageRow,
	PerformanceClient,
	PerformanceClientOptions,
	QueriesPayload,
	QueriesQuery,
	QuerySortKey,
	Recommendation,
	RecommendationActionResult,
	RecommendationsPayload,
	SlowQueryRow,
	WebVitalName,
	WebVitalReport,
	WebVitalStatus,
} from '../performance';
