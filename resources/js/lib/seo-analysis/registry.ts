import { READABILITY_CHECKS } from './checks/readability';
import { SEO_CHECKS } from './checks/seo';
import type { AnalysisCheck } from './types';

const CORE_CHECKS: AnalysisCheck[] = [...SEO_CHECKS, ...READABILITY_CHECKS];

const checks = new Map<string, AnalysisCheck>();

// Adds a check. A check with the same id is replaced in place.
export function registerAnalysisCheck(check: AnalysisCheck): void {
    checks.set(check.id, check);
}

export function unregisterAnalysisCheck(id: string): void {
    checks.delete(id);
}

// All checks in the order they run.
export function analysisChecks(): AnalysisCheck[] {
    return [...checks.values()];
}

// Puts back only the core checks.
export function resetAnalysisChecks(): void {
    checks.clear();
    CORE_CHECKS.forEach(registerAnalysisCheck);
}

resetAnalysisChecks();
