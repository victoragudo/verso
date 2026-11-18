import { Database, PlugZap } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { msg, t } from '@/lib/i18n';
import {
    ChoiceCards,
    Field,
    ResultBox,
    SecretInput,
} from './fields';
import { installRequest } from './install-api';
import type { TestResult } from './install-api';
import { StepFrame } from './step-frame';
import { useStepForm } from './use-step-form';

export type DriverOption = {
    value: string;
    label: string;
    port: number | null;
    available: boolean;
    extension: string;
};

type Defaults = {
    host: string;
    database: string;
    sqlite_path: string;
    charset: string;
    collation: string;
    sslmode: string;
};

type Props = {
    values: Record<string, string | number | null>;
    savedSecrets: string[];
    drivers: DriverOption[];
    sslModes: string[];
    databaseDefaults: Defaults;
    detected: {
        settings: Record<string, string | number | boolean | null>;
        message: string;
    } | null;
};

const DESCRIPTIONS: Record<string, string> = {
    sqlite: msg('One file, no server. Best for small sites and quick starts.'),
    mysql: msg(
        'The most common choice on shared hosting. Version 8.0 or newer.',
    ),
    mariadb: msg('A MySQL compatible server. Version 10.6 or newer.'),
    pgsql: msg('A strong choice for bigger sites. Version 14 or newer.'),
};

const TABLE_PREFIX_EXAMPLE = 'vs_';

const text = (value: unknown): string =>
    typeof value === 'string' || typeof value === 'number' ? String(value) : '';

// Step 3: pick the database, fill in the server details and test the connection.
export function DatabaseStep({
    values,
    savedSecrets,
    drivers,
    sslModes,
    databaseDefaults,
    detected,
}: Props) {
    const { form, formRef, submit, serverErrors } = useStepForm('database', {
        driver: text(values.driver) || 'sqlite',
        database: text(values.database),
        host: text(values.host) || databaseDefaults.host,
        port: text(values.port),
        username: text(values.username),
        password: '',
        prefix: text(values.prefix),
        sslmode: text(values.sslmode) || databaseDefaults.sslmode,
        charset: text(values.charset) || databaseDefaults.charset,
        collation: text(values.collation) || databaseDefaults.collation,
        keep: savedSecrets,
        create_database: false,
        use_detected: false,
    });
    const [testing, setTesting] = useState<'test' | 'create' | null>(null);
    const [result, setResult] = useState<TestResult | null>(null);
    const isSqlite = form.data.driver === 'sqlite';
    const driver = drivers.find((item) => item.value === form.data.driver);

    const changeDriver = (value: string) => {
        const next = drivers.find((item) => item.value === value);
        const wasSqlite = form.data.driver === 'sqlite';

        form.setData((data) => ({
            ...data,
            driver: value,
            port: next?.port ? String(next.port) : '',
            database:
                value === 'sqlite'
                    ? databaseDefaults.sqlite_path
                    : wasSqlite
                      ? databaseDefaults.database
                      : data.database,
        }));
        setResult(null);
    };

    const runTest = async (action: 'test' | 'create') => {
        setTesting(action);
        setResult(null);

        try {
            const url =
                action === 'create'
                    ? '/install/test/database/create'
                    : '/install/test/database';
            setResult(await installRequest<TestResult>(url, 'POST', form.data));
        } catch (error) {
            setResult({ ok: false, message: (error as Error).message });
        } finally {
            setTesting(null);
        }
    };

    const useDetected = () => {
        form.transform((data) => ({ ...data, use_detected: true }));
        submit();
    };

    const connectionError = serverErrors.connection;

    return (
        <StepFrame
            title={t('Database')}
            description={t(
                'Verso keeps posts, pages, users and settings in a database. Pick one and check that Verso can reach it.',
            )}
            back="requirements"
            formRef={formRef}
            onSubmit={(event) => {
                form.transform((data) => data);
                submit(event);
            }}
            processing={form.processing}
            submitLabel={t('Save and continue')}
            errors={connectionError ? [connectionError] : []}
            extraActions={
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => runTest('test')}
                    disabled={testing !== null}
                >
                    {testing === 'test' ? (
                        <Spinner />
                    ) : (
                        <PlugZap aria-hidden="true" />
                    )}
                    {t('Test connection')}
                </Button>
            }
        >
            {detected && (
                <div className="flex flex-col gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-blue-900 dark:bg-blue-950">
                    <div className="flex gap-2.5 text-blue-950 dark:text-blue-100">
                        <Database
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <p className="font-medium">
                                {t(
                                    'We found a working database in your environment',
                                )}
                            </p>
                            <p className="text-blue-900/80 dark:text-blue-100/80">
                                {t(
                                    '{driver} at {host}:{port}, database {database}.',
                                    {
                                        driver: text(detected.settings.driver),
                                        host: text(detected.settings.host),
                                        port: text(detected.settings.port),
                                        database: text(
                                            detected.settings.database,
                                        ),
                                    },
                                )}{' '}
                                {detected.message}
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        onClick={useDetected}
                        disabled={form.processing}
                    >
                        {t('Use this database')}
                    </Button>
                </div>
            )}

            <ChoiceCards
                legend={t('Database type')}
                name="driver"
                value={form.data.driver}
                onChange={changeDriver}
                columns={4}
                error={form.errors.driver}
                options={drivers.map((item) => ({
                    value: item.value,
                    label: item.label,
                    badge:
                        item.value === 'sqlite' ? t('Recommended') : undefined,
                    disabled: !item.available,
                    description: item.available
                        ? DESCRIPTIONS[item.value] &&
                          t(DESCRIPTIONS[item.value])
                        : t('Needs the {extension} PHP extension.', {
                              extension: item.extension,
                          }),
                }))}
            />

            {isSqlite ? (
                <Field
                    label={t('Database file')}
                    error={form.errors.database}
                    hint={t(
                        'A path inside the project folder, or a full path. The file is created for you.',
                    )}
                >
                    <Input
                        name="database"
                        value={form.data.database}
                        onChange={(event) =>
                            form.setData('database', event.target.value)
                        }
                        autoComplete="off"
                        spellCheck={false}
                        autoCapitalize="none"
                        required
                    />
                </Field>
            ) : (
                <div className="grid gap-4 sm:grid-cols-6">
                    <Field
                        label={t('Host')}
                        error={form.errors.host}
                        className="sm:col-span-4"
                        hint={t(
                            'Often 127.0.0.1, localhost, or the name of the database service in Docker.',
                        )}
                    >
                        <Input
                            name="host"
                            value={form.data.host}
                            onChange={(event) =>
                                form.setData('host', event.target.value)
                            }
                            autoComplete="off"
                            spellCheck={false}
                            autoCapitalize="none"
                            required
                        />
                    </Field>
                    <Field
                        label={t('Port')}
                        error={form.errors.port}
                        className="sm:col-span-2"
                    >
                        <Input
                            name="port"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            value={form.data.port}
                            onChange={(event) =>
                                form.setData('port', event.target.value)
                            }
                            placeholder={
                                driver?.port ? String(driver.port) : undefined
                            }
                            required
                        />
                    </Field>
                    <Field
                        label={t('Database name')}
                        error={form.errors.database}
                        className="sm:col-span-3"
                    >
                        <Input
                            name="database"
                            value={form.data.database}
                            onChange={(event) =>
                                form.setData('database', event.target.value)
                            }
                            autoComplete="off"
                            spellCheck={false}
                            autoCapitalize="none"
                            required
                        />
                    </Field>
                    <Field
                        label={t('Username')}
                        error={form.errors.username}
                        className="sm:col-span-3"
                    >
                        <Input
                            name="username"
                            value={form.data.username}
                            onChange={(event) =>
                                form.setData('username', event.target.value)
                            }
                            autoComplete="off"
                            spellCheck={false}
                            autoCapitalize="none"
                            required
                        />
                    </Field>
                    <Field
                        label={t('Password')}
                        error={form.errors.password}
                        className="sm:col-span-6"
                    >
                        <SecretInput
                            name="db_password"
                            value={form.data.password}
                            saved={savedSecrets.includes('password')}
                            autoComplete="off"
                            onChange={(value) =>
                                form.setData('password', value)
                            }
                        />
                    </Field>
                    {form.data.driver === 'pgsql' && (
                        <Field
                            label={t('SSL mode')}
                            error={form.errors.sslmode}
                            className="sm:col-span-3"
                        >
                            <OptionSelect
                                name="sslmode"
                                value={form.data.sslmode}
                                onValueChange={(mode) =>
                                    form.setData('sslmode', mode)
                                }
                                options={sslModes.map((mode) => ({
                                    value: mode,
                                    label: mode,
                                }))}
                            />
                        </Field>
                    )}
                    {form.data.driver !== 'pgsql' && (
                        <details className="group sm:col-span-6">
                            <summary className="cursor-pointer text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {t('Character set and collation')}
                            </summary>
                            <div className="mt-3 grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={t('Character set')}
                                    error={form.errors.charset}
                                >
                                    <Input
                                        name="charset"
                                        value={form.data.charset}
                                        onChange={(event) =>
                                            form.setData(
                                                'charset',
                                                event.target.value,
                                            )
                                        }
                                        spellCheck={false}
                                    />
                                </Field>
                                <Field
                                    label={t('Collation')}
                                    error={form.errors.collation}
                                >
                                    <Input
                                        name="collation"
                                        value={form.data.collation}
                                        onChange={(event) =>
                                            form.setData(
                                                'collation',
                                                event.target.value,
                                            )
                                        }
                                        spellCheck={false}
                                    />
                                </Field>
                            </div>
                        </details>
                    )}
                </div>
            )}

            <Field
                label={t('Table prefix')}
                optional
                error={form.errors.prefix}
                hint={t(
                    'Use a prefix like vs_ when the database already holds other tables with the same names.',
                )}
                className="max-w-xs"
            >
                <Input
                    name="prefix"
                    value={form.data.prefix}
                    onChange={(event) =>
                        form.setData('prefix', event.target.value)
                    }
                    spellCheck={false}
                    autoCapitalize="none"
                    placeholder={TABLE_PREFIX_EXAMPLE}
                />
            </Field>

            {result && (
                <ResultBox
                    ok={result.ok}
                    title={
                        result.ok
                            ? t('Connection works')
                            : t('Connection failed')
                    }
                >
                    <p>{result.message}</p>
                    {result.hint && <p className="opacity-80">{result.hint}</p>}
                    {!result.ok && result.problem === 'unknown_database' && (
                        <div className="flex flex-wrap items-center gap-2 pt-1">
                            {result.can_create_database ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => runTest('create')}
                                    disabled={testing !== null}
                                >
                                    {testing === 'create' && <Spinner />}
                                    {t('Create database')}
                                </Button>
                            ) : (
                                <span className="text-xs">
                                    {t(
                                        'This user may not create databases. Create it in your hosting panel.',
                                    )}
                                </span>
                            )}
                            <label className="flex items-center gap-2 text-xs">
                                <input
                                    type="checkbox"
                                    checked={form.data.create_database}
                                    onChange={(event) =>
                                        form.setData(
                                            'create_database',
                                            event.target.checked,
                                        )
                                    }
                                />
                                {t('Create it when I continue')}
                            </label>
                        </div>
                    )}
                </ResultBox>
            )}
        </StepFrame>
    );
}
