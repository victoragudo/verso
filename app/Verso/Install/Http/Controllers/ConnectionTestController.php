<?php

namespace App\Verso\Install\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Verso\Install\Database\DatabaseConnectionTester;
use App\Verso\Install\Database\DatabaseCreator;
use App\Verso\Install\Database\DatabaseSettings;
use App\Verso\Install\Plan\ServiceSettings;
use App\Verso\Install\Services\ServiceTester;
use App\Verso\Install\Wizard\SecretFields;
use App\Verso\Install\Wizard\StepRules;
use App\Verso\Install\Wizard\WizardStep;
use App\Verso\Install\Wizard\WizardStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

// The wizard test buttons: database connection, create database, test email, Redis and storage.
class ConnectionTestController extends Controller
{
    public function __construct(
        protected WizardStore $store,
        protected StepRules $rules,
    ) {}

    public function database(Request $request, DatabaseConnectionTester $tester): JsonResponse
    {
        return response()->json($tester->test($this->databaseSettings($request))->toArray());
    }

    public function createDatabase(Request $request, DatabaseCreator $creator, DatabaseConnectionTester $tester): JsonResponse
    {
        $settings = $this->databaseSettings($request);

        try {
            $created = $creator->create($settings);
        } catch (RuntimeException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            ...$tester->test($settings)->toArray(),
            'created' => $created,
        ]);
    }

    public function mail(Request $request, ServiceTester $tester): JsonResponse
    {
        $to = (string) $request->validate(['test_email' => ['required', 'email', 'max:255']])['test_email'];

        return response()->json($tester->sendTestMail($this->services($request, ['mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name']), $to)->toArray());
    }

    public function redis(Request $request, ServiceTester $tester): JsonResponse
    {
        return response()->json($tester->testRedis($this->services($request, ['redis_host', 'redis_port', 'redis_password']))->toArray());
    }

    public function storage(Request $request, ServiceTester $tester): JsonResponse
    {
        $settings = $this->services($request, ['storage', 's3_key', 's3_secret', 's3_region', 's3_bucket', 's3_endpoint', 's3_path_style']);

        return response()->json($tester->testStorage($settings, (string) config('verso.install.services.storage_probe'))->toArray());
    }

    protected function databaseSettings(Request $request): DatabaseSettings
    {
        $input = SecretFields::restore(WizardStep::Database, $request->all(), $this->store->get(WizardStep::Database));

        return DatabaseSettings::fromArray($this->rules->validate(WizardStep::Database, $input));
    }

    /**
     * Check only the fields of one service, then build the settings.
     *
     * @param  list<string>  $fields
     */
    protected function services(Request $request, array $fields): ServiceSettings
    {
        $input = SecretFields::restore(WizardStep::Services, $request->all(), $this->store->get(WizardStep::Services));
        $rules = Arr::only($this->rules->rules(WizardStep::Services, ['cache_driver' => 'redis'] + $input), $fields);

        return ServiceSettings::fromArray(Validator::make($input, $rules)->validate() + Arr::only($input, $fields));
    }
}
