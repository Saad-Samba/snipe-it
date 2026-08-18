<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class UserFacingVocabularyTest extends TestCase
{
    public function testApprovedVocabularyIsUsedForEnglishUi(): void
    {
        $general = $this->translations('general');
        $hardwareForm = $this->translations('admin/hardware/form');
        $hardwareTable = $this->translations('admin/hardware/table');
        $consumables = $this->translations('admin/consumables/general');
        $kits = $this->translations('admin/kits/general');

        $this->assertSame('Site', $general['company']);
        $this->assertSame('Sites', $general['companies']);
        $this->assertSame('Default Location', $hardwareForm['default_location']);
        $this->assertSame('Location', $hardwareTable['location']);
        $this->assertSame('Assign', $general['checkout']);
        $this->assertSame('Assign To', $general['assign_to']);
        $this->assertSame('Assignment Type', $general['assignment_type']);
        $this->assertSame('Return', $general['checkin']);
        $this->assertSame('Assigned To', $general['checked_out_to']);
        $this->assertSame('Assignment Date', $hardwareForm['checkout_date']);
        $this->assertSame('Return Date', $hardwareForm['checkin_date']);
        $this->assertSame('Expected Return Date', $hardwareForm['expected_checkin']);
        $this->assertSame('Verify Asset', $general['audit']);
        $this->assertSame('Verify Multiple Assets', $general['bulkaudit']);
        $this->assertSame('Asset Verification History', $general['audit_report']);
        $this->assertSame('Last Verification Date', $general['last_audit']);
        $this->assertSame('Next Verification Due Date', $general['next_audit_date']);
        $this->assertSame('Clear Expected Return Date', $general['clear_expected_return_date']);
        $this->assertSame('Clear Next Verification Due Date', $general['clear_next_verification_due_date']);
        $this->assertSame('Clear Site', $general['clear_site']);
        $this->assertSame('Assignment Date Range', $general['assignment_date_range']);
        $this->assertSame('Return Date Range', $general['return_date_range']);
        $this->assertSame('Issue Consumable to User', $consumables['checkout']);
        $this->assertSame('Assign Kit', $kits['checkout']);
    }

    public function testUnapprovedConceptsKeepTheirExistingNames(): void
    {
        $this->assertSame('Category', $this->translations('general')['category']);
        $this->assertSame('Custom Fields', $this->translations('admin/custom_fields/general')['custom_fields']);
    }

    public function testOutboundEmailVocabularyMatchesEnglishUi(): void
    {
        $mail = $this->translations('mail');

        $this->assertSame('Asset assigned: :tag', $mail['Asset_Checkout_Notification']);
        $this->assertSame('Asset returned: :tag', $mail['Asset_Checkin_Notification']);
        $this->assertSame('Consumable issued', $mail['Consumable_checkout_notification']);
        $this->assertSame('Assignment Date', $mail['checkout_date']);
        $this->assertSame('Return Date', $mail['checkin_date']);
        $this->assertSame('Expected Return Date', $mail['expecting_checkin_date']);
        $this->assertSame('Expected asset return report', $mail['Expected_Checkin_Report']);
        $this->assertStringContainsString('due for verification', $mail['upcoming-audits']);
        $this->assertStringContainsString('items assigned to you', $mail['item_checked_reminder']);
    }

    public function testLegacyAssetHistoryImportHeadersRemainDocumented(): void
    {
        $helpText = $this->translations('admin/hardware/general')['import_text'];

        $this->assertStringContainsString('Asset Tag, Name, Checkout Date, Checkin Date', $helpText);
        $this->assertStringContainsString('legacy names exactly', $helpText);
    }

    private function translations(string $path): array
    {
        return require dirname(__DIR__, 2).'/resources/lang/en-US/'.$path.'.php';
    }
}
