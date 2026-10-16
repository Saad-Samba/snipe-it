<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Discipline;
use App\Models\Manufacturer;
use App\Models\SoftwareModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class SoftwareModelsController extends Controller
{
    public function index(): View
    {
        $this->authorizeLicenseManagement('view');

        return view('software-models.index', [
            'softwareModels' => SoftwareModel::with(['category', 'manufacturer', 'discipline'])->orderBy('name')->paginate(50),
        ]);
    }

    public function create(): View
    {
        $this->authorizeLicenseManagement('create');

        return view('software-models.edit', $this->formData(new SoftwareModel));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeLicenseManagement('create');

        $softwareModel = new SoftwareModel;
        $this->fill($softwareModel, $request);
        $softwareModel->created_by = auth()->id();

        if ($softwareModel->save()) {
            return redirect()->route('software-models.index')->with('success', 'Software model created successfully.');
        }

        return back()->withInput()->withErrors($softwareModel->getErrors());
    }

    public function edit(SoftwareModel $softwareModel): View
    {
        $this->authorizeLicenseManagement('edit');

        return view('software-models.edit', $this->formData($softwareModel));
    }

    public function update(Request $request, SoftwareModel $softwareModel): RedirectResponse
    {
        $this->authorizeLicenseManagement('edit');
        $this->fill($softwareModel, $request);

        if ($softwareModel->save()) {
            return redirect()->route('software-models.index')->with('success', 'Software model updated successfully.');
        }

        return back()->withInput()->withErrors($softwareModel->getErrors());
    }

    private function fill(SoftwareModel $softwareModel, Request $request): void
    {
        $softwareModel->name = $request->input('name');
        $softwareModel->category_id = $request->input('category_id');
        $softwareModel->manufacturer_id = $request->filled('manufacturer_id') ? $request->input('manufacturer_id') : null;
        $softwareModel->discipline_id = $request->filled('discipline_id') ? $request->input('discipline_id') : null;
        $softwareModel->notes = $request->input('notes');
        $softwareModel->active = $request->boolean('active');
    }

    private function formData(SoftwareModel $item): array
    {
        return [
            'item' => $item,
            'categories' => Category::where('category_type', 'license')->orderBy('name')->pluck('name', 'id'),
            'manufacturers' => Manufacturer::orderBy('name')->pluck('name', 'id'),
            'disciplines' => Discipline::orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function authorizeLicenseManagement(string $ability): void
    {
        abort_unless(auth()->user()?->hasAccess('licenses.'.$ability), 403);
    }
}
