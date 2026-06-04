<?php

namespace App\Http\Controllers;

use App\Actions\CheckoutRequests\CancelCheckoutRequestAction;
use App\Actions\CheckoutRequests\CreateCheckoutRequestAction;
use App\Actions\CheckoutRequests\EstimateAssetModelReuseAction;
use App\Actions\CheckoutRequests\EstimateLicenseReuseAction;
use App\Actions\CheckoutRequests\ResolveCheckoutRequestCoordinatorsAction;
use App\Enums\ActionType;
use App\Exceptions\AssetNotRequestable;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\CheckoutRequestCoordinator;
use App\Models\Company;
use App\Models\Discipline;
use App\Models\License;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\RacScopedRequestSummaryNotification;
use App\Notifications\RequestAssetCancelation;
use App\Notifications\RequestAssetNotification;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModelRequestsController extends Controller
{
    private const MODEL_REQUEST_CART_SESSION_KEY = 'model_request_cart';

    public function getRequestableIndex(): View
    {
        $assets = Asset::with('model', 'defaultLoc', 'location', 'assignedTo', 'requests')->Hardware()->RequestableAssets();
        $models = AssetModel::with([
            'category',
            'requests',
            'assets' => function ($q) {
                $q->where('requestable', 1)
                    ->whereHas('assetstatus', fn ($s) => $s->where('archived', 0)
                        ->where(fn ($s) => $s->where('deployable', 1)->orWhere('pending', 1)));
            },
        ])->RequestableModels()->get();
        $licenses = License::with(['category', 'company', 'discipline', 'project', 'requests'])
            ->ActiveLicenses()
            ->where('reassignable', true)
            ->whereNotNull('company_id')
            ->whereNotNull('discipline_id')
            ->get();

        return view('account/requestable-assets', compact('assets', 'models', 'licenses'));
    }

    public function estimateRequestItem(Request $request, $itemType, $itemId = null): JsonResponse
    {
        if (! in_array($itemType, ['asset_model', 'license'], true)) {
            abort(404);
        }

        $validated = $this->validateModelRequestPayload($request);
        $this->ensureModelRequestProjectProvided($validated['project_id'] ?? null);
        $this->ensureModelRequestQuantityProvided($validated['request-quantity'] ?? null);
        $this->ensureModelRequestNeededByDateProvided($validated['needed_by_date'] ?? null);
        $this->ensureModelRequestCompanyProvided(isset($validated['company_id']) ? (int) $validated['company_id'] : null);

        if ($itemType === 'asset_model') {
            $item = AssetModel::findOrFail($itemId);
            $this->ensureModelRequestAuthorized($item, auth()->user());
            $this->ensureModelRequestDisciplineProvided(isset($validated['requested_discipline_id']) ? (int) $validated['requested_discipline_id'] : null);

            return response()->json(
                $this->estimateAssetModelRequest(
                    $item,
                    (int) $validated['request-quantity'],
                    $validated['needed_by_date'] ?? null
                )
            );
        }

        $item = License::findOrFail($itemId);
        $this->ensureLicenseRequestAuthorized($item, auth()->user());
        $this->ensureLicenseAssigneeProvided($validated['requested_for_type'] ?? null, $validated['requested_for_display'] ?? null);
        $this->ensureModelRequestDisciplineProvided(isset($validated['requested_discipline_id']) ? (int) $validated['requested_discipline_id'] : null);

        return response()->json(
            $this->estimateLicenseRequest(
                $item,
                (int) $validated['request-quantity'],
                $validated['needed_by_date'] ?? null
            )
        );
    }

    public function getRequestItem(Request $request, $itemType, $itemId = null, $cancel_by_admin = false, $requestingUser = null): RedirectResponse
    {
        $validated = $this->validateModelRequestPayload($request);

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
        $requestedDisciplineId = isset($validated['requested_discipline_id']) ? (int) $validated['requested_discipline_id'] : null;
        $companyId = isset($validated['company_id']) ? (int) $validated['company_id'] : null;
        $projectId = $validated['project_id'] ?? null;
        $neededByDate = $validated['needed_by_date'] ?? null;
        $data['item_quantity'] = $quantity;
        $data['requested_by'] = $user->display_name;
        $data['item'] = $item;
        $data['item_type'] = $itemType;
        $data['target'] = auth()->user();
        $data['company'] = $companyId ? Company::find($companyId) : null;
        $data['project'] = $projectId ? Project::find($projectId) : null;
        $data['item_url'] = $fullItemType == Asset::class
            ? route('hardware.show', $item->id)
            : ($fullItemType == License::class
                ? route('licenses.show', $item->id)
                : route("view/${itemType}", $item->id));

        $settings = Setting::getSettings();
        $item_request = $item->isRequestedBy($user);
        $isCancelRequest = $cancel_by_admin || $requestAction === 'cancel';

        $requestedForType = $validated['requested_for_type'] ?? null;
        $requestedForDisplay = $validated['requested_for_display'] ?? null;

        if ($fullItemType == AssetModel::class) {
            $this->ensureModelRequestAuthorized($item, $user);
            if (! $isCancelRequest) {
                $this->ensureModelRequestProjectProvided($projectId);
                $this->ensureModelRequestNeededByDateProvided($neededByDate);
                $this->ensureModelRequestQuantityProvided($validated['request-quantity'] ?? null);
                $this->ensureModelRequestDisciplineProvided($requestedDisciplineId);
                $this->ensureModelRequestCompanyProvided($companyId);
            }
            $existingRequest = $this->findActiveModelProjectRequest($item, $user, (int) $projectId, $requestedDisciplineId, $companyId);
        } elseif ($fullItemType == License::class) {
            $this->ensureLicenseRequestAuthorized($item, $user);
            if (! $isCancelRequest) {
                $this->ensureModelRequestProjectProvided($projectId);
                $this->ensureModelRequestNeededByDateProvided($neededByDate);
                $this->ensureModelRequestQuantityProvided($validated['request-quantity'] ?? null);
                $this->ensureModelRequestDisciplineProvided($requestedDisciplineId);
                $this->ensureModelRequestCompanyProvided($companyId);
                $this->ensureLicenseAssigneeProvided($requestedForType, $requestedForDisplay);
            }
            $existingRequest = $this->findActiveLicenseProjectRequest($item, $user, (int) $projectId, $requestedDisciplineId, $companyId);
        } else {
            $existingRequest = $item_request;
        }

        if (
            in_array($fullItemType, [AssetModel::class, License::class], true)
            && ! $isCancelRequest
            && $requestAction === 'create'
            && $existingRequest
        ) {
            throw ValidationException::withMessages([
                'project_id' => 'You already have an active request for this model, project, discipline, and company.',
            ]);
        }

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
        }

        $coordinatorNotificationBuckets = [];
        $requestAttributes = [];
        if ($fullItemType == AssetModel::class) {
            $requestAttributes = array_merge(
                [
                    'project_id' => $projectId,
                    'needed_by_date' => $neededByDate,
                    'requested_discipline_id' => $requestedDisciplineId,
                    'company_id' => $companyId,
                ],
                $this->estimateAssetModelRequest($item, $quantity, $neededByDate)
            );
        } elseif ($fullItemType == License::class) {
            $requestAttributes = array_merge(
                [
                    'project_id' => $projectId,
                    'needed_by_date' => $neededByDate,
                    'requested_discipline_id' => $requestedDisciplineId,
                    'company_id' => $companyId,
                    'requested_for_type' => $requestedForType,
                    'requested_for_display' => $requestedForDisplay,
                ],
                $this->estimateLicenseRequest($item, $quantity, $neededByDate)
            );
        }

        $checkoutRequest = $existingRequest
            ? $this->updateExistingProjectRequest($existingRequest, $quantity, $requestAttributes)
            : $item->request($quantity, $requestAttributes);

        if (in_array($fullItemType, [AssetModel::class, License::class], true)) {
            $coordinatorMatches = ResolveCheckoutRequestCoordinatorsAction::run($checkoutRequest);
            $coordinatorNotificationBuckets = $this->addCoordinatorSummaryLine(
                $coordinatorNotificationBuckets,
                $checkoutRequest,
                $user,
                $data['project'],
                $data['requested_date'],
                $coordinatorMatches
            );
        }

        if (($settings->alert_email != '') && ($settings->alerts_enabled == '1') && (! config('app.lock_passwords'))) {
            $logaction->logaction('requested');
            $settings->notify(new RequestAssetNotification($data));
        }

        $this->sendCoordinatorSummaryNotifications($coordinatorNotificationBuckets);

        return redirect()->back()->with('success')->with('success', trans('admin/hardware/message.requests.success'));
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
            'company_id' => ['required', 'integer', 'exists:companies,id'],
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
                        'company_id' => (int) $validated['company_id'],
                        'project_id' => (int) $validated['project_id'],
                        'needed_by_date' => $validated['needed_by_date'],
                    ],
                    $this->estimateAssetModelRequest($item, (int) $quantity, $validated['needed_by_date'])
                );

                $existingRequest = $this->findActiveModelProjectRequest($item, $user, (int) $validated['project_id'], null, (int) $validated['company_id']);
                $checkoutRequest = $existingRequest
                    ? $this->updateExistingProjectRequest($existingRequest, (int) $quantity, $requestAttributes)
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

    public function addRequestCartItems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.model_id' => ['required', 'integer', 'exists:models,id,deleted_at,NULL'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.discipline_id' => ['required', 'integer', 'exists:disciplines,id,deleted_at,NULL'],
            'lines.*.company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $user = auth()->user();
        if (! $user || ! $user->hasAccess('models.request')) {
            throw new AuthorizationException('You are not authorized to request models.');
        }

        $cart = $this->getModelRequestCart($request);
        foreach ($validated['lines'] as $line) {
            $model = AssetModel::findOrFail((int) $line['model_id']);
            $this->ensureModelRequestAuthorized($model, $user);
            $disciplineId = (int) $line['discipline_id'];
            $companyId = (int) $line['company_id'];
            $key = $this->makeModelRequestCartKey((int) $line['model_id'], $disciplineId, $companyId);
            $cart[$key] = [
                'model_id' => (int) $line['model_id'],
                'quantity' => (int) $line['quantity'],
                'discipline_id' => $disciplineId,
                'company_id' => $companyId,
            ];
        }

        $this->putModelRequestCart($request, $cart);

        return response()->json(['status' => 'success', 'cart_count' => count($cart)]);
    }

    public function removeRequestCartItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_id' => ['required', 'integer'],
            'discipline_id' => ['required', 'integer'],
            'company_id' => ['required', 'integer'],
        ]);

        $cart = $this->getModelRequestCart($request);
        unset($cart[$this->makeModelRequestCartKey((int) $validated['model_id'], (int) $validated['discipline_id'], (int) $validated['company_id'])]);
        $this->putModelRequestCart($request, $cart);

        return response()->json(['status' => 'success', 'cart_count' => count($cart)]);
    }

    public function clearRequestCart(Request $request): JsonResponse
    {
        $request->session()->forget(self::MODEL_REQUEST_CART_SESSION_KEY);

        return response()->json(['status' => 'success', 'cart_count' => 0]);
    }

    public function previewRequestCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id,deleted_at,NULL'],
            'needed_by_date' => ['nullable', 'date'],
        ]);

        $user = auth()->user();
        if (! $user || ! $user->hasAccess('models.request')) {
            throw new AuthorizationException('You are not authorized to request models.');
        }

        $projectId = isset($validated['project_id']) ? (int) $validated['project_id'] : null;
        $neededByDate = $validated['needed_by_date'] ?? null;
        $cart = $this->getModelRequestCart($request);
        $lines = [];
        $totals = [
            'quantity' => 0,
            'reusable_quantity' => 0,
            'due_back_before_needed_by_quantity' => 0,
            'reserved_count' => 0,
            'reserved_by_other_rfqs_count' => 0,
            'procurement_shortfall' => 0,
            'estimated_savings' => 0.0,
            'amount_to_buy' => 0.0,
        ];

        foreach ($cart as $line) {
            $model = AssetModel::findOrFail((int) $line['model_id']);
            $this->ensureModelRequestAuthorized($model, $user);
            $discipline = Discipline::findOrFail((int) $line['discipline_id']);
            $company = Company::findOrFail((int) $line['company_id']);

            $previewLine = $this->buildModelRequestPreviewLine(
                $model,
                $discipline,
                $company,
                (int) $line['quantity'],
                $projectId,
                $neededByDate
            );

            $lines[] = $previewLine;
            $totals['quantity'] += $previewLine['quantity'];
            $totals['reusable_quantity'] += $previewLine['reusable_quantity'];
            $totals['due_back_before_needed_by_quantity'] += $previewLine['due_back_before_needed_by_quantity'];
            $totals['reserved_count'] += $previewLine['reserved_count'];
            $totals['reserved_by_other_rfqs_count'] += $previewLine['reserved_by_other_rfqs_count'];
            $totals['procurement_shortfall'] += $previewLine['procurement_shortfall'];
            $totals['estimated_savings'] += $previewLine['estimated_savings'];
            $totals['amount_to_buy'] += $previewLine['amount_to_buy'];
        }

        $totals['estimated_savings'] = round($totals['estimated_savings'], 2);
        $totals['amount_to_buy'] = round($totals['amount_to_buy'], 2);

        return response()->json([
            'status' => 'success',
            'cart_count' => count($cart),
            'lines' => $lines,
            'totals' => $totals,
            'totals_formatted' => [
                'estimated_savings' => \App\Helpers\Helper::formatCurrencyOutput($totals['estimated_savings']),
                'amount_to_buy' => \App\Helpers\Helper::formatCurrencyOutput($totals['amount_to_buy']),
            ],
        ]);
    }

    public function submitRequestCart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id,deleted_at,NULL'],
            'needed_by_date' => ['required', 'date'],
        ]);

        $user = auth()->user();
        if (! $user || ! $user->hasAccess('models.request')) {
            throw new AuthorizationException('You are not authorized to request models.');
        }

        $cart = $this->getModelRequestCart($request);
        if (empty($cart)) {
            throw ValidationException::withMessages([
                'cart' => 'Add at least one model to the request cart.',
            ]);
        }

        $project = Project::find((int) $validated['project_id']);
        $submittedAt = now()->toDateTimeString();
        $coordinatorNotificationBuckets = [];

        DB::transaction(function () use ($cart, $validated, $user, $project, $submittedAt, &$coordinatorNotificationBuckets) {
            foreach ($cart as $line) {
                $item = AssetModel::findOrFail((int) $line['model_id']);
                $disciplineId = (int) $line['discipline_id'];
                $companyId = (int) $line['company_id'];
                $quantity = (int) $line['quantity'];

                $this->ensureModelRequestAuthorized($item, $user);
                $this->ensureModelRequestDisciplineProvided($disciplineId);
                $this->ensureModelRequestCompanyProvided($companyId);

                $requestAttributes = array_merge(
                    [
                        'company_id' => $companyId,
                        'project_id' => (int) $validated['project_id'],
                        'needed_by_date' => $validated['needed_by_date'],
                        'requested_discipline_id' => $disciplineId,
                    ],
                    $this->estimateAssetModelRequest($item, $quantity, $validated['needed_by_date'])
                );

                $existingRequest = $this->findActiveModelProjectRequest($item, $user, (int) $validated['project_id'], $disciplineId, $companyId);
                $checkoutRequest = $existingRequest
                    ? $this->updateExistingProjectRequest($existingRequest, $quantity, $requestAttributes)
                    : $item->request($quantity, $requestAttributes);

                $coordinatorMatches = ResolveCheckoutRequestCoordinatorsAction::run($checkoutRequest);
                $coordinatorNotificationBuckets = $this->addCoordinatorSummaryLine(
                    $coordinatorNotificationBuckets,
                    $checkoutRequest,
                    $user,
                    $project,
                    $submittedAt,
                    $coordinatorMatches
                );
            }
        });

        $request->session()->forget(self::MODEL_REQUEST_CART_SESSION_KEY);
        $this->sendCoordinatorSummaryNotifications($coordinatorNotificationBuckets);

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
            'payload' => ['id' => (int) $project->id, 'name' => $project->name],
        ]);
    }

    public function updateSubmittedRequest(Request $request, CheckoutRequest $checkoutRequest): RedirectResponse
    {
        $this->authorizeSubmittedRequestAccess($checkoutRequest);
        if ($checkoutRequest->requestable_type !== AssetModel::class) {
            if ($checkoutRequest->requestable_type !== License::class) {
                abort(404);
            }
        }

        $validated = $this->validateModelRequestPayload($request);
        $quantity = (int) ($validated['request-quantity'] ?? 0);
        $projectId = (int) ($validated['project_id'] ?? 0);
        $requestedDisciplineId = isset($validated['requested_discipline_id']) ? (int) $validated['requested_discipline_id'] : 0;
        $companyId = isset($validated['company_id']) ? (int) $validated['company_id'] : 0;
        $neededByDate = $validated['needed_by_date'] ?? null;

        $item = $checkoutRequest->requestedItem;
        abort_if(! $item instanceof AssetModel && ! $item instanceof License, 404);

        $this->ensureModelRequestProjectProvided($projectId);
        $this->ensureModelRequestNeededByDateProvided($neededByDate);
        $this->ensureModelRequestQuantityProvided($quantity);
        $this->ensureModelRequestDisciplineProvided($requestedDisciplineId);
        $this->ensureModelRequestCompanyProvided($companyId);

        $checkoutRequest->quantity = $quantity;
        $checkoutRequest->project_id = $projectId;
        $checkoutRequest->requested_discipline_id = $requestedDisciplineId;
        $checkoutRequest->company_id = $companyId;
        $checkoutRequest->needed_by_date = $neededByDate;

        if ($item instanceof AssetModel) {
            $this->ensureModelRequestAuthorized($item, auth()->user());
            $this->ensureUniqueModelProjectRequest($item, auth()->user(), $projectId, $requestedDisciplineId, $companyId, $checkoutRequest->id);
            $checkoutRequest->fill($this->estimateAssetModelRequest($item, $quantity, $neededByDate));
        } else {
            $this->ensureLicenseRequestAuthorized($item, auth()->user());
            $requestedForType = $validated['requested_for_type'] ?? null;
            $requestedForDisplay = $validated['requested_for_display'] ?? null;
            $this->ensureLicenseAssigneeProvided($requestedForType, $requestedForDisplay);
            $this->ensureUniqueLicenseProjectRequest($item, auth()->user(), $projectId, $requestedDisciplineId, $companyId, $checkoutRequest->id);
            $checkoutRequest->requested_for_type = $requestedForType;
            $checkoutRequest->requested_for_display = $requestedForDisplay;
            $checkoutRequest->fill($this->estimateLicenseRequest($item, $quantity, $neededByDate));
        }

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

    public function getRequestedAssets(Request $request): View
    {
        if (! auth()->user()->hasAccess('models.request') && ! auth()->user()->hasAccess('licenses.request')) {
            throw new AuthorizationException('You are not authorized to view submitted requests.');
        }

        $modelId = $request->integer('model_id');
        $licenseId = $request->integer('license_id');
        $projectId = $request->integer('project_id');
        $query = [];
        $filteredItem = null;
        $filteredProject = null;

        if ($modelId) {
            $query['model_id'] = $modelId;
            $filteredItem = AssetModel::find($modelId);
        }

        if ($licenseId) {
            $query['license_id'] = $licenseId;
            $filteredItem = License::find($licenseId);
        }

        if ($projectId) {
            $query['project_id'] = $projectId;
            $filteredProject = Project::find($projectId);
        }

        $projectSummary = $filteredProject
            ? CheckoutRequest::projectSummaryForUser(auth()->id(), $filteredProject->id)
            : null;

        return view('account/requested', [
            'pageTitle' => 'Submitted Requests',
            'dataUrl' => route('api.requests.index', $query),
            'requestMode' => 'requester',
            'filteredItem' => $filteredItem,
            'filteredProject' => $filteredProject,
            'projectSummary' => $projectSummary,
        ]);
    }

    private function validateModelRequestPayload(Request $request): array
    {
        return $request->validate([
            'request-action' => ['nullable', 'string', 'in:create,update,cancel'],
            'request-quantity' => ['nullable', 'integer', 'min:1'],
            'requested_discipline_id' => ['nullable', 'integer', 'exists:disciplines,id,deleted_at,NULL'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'requested_for_type' => ['nullable', 'string', 'in:user,asset'],
            'requested_for_display' => ['nullable', 'string', 'max:255'],
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

    private function ensureLicenseRequestAuthorized(License $item, User $user): void
    {
        if (! $user->hasAccess('licenses.request')) {
            throw new AuthorizationException('You are not authorized to request licenses.');
        }

        $this->authorize('view', $item);

        if (! $item->isReusableForRequest()) {
            throw ValidationException::withMessages([
                'license' => 'This license is not eligible for reuse requests.',
            ]);
        }
    }

    private function estimateLicenseRequest(License $item, int $quantity, ?string $neededByDate = null): array
    {
        return EstimateLicenseReuseAction::run($item, $quantity, $neededByDate);
    }

    private function ensureModelRequestProjectProvided(?int $projectId): void
    {
        if (! $projectId) {
            throw ValidationException::withMessages(['project_id' => 'Project is required for model requests.']);
        }
    }

    private function ensureModelRequestNeededByDateProvided(?string $neededByDate): void
    {
        if (! $neededByDate) {
            throw ValidationException::withMessages(['needed_by_date' => 'Needed by date is required for model requests.']);
        }
    }

    private function ensureModelRequestQuantityProvided(?int $quantity): void
    {
        if (! $quantity) {
            throw ValidationException::withMessages(['request-quantity' => 'Total needed quantity is required for model requests.']);
        }
    }

    private function ensureModelRequestDisciplineProvided(?int $disciplineId): void
    {
        if (! $disciplineId) {
            throw ValidationException::withMessages(['requested_discipline_id' => 'Discipline is required for model requests.']);
        }
    }

    private function ensureModelRequestCompanyProvided(?int $companyId): void
    {
        if (! $companyId) {
            throw ValidationException::withMessages(['company_id' => 'Company is required for model requests.']);
        }
    }

    private function findActiveModelProjectRequest(AssetModel $item, User $user, int $projectId, ?int $disciplineId = null, ?int $companyId = null): ?CheckoutRequest
    {
        return $item->requests()
            ->where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->where('requested_discipline_id', $disciplineId)
            ->where('company_id', $companyId)
            ->whereNull('canceled_at')
            ->latest('id')
            ->first();
    }

    private function findActiveLicenseProjectRequest(License $item, User $user, int $projectId, ?int $disciplineId = null, ?int $companyId = null): ?CheckoutRequest
    {
        return $item->requests()
            ->where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->where('requested_discipline_id', $disciplineId)
            ->where('company_id', $companyId)
            ->whereNull('canceled_at')
            ->latest('id')
            ->first();
    }

    private function updateExistingProjectRequest(CheckoutRequest $request, int $quantity, array $attributes): CheckoutRequest
    {
        $request->quantity = $quantity;
        $request->fill($attributes);
        $request->status = $request->status ?: CheckoutRequest::STATUS_PENDING;
        $request->save();

        return $request->fresh();
    }

    private function getModelRequestCart(Request $request): array
    {
        $cart = $request->session()->get(self::MODEL_REQUEST_CART_SESSION_KEY, []);
        $normalizedCart = $this->normalizeModelRequestCart(is_array($cart) ? $cart : []);

        if ($normalizedCart !== $cart) {
            $request->session()->put(self::MODEL_REQUEST_CART_SESSION_KEY, $normalizedCart);
        }

        return $normalizedCart;
    }

    private function putModelRequestCart(Request $request, array $cart): void
    {
        $request->session()->put(self::MODEL_REQUEST_CART_SESSION_KEY, $this->normalizeModelRequestCart($cart));
    }

    private function normalizeModelRequestCart(array $cart): array
    {
        $normalizedCart = [];

        foreach ($cart as $line) {
            if (! is_array($line)) {
                continue;
            }

            $modelId = (int) ($line['model_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);
            $disciplineId = (int) ($line['discipline_id'] ?? 0);
            $companyId = (int) ($line['company_id'] ?? 0);

            if ($modelId < 1 || $quantity < 1 || $disciplineId < 1 || $companyId < 1) {
                continue;
            }

            $normalizedCart[$this->makeModelRequestCartKey($modelId, $disciplineId, $companyId)] = [
                'model_id' => $modelId,
                'quantity' => $quantity,
                'discipline_id' => $disciplineId,
                'company_id' => $companyId,
            ];
        }

        return $normalizedCart;
    }

    private function makeModelRequestCartKey(int $modelId, int $disciplineId, int $companyId): string
    {
        return $modelId.':'.$disciplineId.':'.$companyId;
    }

    private function addCoordinatorSummaryLine(array $buckets, CheckoutRequest $checkoutRequest, User $requester, ?Project $project, string $submittedAt, $coordinatorMatches): array
    {
        foreach ($coordinatorMatches as $match) {
            $coordinator = $match['coordinator'] ?? null;
            $reusableQuantity = (int) ($match['reusable_quantity'] ?? 0);

            if (! $coordinator || $reusableQuantity < 1) {
                continue;
            }

            $coordinatorId = (int) $match['user_id'];
            if (! isset($buckets[$coordinatorId])) {
                $buckets[$coordinatorId] = [
                    'rac_user' => $coordinator,
                    'requester' => $requester,
                    'submitted_at' => $submittedAt,
                    'project_name' => $project?->name,
                    'lines' => [],
                ];
            }

            $buckets[$coordinatorId]['lines'][] = [
                'request_id' => (int) $checkoutRequest->id,
                'model_name' => $checkoutRequest->requestedItem()?->name ?? $checkoutRequest->name(),
                'project_name' => $project?->name ?: '-',
                'company_name' => optional($checkoutRequest->company)->name ?: '-',
                'discipline_name' => optional($checkoutRequest->requestedDiscipline)->name ?: '-',
                'requested_quantity' => (int) $checkoutRequest->quantity,
                'reusable_quantity' => $reusableQuantity,
                'needed_by_date' => optional($checkoutRequest->needed_by_date)?->format('Y-m-d') ?: '-',
                'model_show_url' => $checkoutRequest->requestable_type === License::class
                    ? route('licenses.show', $checkoutRequest->requestable_id)
                    : route('models.show', $checkoutRequest->requestable_id),
                'project_requests_url' => $checkoutRequest->project_id
                    ? route('projects.show', ['project' => $checkoutRequest->project_id, 'tab' => 'requests'])
                    : route('requests.index'),
                'request_detail_url' => $this->requestDetailUrlFor($checkoutRequest),
            ];
        }

        return $buckets;
    }

    private function sendCoordinatorSummaryNotifications(array $buckets): void
    {
        foreach ($buckets as $bucket) {
            $bucket['rac_user']->notify(new RacScopedRequestSummaryNotification($bucket));

            $requestIds = collect($bucket['lines'] ?? [])
                ->pluck('request_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($requestIds->isEmpty()) {
                continue;
            }

            CheckoutRequestCoordinator::query()
                ->where('user_id', $bucket['rac_user']->id)
                ->whereIn('checkout_request_id', $requestIds)
                ->whereNull('initial_notified_at')
                ->update(['initial_notified_at' => now()]);
        }
    }

    private function requestDetailUrlFor(CheckoutRequest $checkoutRequest): string
    {
        if ($checkoutRequest->requestable_type === License::class) {
            return route('licenses.index', [
                'request_id' => $checkoutRequest->id,
                'license_id' => $checkoutRequest->requestable_id,
            ]);
        }

        return route('hardware.index', [
            'request_id' => $checkoutRequest->id,
            'request_bucket' => 'reusable_now',
        ]);
    }

    private function buildModelRequestPreviewLine(AssetModel $model, Discipline $discipline, Company $company, int $quantity, ?int $projectId, ?string $neededByDate): array
    {
        $estimate = $neededByDate
            ? $this->estimateAssetModelRequest($model, $quantity, $neededByDate)
            : [
                'reusable_quantity' => 0,
                'due_back_before_needed_by_quantity' => 0,
                'procurement_shortfall' => 0,
                'estimated_savings' => 0,
                'reference_price_snapshot' => $model->reference_price !== null ? (float) $model->reference_price : null,
            ];

        $requestContext = new CheckoutRequest([
            'project_id' => $projectId,
            'requested_discipline_id' => $discipline->id,
            'quantity' => $quantity,
            'procurement_shortfall' => $estimate['procurement_shortfall'] ?? 0,
            'reference_price_snapshot' => $estimate['reference_price_snapshot'] ?? null,
        ]);
        $requestContext->requestable_id = $model->id;
        $requestContext->requestable_type = AssetModel::class;

        $reservedCount = $projectId ? $requestContext->reservedAssetsCount() : 0;
        $reservedByOtherProjectsCount = $projectId ? $requestContext->reservedByOtherRfqsCount() : 0;
        $amountToBuy = round(((float) ($estimate['procurement_shortfall'] ?? 0)) * ((float) ($estimate['reference_price_snapshot'] ?? 0)), 2);

        return [
            'model_id' => (int) $model->id,
            'model_name' => $model->name,
            'discipline_id' => (int) $discipline->id,
            'discipline_name' => $discipline->name,
            'company_id' => (int) $company->id,
            'company_name' => $company->name,
            'quantity' => $quantity,
            'reusable_quantity' => (int) ($estimate['reusable_quantity'] ?? 0),
            'due_back_before_needed_by_quantity' => (int) ($estimate['due_back_before_needed_by_quantity'] ?? 0),
            'reserved_count' => $reservedCount,
            'reserved_by_other_rfqs_count' => $reservedByOtherProjectsCount,
            'procurement_shortfall' => (int) ($estimate['procurement_shortfall'] ?? 0),
            'estimated_savings' => round((float) ($estimate['estimated_savings'] ?? 0), 2),
            'estimated_savings_formatted' => \App\Helpers\Helper::formatCurrencyOutput((float) ($estimate['estimated_savings'] ?? 0)),
            'amount_to_buy' => $amountToBuy,
            'amount_to_buy_formatted' => \App\Helpers\Helper::formatCurrencyOutput($amountToBuy),
        ];
    }

    private function authorizeSubmittedRequestAccess(CheckoutRequest $checkoutRequest): void
    {
        if (! auth()->user()->hasAccess('models.request') && ! auth()->user()->hasAccess('licenses.request')) {
            throw new AuthorizationException('You are not authorized to manage submitted requests.');
        }

        abort_unless((int) $checkoutRequest->user_id === (int) auth()->id(), 403);
    }

    private function ensureUniqueModelProjectRequest(AssetModel $item, User $user, int $projectId, int $disciplineId, int $companyId, ?int $ignoreRequestId = null): void
    {
        $duplicateQuery = $item->requests()
            ->where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->where('requested_discipline_id', $disciplineId)
            ->where('company_id', $companyId)
            ->whereNull('canceled_at');

        if ($ignoreRequestId) {
            $duplicateQuery->where('id', '!=', $ignoreRequestId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'project_id' => 'You already have an active request for this model, project, discipline, and company.',
            ]);
        }
    }

    private function ensureUniqueLicenseProjectRequest(License $item, User $user, int $projectId, int $disciplineId, int $companyId, ?int $ignoreRequestId = null): void
    {
        $duplicateQuery = $item->requests()
            ->where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->where('requested_discipline_id', $disciplineId)
            ->where('company_id', $companyId)
            ->whereNull('canceled_at');

        if ($ignoreRequestId) {
            $duplicateQuery->where('id', '!=', $ignoreRequestId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'project_id' => 'You already have an active request for this license, project, discipline, and company.',
            ]);
        }
    }

    private function ensureLicenseAssigneeProvided(?string $requestedForType, ?string $requestedForDisplay): void
    {
        if (! $requestedForType) {
            throw ValidationException::withMessages([
                'requested_for_type' => 'Assignee type is required for license requests.',
            ]);
        }

        if (! $requestedForDisplay) {
            throw ValidationException::withMessages([
                'requested_for_display' => 'Assignee is required for license requests.',
            ]);
        }
    }
}
