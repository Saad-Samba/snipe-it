<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\TokenRepository;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Facades\Gate;
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
