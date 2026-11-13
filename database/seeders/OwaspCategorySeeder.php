<?php

namespace Database\Seeders;

use App\Models\OwaspCategory;
use Illuminate\Database\Seeder;

class OwaspCategorySeeder extends Seeder
{
    /**
     * @return array<int, array{year: int, code: string, name: string, sort: int}>
     */
    public static function data(): array
    {
        $twentyOne = [
            'A01' => 'Broken Access Control',
            'A02' => 'Cryptographic Failures',
            'A03' => 'Injection',
            'A04' => 'Insecure Design',
            'A05' => 'Security Misconfiguration',
            'A06' => 'Vulnerable and Outdated Components',
            'A07' => 'Identification and Authentication Failures',
            'A08' => 'Software and Data Integrity Failures',
            'A09' => 'Security Logging and Monitoring Failures',
            'A10' => 'Server-Side Request Forgery',
        ];

        $rows = [];

        foreach ($twentyOne as $letter => $name) {
            $rows[] = ['year' => 2021, 'code' => $letter.':2021', 'name' => $name, 'sort' => (int) substr($letter, 1)];
        }

        foreach ($twentyOne as $letter => $name) {
            $rows[] = ['year' => 2025, 'code' => $letter.':2025', 'name' => $name, 'sort' => (int) substr($letter, 1)];
        }

        return $rows;
    }

    public function run(): void
    {
        foreach (self::data() as $row) {
            OwaspCategory::updateOrCreate(
                ['year' => $row['year'], 'code' => $row['code']],
                $row
            );
        }
    }
}
