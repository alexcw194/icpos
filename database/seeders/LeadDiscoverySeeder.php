<?php

namespace Database\Seeders;

use App\Models\LdGridCell;
use App\Models\LdKeyword;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LeadDiscoverySeeder extends Seeder
{
    public function run(): void
    {
        $keywords = [
            ['keyword' => 'factory', 'category_label' => 'Manufacturing', 'priority' => 10],
            ['keyword' => 'manufacturing', 'category_label' => 'Manufacturing', 'priority' => 20],
            ['keyword' => 'industrial plant', 'category_label' => 'Manufacturing', 'priority' => 30],
            ['keyword' => 'warehouse', 'category_label' => 'Industrial', 'priority' => 40],
            ['keyword' => 'hospital', 'category_label' => 'Healthcare', 'priority' => 50],
            ['keyword' => 'hotel', 'category_label' => 'Hospitality', 'priority' => 60],
            ['keyword' => 'pabrik gula', 'category_label' => 'Manufacturing', 'priority' => 70],
            ['keyword' => 'sugar factory', 'category_label' => 'Manufacturing', 'priority' => 80],
            ['keyword' => 'pabrik tepung', 'category_label' => 'Manufacturing', 'priority' => 90],
            ['keyword' => 'flour mill', 'category_label' => 'Manufacturing', 'priority' => 100],
            ['keyword' => 'food processing', 'category_label' => 'Manufacturing', 'priority' => 110],
            ['keyword' => 'cement factory', 'category_label' => 'Manufacturing', 'priority' => 120],
        ];

        foreach ($keywords as $row) {
            LdKeyword::updateOrCreate(
                ['keyword' => $row['keyword']],
                [
                    'category_label' => $row['category_label'],
                    'priority' => $row['priority'],
                    'is_active' => true,
                ]
            );
        }

        $cells = [
            ['name' => 'Surabaya-01', 'center_lat' => -7.2574719, 'center_lng' => 112.7520883, 'radius_m' => 12000, 'city' => 'Surabaya', 'province' => 'Jawa Timur', 'region_code' => 'ID-JI'],
            ['name' => 'Sidoarjo-01', 'center_lat' => -7.4460278, 'center_lng' => 112.7174629, 'radius_m' => 12000, 'city' => 'Sidoarjo', 'province' => 'Jawa Timur', 'region_code' => 'ID-JI'],
            ['name' => 'Gresik-01', 'center_lat' => -7.15665, 'center_lng' => 112.6555, 'radius_m' => 12000, 'city' => 'Gresik', 'province' => 'Jawa Timur', 'region_code' => 'ID-JI'],
            ['name' => 'Pasuruan-01', 'center_lat' => -7.6453, 'center_lng' => 112.9075, 'radius_m' => 15000, 'city' => 'Pasuruan', 'province' => 'Jawa Timur', 'region_code' => 'ID-JI'],
            ['name' => 'Malang-01', 'center_lat' => -7.9666204, 'center_lng' => 112.6326321, 'radius_m' => 14000, 'city' => 'Malang', 'province' => 'Jawa Timur', 'region_code' => 'ID-JI'],
            ['name' => 'Denpasar-01', 'center_lat' => -8.6704582, 'center_lng' => 115.2126293, 'radius_m' => 12000, 'city' => 'Denpasar', 'province' => 'Bali', 'region_code' => 'ID-BA'],
            ['name' => 'Badung-01', 'center_lat' => -8.5811132, 'center_lng' => 115.1776019, 'radius_m' => 12000, 'city' => 'Badung', 'province' => 'Bali', 'region_code' => 'ID-BA'],
            ['name' => 'Gianyar-01', 'center_lat' => -8.5449114, 'center_lng' => 115.3257408, 'radius_m' => 12000, 'city' => 'Gianyar', 'province' => 'Bali', 'region_code' => 'ID-BA'],
            ['name' => 'Tabanan-01', 'center_lat' => -8.539444, 'center_lng' => 115.125, 'radius_m' => 12000, 'city' => 'Tabanan', 'province' => 'Bali', 'region_code' => 'ID-BA'],
        ];

        foreach ($cells as $row) {
            LdGridCell::updateOrCreate(
                ['name' => $row['name']],
                [
                    'center_lat' => $row['center_lat'],
                    'center_lng' => $row['center_lng'],
                    'radius_m' => $row['radius_m'],
                    'city' => $row['city'],
                    'province' => $row['province'],
                    'region_code' => $row['region_code'],
                    'is_active' => true,
                ]
            );
        }

        if (Schema::hasTable('settings')) {
            Setting::setMany([
                'lead_discovery.enabled' => '0',
                'lead_discovery.max_cells_per_run' => '20',
                'lead_discovery.max_keywords_per_cell' => '8',
                'lead_discovery.max_pages_per_query' => '3',
                'lead_discovery.page_token_delay_ms' => '2200',
                'lead_discovery.request_timeout_sec' => '20',
                'lead_discovery.retry_max' => '2',
            ]);
        }
    }
}

