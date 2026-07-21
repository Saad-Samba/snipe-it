<?php

declare(strict_types=1);

namespace Tests\Support\Importing;

use Illuminate\Support\Str;

/**
 * Build a consumables import file at runtime for testing.
 *
 * @template Row of array{
 * category?: string,
 * companyName?: string,
 * expirationDate?: string,
 * isMaintained?: bool,
 * isReassignAble?: bool,
 * licensedToName?: string,
 * licensedToEmail?: email,
 * licenseName?: string,
 * manufacturerName?: string,
 * notes?: string,
 * orderNumber?: string,
 * purchaseCost?: int,
 * purchaseDate?: string,
 * productKey?: string,
 * seats?: int,
 * serialNumber?: string,
 * softwareVersion?: string,
 * supplierName?: string
 * }
 *
 * @extends FileBuilder<Row>
 */
class LicensesImportFileBuilder extends FileBuilder
{
    /**
     * @inheritdoc
     */
    protected function getDictionary(): array
    {
        return [
            'category'         => 'Category',
            'companyName'      => 'Company',
            'expirationDate'   => 'expiration date',
            'isMaintained'     => 'maintained',
            'isReassignAble'   => 'reassignable',
            'licensedToName'   => 'Licensed To Name',
            'licensedToEmail'  => 'Licensed to Email',
            'licenseName'      => 'Item name',
            'manufacturerName' => 'manufacturer',
            'notes'            => 'notes',
            'orderNumber'      => 'Order Number',
            'purchaseCost'     => 'Purchase Cost',
            'purchaseDate'     => 'Purchase Date',
            'productKey'       => 'Product Key',
            'seats'            => 'seats',
            'serialNumber'     => 'Serial number',
            'softwareVersion'  => 'Software Version',
            'supplierName'     => 'supplier',
        ];
    }

    /**
     * @inheritdoc
     */
    public function definition(): array
    {
        $faker = fake();

        return [
            'category'         => Str::random(),
            'companyName'      => Str::random() . " {$faker->companySuffix}",
            'expirationDate'   => $faker->date,
            'isMaintained'     => $faker->randomElement(['TRUE', 'FALSE']),
            'isReassignAble'   => $faker->randomElement(['TRUE', 'FALSE']),
            'licensedToName'   => $faker->name,
            'licensedToEmail'  => $faker->email,
            'licenseName'      => $faker->company,
            'manufacturerName' => $faker->company,
            'notes'            => $faker->sentence,
            'orderNumber'      => "ON:LIC:{$faker->uuid}",
            'purchaseCost'     => rand(1, 100_000),
            'purchaseDate'     => $faker->date,
            'productKey'       => 'PK:LIC:' . Str::random(),
            'seats'            => rand(1, 10),
            'serialNumber'     => 'SN:LIC:' . Str::random(),
            'softwareVersion'  => $faker->numerify('##.#.#'),
            'supplierName'     => $faker->company,
        ];
    }
}
