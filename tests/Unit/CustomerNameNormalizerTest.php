<?php

namespace Tests\Unit;

use App\Models\Customer;
use PHPUnit\Framework\TestCase;

class CustomerNameNormalizerTest extends TestCase
{
    public function test_it_formats_name_to_title_case(): void
    {
        $this->assertSame(
            'Excelindo Mitra Sentosa',
            Customer::normalizeCustomerName('excelindo mitra sentosa')
        );
    }

    public function test_it_forces_pt_cv_ud_to_uppercase(): void
    {
        $this->assertSame(
            'PT Excelindo Mitra Sentosa',
            Customer::normalizeCustomerName('pt excelindo mitra sentosa')
        );

        $this->assertSame(
            'Excelindo Mitra Sentosa, PT.',
            Customer::normalizeCustomerName('excelindo mitra sentosa, pt.')
        );

        $this->assertSame(
            'CV/UD Maju Jaya',
            Customer::normalizeCustomerName('cv/ud maju jaya')
        );
    }
}

