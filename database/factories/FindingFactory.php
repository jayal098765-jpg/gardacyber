<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
{
    protected $model = Finding::class;

    public function definition(): array
    {
        $kind = fake()->randomElement(Finding::KINDS);

        return [
            'asset_group_id' => AssetGroup::factory(),
            'asset_id' => Asset::factory(),
            'kind' => $kind,
            'severity' => fake()->randomElement(Finding::SEVERITIES),
            'category' => fake()->randomElement(['cve', 'exposure', 'misconfiguration', 'weak-credentials', 'ssl-tls', 'dns', 'defacement', 'gambling-injection', 'seo-spam', 'other']),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'evidence' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(Finding::STATUSES),
            'first_seen_at' => fake()->dateTimeBetween('-90 days', '-10 days'),
            'last_seen_at' => fake()->dateTimeBetween('-10 days', 'now'),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => Finding::STATUS_OPEN]);
    }

    public function vulnerability(): static
    {
        return $this->state(fn (): array => ['kind' => Finding::KIND_VULNERABILITY]);
    }

    public function incident(): static
    {
        return $this->state(fn (): array => ['kind' => Finding::KIND_INCIDENT]);
    }

    public function severity(string $severity): static
    {
        return $this->state(fn (): array => ['severity' => $severity]);
    }
}
