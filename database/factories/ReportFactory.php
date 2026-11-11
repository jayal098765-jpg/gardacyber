<?php

namespace Database\Factories;

use App\Models\AssetGroup;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'asset_group_id' => null,
            'scope' => fake()->randomElement([Report::SCOPE_ORGANIZATION, Report::SCOPE_GROUP, Report::SCOPE_VULNERABILITIES, Report::SCOPE_INCIDENTS]),
            'kind' => fake()->randomElement([Report::KIND_CSV, Report::KIND_HTML]),
            'item_count' => fake()->numberBetween(1, 120),
            'payload' => [],
            'generated_at' => fake()->dateTimeBetween('-14 days', 'now'),
        ];
    }

    public function forGroup(AssetGroup $group): static
    {
        return $this->state(fn (): array => [
            'asset_group_id' => $group->id,
            'scope' => Report::SCOPE_GROUP,
        ]);
    }
}
