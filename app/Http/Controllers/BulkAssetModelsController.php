<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\AssetModel;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use \Illuminate\Contracts\View\View;

class BulkAssetModelsController extends Controller
{
    /**
     * Returns a view that allows the user to bulk edit model attrbutes
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.7]
     * @param Request $request
     */
    public function edit(Request $request) : View | RedirectResponse
    {
        $models_raw_array = array_values($request->input('ids', []));

        if ($request->input('bulk_actions') == 'delete') {
            $this->authorize('delete', AssetModel::class);
        } else {
            $this->authorize('update', AssetModel::class);
        }

        $models = $this->authorizedModels($models_raw_array)
            ->withCount('assets as assets_count')
            ->orderBy('assets_count', 'ASC')
            ->get();

        $this->ensureAllModelsAreAuthorized($models_raw_array, $models->count());

        // Make sure some IDs have been selected
        if ((is_array($models_raw_array)) && (count($models_raw_array) > 0)) {
            // If deleting....
            if ($request->input('bulk_actions') == 'delete') {
                $valid_count = 0;
                foreach ($models as $model) {
                    if ($model->assets_count == 0) {
                        $valid_count++;
                    }
                }

                return view('models/bulk-delete', compact('models'))->with('valid_count', $valid_count);
            }
            $nochange = ['NC' => 'No Change'];

            return view('models/bulk-edit', compact('models'))
                ->with('availableCategories', $this->availableAssetCategories())
                ->with('fieldset_list', $nochange + Helper::customFieldsetList());
        }

        return redirect()->route('models.index')
            ->with('error', 'You must select at least one model to edit.');
    }

    /**
     * Returns a view that allows the user to bulk edit model attrbutes
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.7]
     * @param Request $request
     */
    public function update(Request $request): View | RedirectResponse
    {
        $this->authorize('update', AssetModel::class);

        $models_raw_array = array_values($request->input('ids', []));
        $authorizedModelIds = $this->authorizedModels($models_raw_array)->pluck('id')->all();
        $this->ensureAllModelsAreAuthorized($models_raw_array, count($authorizedModelIds));
        $update_array = [];

        if (($request->filled('manufacturer_id') && ($request->input('manufacturer_id') != 'NC'))) {
            $update_array['manufacturer_id'] = $request->input('manufacturer_id');
        }
        
        if (($request->filled('category_id') && ($request->input('category_id') != 'NC'))) {
            $this->ensureManagedCategory($request->integer('category_id'));
            $update_array['category_id'] = $request->input('category_id');
        }

        if ($request->input('fieldset_id') != 'NC') {
            $update_array['fieldset_id'] = $request->input('fieldset_id');
        }

        if ($request->input('obsolete') != '') {
            $update_array['obsolete'] = $request->input('obsolete');
        }

        if ($request->filled('min_amt')) {
            $update_array['min_amt'] = $request->input('min_amt');
        }

        if ($request->filled('reference_price')) {
            $update_array['reference_price'] = $request->input('reference_price');
        }

        if ($request->filled('require_serial')) {
            $update_array['require_serial'] = $request->input('require_serial');
        }

        if (count($update_array) > 0) {
            AssetModel::whereIn('id', $authorizedModelIds)->update($update_array);

            return redirect()->route('models.index')
                ->with('success', trans_choice('admin/models/message.bulkedit.success', count($authorizedModelIds), ['model_count' => count($authorizedModelIds)]));
        }

        return redirect()->route('models.index')
            ->with('warning', trans('admin/models/message.bulkedit.error'));
    }

    /**
     * Validate and delete the given Asset Models. An Asset Model
     * cannot be deleted if there are associated assets.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v1.0]
     */
    public function destroy(Request $request) : RedirectResponse
    {
        $this->authorize('delete', AssetModel::class);

        $models_raw_array = array_values($request->input('ids', []));

        if ((is_array($models_raw_array)) && (count($models_raw_array) > 0)) {
            $models = $this->authorizedModels($models_raw_array)->withCount('assets as assets_count')->get();
            $this->ensureAllModelsAreAuthorized($models_raw_array, $models->count());

            $del_error_count = 0;
            $del_count = 0;

            foreach ($models as $model) {
                if ($model->assets_count > 0) {
                    $del_error_count++;
                } else {
                    $model->delete();
                    $del_count++;
                }
            }

            if ($del_error_count == 0) {
                return redirect()->route('models.index')
                    ->with('success', trans('admin/models/message.bulkdelete.success', ['success_count'=> $del_count]));
            }

            return redirect()->route('models.index')
                ->with('warning', trans('admin/models/message.bulkdelete.success_partial', ['fail_count'=>$del_error_count, 'success_count'=> $del_count]));
        }

        return redirect()->route('models.index')
            ->with('error', trans('admin/models/message.bulkdelete.error'));
    }

    private function authorizedModels(array $ids)
    {
        return AssetModel::managedBy(auth()->user())->whereIn('id', $ids);
    }

    private function ensureAllModelsAreAuthorized(array $requestedIds, int $authorizedCount): void
    {
        if (count($requestedIds) !== $authorizedCount) {
            abort(403);
        }
    }

    private function ensureManagedCategory(int $categoryId): void
    {
        if (auth()->user()->isSuperUser() || auth()->user()->isAdmin()) {
            return;
        }

        $isManagedCategory = Category::whereKey($categoryId)
            ->where('category_type', 'asset')
            ->where('manager_id', auth()->id())
            ->exists();

        if (! $isManagedCategory) {
            throw ValidationException::withMessages([
                'category_id' => 'Selected category is not managed by you.',
            ]);
        }
    }

    private function availableAssetCategories()
    {
        $categories = Category::query()
            ->where('category_type', 'asset')
            ->orderBy('name', 'asc');

        if (! auth()->user()->isSuperUser() && ! auth()->user()->isAdmin()) {
            $categories->where('manager_id', auth()->id());
        }

        return $categories->get(['id', 'name']);
    }
}
