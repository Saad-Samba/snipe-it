<?php

namespace App\Importer;

use App\Models\Asset;
use App\Models\License;
use Illuminate\Support\Facades\Auth;

class LicenseImporter extends ItemImporter
{
    public function __construct($filename)
    {
        parent::__construct($filename);
        $this->setFieldMappings([]);
    }

    public function setFieldMappings($fields)
    {
        return parent::setFieldMappings(array_merge([
            'serial' => 'product key',
            'serial_number' => 'serial number',
            'software_version' => 'software version',
        ], $fields));
    }

    protected function handle($row)
    {
        // ItemImporter handles the general fetching.
        parent::handle($row);
        $this->createLicenseIfNotExists($row);
    }

    /**
     * Create the license if it does not exist.
     *
     * @author Daniel Melzter
     * @since 4.0
     * @param array $row
     * @return License|mixed|null
     * updated @author Jes Vinsmoke
     * @since 6.1
     *
     */
    public function createLicenseIfNotExists(array $row)
    {
        $editingLicense = false;
        $productKey = trim((string) ($this->item['serial'] ?? ''));
        $serialNumber = trim((string) ($this->item['serial_number'] ?? ''));
        $this->item['serial'] = $productKey;
        $this->item['serial_number'] = $serialNumber;

        $licenseQuery = License::where('name', $this->item['name']);
        $hasProductKey = $productKey !== '';
        $hasSerialNumber = $serialNumber !== '';

        if ($hasProductKey || $hasSerialNumber) {
            $licenseQuery->where(function ($query) use ($hasProductKey, $hasSerialNumber, $productKey, $serialNumber) {
                if ($hasProductKey) {
                    $query->orWhere('serial', $productKey);
                }

                if ($hasSerialNumber) {
                    $query->orWhere('serial_number', $serialNumber);
                }
            });
        } else {
            $licenseQuery
                ->where(function ($query) {
                    $query->whereNull('serial')->orWhere('serial', '');
                })
                ->where(function ($query) {
                    $query->whereNull('serial_number')->orWhere('serial_number', '');
                });
        }

        $matchingLicenses = $licenseQuery->limit(2)->get();

        if ($matchingLicenses->count() > 1) {
            $this->log('Multiple matching Licenses found for '.$this->item['name'].'; import row skipped.');

            return;
        }

        $license = $matchingLicenses->first();
        if ($license) {
            if ($this->hasConflictingIdentifiers($license, $productKey, $serialNumber)) {
                $this->log('Conflicting identifiers found for License '.$this->item['name'].'; import row skipped.');

                return;
            }

            if (! $this->updating) {
                $this->log($this->describeMatchingLicense());

                return;
            }

            $this->log('Updating License');
            $editingLicense = true;
        } else {
            $this->log('No Matching License, Creating a new one');
            $license = new License;
        }
        $asset_tag = $this->item['asset_tag'] = trim($this->findCsvMatch($row, 'asset_tag')); // used for checkout out to an asset.

        $this->item["expiration_date"] = null;
        if ($this->findCsvMatch($row, "expiration_date")!='') {
            $this->item["expiration_date"] = date("Y-m-d 00:00:01", strtotime(trim($this->findCsvMatch($row, "expiration_date"))));
        }
        $this->item['license_email'] = trim($this->findCsvMatch($row, 'license_email'));
        $this->item['license_name'] = trim($this->findCsvMatch($row, 'license_name'));
        $this->item['software_version'] = trim($this->findCsvMatch($row, 'software_version'));
        $this->item['maintained'] = trim($this->findCsvMatch($row, 'maintained'));
        $this->item['purchase_order'] = trim($this->findCsvMatch($row, 'purchase_order'));
        $this->item['order_number'] = trim($this->findCsvMatch($row, 'order_number'));
        $this->item['reassignable'] = trim($this->findCsvMatch($row, 'reassignable'));
        $this->item['manufacturer'] = $this->createOrFetchManufacturer(trim($this->findCsvMatch($row, 'manufacturer')));
        $this->item['min_amt'] = trim($this->findCsvMatch($row, 'min_amt'));

        if($this->item['reassignable'] == "")
        {
            $this->item['reassignable'] = 1;
        }
        $this->item['seats'] = $this->findCsvMatch($row, 'seats');
        
        $this->item["termination_date"] = null;
        if ($this->findCsvMatch($row, "termination_date")!='') {
            $this->item["termination_date"] = date("Y-m-d 00:00:01", strtotime($this->findCsvMatch($row, "termination_date")));
        }

        if ($editingLicense) {
            $license->update($this->sanitizeItemForUpdating($license));
        } else {
            $license->fill($this->sanitizeItemForStoring($license));
            $license->created_by = auth()->id();
        }

        // This sets an attribute on the Loggable trait for the action log
        $license->setImported(true);
        if ($license->save()) {
            $this->log('License '.$this->item['name'].' with serial number '.$this->item['serial'].' was created');

            // Lets try to checkout seats if the fields exist and we have seats.
            if ($license->seats > 0) {
                $checkout_target = $this->item['checkout_target'];
                $asset = Asset::where('asset_tag', $asset_tag)->first();
                $targetLicense = $license->freeSeat();

                if (is_null($targetLicense)){
                    return;
                }

                if ($checkout_target) {
                    $targetLicense->assigned_to = $checkout_target->id;
                    $targetLicense->created_by = auth()->id();
                    if ($asset) {
                        $targetLicense->asset_id = $asset->id;
                    }
                    $targetLicense->save();
                } elseif ($asset) {
                    $targetLicense->created_by = auth()->id();
                    $targetLicense->asset_id = $asset->id;
                    $targetLicense->save();
                }
            }

            return;
        }
        $this->logError($license, 'License "'.$this->item['name'].'"');
    }

    private function describeMatchingLicense(): string
    {
        $details = [];

        if ($this->item['serial'] !== '') {
            $details[] = 'product key ' . $this->item['serial'];
        }

        if ($this->item['serial_number'] !== '') {
            $details[] = 'serial number ' . $this->item['serial_number'];
        }

        if ($details === []) {
            $details[] = 'no product key or serial number';
        }

        return 'A matching License ' . $this->item['name'] . ' with ' . implode(' and ', $details) . ' already exists';
    }

    private function hasConflictingIdentifiers(License $license, string $productKey, string $serialNumber): bool
    {
        $existingProductKey = trim((string) $license->serial);
        $existingSerialNumber = trim((string) $license->serial_number);

        return ($productKey !== '' && $existingProductKey !== '' && $productKey !== $existingProductKey)
            || ($serialNumber !== '' && $existingSerialNumber !== '' && $serialNumber !== $existingSerialNumber);
    }
}
