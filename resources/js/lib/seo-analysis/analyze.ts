import { t } from '@/lib/i18n';
import { DEFAULT_ANALYSIS_CONFIG, ratingOf } from './config';
import { createKeyphraseMatcher } from './keyphrase';
import { languageOf } from './languages';
import { parseBlocks } from './parse';
import { analysisChecks } from './registry';
import type {
    AnalysisCheck,
    AnalysisConfig,
    AnalysisContext,
    AnalysisInput,
    AnalysisResult,
    CheckGroup,
    CheckResult,
    CheckStatus,
} from './types';

const STATUS_VALUE: Record<CheckStatus, number> = {
    good: 1,
    ok: 0.5,
    problem: 0,
};

const STATUS_ORDER: Record<CheckStatus, number> = {
    problem: 0,
    ok: 1,
    good: 2,
};

type ScoredCheck = CheckResult & { weight: number };

function shouldRun(check: AnalysisCheck, context: AnalysisContext): boolean {
    if (check.needsKeyphrase && context.keyphrase.isEmpty) {
        return false;
    }

    return !check.languages || check.languages.includes(context.language);
}

// Runs one check. A broken plugin check is skipped, so the editor keeps working.
function runCheck(
    check: AnalysisCheck,
    context: AnalysisContext,
): ScoredCheck | null {
    try {
        const outcome = check.run(context);

        if (outcome === null) {
            return null;
        }

        return {
            id: check.id,
            group: check.group,
            label: t(check.label),
            status: outcome.status,
            message: outcome.message,
            weight: Math.max(0, check.weight ?? 1),
        };
    } catch {
        return null;
    }
}

// Weighted score of one group, or null when the group has no checks.
function groupScore(results: ScoredCheck[], group: CheckGroup): number | null {
    let total = 0;
    let earned = 0;

    for (const result of results) {
        if (result.group === group) {
            total += result.weight;
            earned += result.weight * STATUS_VALUE[result.status];
        }
    }

    return total === 0 ? null : Math.round((earned / total) * 100);
}

function withoutWeight({
    weight: _weight,
    ...result
}: ScoredCheck): CheckResult {
    return result;
}

// Runs every registered check on an entry and returns the scores.
export function analyze(
    input: AnalysisInput,
    config: Partial<AnalysisConfig> = {},
): AnalysisResult {
    const fullConfig = { ...DEFAULT_ANALYSIS_CONFIG, ...config };
    const language = languageOf(input.locale);
    const context: AnalysisContext = {
        input,
        config: fullConfig,
        language,
        content: parseBlocks(input.blocks, input.siteHost),
        keyphrase: createKeyphraseMatcher(input.keyphrase, language),
    };

    const results = analysisChecks()
        .filter((check) => shouldRun(check, context))
        .map((check) => runCheck(check, context))
        .filter((result): result is ScoredCheck => result !== null)
        .map((result, index) => ({ result, index }))
        .sort(
            (a, b) =>
                STATUS_ORDER[a.result.status] - STATUS_ORDER[b.result.status] ||
                a.index - b.index,
        )
        .map(({ result }) => result);

    const seoScore = groupScore(results, 'seo');
    const readabilityScore = groupScore(results, 'readability');
    const scores = [seoScore, readabilityScore].filter(
        (score): score is number => score !== null,
    );
    const score =
        scores.length === 0
            ? 0
            : Math.round(
                  scores.reduce((sum, value) => sum + value, 0) / scores.length,
              );
    const wordCount = context.content.words.length;
    const isBlank = wordCount === 0 && input.title.trim() === '';

    return {
        checks: results.map(withoutWeight),
        seoScore: seoScore ?? 0,
        readabilityScore: readabilityScore ?? 0,
        score,
        rating: isBlank ? 'none' : ratingOf(score, fullConfig),
        language,
        wordCount,
    };
}
