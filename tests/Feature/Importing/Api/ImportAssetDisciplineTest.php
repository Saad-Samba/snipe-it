<?php

namespace Tests\Feature\Importing\Api;

use App\Models\Asset;
use App\Models\Discipline;
use App\Models\Import;
use App\Models\User;
use Tests\Support\Importing\AssetsImportFileBuilder;
use Tests\Support\Importing\CleansUpImportFiles;

class ImportAssetDisciplineTest extends ImportDataTestCase
{
    use CleansUpImportFiles;

    private function importRow(array $row, array $options = [])
    {
        $file = new AssetsImportFileBuilder([$row]);
        $import = Import::factory()->asset()->create(['file_path' => $file->saveToImportsDirectory()]);
        $this->actingAsForApi(User::factory()->superuser()->create());

        return $this->importFileResponse(['import' => $import->id, 'import-type' => 'asset'] + $options);
    }

    public function testNewImportsRejectMissingBlankUnknownAndDeletedDiscipline(): void
    {
        $deleted = Discipline::create(['name' => 'Deleted discipline']);
        $deleted->delete();
        foreach ([null, '', '   ', 'Unknown discipline', $deleted->name] as $value) {
            $row = AssetsImportFileBuilder::new()->firstRow();
            if ($value === null) {
                unset($row['disciplineName']);
            } else {
                $row['disciplineName'] = $value;
            }
            $response = $this->importRow($row)->assertJsonPath('status', 'import-errors');
            $this->assertStringContainsString('discipline_id', $response->getContent());
            $this->assertDatabaseMissing('assets', ['asset_tag' => $row['tag']]);
        }
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseMissing('disciplines', ['name' => 'Unknown discipline']);
    }

    public function testMixedImportSavesValidRowsAndIdentifiesEachInvalidAssetTag(): void
    {
        $good = AssetsImportFileBuilder::new()->firstRow();
        $blank = AssetsImportFileBuilder::new(['disciplineName' => ''])->firstRow();
        $unknown = AssetsImportFileBuilder::new(['disciplineName' => 'Unknown discipline'])->firstRow();
        $file = new AssetsImportFileBuilder([$good, $blank, $unknown]);
        $import = Import::factory()->asset()->create(['file_path' => $file->saveToImportsDirectory()]);
        $this->actingAsForApi(User::factory()->superuser()->create());
        $response = $this->importFileResponse(['import' => $import->id, 'import-type' => 'asset'])
            ->assertJsonPath('status', 'import-errors');
        $this->assertArrayHasKey($blank['tag'], $response->json('messages'));
        $this->assertArrayHasKey($unknown['tag'], $response->json('messages'));
        $this->assertDatabaseHas('assets', ['asset_tag' => $good['tag']]);
        $this->assertDatabaseCount('assets', 1);
    }

    public function testNewImportResolvesActiveDisciplineAndCustomMapping(): void
    {
        $row = AssetsImportFileBuilder::new()->firstRow();
        $name = $row['disciplineName'];
        $row['Owning Team'] = ' '.$name.' ';
        unset($row['disciplineName']);
        $this->importRow($row, ['column-mappings' => ['Owning Team' => 'discipline']])
            ->assertStatusMessageIs('success');
        $this->assertDatabaseHas('assets', [
            'asset_tag' => $row['tag'],
            'discipline_id' => Discipline::where('name', $name)->sole()->id,
        ]);
    }

    public function testUpdateImportsPreserveLegacyBlankAndExistingDisciplineWhenCellBlank(): void
    {
        $discipline = Discipline::create(['name' => 'Hardware']);
        foreach ([null, $discipline->id] as $id) {
            $asset = Asset::factory()->create(['discipline_id' => $id]);
            $row = AssetsImportFileBuilder::new(['tag' => $asset->asset_tag, 'disciplineName' => ''])->firstRow();
            $this->importRow($row, ['import-update' => true])->assertStatusMessageIs('success');
            $this->assertSame($id, $asset->fresh()->discipline_id);
        }
    }

    public function testUpdateImportsCanFillLegacyBlankButRejectUnknownDiscipline(): void
    {
        $asset = Asset::factory()->create(['discipline_id' => null]);
        $row = AssetsImportFileBuilder::new(['tag' => $asset->asset_tag])->firstRow();
        $this->importRow($row, ['import-update' => true])->assertStatusMessageIs('success');
        $id = Discipline::where('name', $row['disciplineName'])->sole()->id;
        $this->assertSame($id, $asset->fresh()->discipline_id);
        $row['disciplineName'] = 'Unknown discipline';
        $this->importRow($row, ['import-update' => true])->assertJsonPath('status', 'import-errors');
        $this->assertSame($id, $asset->fresh()->discipline_id);
    }
}
