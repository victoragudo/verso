import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Vitest setup for the admin React code: jsdom and the same "@" alias as tsconfig.
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{ts,tsx}'],
        globalSetup: ['resources/js/tests/global-setup.ts'],
        setupFiles: ['resources/js/tests/setup.ts'],
        clearMocks: true,
        restoreMocks: true,
        // A zone far from UTC, so tests fail if code uses the computer zone.
        env: { TZ: 'Pacific/Auckland' },
        css: false,
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.{ts,tsx}'],
            exclude: [
                'resources/js/tests/**',
                'resources/js/**/*.test.{ts,tsx}',
                'resources/js/actions/**',
                'resources/js/routes/**',
                'resources/js/wayfinder/**',
                'resources/js/components/ui/**',
                'resources/js/types/**',
            ],
            reporter: ['text', 'html'],
        },
    },
});
