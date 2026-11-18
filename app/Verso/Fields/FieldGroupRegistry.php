<?php

namespace App\Verso\Fields;

use App\Verso\Fields\Definitions\Field;
use App\Verso\Fields\Definitions\FieldGroup;
use App\Verso\Fields\Definitions\LocationContext;
use App\Verso\Fields\Exceptions\InvalidFieldDefinition;
use App\Verso\Fields\Json\FieldGroupJson;
use App\Verso\Fields\Models\FieldGroupRecord;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

// Every field group: made in the admin, registered by plugin code, or read from JSON files.
class FieldGroupRegistry
{
    /** @var array<string, FieldGroup> */
    protected array $coded = [];

    /** @var list<Closure(): (string|null)|string> */
    protected array $folders = [];

    /** @var array<string, FieldGroup>|null */
    protected ?array $fromJson = null;

    /** @var array<string, FieldGroup>|null */
    protected ?array $fromDatabase = null;

    /** @var array<string, string> */
    protected array $problems = [];

    public function __construct(protected FieldGroupJson $json) {}

    /**
     * Adds a group from code. Arrays use the same shape as the JSON export.
     *
     * @param  FieldGroup|array<mixed>  $group
     */
    public function register(FieldGroup|array $group): void
    {
        $group = is_array($group) ? FieldGroup::fromArray($group) : $group;

        $this->coded[$group->key] = $group;
    }

    // Adds a folder of JSON files. A closure is read the first time groups are needed.
    public function addFolder(string|Closure $folder): void
    {
        $this->folders[] = $folder;
        $this->fromJson = null;
    }

    // Forget groups read from the database, after a save or a delete.
    public function forget(): void
    {
        $this->fromDatabase = null;
    }

    /**
     * Every group by key. Database groups win over JSON groups, which win over code groups.
     *
     * @return array<string, FieldGroup>
     */
    public function all(): array
    {
        $groups = [...$this->coded, ...$this->jsonGroups(), ...$this->databaseGroups()];

        uasort($groups, fn (FieldGroup $a, FieldGroup $b): int => [$a->order, $a->title, $a->key] <=> [$b->order, $b->title, $b->key]);

        return $groups;
    }

    public function find(string $key): ?FieldGroup
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Active groups whose location matches. Parts the context does not know count as matching.
     *
     * @return list<FieldGroup>
     */
    public function matching(LocationContext $context): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (FieldGroup $group): bool => $group->active && $group->location->matches($context),
        ));
    }

    /**
     * Top level fields of the matching groups, by name. The first group wins when names repeat.
     *
     * @return array<string, Field>
     */
    public function fields(LocationContext $context): array
    {
        $fields = [];

        foreach ($this->matching($context) as $group) {
            foreach ($group->fields as $field) {
                $fields[$field->name] ??= $field;
            }
        }

        return $fields;
    }

    /**
     * Files or rows that could not be read, with the reason.
     *
     * @return array<string, string>
     */
    public function problems(): array
    {
        $this->jsonGroups();
        $this->databaseGroups();

        return $this->problems;
    }

    /** @return array<string, FieldGroup> */
    protected function jsonGroups(): array
    {
        if ($this->fromJson !== null) {
            return $this->fromJson;
        }

        $this->fromJson = [];

        foreach ($this->folders as $folder) {
            $path = $folder instanceof Closure ? $folder() : $folder;

            if (! is_string($path) || ! is_dir($path)) {
                continue;
            }

            foreach (glob(rtrim($path, '/\\').DIRECTORY_SEPARATOR.'*.json') ?: [] as $file) {
                try {
                    foreach ($this->json->decode((string) file_get_contents($file), FieldGroup::SOURCE_JSON) as $group) {
                        $this->fromJson[$group->key] = $group;
                    }
                } catch (InvalidFieldDefinition $exception) {
                    $this->problems[basename($file)] = $exception->getMessage();
                    Log::warning("Verso could not read the field group file {$file}: {$exception->getMessage()}");
                }
            }
        }

        return $this->fromJson;
    }

    /** @return array<string, FieldGroup> */
    protected function databaseGroups(): array
    {
        if ($this->fromDatabase !== null) {
            return $this->fromDatabase;
        }

        $this->fromDatabase = [];

        try {
            if (! Schema::hasTable('field_groups')) {
                return $this->fromDatabase;
            }
        } catch (Throwable) {
            return $this->fromDatabase;
        }

        foreach (FieldGroupRecord::query()->orderBy('id')->get() as $record) {
            try {
                $this->fromDatabase[$record->key] = $record->toDefinition();
            } catch (InvalidFieldDefinition $exception) {
                $this->problems[$record->key] = $exception->getMessage();
            }
        }

        return $this->fromDatabase;
    }
}
