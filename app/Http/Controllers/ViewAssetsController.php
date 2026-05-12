<?php

namespace App\Http\Controllers;

use App\Actions\CheckoutRequests\EstimateAssetModelReuseAction;
use App\Actions\CheckoutRequests\CancelCheckoutRequestAction;
use App\Actions\CheckoutRequests\CreateCheckoutRequestAction;
use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Enums\ActionType;
use App\Exceptions\AssetNotRequestable;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\RequestAssetCancelation;
use App\Notifications\RequestAssetNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use \Illuminate\Contracts\View\View;
use Exception;

/**
 * This controller handles all actions related to the ability for users
 * to view their own assets in the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class ViewAssetsController extends Controller
{
    /**
     * Extract custom fields that should be displayed in user view.
     *
     * @param User $user
     * @return array
     */
    private function extractCustomFields(User $user): array
    {
        $fieldArray = [];
        foreach ($user->assets as $asset) {
            if ($asset->model && $asset->model->fieldset) {
                foreach ($asset->model->fieldset->fields as $field) {
                    if ($field->display_in_user_view == '1') {
                        $fieldArray[$field->db_column] = $field->name;
                    }
                }
            }
        }
        return array_unique($fieldArray);
    }

    /**
     * Get list of users viewable by the current user.
     *
     * @param User $authUser
     * @return \Illuminate\Support\Collection
     */
    private function getViewableUsers(User $authUser): \Illuminate\Support\Collection
    {
        // SuperAdmin sees all users
        if ($authUser->isSuperUser()) {
            return User::select('id', 'first_name', 'last_name', 'username')
                ->where('activated', 1)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
        }

        // Regular manager sees only their subordinates + self
        $managedUsers = $authUser->getAllSubordinates();
        
        // If user has subordinates, show them with self at beginning
        if ($managedUsers->count() > 0) {
            return collect([$authUser])->merge($managedUsers)
                ->sortBy('last_name')
                ->sortBy('first_name');
        }
        
        // User has no subordinates, only sees themselves
        return collect([$authUser]);
    }

    /**
     * Get the selected user ID from request or default to current user.
     *
     * @param Request $request
     * @param \Illuminate\Support\Collection $subordinates
     * @param int $defaultUserId
     * @return int
     */
    private function getSelectedUserId(Request $request, \Illuminate\Support\Collection $subordinates, int $defaultUserId): int
    {
        // If no subordinates or no user_id in request, return default
        if ($subordinates->count() <= 1 || !$request->filled('user_id')) {
            return $defaultUserId;
        }

        $requestedUserId = (int) $request->input('user_id');
        
        // Validate if the requested user is allowed
        if ($subordinates->contains('id', $requestedUserId)) {
            return $requestedUserId;
        }
        
        // If invalid ID or not authorized, return default
        return $defaultUserId;
    }

    /**
     * Show user's assigned assets with optional manager view functionality.
     *
     */
    public function getIndex(Request $request) : View | RedirectResponse
    {
        $authUser = auth()->user();
        $settings = Setting::getSettings();
        $subordinates = collect();
        $selectedUserId = $authUser->id;

        // Process manager view if enabled
        if ($settings->manager_view_enabled) {
            $subordinates = $this->getViewableUsers($authUser);
            $selectedUserId = $this->getSelectedUserId($request, $subordinates, $authUser->id);
        }

        // Load the data for the user to be viewed (either auth user or selected subordinate)
        $userToView = User::with([
            'assets',
            'assets.model',
            'assets.model.fieldset.fields',
            'consumables',
            'accessories',
            'licenses'
        ])->find($selectedUserId);

        // If the user to view couldn't be found (shouldn't happen with proper logic), redirect with error
        if (!$userToView) {
            return redirect()->route('view-assets')->with('error', trans('admin/users/message.user_not_found'));
        }

        // Process custom fields for the user being viewed
        $fieldArray = $this->extractCustomFields($userToView);

        // Pass the necessary data to the view
        return view('account/view-assets', [
            'user' => $userToView, // Use 'user' for compatibility with the existing view
            'field_array' => $fieldArray,
            'settings' => $settings,
            'subordinates' => $subordinates,
            'selectedUserId' => $selectedUserId
        ]);
    }

    /**
     * Returns view of requestable items for a user.
     */
    public function getRequestableIndex() : View
    {
        $assets = Asset::with('model', 'defaultLoc', 'location', 'assignedTo', 'requests')->Hardware()->RequestableAssets();
        $models = AssetModel::with([
            'category',
            'requests',
            'assets' => function ($q) {
                $q->where('requestable', 1)
                    ->whereHas('assetstatus', fn ($s) =>
                    $s->where('archived', 0)
                        ->where(fn ($s) =>
                        $s->where('deployable', 1)->orWhere('pending', 1)
                        )
                    );
            },
        ])->RequestableModels()->get();

        return view('account/requestable-assets', compact('assets', 'models'));
    }

    public function estimateRequestItem(Request $request, $itemType, $itemId = null): JsonResponse
    {
        if ($itemType !== 'asset_model') {
            abort(404);
        }

        $validated = $this->validateModelRequestPayload($request);
        $item = AssetModel::findOrFail($itemId);
        $this->ensureModelRequestAuthorized($item, auth()->user());
        $this->ensureModelRequestProjectProvided($validated['project_id'] ?? null);
        $this->ensureModelRequestQuantityProvided($validated['request-quantity'] ?? null);
        $this->ensureModelRequestNeededByDateProvided($validated['needed_by_date'] ?? null);
        $estimate = $this->estimateAssetModelRequest(
            $item,
            (int) $validated['request-quantity'],
            $validated['needed_by_date'] ?? null
        );

        return response()->json($estimate);
    }

    public function getRequestItem(Request $request, $itemType, $itemId = null, $cancel_by_admin = false, $requestingUser = null): RedirectResponse
    {
        $validated = $this->validateModelRequestPayload($request);

        $item = null;
        $fullItemType = 'App\\Models\\'.studly_case($itemType);

        if ($itemType == 'asset_model') {
            $itemType = 'model';
        }
        $item = call_user_func([$fullItemType, 'find'], $itemId);

        $user = auth()->user();

        $logaction = new Actionlog();
        $logaction->item_id = $data['asset_id'] = $item->id;
        $logaction->item_type = $fullItemType;
        $logaction->created_at = $data['requested_date'] = date('Y-m-d H:i:s');

        if ($user->location_id) {
            $logaction->location_id = $user->location_id;
        }

        $logaction->target_id = $data['user_id'] = auth()->id();
        $logaction->target_type = User::class;

        $quantity = (int) ($validated['request-quantity'] ?? 1);
        $requestAction = $validated['request-action'] ?? 'create';
        $projectId = $validated['project_id'] ?? null;
        $neededByDate = $validated['needed_by_date'] ?? null;
        $data['item_quantity'] = $quantity;
        $data['requested_by'] = $user->display_name;
        $data['item'] = $item;
        $data['item_type'] = $itemType;
        $data['target'] = auth()->user();
        $data['project'] = $projectId ? Project::find($projectId) : null;

        if ($fullItemType == Asset::class) {
            $data['item_url'] = route('hardware.show', $item->id);
        } else {
            $data['item_url'] = route("view/${itemType}", $item->id);
        }

        $settings = Setting::getSettings();
        $item_request = $item->isRequestedBy($user);
        $isCancelRequest = $cancel_by_admin || $requestAction === 'cancel';

        if ($fullItemType == AssetModel::class) {
            $this->ensureModelRequestAuthorized($item, $user);
            if (! $isCancelRequest) {
                $this->ensureModelRequestProjectProvided($projectId);
                $this->ensureModelRequestNeededByDateProvided($neededByDate);
                $this->ensureModelRequestQuantityProvided($validated['request-quantity'] ?? null);
            }
        }

        $existingRequest = $fullItemType == AssetModel::class
            ? $this->findActiveModelProjectRequest($item, $user, (int) $projectId)
            : $item_request;

        if ($isCancelRequest) {
            if ($fullItemType == AssetModel::class && $existingRequest) {
                $existingRequest->update([
                    'canceled_at' => now(),
                    'status' => CheckoutRequest::STATUS_CANCELED,
                ]);
            } else {
                $item->cancelRequest($requestingUser);
            }
            $data['item_quantity'] = $existingRequest ? $existingRequest->quantity : 1;
            $logaction->logaction(ActionType::RequestCanceled);

            if (($settings->alert_email != '') && ($settings->alerts_enabled == '1') && (! config('app.lock_passwords'))) {
                $settings->notify(new RequestAssetCancelation($data));
            }

            return redirect()->back()->with('success')->with('success', trans('admin/hardware/message.requests.canceled'));
        } else {
            $requestAttributes = $fullItemType == AssetModel::class
                ? array_merge(
                    ['project_id' => $projectId, 'needed_by_date' => $neededByDate],
                    $this->estimateAssetModelRequest($item, $quantity, $neededByDate)
                )
                : [];

            $checkoutRequest = $existingRequest
                ? $this->updateExistingModelProjectRequest($existingRequest, $quantity, $requestAttributes)
                : $item->request($quantity, $requestAttributes);

            if ($fullItemType == AssetModel::class) {
                ResolveCheckoutRequestCoordinatorsAction::run($checkoutRequest, $data);
            }

            if (($settings->alert_email != '') && ($settings->alerts_enabled == '1') && (! config('app.lock_passwords'))) {
                $logaction->logaction('requested');
                $settings->notify(new RequestAssetNotification($data));
            }

            return redirect()->back()->with('success')->with('success', trans('admin/hardware/message.requests.success'));
        }
    }

    private function validateModelRequestPayload(Request $request): array
    {
        return $request->validate([
            'request-action' => ['nullable', 'string', 'in:create,update,cancel'],
            'request-quantity' => ['nullable', 'integer', 'min:1'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id,deleted_at,NULL'],
            'needed_by_date' => ['nullable', 'date'],
        ]);
    }

    private function ensureModelRequestAuthorized(AssetModel $item, User $user): void
    {
        if (! $user->hasAccess('models.request')) {
            throw new AuthorizationException('You are not authorized to request models.');
        }

        $this->authorize('view', $item);
    }

    private function estimateAssetModelRequest(AssetModel $item, int $quantity, ?string $neededByDate = null): array
    {
        return EstimateAssetModelReuseAction::run($item, $quantity, $neededByDate);
    }

    private function ensureModelRequestProjectProvided(?int $projectId): void
    {
        if (! $projectId) {
            throw ValidationException::withMessages([
                'project_id' => 'Project is required for model requests.',
            ]);
        }
    }

    private function ensureModelRequestNeededByDateProvided(?string $neededByDate): void
    {
        if (! $neededByDate) {
            throw ValidationException::withMessages([
                'needed_by_date' => 'Needed by date is required for model requests.',
            ]);
        }
    }

    private function ensureModelRequestQuantityProvided(?int $quantity): void
    {
        if (! $quantity) {
            throw ValidationException::withMessages([
                'request-quantity' => 'Total needed quantity is required for model requests.',
            ]);
        }
    }

    public function bulkRequestItems(Request $request): RedirectResponse
    {
        $modelQuantities = $request->input('model_quantities');

        if (is_string($modelQuantities)) {
            $decoded = json_decode($modelQuantities, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['model_quantities' => $decoded]);
            }
        }

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id,deleted_at,NULL'],
            'needed_by_date' => ['required', 'date'],
            'model_quantities' => ['required', 'array', 'min:1'],
            'model_quantities.*' => ['required', 'integer', 'min:1'],
        ]);

        $user = auth()->user();

        if (! $user->hasAccess('models.request')) {
            throw new AuthorizationException('You are not authorized to request models.');
        }

        DB::transaction(function () use ($validated, $user) {
            foreach ($validated['model_quantities'] as $modelId => $quantity) {
                $item = AssetModel::findOrFail((int) $modelId);
                $this->ensureModelRequestAuthorized($item, $user);

                $requestAttributes = array_merge(
                    [
                        'project_id' => (int) $validated['project_id'],
                        'needed_by_date' => $validated['needed_by_date'],
                    ],
                    $this->estimateAssetModelRequest($item, (int) $quantity, $validated['needed_by_date'])
                );

                $existingRequest = $this->findActiveModelProjectRequest($item, $user, (int) $validated['project_id']);

                $checkoutRequest = $existingRequest
                    ? $this->updateExistingModelProjectRequest($existingRequest, (int) $quantity, $requestAttributes)
                    : $item->request((int) $quantity, $requestAttributes);

                $data = [
                    'item_quantity' => (int) $quantity,
                    'requested_by' => $user->display_name,
                    'item' => $item,
                    'item_type' => 'model',
                    'target' => $user,
                    'project' => Project::find((int) $validated['project_id']),
                    'item_url' => route('view/model', $item->id),
                ];

                ResolveCheckoutRequestCoordinatorsAction::run($checkoutRequest, $data);
            }
        });

        return redirect()->back()->with('success', trans('admin/hardware/message.requests.success'));
    }

    public function storeRequestProject(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user || ! $user->hasAccess('models.request')) {
            throw new AuthorizationException('You are not authorized to create request projects.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique_undeleted:projects,name'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = new Project();
        $project->fill($validated);
        $project->created_by = $user->id;
        $project->save();

        return response()->json([
            'status' => 'success',
            'messages' => trans('admin/projects/message.create.success'),
            'payload' => [
                'id' => (int) $project->id,
                'name' => $project->name,
            ],
        ]);
    }

    public function updateSubmittedRequest(Request $request, CheckoutRequest $checkoutRequest): RedirectResponse
    {
        $this->authorizeSubmittedRequestAccess($checkoutRequest);

        if ($checkoutRequest->requestable_type !== AssetModel::class) {
            abort(404);
        }

        $validated = $this->validateModelRequestPayload($request);
        $quantity = (int) ($validated['request-quantity'] ?? 0);
        $projectId = (int) ($validated['project_id'] ?? 0);
        $neededByDate = $validated['needed_by_date'] ?? null;

        $item = $checkoutRequest->requestedItem;
        abort_if(! $item instanceof AssetModel, 404);

        $this->ensureModelRequestAuthorized($item, auth()->user());
        $this->ensureModelRequestProjectProvided($projectId);
        $this->ensureModelRequestNeededByDateProvided($neededByDate);
        $this->ensureModelRequestQuantityProvided($quantity);
        $this->ensureUniqueModelProjectRequest($item, auth()->user(), $projectId, $checkoutRequest->id);

        $checkoutRequest->quantity = $quantity;
        $checkoutRequest->project_id = $projectId;
        $checkoutRequest->needed_by_date = $neededByDate;
        $checkoutRequest->fill($this->estimateAssetModelRequest($item, $quantity, $neededByDate));
        $checkoutRequest->status = CheckoutRequest::STATUS_PENDING;
        $checkoutRequest->save();

        return redirect()->back()->with('success', trans('admin/hardware/message.requests.success'));
    }

    public function cancelSubmittedRequest(CheckoutRequest $checkoutRequest): RedirectResponse
    {
        $this->authorizeSubmittedRequestAccess($checkoutRequest);

        $checkoutRequest->canceled_at = now();
        $checkoutRequest->status = CheckoutRequest::STATUS_CANCELED;
        $checkoutRequest->save();

        return redirect()->back()->with('success', trans('admin/hardware/message.requests.canceled'));
    }

    /**
     * Process a specific requested asset
     * @param null $assetId
     */
    public function store(Asset $asset): RedirectResponse
    {
        try {
            CreateCheckoutRequestAction::run($asset, auth()->user());
            return redirect()->route('requestable-assets')->with('success')->with('success', trans('admin/hardware/message.requests.success'));
        } catch (AssetNotRequestable $e) {
            return redirect()->back()->with('error', 'Asset is not requestable');
        } catch (AuthorizationException $e) {
            return redirect()->back()->with('error', trans('admin/hardware/message.requests.error'));
        } catch (Exception $e) {
            report($e);
            return redirect()->back()->with('error', trans('general.something_went_wrong'));
        }
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        try {
            CancelCheckoutRequestAction::run($asset, auth()->user());
            return redirect()->route('requestable-assets')->with('success')->with('success', trans('admin/hardware/message.requests.canceled'));
        } catch (Exception $e) {
            report($e);
            return redirect()->back()->with('error', trans('general.something_went_wrong'));
        }
    }


    public function getRequestedAssets(Request $request) : View
    {
        if (! auth()->user()->hasAccess('models.request')) {
            throw new AuthorizationException('You are not authorized to view submitted requests.');
        }

        $modelId = $request->integer('model_id');
        $projectId = $request->integer('project_id');
        $query = [];
        $filteredModel = null;
        $filteredProject = null;

        if ($modelId) {
            $query['model_id'] = $modelId;
            $filteredModel = AssetModel::find($modelId);
        }

        if ($projectId) {
            $query['project_id'] = $projectId;
            $filteredProject = Project::find($projectId);
        }

        $projectSummary = null;
        if ($filteredProject) {
            $projectSummary = CheckoutRequest::projectSummaryForUser(auth()->id(), $filteredProject->id);
        }

        return view('account/requested', [
            'pageTitle' => 'Submitted Requests',
            'dataUrl' => route('api.assets.requested', $query),
            'requestMode' => 'requester',
            'filteredModel' => $filteredModel,
            'filteredProject' => $filteredProject,
            'projectSummary' => $projectSummary,
        ]);
    }

    private function findActiveModelProjectRequest(AssetModel $item, User $user, int $projectId): ?CheckoutRequest
    {
        return $item->requests()
            ->where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->whereNull('canceled_at')
            ->latest('id')
            ->first();
    }

    private function updateExistingModelProjectRequest(CheckoutRequest $request, int $quantity, array $attributes): CheckoutRequest
    {
        $request->quantity = $quantity;
        $request->fill($attributes);
        $request->status = $request->status ?: CheckoutRequest::STATUS_PENDING;
        $request->save();

        return $request->fresh();
    }

    private function authorizeSubmittedRequestAccess(CheckoutRequest $checkoutRequest): void
    {
        if (! auth()->user()->hasAccess('models.request')) {
            throw new AuthorizationException('You are not authorized to manage submitted requests.');
        }

        abort_unless((int) $checkoutRequest->user_id === (int) auth()->id(), 403);
    }

    private function ensureUniqueModelProjectRequest(AssetModel $item, User $user, int $projectId, ?int $ignoreRequestId = null): void
    {
        $duplicateQuery = $item->requests()
            ->where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->whereNull('canceled_at');

        if ($ignoreRequestId) {
            $duplicateQuery->where('id', '!=', $ignoreRequestId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'project_id' => 'You already have an active request for this model and project.',
            ]);
        }
    }
}
