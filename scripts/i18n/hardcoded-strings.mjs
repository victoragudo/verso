// Finds English text in admin TSX that is not passed through t() or msg().
// Run: node scripts/i18n/hardcoded-strings.mjs [paths...]
// The Vitest test resources/js/tests/i18n/hardcoded-strings.test.ts runs it in CI.
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import ts from 'typescript';

// The project root: two folders up from this script, or the current folder.
const ROOT = import.meta.dirname
    ? join(import.meta.dirname, '..', '..')
    : process.cwd();
const ALLOW_FILE = join(ROOT, 'scripts/i18n/hardcoded-allowlist.json');

// Folders with admin code. Generated route files and tests are skipped.
export const SCAN_ROOTS = ['resources/js', 'plugins'];
const SKIP_PARTS = [
    '/tests/',
    '/routes/',
    '/actions/',
    '/wayfinder/',
    '/types/',
    '/node_modules/',
    '/vendor/',
];

// JSX attributes and object keys that people read or hear.
const TEXT_ATTRIBUTE =
    /^(aria-label|aria-description|aria-roledescription|aria-valuetext|alt|title|placeholder|label|description|hint|help|heading|subtitle|caption|tooltip|emptyText|emptyMessage|emptyTitle|emptyDescription|searchPlaceholder|submitLabel|confirmLabel|cancelLabel|actionLabel|buttonLabel|message|text|summary|badge|legend|prefix|suffix|unitLabel|ariaLabel|srLabel|noResults|loadingText|successMessage|errorMessage)$/;

// Calls whose first argument is shown to people.
const TEXT_CALLS =
    /^(toast(\.(success|error|info|warning|message|loading))?|confirm|window\.confirm|alert|window\.alert|setError|setStatus|setMessage|announce)$/;

// Short codes, units and names that stay the same in every language.
const CODE_LIKE = [
    /^[^\p{L}]*$/u,
    /^[^\p{Ll}]{1,4}$/u,
    /^[A-Z0-9][A-Z0-9 ./:+&_-]{0,5}$/,
    /^[a-z0-9]+([._/:#@-][a-z0-9*]+)+[/]?$/i,
    /^(https?:|mailto:|\/|#|\.|--|\{|<)/,
    /^[a-z][a-zA-Z0-9]*$/,
    /^[a-z]+(-[a-z0-9]+)+$/,
];

function allowList() {
    try {
        return JSON.parse(readFileSync(ALLOW_FILE, 'utf8'));
    } catch {
        return { texts: [], files: [] };
    }
}

function filesIn(path) {
    const full = join(ROOT, path);
    const stat = statSync(full);

    if (stat.isFile()) {
        return [full];
    }

    return readdirSync(full, { recursive: true })
        .map((file) => join(full, String(file)))
        .filter(
            (file) => /\.tsx?$/.test(file) && !/\.(test|d)\.tsx?$/.test(file),
        )
        .filter(
            (file) =>
                !SKIP_PARTS.some((part) =>
                    file.split(sep).join('/').includes(part),
                ),
        )
        .filter(
            (file) =>
                !file.includes(`${sep}plugins${sep}`) ||
                file.includes(`${sep}resources${sep}js${sep}`),
        );
}

function isTranslationCall(node) {
    return (
        ts.isCallExpression(node) &&
        ts.isIdentifier(node.expression) &&
        ['t', 'msg'].includes(node.expression.text)
    );
}

// True when the text is inside t('...'), msg('...') or a type position.
function isWrapped(node) {
    for (let parent = node.parent; parent; parent = parent.parent) {
        if (isTranslationCall(parent)) {
            return true;
        }

        if (
            ts.isTypeNode(parent) ||
            ts.isImportDeclaration(parent) ||
            ts.isExportDeclaration(parent)
        ) {
            return true;
        }

        if (
            ts.isJsxAttribute(parent) ||
            ts.isPropertyAssignment(parent) ||
            ts.isJsxExpression(parent) ||
            ts.isCallExpression(parent)
        ) {
            return false;
        }
    }

    return false;
}

function looksLikeText(text, allowed) {
    const value = text.replace(/\s+/g, ' ').trim();

    if (value === '' || !/\p{L}/u.test(value)) {
        return false;
    }

    if (allowed.has(value)) {
        return false;
    }

    return !CODE_LIKE.some((pattern) => pattern.test(value));
}

function stringValue(node) {
    if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) {
        return node.text;
    }

    if (ts.isTemplateExpression(node)) {
        return [
            node.head.text,
            ...node.templateSpans.map((span) => span.literal.text),
        ].join(' ');
    }

    return null;
}

// Strings that end up as a JSX child: {'Text'}, {ok ? 'Yes' : 'No'}, {a || 'None'}.
function childStrings(expression) {
    if (!expression) {
        return [];
    }

    if (stringValue(expression) !== null) {
        return [expression];
    }

    if (ts.isConditionalExpression(expression)) {
        return [
            ...childStrings(expression.whenTrue),
            ...childStrings(expression.whenFalse),
        ];
    }

    if (ts.isBinaryExpression(expression)) {
        return [
            ...childStrings(expression.left),
            ...childStrings(expression.right),
        ];
    }

    if (ts.isParenthesizedExpression(expression)) {
        return childStrings(expression.expression);
    }

    return [];
}

function attributeName(node) {
    return node.name &&
        (ts.isIdentifier(node.name) ||
            ts.isStringLiteral(node.name) ||
            ts.isJsxNamespacedName?.(node.name))
        ? node.name.getText()
        : null;
}

export function scanSource(fileName, source, allowed = new Set()) {
    const file = ts.createSourceFile(
        fileName,
        source,
        ts.ScriptTarget.Latest,
        true,
        fileName.endsWith('.tsx') ? ts.ScriptKind.TSX : ts.ScriptKind.TS,
    );
    const found = [];

    const report = (node, text) => {
        if (!looksLikeText(text, allowed) || isWrapped(node)) {
            return;
        }

        const { line } = file.getLineAndCharacterOfPosition(node.getStart());
        found.push({ line: line + 1, text: text.replace(/\s+/g, ' ').trim() });
    };

    const visit = (node) => {
        if (ts.isJsxText(node)) {
            report(node, node.text);
        } else if (
            ts.isJsxExpression(node) &&
            (ts.isJsxElement(node.parent) || ts.isJsxFragment(node.parent))
        ) {
            childStrings(node.expression).forEach((item) =>
                report(item, stringValue(item)),
            );
        } else if (
            ts.isJsxAttribute(node) &&
            TEXT_ATTRIBUTE.test(attributeName(node) ?? '') &&
            node.initializer
        ) {
            const value = ts.isJsxExpression(node.initializer)
                ? node.initializer.expression
                : node.initializer;
            childStrings(value).forEach((item) =>
                report(item, stringValue(item)),
            );
        } else if (
            ts.isPropertyAssignment(node) &&
            TEXT_ATTRIBUTE.test(attributeName(node) ?? '')
        ) {
            childStrings(node.initializer).forEach((item) =>
                report(item, stringValue(item)),
            );
        } else if (
            ts.isCallExpression(node) &&
            TEXT_CALLS.test(node.expression.getText()) &&
            node.arguments[0]
        ) {
            childStrings(node.arguments[0]).forEach((item) =>
                report(item, stringValue(item)),
            );
        }

        ts.forEachChild(node, visit);
    };

    visit(file);

    return found;
}

export function scan(paths = SCAN_ROOTS) {
    const allow = allowList();
    const texts = new Set(allow.texts ?? []);
    const skipped = new Set(allow.files ?? []);
    const results = [];

    for (const path of paths) {
        for (const file of filesIn(path)) {
            const name = relative(ROOT, file).split(sep).join('/');

            if (skipped.has(name)) {
                continue;
            }

            for (const item of scanSource(
                name,
                readFileSync(file, 'utf8'),
                texts,
            )) {
                results.push({ file: name, ...item });
            }
        }
    }

    return results;
}

if (import.meta.filename && process.argv[1] === import.meta.filename) {
    const results = scan(
        process.argv.length > 2 ? process.argv.slice(2) : SCAN_ROOTS,
    );

    for (const item of results) {
        console.log(`${item.file}:${item.line}  ${item.text}`);
    }

    console.log(`${results.length} hard coded strings`);
    process.exitCode = results.length > 0 ? 1 : 0;
}
