<?php

namespace Database\Factories;

use App\Models\User;
use App\Verso\Importer\Enums\ImportStatus;
use App\Verso\Importer\Models\Import;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => Import::SOURCE_WORDPRESS,
            'status' => ImportStatus::Pending,
            'disk' => 'local',
            'path' => 'imports/'.fake()->uuid().'.xml',
            'original_name' => 'harbourlight.WordPress.'.fake()->date('Y-m-d').'.xml',
            'user_id' => User::factory(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => ['status' => ImportStatus::Running, 'started_at' => now()]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportStatus::Completed,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
            'processed' => 12,
            'report' => [
                'rows' => [
                    ['kind' => 'authors', 'created' => 2, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
                    ['kind' => 'entries', 'created' => 8, 'updated' => 0, 'skipped' => 2, 'failed' => 0],
                ],
                'messages' => ['The featured image of "Grilled Sardines with Peppers" (attachment 999) was not imported.'],
                'dropped_messages' => 0,
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportStatus::Failed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'error' => 'The file is not a WordPress export.',
        ]);
    }
}
