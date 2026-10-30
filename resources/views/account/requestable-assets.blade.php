@extends('layouts/default')

@section('title0')
    Request Models
@stop

@section('title')
    @yield('title0') @parent
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right">
                    <a href="{{ route('requests.index') }}" class="btn btn-default">
                        <i class="fas fa-list" aria-hidden="true"></i>
                        Submitted Requests
                    </a>
                    <button type="button" class="btn btn-primary" id="modelRequestCartButton">
                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                        Request Cart
                        <x-new-feature-label />
                        <span class="badge" id="modelRequestCartCount">{{ count(session('model_request_cart', [])) }}</span>
                    </button>
                </div>
                <h2 class="box-title">Request Models</h2>
            </div>

            <div class="box-body">
                <div class="well well-sm" role="region" aria-label="Catalogue filters">
                    <div class="row">
                        <div class="col-sm-5 col-md-4">
                            <form id="requestableCatalogueFilters" method="GET" action="{{ route('requestable-assets') }}">
                                <input type="hidden" name="search" value="{{ $search }}">
                                <label for="catalogue-category">{{ trans('general.category') }}</label>
                                <select id="catalogue-category" name="category_id" class="form-control select2" style="width:100%;">
                                    <option value="">All categories</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ (string) $categoryId === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                        <div class="col-sm-7 col-md-8">
                            <p class="help-block">Use the table search to find models or add technical specification filters.</p>
                            @if ($search !== '' || $categoryId || count($technicalFilters))
                                <a href="{{ route('requestable-assets') }}" class="btn btn-default btn-sm">Clear filters</a>
                            @endif
                        </div>
                    </div>
                    <div id="requestableSpecificationChips" class="form-inline" style="margin-top:8px;" aria-live="polite"></div>
                </div>

                <div id="requestableModelsToolbar">
                    <form id="requestableModelsBulkForm" class="form-inline">
                        <label for="requestableModelsBulkAction" class="sr-only">{{ trans('general.bulk_actions') }}</label>
                        <select id="requestableModelsBulkAction" name="bulk_actions" class="form-control select2" style="width: 200px;" aria-label="bulk_actions">
                            <option value="add_to_cart">Add Selected to Cart</option>
                        </select>
                        <button type="submit" class="btn btn-primary" id="requestableModelsBulkAddButton" disabled>{{ trans('button.go') }}</button>
                    </form>
                </div>

                    <div class="table-responsive">
                        <table
                            id="requestableModelsTable"
                            class="table table-striped snipe-table"
                            data-id-table="requestableModelsTable"
                            data-cookie-id-table="requestableModelsDemandTable"
                            data-bulk-button-id="#requestableModelsBulkAddButton"
                            data-bulk-form-id="#requestableModelsBulkForm"
                            data-click-to-select="false"
                            data-toolbar="#requestableModelsToolbar"
                            data-search="true"
                            data-search-text="{{ $search }}"
                            data-catalogue-search="true"
                            data-advanced-search="false"
                            data-cookie="false"
                            data-addrbar="false"
                            data-empty-message="No catalogue models match your search."
                            data-pagination="false">
                            <thead>
                                <tr>
                                    <th data-field="state" data-checkbox="true"></th>
                                    <th data-field="id" data-visible="false">ID</th>
                                    <th data-sortable="false">{{ trans('general.image') }}</th>
                                    <th data-sortable="true">{{ trans('admin/hardware/table.asset_model') }}</th>
                                    <th data-sortable="true">{{ trans('general.category') }}</th>
                                    <th data-sortable="true">{{ trans('admin/models/table.modelnumber') }}</th>
                                    <th data-field="specifications" data-sortable="false">Technical specifications</th>
                                    <th data-sortable="false">Total Needed <x-new-feature-label /></th>
                                    <th data-sortable="false">Discipline <x-new-feature-label /></th>
                                    <th data-sortable="false">Destination Site <x-new-feature-label /></th>
                                    <th data-sortable="false" class="text-right">{{ trans('table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($models as $requestableModel)
                                    <tr>
                                        <td></td>
                                        <td>{{ $requestableModel->id }}</td>
                                        <td>
                                            @if ($requestableModel->image && $requestableModel->getImageUrl())
                                                <img
                                                    src="{{ $requestableModel->getImageUrl() }}"
                                                    alt=""
                                                    style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width:auto;"
                                                    class="img-responsive">
                                            @endif
                                        </td>
                                        <td>
                                            @can('view', $requestableModel)
                                                <a href="{{ route('models.show', $requestableModel->id) }}">{{ $requestableModel->name }}</a>
                                            @else
                                                {{ $requestableModel->name }}
                                            @endcan
                                            @if ($requestableModel->obsolete)<span class="label label-warning">Obsolete</span>@endif
                                        </td>
                                        <td>
                                            @if ($requestableModel->category)
                                                @can('view', $requestableModel->category)
                                                    <a href="{{ route('categories.show', $requestableModel->category->id) }}">{{ $requestableModel->category->name }}</a>
                                                @else
                                                    {{ $requestableModel->category->name }}
                                                @endcan
                                            @endif
                                        </td>
                                        <td>{{ $requestableModel->model_number }}</td>
                                        <td>
                                            @php
                                                $specificationFields = ($requestableModel->fieldset?->fields ?? collect())->filter(fn ($field) => $field->show_in_requestable_list && ! $field->field_encrypted && ! $field->is_unique);
                                            @endphp
                                            @if ($specificationFields->isNotEmpty())
                                                <details>
                                                    <summary style="cursor: pointer;">View specifications</summary>
                                                    <dl>
                                                        @foreach ($specificationFields as $field)
                                                            <dt>{{ $field->name }}</dt>
                                                            <dd>{{ $requestableModel->specificationValue($field) ?? '—' }}</dd>
                                                        @endforeach
                                                    </dl>
                                                </details>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                min="1"
                                                value="1"
                                                id="model-booking-quantity-{{ $requestableModel->id }}"
                                                class="form-control input-sm model-request-inline-control"
                                                style="width:70px;">
                                        </td>
                                        <td>
                                            <select
                                                id="model-booking-discipline-{{ $requestableModel->id }}"
                                                class="form-control input-sm model-request-inline-control"
                                                style="width:160px;">
                                                <option value="">{{ trans('general.select_discipline') }}</option>
                                                @foreach ($disciplines as $discipline)
                                                    <option value="{{ $discipline->id }}">{{ $discipline->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select
                                                id="model-booking-company-{{ $requestableModel->id }}"
                                                class="form-control input-sm model-request-inline-control"
                                                style="width:160px;">
                                                <option value="">{{ trans('general.select_company') }}</option>
                                                @foreach ($companies as $company)
                                                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-right">
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm add-model-to-request-cart"
                                                data-model-id="{{ $requestableModel->id }}">
                                                <i class="fas fa-cart-plus" aria-hidden="true"></i>
                                                Add to Request
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted">Showing {{ $models->firstItem() ?? 0 }}–{{ $models->lastItem() ?? 0 }} of {{ $models->total() }} models. Selection applies to this page; your cart is retained across pages.</p>
                    {{ $models->links() }}
            </div>
        </div>
    </div>
</div>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table', [
        'exportFile' => 'requestable-models-export',
        'search' => true,
        'clientSearch' => false,
    ])

    <script nonce="{{ csrf_token() }}">
        var requestableSpecificationFields = @json($specificationSearchFields);
        function requestLineForModel(modelId) {
            var quantity = parseInt($('#model-booking-quantity-' + modelId).val(), 10);
            var disciplineId = parseInt($('#model-booking-discipline-' + modelId).val(), 10);
            var companyId = parseInt($('#model-booking-company-' + modelId).val(), 10);

            if (!quantity) {
                window.alert('Enter a total needed quantity for each model.');
                return null;
            }

            if (!disciplineId) {
                window.alert('Select a discipline for each model.');
                return null;
            }

            if (!companyId) {
                window.alert('Select a site for each model.');
                return null;
            }

            return {
                model_id: modelId,
                quantity: quantity,
                discipline_id: disciplineId,
                company_id: companyId
            };
        }

        $(document)
            .off('click.add-model-request', '.add-model-to-request-cart')
            .on('click.add-model-request', '.add-model-to-request-cart', function () {
                var line = requestLineForModel(parseInt($(this).data('model-id'), 10));

                if (line) {
                    addLinesToRequestCart([line], false);
                }
            });

        $('#requestableModelsBulkForm').on('submit', function (event) {
            event.preventDefault();

            var rows = $('#requestableModelsTable').bootstrapTable('getSelections');

            if (!rows.length) {
                window.alert('Select at least one model.');
                return false;
            }

            var lines = [];

            for (var i = 0; i < rows.length; i++) {
                var line = requestLineForModel(parseInt(rows[i].id, 10));

                if (!line) {
                    return false;
                }

                lines.push(line);
            }

            addLinesToRequestCart(lines, false);
            return false;
        });
        $(function () {
            var $table = $('#requestableModelsTable');
            var $category = $('#catalogue-category');
            var tokens = @json($technicalFilters).map(function (token) {
                var field = requestableSpecificationFields.find(function (field) { return field.id === Number(token.field_id); });
                return { field: field, operator: token.operator, value: token.value, number: Number(token.value) };
            });
            var freeTextSearch = @json($search);
            var hideSuggestionsTimer;
            var searchTimer;
            var isResettingSearch = false;

            if (!$table.length) {
                return;
            }

            var $input = $('.fixed-table-toolbar .search input').attr('placeholder', 'Search models or specs');
            var $menu = $('<div class="dropdown-menu" id="requestableSpecificationSuggestions"></div>').css({
                maxHeight: '240px',
                overflowY: 'auto',
                zIndex: 1050
            });
            var $chips = $('#requestableSpecificationChips');

            $input.parent().css('position', 'relative').append($menu);
            $menu.on('mousedown', function (event) {
                clearTimeout(hideSuggestionsTimer);
                event.preventDefault();
            });

            function availableFields() {
                var category = $category.val();

                return requestableSpecificationFields.filter(function (field) {
                    return !category || field.categories.indexOf(category) !== -1;
                });
            }

            function fieldFor(value) {
                var lowerValue = value.toLowerCase();

                return availableFields()
                    .filter(function (field) {
                        var remainder = lowerValue.slice(field.name.length);

                        return lowerValue.indexOf(field.name.toLowerCase()) === 0 && /^(?:\s|$|:|=|>|<)/.test(remainder);
                    })
                    .sort(function (first, second) {
                        return second.name.length - first.name.length;
                    })[0];
            }

            function isTechnicalExpression(value) {
                var field = fieldFor(value);

                if (!field) {
                    return false;
                }

                return /^\s*$/.test(value.slice(field.name.length))
                    || /^(>=|<=|=|:)/.test($.trim(value.slice(field.name.length)));
            }

            function isPotentialTechnicalField(value) {
                return !!value && availableFields().some(function (field) {
                    return field.name.toLowerCase().indexOf(value.toLowerCase()) !== -1;
                });
            }

            function clearFreeTextSearch() {
                isResettingSearch = true;
                $table.bootstrapTable('resetSearch', '');
                isResettingSearch = false;
            }

            function applyFilters() {
                var url = new URL(@json(route('requestable-assets')));
                if ($category.val()) url.searchParams.set('category_id', $category.val());
                if (freeTextSearch) url.searchParams.set('search', freeTextSearch);
                tokens.forEach(function (token, index) {
                    var prefix = 'specifications[' + index + ']';
                    url.searchParams.set(prefix + '[field_id]', token.field.id);
                    url.searchParams.set(prefix + '[operator]', token.operator);
                    url.searchParams.set(prefix + '[value]', token.value);
                });
                window.location.assign(url.toString());
            }

            function renderChips() {
                $chips.empty();

                if (tokens.length || freeTextSearch) {
                    $('<span class="text-primary" style="margin-right:8px; font-weight:600"></span>')
                        .text('Active filters:')
                        .appendTo($chips);
                }

                tokens.forEach(function (token, index) {
                    var operatorLabel = token.operator === ':'
                        ? (token.field.multiple ? 'includes' : (token.field.values.length ? 'is' : 'contains'))
                        : token.operator;
                    var $chip = $('<button type="button" class="btn btn-primary btn-sm" style="margin-right:6px; margin-bottom:6px; border-radius:16px"></button>')
                        .text(token.field.name + ' ' + operatorLabel + ' ' + token.value + ' ×')
                        .on('click', function () {
                            tokens.splice(index, 1);
                            renderChips();
                            applyFilters();
                        });

                    $chips.append($chip);
                });

                if (freeTextSearch) {
                    $('<button type="button" class="btn btn-primary btn-sm" style="margin-right:6px; margin-bottom:6px; border-radius:16px"></button>')
                        .text('Text contains ' + freeTextSearch + ' ×')
                        .on('click', function () {
                            freeTextSearch = '';
                            clearFreeTextSearch();
                            renderChips();
                            applyFilters();
                        })
                        .appendTo($chips);
                }
            }

            function addToken(field, operator, value) {
                value = $.trim(value);

                if (!value) {
                    return;
                }

                var token = {
                    field: field,
                    operator: operator,
                    value: value,
                    number: parseFloat(value)
                };

                if (field.numeric && !isFinite(Number(value))) {
                    return;
                }

                tokens.push(token);
                clearFreeTextSearch();
                $input.val('');
                $menu.hide();
                renderChips();
                applyFilters();
            }

            function showSuggestions(items) {
                clearTimeout(hideSuggestionsTimer);
                $menu.empty();

                items.forEach(function (item) {
                    var $option = $('<button type="button" class="dropdown-item btn btn-link btn-block text-left"></button>')
                        .text(item.label)
                        .on('click', item.action);

                    $menu.append($option);
                });

                $menu.toggle(items.length > 0);
            }

            function suggest() {
                var value = $.trim($input.val());
                var field = fieldFor(value);

                if (!field) {
                    var matches = availableFields().filter(function (candidate) {
                        return candidate.name.toLowerCase().indexOf(value.toLowerCase()) !== -1;
                    });

                    showSuggestions(matches.slice(0, 8).map(function (candidate) {
                        return {
                            label: candidate.name,
                            action: function () {
                                clearFreeTextSearch();
                                $input.val(candidate.name + (candidate.numeric ? ' ' : ': ')).focus();
                                suggest();
                            }
                        };
                    }));

                    return;
                }

                var remainder = $.trim(value.slice(field.name.length));

                if (!remainder && field.numeric) {
                    showSuggestions(['>=', '<=', '='].map(function (operator) {
                        return {
                            label: operator,
                            action: function () {
                                clearFreeTextSearch();
                                $input.val(field.name + ' ' + operator + ' ').focus();
                                suggest();
                            }
                        };
                    }));

                    return;
                }

                if (!remainder) {
                    showSuggestions([{
                        label: field.name + ': value',
                        action: function () {
                            clearFreeTextSearch();
                            $input.val(field.name + ': ').focus();
                            suggest();
                        }
                    }]);

                    return;
                }

                var operator = (remainder.match(/^(>=|<=|=|:)/) || [])[1];

                if (field.numeric && !operator) {
                    showSuggestions(['>=', '<=', '='].map(function (choice) {
                        return {
                            label: choice,
                            action: function () {
                                clearFreeTextSearch();
                                $input.val(field.name + ' ' + choice + ' ').focus();
                                suggest();
                            }
                        };
                    }));

                    return;
                }

                var typedValue = $.trim(remainder.replace(/^(>=|<=|=|:)/, ''));

                if (field.values.length) {
                    showSuggestions(field.values
                        .filter(function (candidate) {
                            return candidate.toLowerCase().indexOf(typedValue.toLowerCase()) !== -1;
                        })
                        .slice(0, 8)
                        .map(function (candidate) {
                            return {
                                label: candidate,
                                action: function () {
                                    addToken(field, operator || ':', candidate);
                                }
                            };
                        }));

                    return;
                }

                $menu.hide();
            }

            function commitSearch() {
                var value = $.trim($input.val());
                var field = fieldFor(value);

                if (!field) {
                    return false;
                }

                var remainder = $.trim(value.slice(field.name.length));
                var match = remainder.match(/^(>=|<=|=|:)\s*(.+)$/);

                if (!match) {
                    return false;
                }

                addToken(field, match[1], match[2]);

                return true;
            }

            $input[0].addEventListener('input', function (event) {
                event.stopImmediatePropagation();
                clearTimeout(searchTimer);
                var value = $.trim($input.val());
                if (!isTechnicalExpression(value) && !isPotentialTechnicalField(value)) {
                    searchTimer = setTimeout(function () { freeTextSearch = value; applyFilters(); }, 600);
                }
                suggest();
            }, true);

            $input.closest('.search')[0].addEventListener('click', function (event) {
                if (event.target.closest('button[name="clearSearch"]')) {
                    freeTextSearch = '';
                    renderChips();
                    applyFilters();
                }
            }, true);

            $input[0].addEventListener('keyup', function (event) {
                event.stopImmediatePropagation();
            }, true);

            $input
                .on('input', suggest)
                .on('keydown', function (event) {
                    if (event.key === 'Enter') {
                        clearTimeout(searchTimer);
                        event.preventDefault();
                        if (!commitSearch()) {
                            freeTextSearch = $.trim($input.val());
                            applyFilters();
                        }
                    }

                    if (event.key === 'Escape') {
                        $menu.hide();
                    }
                })
                .on('blur', function () {
                    hideSuggestionsTimer = setTimeout(function () {
                        $menu.hide();
                    }, 150);
                });

            renderChips();
            $category.on('change', applyFilters);
        });
    </script>
@stop
