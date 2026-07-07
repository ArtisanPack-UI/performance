/**
 * Barrel export for the Performance package's React companion components.
 *
 * @since 1.0.0
 */

export { LazyImage } from './LazyImage';
export type { LazyImageFetchPriority, LazyImagePlaceholder, LazyImageProps } from './LazyImage';

export { ResponsiveImage } from './ResponsiveImage';
export type { ResponsiveImageProps } from './ResponsiveImage';

export { PerfEmbed } from './PerfEmbed';
export type { PerfEmbedFacade, PerfEmbedMode, PerfEmbedProps, PerfEmbedProvider } from './PerfEmbed';

export { PerfPrefetch } from './PerfPrefetch';
export type { PerfPrefetchProps } from './PerfPrefetch';

export { SpeculativeRules } from './SpeculativeRules';
export type { SpeculationRules, SpeculativeRulesProps } from './SpeculativeRules';

export { PerformanceDashboard } from './PerformanceDashboard';
export type { PerformanceDashboardProps, PerformanceDashboardTab } from './PerformanceDashboard';

export { MetricsChart } from './MetricsChart';
export type { MetricsChartProps } from './MetricsChart';

export { CacheManager } from './CacheManager';
export type { CacheManagerProps } from './CacheManager';

export { QueryAnalyzer } from './QueryAnalyzer';
export type { QueryAnalyzerProps } from './QueryAnalyzer';

export { RecommendationsPanel } from './RecommendationsPanel';
export type { RecommendationsPanelProps } from './RecommendationsPanel';

export { QueryInsightPanel } from './QueryInsightPanel';
export type { QueryInsightPanelProps } from './QueryInsightPanel';

export { OptimizationSuggestionPanel } from './OptimizationSuggestionPanel';
export type { OptimizationSuggestionPanelProps } from './OptimizationSuggestionPanel';

export { usePerformance } from './usePerformance';
export type { UsePerformanceOptions, UsePerformanceResult } from './usePerformance';

export type {
	AiAgentResponse,
	AiFeatureKey,
	CacheActionResult,
	CachePayload,
	CacheSummary,
	ChartDataset,
	ChartPayload,
	ChartRangeKey,
	DashboardPayload,
	DateRangeKey,
	FragmentTag,
	OptimizationFocusArea,
	OptimizationLevel,
	OptimizationMetricRow,
	OptimizationSuggestion,
	OptimizationSuggestionInput,
	OverviewRow,
	PageEntry,
	PageRow,
	PerformanceClient,
	PerformanceClientOptions,
	QueriesPayload,
	QueriesQuery,
	QueryInsight,
	QueryInsightInput,
	QueryRewrite,
	QuerySortKey,
	Recommendation,
	RecommendationActionResult,
	RecommendationsPayload,
	SlowQueryRow,
	SuggestedIndex,
	WebVitalName,
	WebVitalReport,
	WebVitalStatus,
} from '../performance';

export { PerformanceAiError } from '../performance';
