<?php

namespace App\Verso\Seo\Audit;

// One check of the SEO overview. Core and plugins add checks to the SeoCheckRegistry.
interface SeoCheck
{
    // Short id used in URLs, like "missing-description".
    public function key(): string;

    // What is wrong, in plain words, like "Pages without a meta description".
    public function label(): string;

    // Why it matters and how to fix it, in one or two sentences.
    public function description(): string;

    public function severity(): Severity;

    public function run(AuditContext $context): CheckResult;

    // The main way to fix the issue, or null when each item is fixed on its own.
    public function fix(): ?IssueFix;
}
