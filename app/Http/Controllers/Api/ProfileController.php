<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ProfileTransformer;
use App\Models\AssetModel;
use App\Models\CheckoutRequest;
use App\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\TokenRepository;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Facades\Gate;
use App\Models\CustomField;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{

    /**
     * The token repository implementation.
     *
     * @var \Laravel\Passport\TokenRepository
     */
    protected $tokenRepository;

    /**
     * Create a controller instance.
     *
     * @param  \Laravel\Passport\TokenRepository  $tokenRepository
     * @param  \Illuminate\Contracts\Validation\Factory  $validation
     * @return void
     */
    public function __construct(TokenRepository $tokenRepository, ValidationFactory $validation)
    {
        $this->validation = $validation;
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * Display a listing of requested assets.
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v4.3.0]
     */
    public function requestedAssets(Request $request) :  array
    {
        $checkoutRequests = CheckoutRequest::query()
            ->with([
                'requestedItem',
                'project',
                'user',
            ]);

        $checkoutRequests->where('user_id', auth()->id())
            ->whereNull('canceled_at');

        if ($request->filled('model_id')) {
            $checkoutRequests
                ->where('requestable_type', \App\Models\AssetModel::class)
                ->where('requestable_id', (int) $request->input('model_id'));
        }

        if ($request->filled('license_id')) {
            $checkoutRequests
                ->where('requestable_type', License::class)
                ->where('requestable_id', (int) $request->input('license_id'));
        }

        $checkoutRequests = $checkoutRequests->get();

        $results = array();
        $show_field = array();
        $showable_fields = array();
        $results['total'] = $checkoutRequests->count();

        $all_custom_fields = CustomField::all(); //used as a 'cache' of custom fields throughout this page load
        foreach ($all_custom_fields as $field) {
            if (($field->field_encrypted=='0') && ($field->show_in_requestable_list=='1')) {
                $showable_fields[] = $field->db_column_name();
            }
        }

        foreach ($checkoutRequests as $checkoutRequest) {

            // Make sure the asset and request still exist
            if ($checkoutRequest && $checkoutRequest->itemRequested()) {
                $bookedCount = $checkoutRequest->bookedQuantity();
                $statusValue = $bookedCount >= $checkoutRequest->quantity
                    ? CheckoutRequest::STATUS_FULLY_ALLOCATED
                    : ($bookedCount > 0 ? CheckoutRequest::STATUS_PARTIALLY_ALLOCATED : CheckoutRequest::STATUS_PENDING);
                $isAssetModelRequest = $checkoutRequest->requestable_type === AssetModel::class;
                $isLicenseRequest = $checkoutRequest->requestable_type === License::class;
                $itemShowUrl = $isAssetModelRequest
                    ? route('models.show', $checkoutRequest->requestable_id)
                    : ($isLicenseRequest ? route('licenses.show', $checkoutRequest->requestable_id) : null);
                $itemRequestsUrl = $isAssetModelRequest
                    ? route('account.requested', ['model_id' => $checkoutRequest->requestable_id])
                    : ($isLicenseRequest ? route('account.requested', ['license_id' => $checkoutRequest->requestable_id]) : null);
                $requestDetailUrl = $isAssetModelRequest
                    ? route('hardware.index', [
                        'request_id' => $checkoutRequest->id,
                        'status' => 'RTD',
                        'model_id' => $checkoutRequest->requestable_id,
                    ])
                    : ($isLicenseRequest ? route('licenses.index', [
                        'request_id' => $checkoutRequest->id,
                        'license_id' => $checkoutRequest->requestable_id,
                    ]) : null);
                $itemImageUrl = method_exists($checkoutRequest->itemRequested()->present(), 'getImageUrl')
                    ? e($checkoutRequest->itemRequested()->present()->getImageUrl())
                    : null;

                $assets = [
                    'request_id' => (int) $checkoutRequest->id,
                    'image' => $itemImageUrl,
                    'name' => e($checkoutRequest->name()),
                    'model_id' => $isAssetModelRequest ? (int) $checkoutRequest->requestable_id : null,
                    'license_id' => $isLicenseRequest ? (int) $checkoutRequest->requestable_id : null,
                    'type' => e($checkoutRequest->itemType()),
                    'qty' => (int) $checkoutRequest->quantity,
                    'project' => e(optional($checkoutRequest->project)->name),
                    'booked_count' => $bookedCount,
                    'status' => e(ucfirst(str_replace('_', ' ', $statusValue))),
                    'status_value' => e($statusValue),
                    'location' => ($checkoutRequest->location()) ? e($checkoutRequest->location()->name) : null,
                    'requested_by' => ($checkoutRequest->requestingUser()) ? e($checkoutRequest->requestingUser()->display_name) : null,
                    'expected_checkin' => Helper::getFormattedDateObject($checkoutRequest->itemRequested()->expected_checkin, 'datetime'),
                    'request_date' => Helper::getFormattedDateObject($checkoutRequest->created_at, 'datetime'),
                    'updated_at' => Helper::getFormattedDateObject($checkoutRequest->updated_at, 'datetime'),
                    'model_show_url' => $itemShowUrl,
                    'item_show_url' => $itemShowUrl,
                    'model_requests_url' => $itemRequestsUrl,
                    'item_requests_url' => $itemRequestsUrl,
                    'request_detail_url' => $requestDetailUrl,
                ];

                foreach ($showable_fields as $showable_field_name) {
                    $show_field['custom_fields.'.$showable_field_name] =  $checkoutRequest->itemRequested()->{$showable_field_name};
                }

                // Merge the plain asset data and the custom fields data
                $results['rows'][] = array_merge($assets, $show_field);
            }


        }

        return $results;
    }


    /**
     * Delete an API token
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v6.0.5]
     */
    public function createApiToken(Request $request) : JsonResponse
    {

        if (!Gate::allows('self.api')) {
            abort(403);
        }

        $accessTokenName = $request->input('name', 'Auth Token');

        if ($accessToken = auth()->user()->createToken($accessTokenName)->accessToken) {

            // Get the ID so we can return that with the payload
            $token = DB::table('oauth_access_tokens')->where('user_id', '=', auth()->id())->where('name','=',$accessTokenName)->orderBy('created_at', 'desc')->first();
            $accessTokenData['id'] = $token->id;
            $accessTokenData['token'] = $accessToken;
            $accessTokenData['name'] = $accessTokenName;
            return response()->json(Helper::formatStandardApiResponse('success', $accessTokenData, trans('account/general.personal_api_keys_success', ['key' => $accessTokenName])));
        }
        return response()->json(Helper::formatStandardApiResponse('error', null, 'Token could not be created.'));

    }


    /**
     * Delete an API token
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v6.0.5]
     */
    public function deleteApiToken($tokenId) : Response
    {

        if (!Gate::allows('self.api')) {
            abort(403);
        }

        $token = $this->tokenRepository->findForUser(
            $tokenId, auth()->user()->getAuthIdentifier()
        );

        if (is_null($token)) {
            return new Response('', 404);
        }

        $token->revoke();

        return new Response('', Response::HTTP_NO_CONTENT);

    }


    /**
     * Show user's API tokens
     *
     * @author [A. Gianotto] [<snipe@snipe.net>]
     * @since [v6.0.5]
     */
    public function showApiTokens() : JsonResponse
    {

        if (!Gate::allows('self.api')) {
            abort(403);
        }
        
        $tokens = $this->tokenRepository->forUser(auth()->user()->getAuthIdentifier());
        $token_values = $tokens->load('client')->filter(function ($token) {
            return $token->client->personal_access_client && ! $token->revoked;
        })->values();

        return response()->json(Helper::formatStandardApiResponse('success', $token_values, null));

    }

    /**
     * Display the EULAs accepted by the user.
     *
     *  @param \App\Http\Transformers\ActionlogsTransformer $transformer
     *  @return \Illuminate\Http\JsonResponse
     *@since [v8.1.16]
     * @author [Godfrey Martinez] [<gmartinez@grokability.com>]
     */
    public function eulas(ProfileTransformer $transformer)
    {
        // Only return this user's EULAs
        $eulas = auth()->user()->eulas;
        return response()->json(
            $transformer->transformFiles($eulas, $eulas->count())
        );
    }


}
