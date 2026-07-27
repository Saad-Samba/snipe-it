<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\CustomField;
use Illuminate\Http\Request;

class ModelRequestsController extends Controller
{
    public function index(Request $request): array
    {
        if (! auth()->user()->hasAccess('models.request')) {
            abort(403, 'You are not authorized to view submitted requests.');
        }

        $checkoutRequests = CheckoutRequest::requesterScopedQuery(auth()->user())
            ->with([
                'requestedItem',
                'project',
                'company',
                'requestedDiscipline',
                'user',
            ])
        ;

        if ($request->filled('model_id')) {
            $checkoutRequests
                ->where('requestable_type', AssetModel::class)
                ->where('requestable_id', (int) $request->input('model_id'));
        }

        if ($request->filled('project_id')) {
            $checkoutRequests->where('project_id', (int) $request->input('project_id'));
        }

        if ($request->filled('project')) {
            $projectSearch = trim((string) $request->input('project'));
            $checkoutRequests->whereHas('project', function ($query) use ($projectSearch) {
                $query->where('name', 'LIKE', '%'.$projectSearch.'%');
            });
        }

        $checkoutRequests = $checkoutRequests->get();

        $results = [];
        $results['total'] = $checkoutRequests->count();
        $showableFields = [];

        foreach (CustomField::all() as $field) {
            if (($field->field_encrypted == '0') && ($field->show_in_requestable_list == '1')) {
                $showableFields[] = $field->db_column_name();
            }
        }

        foreach ($checkoutRequests as $checkoutRequest) {
            if (! $checkoutRequest || ! $checkoutRequest->itemRequested()) {
                continue;
            }

            $statusValue = $checkoutRequest->requesterAllocationStatus();
            $liveMetrics = $checkoutRequest->liveRequestMetrics();
            $requestAssetBucketBaseQuery = [
                'request_id' => $checkoutRequest->id,
            ];
            $requestDetailQuery = [
                'request_id' => $checkoutRequest->id,
                'request_bucket' => 'reusable_now',
            ];

            $assets = [
                'request_id' => (int) $checkoutRequest->id,
                'image' => e($checkoutRequest->itemRequested()->present()->getImageUrl()),
                'category' => e($this->categoryName($checkoutRequest)),
                'name' => e($checkoutRequest->name()),
                'model_id' => $checkoutRequest->requestable_type === AssetModel::class ? (int) $checkoutRequest->requestable_id : null,
                'type' => e($checkoutRequest->itemType()),
                'qty' => (int) $checkoutRequest->quantity,
                'requested_discipline_id' => $checkoutRequest->requested_discipline_id ? (int) $checkoutRequest->requested_discipline_id : null,
                'requested_discipline' => e(optional($checkoutRequest->requestedDiscipline)->name),
                'company_id' => $checkoutRequest->company_id ? (int) $checkoutRequest->company_id : null,
                'company' => e(optional($checkoutRequest->company)->name),
                'project_id' => $checkoutRequest->project_id ? (int) $checkoutRequest->project_id : null,
                'project' => e(optional($checkoutRequest->project)->name),
                'needed_by_date' => Helper::getFormattedDateObject($checkoutRequest->needed_by_date, 'date'),
                'needed_by_date_value' => optional($checkoutRequest->needed_by_date)->format('Y-m-d'),
                'reusable_quantity' => (int) ($liveMetrics['reusable_quantity'] ?? 0),
                'due_back_before_needed_by_quantity' => (int) ($liveMetrics['due_back_before_needed_by_quantity'] ?? 0),
                'procurement_shortfall' => (int) ($liveMetrics['procurement_shortfall'] ?? 0),
                'estimated_savings' => isset($liveMetrics['estimated_savings']) ? (float) $liveMetrics['estimated_savings'] : null,
                'estimated_savings_formatted' => isset($liveMetrics['estimated_savings'])
                    ? Helper::formatCurrencyOutput((float) $liveMetrics['estimated_savings'])
                    : null,
                'amount_to_buy' => (float) ($liveMetrics['amount_to_buy'] ?? 0),
                'amount_to_buy_formatted' => Helper::formatCurrencyOutput((float) ($liveMetrics['amount_to_buy'] ?? 0)),
                'reference_price_snapshot' => $checkoutRequest->reference_price_snapshot !== null ? (float) $checkoutRequest->reference_price_snapshot : null,
                'reference_price_snapshot_formatted' => $checkoutRequest->reference_price_snapshot !== null
                    ? Helper::formatCurrencyOutput($checkoutRequest->reference_price_snapshot)
                    : null,
                'total_need_cost' => $checkoutRequest->reference_price_snapshot !== null
                    ? round((float) $checkoutRequest->reference_price_snapshot * (int) $checkoutRequest->quantity, 2)
                    : null,
                'total_need_cost_formatted' => $checkoutRequest->reference_price_snapshot !== null
                    ? Helper::formatCurrencyOutput((float) $checkoutRequest->reference_price_snapshot * (int) $checkoutRequest->quantity)
                    : null,
                'reserved_count' => $checkoutRequest->reservedAssetsCount(),
                'reserved_by_other_rfqs_count' => $checkoutRequest->reservedByOtherRfqsCount(),
                'status' => e(ucfirst(str_replace('_', ' ', $statusValue))),
                'status_value' => e($statusValue),
                'rac_routing_status' => e(ucfirst(str_replace('_', ' ', $checkoutRequest->rac_routing_status ?: 'not_evaluated'))),
                'rac_routing_status_value' => e($checkoutRequest->rac_routing_status ?: 'not_evaluated'),
                'rac_unrouted_scopes' => $checkoutRequest->rac_unrouted_scopes ?? [],
                'location' => ($checkoutRequest->location()) ? e($checkoutRequest->location()->name) : null,
                'requested_by' => ($checkoutRequest->requestingUser()) ? e($checkoutRequest->requestingUser()->display_name) : null,
                'expected_checkin' => Helper::getFormattedDateObject($checkoutRequest->itemRequested()->expected_checkin, 'datetime'),
                'request_date' => Helper::getFormattedDateObject($checkoutRequest->created_at, 'datetime'),
                'updated_at' => Helper::getFormattedDateObject($checkoutRequest->updated_at, 'datetime'),
                'model_show_url' => ($checkoutRequest->requestable_type === AssetModel::class)
                    ? route('models.show', $checkoutRequest->requestable_id)
                    : null,
                'model_requests_url' => ($checkoutRequest->requestable_type === AssetModel::class)
                    ? route('requests.index', ['model_id' => $checkoutRequest->requestable_id])
                    : null,
                'project_requests_url' => $checkoutRequest->project_id
                    ? route('projects.show', ['project' => $checkoutRequest->project_id, 'tab' => 'requests'])
                    : null,
                'request_detail_url' => route('hardware.index', $requestDetailQuery),
                'reusable_now_url' => route('hardware.index', array_merge($requestAssetBucketBaseQuery, [
                    'request_bucket' => 'reusable_now',
                ])),
                'due_back_url' => route('hardware.index', array_merge($requestAssetBucketBaseQuery, [
                    'request_bucket' => 'due_back',
                ])),
                'reserved_assets_url' => route('hardware.index', array_merge($requestAssetBucketBaseQuery, [
                    'request_bucket' => 'reserved',
                ])),
                'reserved_by_other_project_url' => route('hardware.index', array_merge($requestAssetBucketBaseQuery, [
                    'request_bucket' => 'reserved_other_project',
                ])),
                'request_update_url' => route('requests.update', $checkoutRequest),
                'request_cancel_url' => route('requests.cancel', $checkoutRequest),
            ];

            $showField = [];
            foreach ($showableFields as $showableFieldName) {
                $showField['custom_fields.'.$showableFieldName] = $checkoutRequest->itemRequested()->{$showableFieldName};
            }

            $results['rows'][] = array_merge($assets, $showField);
        }

        return $results;
    }

    private function categoryName(CheckoutRequest $checkoutRequest): ?string
    {
        if ($checkoutRequest->requestable_type === AssetModel::class) {
            return optional(optional($checkoutRequest->itemRequested())->category)->name;
        }

        return optional(optional(optional($checkoutRequest->itemRequested())->model)->category)->name;
    }
}
