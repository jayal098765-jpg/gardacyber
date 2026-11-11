<?php

namespace Database\Factories;

use App\Models\AssetGroup;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scan>
 */
class ScanFactory extends Factory
{
    protected $model = Scan::class;

    public function definition(): array
    {
        return [
            'asset_group_id' => AssetGroup::factory(),
            'type' => fake()->randomElement([Scan::TYPE_FULL, Scan::TYPE_VULNERABILITY, Scan::TYPE_INCIDENT]),
            'status' => Scan::STATUS_COMPLETED,
            'options' => ['port_intensity' => 'quick', 'auto_rescan' => 'off'],
            'stage_count' => 6,
            'started_at' => fake()->dateTimeBetween('-30 days', '-2 days'),
            'finished_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => Scan::STATUS_RUNNING,
            'started_at' => now()->subMinutes(5),
            'finished_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => Scan::STATUS_COMPLETED,
            'finished_at' => now()->subMinutes(5),
        ]);
    }
}
