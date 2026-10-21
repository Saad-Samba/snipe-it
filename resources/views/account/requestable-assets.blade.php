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
                    @if ($models->isEmpty())
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No models with reusable inventory are currently available.
                        </div>
                    @else
                        <div class="clearfix" id="requestableModelsToolbar" style="margin-bottom:15px">
                            <div class="pull-left form-inline">
                                <label for="requestableModelsCategoryFilter">Category</label>
                                <select id="requestableModelsCategoryFilter" class="form-control input-sm">
                                    <option value="">All categories</option>
                                    @foreach ($models->pluck('category.name')->filter()->unique()->sort() as $categoryName)
                                        <option value="{{ $categoryName }}">{{ $categoryName }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="pull-right text-muted">Type a technical field to add a filter.</div>
                        </div>

                        <div id="requestableSpecificationChips" class="form-inline" style="margin-bottom:8px" aria-live="polite"></div>

                        <div class="table-responsive">
                            <table
                                id="requestableModelsTable"
                                class="table table-striped snipe-table"
                                data-id-table="requestableModelsTable"
                                data-cookie-id-table="requestableModelsTable"
                                data-click-to-select="false"
                                data-advanced-search="false"
                                data-search="true"
                                data-pagination="true">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-visible="false" data-sortable="true">ID</th>
                                        <th data-sortable="true">{{ trans('general.image') }}</th>
                                        <th data-sortable="true">{{ trans('admin/hardware/table.asset_model') }}</th>
                                        <th data-field="category" data-sortable="true">{{ trans('general.category') }}</th>
                                        <th data-sortable="true">{{ trans('admin/models/table.modelnumber') }}</th>
                                        <th data-field="specifications">Technical specifications</th>
                                        <th data-sortable="true">Reusable Assets <x-new-feature-label /></th>
                                        <th data-sortable="true">Reference Price <x-new-feature-label /></th>
                                        <th data-sortable="false">Total Needed <x-new-feature-label /></th>
                                        <th data-sortable="false">Discipline <x-new-feature-label /></th>
                                        <th data-sortable="false">Destination Site <x-new-feature-label /></th>
                                        <th data-sortable="false" class="text-right">{{ trans('table.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($models as $model)
                                        @php
                                            $modelSpecificationFields = collect($model->fieldset?->fields)
                                                ->filter(fn ($field) => $field->show_in_requestable_list && ! $field->is_unique && ! $field->field_encrypted)
                                                ->values();
                                            $specs = $modelSpecificationFields
                                                ->mapWithKeys(fn ($field) => [$field->id => $model->specificationValue($field)])
                                                ->filter(fn ($value) => filled($value));
                                        @endphp
                                        <tr>
                                            <td>{{ $model->id }}</td>
                                            <td>
                                                @if ($model->image && $model->getImageUrl())
                                                    <img
                                                        src="{{ $model->getImageUrl() }}"
                                                        alt=""
                                                        style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width:auto;"
                                                        class="img-responsive">
                                                @endif
                                            </td>
                                            <td>{{ $model->name }}</td>
                                            <td>{{ $model->category?->name }}</td>
                                            <td>{{ $model->model_number }}</td>
                                            <td>
                                                @forelse ($specs as $id => $value)
                                                    <span class="label label-default">
                                                        {{ $modelSpecificationFields->firstWhere('id', $id)?->name }}: {{ $value }}
                                                    </span>
                                                @empty
                                                    &mdash;
                                                @endforelse
                                            </td>
                                            <td>{{ $model->reusable_assets_count }}</td>
                                            <td>
                                                @if ($model->reference_price !== null)
                                                    {{ App\Helpers\Helper::formatCurrencyOutput($model->reference_price) }}
                                                @else
                                                    &mdash;
                                                @endif
                                            </td>
                                            <td>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value="1"
                                                    id="model-booking-quantity-{{ $model->id }}"
                                                    class="form-control input-sm"
                                                    style="width:70px">
                                            </td>
                                            <td>
                                                <select id="model-booking-discipline-{{ $model->id }}" class="form-control input-sm">
                                                    <option value="">{{ trans('general.select_discipline') }}</option>
                                                    @foreach ($disciplines as $discipline)
                                                        <option value="{{ $discipline->id }}">{{ $discipline->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select id="model-booking-company-{{ $model->id }}" class="form-control input-sm">
                                                    <option value="">{{ trans('general.select_company') }}</option>
                                                    @foreach ($companies as $company)
                                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn btn-primary btn-sm add-model-to-request-cart"
                                                    data-model-id="{{ $model->id }}">
                                                    <i class="fas fa-cart-plus" aria-hidden="true"></i>
                                                    Add to Cart
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop

@section('moar_scripts')
    @include('partials.bootstrap-table', [
        'exportFile' => 'requestable-models-export',
        'search' => true,
        'clientSearch' => true,
    ])

    <script nonce="{{ csrf_token() }}">
        var requestableSpecificationFields = @json($specificationSearchFields);

        function requestLineForModel(id) {
            var quantity = parseInt($('#model-booking-quantity-' + id).val(), 10);
            var disciplineId = parseInt($('#model-booking-discipline-' + id).val(), 10);
            var companyId = parseInt($('#model-booking-company-' + id).val(), 10);

            if (!quantity || !disciplineId || !companyId) {
                window.alert('Enter a total needed quantity, discipline, and destination site for each model.');

                return null;
            }

            return {
                model_id: id,
                quantity: quantity,
                discipline_id: disciplineId,
                company_id: companyId
            };
        }

        $(document).on('click', '.add-model-to-request-cart', function () {
            var line = requestLineForModel(+$(this).data('model-id'));

            if (line) {
                addLinesToRequestCart([line], false);
            }
        });

        $(function () {
            var $table = $('#requestableModelsTable');
            var $category = $('#requestableModelsCategoryFilter');
            var tokens = [];
            var freeTextSearch = '';
            var hideSuggestionsTimer;
            var isResettingSearch = false;
            var wasTypingTechnicalExpression = false;

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

            function specificationValues(row) {
                var values = {};
                $('<div>').html(row.specifications || '').find('.label').each(function () {
                    var text = $(this).text().trim();
                    var separator = text.indexOf(': ');
                    if (separator !== -1) {
                        values[text.slice(0, separator).toLowerCase()] = text.slice(separator + 2).trim();
                    }
                });

                return values;
            }

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
                    return field.name.toLowerCase().indexOf(value.toLowerCase()) === 0;
                });
            }

            function clearFreeTextSearch() {
                isResettingSearch = true;
                $table.bootstrapTable('resetSearch', '');
                isResettingSearch = false;
            }

            function searchableText(row) {
                return $('<div>')
                    .html(Object.keys(row).map(function (key) { return row[key]; }).join(' '))
                    .text()
                    .replace(/\s+/g, ' ')
                    .toLowerCase();
            }

            function applyFilters() {
                var category = $category.val();
                var filters = category ? { category: category } : {};

                $table.bootstrapTable('filterBy', filters, {
                    filterAlgorithm: function (row) {
                        if (category && $('<div>').html(row.category || '').text().trim() !== category) {
                            return false;
                        }

                        if (freeTextSearch && searchableText(row).indexOf(freeTextSearch.toLowerCase()) === -1) {
                            return false;
                        }

                        var specifications = specificationValues(row);

                        return tokens.every(function (token) {
                            var specification = specifications[token.field.name.toLowerCase()];
                            if (specification === undefined) {
                                return false;
                            }

                            if (token.field.numeric) {
                                var number = Number(specification);
                                if (!isFinite(number)) {
                                    return false;
                                }

                                if (token.operator === '>=') {
                                    return number >= token.number;
                                }

                                if (token.operator === '<=') {
                                    return number <= token.number;
                                }

                                return number === token.number;
                            }

                            if (token.field.values.length || token.operator === '=') {
                                return specification.toLowerCase() === token.value.toLowerCase();
                            }

                            return specification.toLowerCase().indexOf(token.value.toLowerCase()) !== -1;
                        });
                    },
                });
            }

            function renderChips() {
                $chips.empty();

                tokens.forEach(function (token, index) {
                    var operatorLabel = token.operator === ':'
                        ? (token.field.values.length ? 'is' : 'contains')
                        : token.operator;
                    var $chip = $('<button type="button" class="btn btn-default btn-sm" style="margin-right:5px"></button>')
                        .text(token.field.name + ' ' + operatorLabel + ' ' + token.value + ' ×')
                        .on('click', function () {
                            tokens.splice(index, 1);
                            renderChips();
                            applyFilters();
                        });

                    $chips.append($chip);
                });

                if (freeTextSearch) {
                    $('<button type="button" class="btn btn-default btn-sm" style="margin-right:5px"></button>')
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
                var value = $.trim($input.val());

                if (isTechnicalExpression(value) || isPotentialTechnicalField(value)) {
                    event.stopImmediatePropagation();
                    if (!wasTypingTechnicalExpression) {
                        clearFreeTextSearch();
                        $input.val(value);
                    }
                    wasTypingTechnicalExpression = true;
                    suggest();
                } else if (!isResettingSearch) {
                    wasTypingTechnicalExpression = false;
                    freeTextSearch = value;
                    renderChips();
                    applyFilters();
                }
            }, true);

            $input[0].addEventListener('keyup', function (event) {
                if (isTechnicalExpression($.trim($input.val())) || isPotentialTechnicalField($.trim($input.val()))) {
                    event.stopImmediatePropagation();
                }
            }, true);

            $input
                .on('input', suggest)
                .on('keydown', function (event) {
                    if (event.key === 'Enter') {
                        if (commitSearch()) {
                            event.preventDefault();
                        } else if (isPotentialTechnicalField($.trim($input.val()))) {
                            freeTextSearch = $.trim($input.val());
                            $table.bootstrapTable('resetSearch', freeTextSearch);
                            renderChips();
                            applyFilters();
                            $menu.hide();
                            event.preventDefault();
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

            var bootstrapTable = $table.data('bootstrap.table');
            var selectedCategory = bootstrapTable && bootstrapTable.filterColumns
                ? bootstrapTable.filterColumns.category
                : '';

            $category.val(selectedCategory || '').on('change', applyFilters);

            if (selectedCategory) {
                applyFilters();
            }
        });
    </script>
@stop
