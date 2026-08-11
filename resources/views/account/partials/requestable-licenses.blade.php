<div class="clearfix" style="margin-bottom:10px;">
    <div class="pull-right">
        <button type="button" class="btn btn-info" id="licenseRequestCartButton">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            License Cart
            <span class="badge" id="licenseRequestCartCount">{{ count(session('license_request_cart', [])) }}</span>
        </button>
    </div>
    <h3 style="margin-top:5px;">Reusable License Seats</h3>
    <p class="text-muted">Choose a license pool, destination scope, and the user or asset that needs the seat.</p>
</div>

                @if ($licenses->isEmpty())
                    <div class="alert alert-info" style="margin-bottom:0;">No reusable license pools are available.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped" id="requestableLicensesTable">
                            <thead>
                                <tr>
                                    <th>License</th>
                                    <th>Source</th>
                                    <th>Available Now</th>
                                    <th>Expected Release</th>
                                    <th>Unit Cost</th>
                                    <th>Needed</th>
                                    <th>Destination Discipline</th>
                                    <th>Destination Company</th>
                                    <th>Target</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($licenses as $requestableLicense)
                                    <tr data-license-id="{{ $requestableLicense->id }}">
                                        <td>
                                            <a href="{{ route('licenses.show', $requestableLicense->id) }}">{{ $requestableLicense->name }}</a>
                                            @if ($requestableLicense->software_version)
                                                <small class="text-muted">{{ $requestableLicense->software_version }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $requestableLicense->company?->name ?: '—' }}<br>
                                            <small class="text-muted">{{ $requestableLicense->discipline?->name ?: '—' }}</small>
                                        </td>
                                        <td>{{ $requestableLicense->reusableFreeSeatsCount() }}</td>
                                        <td>{{ $requestableLicense->expectedReleaseSeatCount() }}</td>
                                        <td>{{ App\Helpers\Helper::formatCurrencyOutput($requestableLicense->unitPurchaseCost()) }}</td>
                                        <td>
                                            <input type="number" min="1" value="1" class="form-control input-sm license-request-quantity" style="width:70px;">
                                        </td>
                                        <td>
                                            <select class="form-control input-sm license-request-discipline" style="width:150px;">
                                                <option value="">{{ trans('general.select_discipline') }}</option>
                                                @foreach ($disciplines as $discipline)
                                                    <option value="{{ $discipline->id }}">{{ $discipline->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control input-sm license-request-company" style="width:150px;">
                                                <option value="">{{ trans('general.select_company') }}</option>
                                                @foreach ($companies as $company)
                                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td style="min-width:210px;">
                                            <select class="form-control input-sm license-request-target-type" style="margin-bottom:5px;">
                                                <option value="user">User</option>
                                                <option value="asset">Asset</option>
                                            </select>
                                            <select class="form-control input-sm license-request-user-target">
                                                <option value="">Select user</option>
                                                @foreach ($requestUsers as $requestUser)
                                                    <option value="{{ $requestUser->id }}" data-company-id="{{ $requestUser->company_id }}">
                                                        {{ $requestUser->display_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <select class="form-control input-sm license-request-asset-target" style="display:none;">
                                                <option value="">Select asset</option>
                                                @foreach ($requestAssets as $requestAsset)
                                                    <option
                                                        value="{{ $requestAsset->id }}"
                                                        data-company-id="{{ $requestAsset->company_id }}"
                                                        data-discipline-id="{{ $requestAsset->discipline_id }}">
                                                        {{ $requestAsset->asset_tag }} {{ $requestAsset->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm add-license-to-request-cart">
                                                <i class="fas fa-cart-plus" aria-hidden="true"></i> Add
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

<div class="modal fade" id="licenseRequestCartModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('account.request-cart.licenses.submit') }}" id="licenseRequestCartForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">License Request Cart</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" id="licenseRequestCartError" style="display:none;"></div>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="licenseRequestProject">{{ trans('general.project') }}</label>
                            <select name="project_id" id="licenseRequestProject" class="form-control" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="licenseRequestNeededBy">Needed By</label>
                            <input type="date" name="needed_by_date" id="licenseRequestNeededBy" class="form-control" required>
                        </div>
                    </div>
                    <div class="table-responsive" style="margin-top:15px;">
                        <table class="table table-condensed">
                            <thead>
                                <tr>
                                    <th>License</th><th>Target</th><th>Needed</th><th>Now</th><th>Expected</th><th>Shortfall</th><th></th>
                                </tr>
                            </thead>
                            <tbody id="licenseRequestCartLines"></tbody>
                        </table>
                    </div>
                    <div class="well well-sm" id="licenseRequestCartTotals"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger pull-left" id="clearLicenseRequestCart">Clear Cart</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-info" id="submitLicenseRequestCart">Submit License Requests</button>
                </div>
            </div>
        </form>
    </div>
</div>
