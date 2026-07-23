@push('css')
    <link rel="stylesheet" href="{{ url(mix('css/dist/bootstrap-table.css')) }}">
@endpush

@push('js')

<script src="{{ url(mix('js/dist/bootstrap-table.js')) }}"></script>
<script src="{{ url(mix('js/dist/bootstrap-table-locale-all.min.js')) }}"></script>

<!-- load english again here, even though it's in the all.js file, because if BS table doesn't have the translation, it otherwise defaults to chinese. See https://bootstrap-table.com/docs/api/table-options/#locale -->
<script src="{{ url(mix('js/dist/bootstrap-table-en-US.min.js')) }}"></script>

<script nonce="{{ csrf_token() }}">
    $(function () {


        var blockedFields = "searchable,sortable,switchable,title,visible,formatter,class".split(",");

        var keyBlocked = function(key) {
            for(var j in blockedFields) {
                if (key === blockedFields[j]) {
                    return true;
                }
            }
            return false;
        }

        $('.snipe-table').bootstrapTable('destroy').each(function () {

            data_export_options = $(this).attr('data-export-options');
            export_options = data_export_options ? JSON.parse(data_export_options) : {};
            export_options['htmlContent'] = false; // this is already the default; but let's be explicit about it
            export_options['jspdf'] = {
                "orientation": "l",
                "autotable": {
                        "styles": {
                            overflow: 'linebreak'
                        },
                        tableWidth: 'wrap'
                }
            };
            // tableWidth: 'wrap',
            // the following callback method is necessary to prevent XSS vulnerabilities
            // (this is taken from Bootstrap Tables's default wrapper around jQuery Table Export)
            export_options['onCellHtmlData'] = function (cell, rowIndex, colIndex, htmlData) {
                if (cell.is('th')) {
                    return cell.find('.th-inner').text()
                }
                return htmlData
            }

            // This allows us to override the table defaults set below using the data-dash attributes
            var table = this;
            var data_with_default = function (key,default_value) {
                attrib_val = $(table).data(key);
                if(attrib_val !== undefined) {
                    return attrib_val;
                }
                return default_value;
            }



            $(this).bootstrapTable({

                ajaxOptions: {
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                },
                // reorderableColumns: true,
                // buttonsPrefix: "btn",
                addrbar: {{ (config('session.bs_table_addrbar') == 'true') ? 'true' : 'false'}}, // deeplink search phrases, sorting, etc
                advancedSearch: data_with_default('advanced-search', true),
                buttonsClass: "tableButton tableButton btn-primary hidden-print",
                buttonsOrder: [
                    'columns',
                    'btnAdd',
                    'btnShowDeleted',
                    'btnFilterObsoleteModels',
                    'btnFilterCurrentModels',
                    'btnShowAssignedOnly',
                    'btnShowUnassignedOnly',
                    'btnShowAdmins',
                    'btnShowExpiring',
                    'btnShowInactive',
                    'refresh',
                    'btnExport',
                    'export',
                    'print',
                    'fullscreen',
                    'advancedSearch',
                ],
                classes: 'table table-responsive table-striped snipe-table table-no-bordered',
                clickToSelect: data_with_default('click-to-select', true),
                cookie: true,
                cookieExpire: '2y',
                cookieStorage: '{{ config('session.bs_table_storage') }}',
                iconsPrefix: 'fa',
                maintainSelected: data_with_default('maintain-selected', true),
                minimumCountColumns: data_with_default('minimum-count-columns', 2),
                mobileResponsive: data_with_default('mobile-responsive', true),
                pagination: data_with_default('pagination', true),
                paginationFirstText: "{{ trans('general.first') }}",
                paginationLastText: "{{ trans('general.last') }}",
                paginationNextText: "{{ trans('general.next') }}",
                paginationPreText: "{{ trans('general.previous') }}",
                search: data_with_default('search', true),
                searchHighlight: data_with_default('search-highlight', true),
                showColumns: data_with_default('show-columns', true),
                showColumnsToggleAll: data_with_default('show-columns-toggle-all', true),
                showExport: data_with_default('show-export', true),
                showFullscreen: data_with_default('show-fullscreen', true),
                showPrint: data_with_default('show-print', true),
                showRefresh: data_with_default('show-refresh', true),
                showSearchClearButton: data_with_default('show-search-clear-button', true),
                sortName: data_with_default('sort-name', 'created_at'),
                sortOrder: data_with_default('sort-order', 'desc'),
                stickyHeader: true,
                stickyHeaderOffsetLeft: parseInt($('body').css('padding-left'), 10),
                stickyHeaderOffsetRight: parseInt($('body').css('padding-right'), 10),
                trimOnSearch: false,
                undefinedText: '',
                pageList: ['10', '20', '30', '50', '100', '150', '200'{!! ((config('app.max_results') > 200) ? ",'500'" : '') !!}{!! ((config('app.max_results') > 500) ? ",'".config('app.max_results')."'" : '') !!}],
                pageSize: {{  (($snipeSettings->per_page!='') && ($snipeSettings->per_page > 0)) ? $snipeSettings->per_page : 20 }},
                paginationVAlign: 'both',
                queryParams: function (params) {
                    var newParams = {};
                    var bootstrapTableInstance = $(table).data('bootstrap.table');
                    var advancedFilters = (bootstrapTableInstance && bootstrapTableInstance.filterColumnsPartial) || {};

                    for (var i in params) {
                        if (!keyBlocked(i)) { // only send the field if it's not in blockedFields
                            newParams[i] = params[i];
                        }
                    }

                    for (var filterKey in advancedFilters) {
                        if (advancedFilters[filterKey] !== undefined && advancedFilters[filterKey] !== null && advancedFilters[filterKey] !== '') {
                            newParams[filterKey] = advancedFilters[filterKey];
                        }
                    }

                    return newParams;
                },
                formatLoadingMessage: function () {
                    return '<h2><x-icon type="spinner" /> {{ trans('general.loading') }} </h2>';
                },
                icons: {
                    advancedSearchIcon: 'fas fa-search-plus',
                    paginationSwitchDown: 'fa-caret-square-o-down',
                    paginationSwitchUp: 'fa-caret-square-o-up',
                    fullscreen: 'fa-expand',
                    columns: 'fa-columns',
                    print: 'fa-print',
                    refresh: 'fas fa-sync-alt',
                    export: 'fa-download',
                    clearSearch: 'fa-times',
                },
                locale: '{{ app()->getLocale() }}',
                exportOptions: export_options,
                exportTypes: ['xlsx', 'excel', 'csv', 'pdf', 'json', 'xml', 'txt', 'sql', 'doc'],
                onLoadSuccess: function () { // possible 'fixme'? this might be for contents, not for headers?
                    $('[data-tooltip="true"]').tooltip(); // Needed to attach tooltips after ajax call
                },
                onPostHeader: function () {
                    var lookup = {};
                    var lookup_initialized = false;
                    var ths = $('th');
                    var toolbar_buttons = $('.tableButton');

                    ths.each(function (index, element) {
                        th = $(element);
                        //only populate the lookup table once; don't need to keep doing it.
                        if (!lookup_initialized) {
                            // th -> tr -> thead -> table
                            var table = th.parent().parent().parent()
                            var column_data = table.data('columns')

                            for (var column in column_data) {
                                lookup[column_data[column].field] = column_data[column].titleTooltip;
                            }

                            lookup_initialized = true
                        }

                        field = th.data('field'); // find fieldname this column refers to
                        title = lookup[field];

                        if (title) {
                            th.attr('data-toggle', 'tooltip');
                            th.attr('data-tooltip', 'true');
                            th.attr('data-placement', 'top');
                            th.tooltip({container: 'body', title: title});

                        }
                    });

                    // Add tooltips to the toolbar buttons too
                    toolbar_buttons.each(function (index, element) {
                        tableButton = $(element);
                        title = tableButton.attr('title');
                        override_class = tableButton.attr('class');

                        if (title) {
                            // Keep this commented out so that we don't interfere with the dropdown toggle for columns, etc
                            // tableButton.attr('data-toggle', 'tooltip');
                            tableButton.attr('data-tooltip', 'true');
                            tableButton.attr('data-placement', 'auto');

                            // This prevents the slight button jitter on the mouseovees on the dashboard
                            tableButton.tooltip({container: 'body', title: title});

                            // This handles the case where we want a different color button than the default
                            if ((override_class) && (
                                (override_class.indexOf('btn-info') >= 0)
                                || (override_class.indexOf('btn-danger') >= 0)
                                || (override_class.indexOf('btn-warning') >= 0)
                                || (override_class.indexOf('btn-success') >= 0)
                            )) {
                                tableButton.removeClass('btn-primary');
                            }
                        }
                    });

                },
                formatNoMatches: function () {
                    return '{{ trans('table.no_matching_records') }}';
                }

            });

        });
    });


    // User table buttons
    window.userButtons = () => ({
        @can('create', \App\Models\User::class)
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('users.create') }}';
            },
            attributes: {
                title: '{{ trans('general.create') }}',
                class: 'btn-info',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
        @endcan

        btnExport: {
            text: '{{ trans('general.export_all_to_csv') }}',
            icon: 'fa-solid fa-file-csv',
            event () {
                window.location.href = '{{ route('users.export') }}';
            },
            attributes: {
                title: '{{ trans('general.export_all_to_csv') }}',
            }
        },

        btnShowAdmins: {
            text: '{{ trans('general.show_admins') }}',
            icon: 'fa-solid fa-crown{{ (request()->input('admins') == "true") ? ' text-danger' : '' }}',
            event () {
                window.location.href = '{{ (request()->input('admins') == "true") ? route('users.index') : route('users.index', ['admins' => 'true']) }}';
            },
            attributes: {
                title: '{{ trans('general.show_admins') }}',
            }
        },

        btnShowDeleted: {
            text: '{{ (request()->input('status') == "deleted") ? trans('admin/users/table.show_current') : trans('admin/users/table.show_deleted') }}',
            icon: 'fa-solid fa-trash',
            event () {
                window.location.href = '{{ (request()->input('status') == "deleted") ? route('users.index') : route('users.index', ['status' => 'deleted']) }}';
            },
            attributes: {
                class: '{{ (request()->input('status') == "deleted") ? ' btn-danger' : '' }}',
                title: '{{ (request()->input('status') == "deleted") ? trans('admin/users/table.show_current') : trans('admin/users/table.show_deleted') }}',

            }
        },

    }); // end user table buttons


    @can('create', \App\Models\Company::class)
    // Company table buttons
    window.companyButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('companies.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },

    }); // End company table buttons
    @endcan


    @can('create', \App\Models\Groups::class)
    // Groups table buttons
    window.groupButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('groups.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },

    }); // End Groups table buttons
    @endcan

    function obsoleteOnlyButtonConfig(currentState, routes, labels) {
        var isActive = currentState === 'obsolete';

        return {
            text: '',
            icon: 'fa-solid fa-triangle-exclamation',
            event() {
                window.location.href = isActive ? routes.all : routes.obsolete;
            },
            attributes: {
                title: isActive ? labels.obsolete : labels.all,
                'data-tooltip': 'true',
                class: isActive ? 'btn-warning' : '',
            }
        };
    }

    function activeOnlyButtonConfig(currentState, routes, labels) {
        var isActive = currentState === 'active';

        return {
            text: '',
            icon: 'fa-solid fa-circle-check',
            event() {
                window.location.href = isActive ? routes.all : routes.active;
            },
            attributes: {
                title: isActive ? labels.active : labels.inactive,
                'data-tooltip': 'true',
                class: isActive ? 'btn-success' : '',
            }
        };
    }

    function assignedOnlyButtonConfig(currentState, routes, labels) {
        var isActive = currentState === 'assigned';

        return {
            text: '',
            icon: 'fa-solid fa-user-check',
            event() {
                window.location.href = isActive ? routes.all : routes.assigned;
            },
            attributes: {
                title: isActive ? labels.assigned : labels.all,
                'data-tooltip': 'true',
                class: isActive ? 'btn-info' : '',
            }
        };
    }

    function unassignedOnlyButtonConfig(currentState, routes, labels) {
        var isActive = currentState === 'unassigned';

        return {
            text: '',
            icon: 'fa-solid fa-box-open',
            event() {
                window.location.href = isActive ? routes.all : routes.unassigned;
            },
            attributes: {
                title: isActive ? labels.unassigned : labels.inactive,
                'data-tooltip': 'true',
                class: isActive ? 'btn-success' : '',
            }
        };
    }

    // Asset table buttons
    window.assetButtons = () => ({
        @can('create', \App\Models\Asset::class)
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('hardware.create') }}';
            },
            attributes: {
                title: '{{ trans('general.create') }}',
                class: 'btn-info',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
        @endcan

        @can('update', \App\Models\Asset::class)
        btnAddMaintenance: {
            text: '{{ trans('button.add_maintenance') }}',
            icon: 'fa-solid fa-screwdriver-wrench',
            event () {
                window.location.href = '{{ route('maintenances.create', ['asset_id' => (isset($asset)) ? $asset->id :'' ]) }}';
            },
            attributes: {
                title: '{{ trans('button.add_maintenance') }}',
            }
        },
        @endcan


        btnExport: {
            text: '{{ trans('admin/hardware/general.custom_export') }}',
            icon: 'fa-solid fa-file-csv',
            event () {
                window.location.href = '{{ route('reports/custom') }}';
            },
            attributes: {
                title: '{{ trans('admin/hardware/general.custom_export') }}',
            }
        },

        @php
            $assetQuery = request()->query();
            $isStatusLabelPage = request()->routeIs('statuslabels.show') && request()->route('statuslabel');
            $isDeployableStatusPage = request()->routeIs('statuslabels.show')
                && request()->route('statuslabel')
                && request()->route('statuslabel')->deployable == 1
                && request()->route('statuslabel')->pending == 0
                && request()->route('statuslabel')->archived == 0;
            if ($isStatusLabelPage) {
                $assetQuery['status_id'] = request()->route('statuslabel')->id;
            }
            $assetFilter = $assetQuery['model_obsolete'] ?? null;
            $assetObsoleteBaseQuery = $assetQuery;
            unset($assetObsoleteBaseQuery['model_obsolete']);
            $assetObsoleteAllUrl = route('hardware.index', $assetObsoleteBaseQuery);
            $assetObsoleteUrl = route('hardware.index', array_merge($assetObsoleteBaseQuery, ['model_obsolete' => 1]));
            $assetActiveUrl = route('hardware.index', array_merge($assetObsoleteBaseQuery, ['model_obsolete' => 0]));
            $assetState = $assetFilter === '1' ? 'obsolete' : ($assetFilter === '0' ? 'active' : 'all');

            $assignmentFilter = request()->query('assignment');
            $assetAssignmentBaseQuery = $assetQuery;
            unset($assetAssignmentBaseQuery['assignment']);
            $assetAssignmentAllUrl = route('hardware.index', $assetAssignmentBaseQuery);
            $assetAssignmentState = $assignmentFilter === 'assigned' ? 'assigned' : ($assignmentFilter === 'unassigned' ? 'unassigned' : 'all');
            if ($isDeployableStatusPage) {
                $statusLabelRouteParams = ['statuslabel' => request()->route('statuslabel')->id];
                $assetObsoleteAllUrl = route('statuslabels.show', array_merge($statusLabelRouteParams, $assetObsoleteBaseQuery));
                $assetObsoleteUrl = route('statuslabels.show', array_merge($statusLabelRouteParams, $assetObsoleteBaseQuery, ['model_obsolete' => 1]));
                $assetActiveUrl = route('statuslabels.show', array_merge($statusLabelRouteParams, $assetObsoleteBaseQuery, ['model_obsolete' => 0]));
                $assetAssignmentAllUrl = route('statuslabels.show', array_merge($statusLabelRouteParams, $assetAssignmentBaseQuery));
                $assetAssignedUrl = route('statuslabels.show', array_merge($statusLabelRouteParams, $assetAssignmentBaseQuery, ['assignment' => 'assigned']));
                $assetUnassignedUrl = route('statuslabels.show', array_merge($statusLabelRouteParams, $assetAssignmentBaseQuery, ['assignment' => 'unassigned']));
            } elseif ($isStatusLabelPage) {
                $assetObsoleteAllUrl = route('statuslabels.show', ['statuslabel' => request()->route('statuslabel')->id]);
                $assetObsoleteUrl = route('statuslabels.show', ['statuslabel' => request()->route('statuslabel')->id, 'model_obsolete' => 1]);
                $assetActiveUrl = route('statuslabels.show', ['statuslabel' => request()->route('statuslabel')->id, 'model_obsolete' => 0]);
                $assetState = $assetFilter === '1' ? 'obsolete' : ($assetFilter === '0' ? 'active' : 'all');
            }

            $assetDeletedBaseQuery = $assetQuery;
            unset($assetDeletedBaseQuery['status']);
            $assetDeletedToggleUrl = request()->input('status') == 'Deleted'
                ? route('hardware.index', $assetDeletedBaseQuery)
                : route('hardware.index', array_merge($assetDeletedBaseQuery, ['status' => 'Deleted']));
        @endphp
        btnShowDeleted: {
            text: '{{ (request()->input('status') == "Deleted") ? trans('general.list_all') : trans('general.deleted') }}',
            icon: 'fa-solid fa-trash',
            event () {
                window.location.href = {!! \Illuminate\Support\Js::from($assetDeletedToggleUrl) !!};
            },
            attributes: {
                class: '{{ (request()->input('status') == "Deleted") ? ' btn-danger' : '' }}',
                title: '{{ (request()->input('status') == "Deleted") ? trans('general.list_all') : trans('general.deleted') }}',

            }
        },
        btnFilterObsoleteModels: obsoleteOnlyButtonConfig(
            '{{ $assetState }}',
            {
                all: {!! \Illuminate\Support\Js::from($assetObsoleteAllUrl) !!},
                obsolete: {!! \Illuminate\Support\Js::from($assetObsoleteUrl) !!},
                active: {!! \Illuminate\Support\Js::from($assetActiveUrl) !!}
            },
            {
                all: '{{ trans('admin/models/general.filter_all_to_obsolete') }}',
                obsolete: '{{ trans('admin/models/general.filter_obsolete_to_active') }}',
                active: '{{ trans('admin/models/general.filter_active_to_all') }}',
                inactive: '{{ trans('admin/models/general.filter_all_to_active') }}',
                optionAll: '{{ trans('admin/models/general.filter_all_option') }}',
                optionObsolete: '{{ trans('admin/models/general.filter_obsolete_option') }}',
                optionActive: '{{ trans('admin/models/general.filter_active_option') }}'
            }
        ),
        btnFilterCurrentModels: activeOnlyButtonConfig(
            '{{ $assetState }}',
            {
                all: {!! \Illuminate\Support\Js::from($assetObsoleteAllUrl) !!},
                obsolete: {!! \Illuminate\Support\Js::from($assetObsoleteUrl) !!},
                active: {!! \Illuminate\Support\Js::from($assetActiveUrl) !!}
            },
            {
                all: '{{ trans('admin/models/general.filter_all_to_obsolete') }}',
                obsolete: '{{ trans('admin/models/general.filter_obsolete_to_active') }}',
                active: '{{ trans('admin/models/general.filter_active_to_all') }}',
                inactive: '{{ trans('admin/models/general.filter_all_to_active') }}',
                optionAll: '{{ trans('admin/models/general.filter_all_option') }}',
                optionObsolete: '{{ trans('admin/models/general.filter_obsolete_option') }}',
                optionActive: '{{ trans('admin/models/general.filter_active_option') }}'
            }
        ),
        @if ($isDeployableStatusPage || ! $isStatusLabelPage)
        btnShowAssignedOnly: assignedOnlyButtonConfig(
            '{{ $assetAssignmentState }}',
            {
                all: {!! \Illuminate\Support\Js::from($assetAssignmentAllUrl) !!},
                assigned: {!! \Illuminate\Support\Js::from($assetAssignedUrl ?? route('hardware.index', array_merge($assetAssignmentBaseQuery, ['assignment' => 'assigned']))) !!},
                unassigned: {!! \Illuminate\Support\Js::from($assetUnassignedUrl ?? route('hardware.index', array_merge($assetAssignmentBaseQuery, ['assignment' => 'unassigned']))) !!}
            },
            {
                all: '{{ trans('general.filter_all_to_assigned') }}',
                assigned: '{{ trans('general.filter_assigned_to_all') }}',
                unassigned: '{{ trans('general.filter_unassigned_to_all') }}',
                inactive: '{{ trans('general.filter_all_to_unassigned') }}'
            }
        ),
        btnShowUnassignedOnly: unassignedOnlyButtonConfig(
            '{{ $assetAssignmentState }}',
            {
                all: {!! \Illuminate\Support\Js::from($assetAssignmentAllUrl) !!},
                assigned: {!! \Illuminate\Support\Js::from($assetAssignedUrl ?? route('hardware.index', array_merge($assetAssignmentBaseQuery, ['assignment' => 'assigned']))) !!},
                unassigned: {!! \Illuminate\Support\Js::from($assetUnassignedUrl ?? route('hardware.index', array_merge($assetAssignmentBaseQuery, ['assignment' => 'unassigned']))) !!}
            },
            {
                all: '{{ trans('general.filter_all_to_assigned') }}',
                assigned: '{{ trans('general.filter_assigned_to_all') }}',
                unassigned: '{{ trans('general.filter_unassigned_to_all') }}',
                inactive: '{{ trans('general.filter_all_to_unassigned') }}'
            }
        ),
        @endif
    });

    @can('create', \App\Models\Location::class)
    // Location table buttons
    window.locationButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('locations.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },

        btnShowDeleted: {
            text: '{{ (request()->input('status') == "deleted") ? trans('admin/users/table.show_current') : trans('admin/users/table.show_deleted') }}',
            icon: 'fa-solid fa-trash',
            event () {
                window.location.href = '{{ (request()->input('status') == "deleted") ? route('locations.index') : route('locations.index', ['status' => 'deleted']) }}';
            },
            attributes: {
                title: '{{ (request()->input('status') == "deleted") ? trans('admin/users/table.show_current') : trans('admin/users/table.show_deleted') }}',

            }
        },
    });
    @endcan

    @can('create', \App\Models\Project::class)
    // Project table buttons
    window.projectButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('projects.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\Discipline::class)
    // Discipline table buttons
    window.disciplineButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('disciplines.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\Accessory::class)
    // Accessory table buttons
    window.accessoryButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('accessories.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\Depreciation::class)
    // Accessory table buttons
    window.depreciationButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('depreciations.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\CustomField::class)
    // Accessory table buttons
    window.customFieldButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('fields.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan


    @can('create', \App\Models\CustomFieldset::class)
    // Accessory table buttons
    window.customFieldsetButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('fieldsets.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\Component::class)
    // Compoment table buttons
    window.componentButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('components.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\Consumable::class)
    // Consumable table buttons
    window.consumableButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('consumables.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\Manufacturer::class)
    // Consumable table buttons
    window.manufacturerButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('manufacturers.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            },

            btnShowDeleted: {
                text: '{{ (request()->input('status') == "Deleted") ? trans('general.list_all') : trans('general.deleted') }}',
                icon: 'fa-solid fa-trash',
                event () {
                    window.location.href = '{{ (request()->input('status') == "deleted") ? route('manufacturers.index') : route('manufacturers.index', ['status' => 'deleted']) }}';
                },
                attributes: {
                    class: '{{ (request()->input('status') == "Deleted") ? ' btn-danger' : '' }}',
                    title: '{{ (request()->input('status') == "Deleted") ? trans('general.list_all') : trans('general.deleted') }}',

                }
            },
        },
    });
    @endcan

    @can('create', \App\Models\Supplier::class)
    // Consumable table buttons
    window.supplierButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('suppliers.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\Department::class)
    // Department table buttons
    window.departmentButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('departments.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('create', \App\Models\Department::class)
    // Custom Field table buttons
    window.departmentButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('departments.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    @can('update', \App\Models\Asset::class)
    // Custom Field table buttons
    window.maintenanceButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('maintenances.create', ['asset_id' => (isset($asset)) ? $asset->id :'' ]) }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('button.add_maintenance') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan

    // Custom Field table buttons
    window.categoryButtons = () => {
        const buttons = {
            btnShowMine: {
                text: 'My Categories',
                icon: 'fa fa-user',
                event () {
                    window.location.href = '{{ route('categories.index', ['manager_id' => auth()->id()]) }}';
                },
                attributes: {
                    title: 'My Categories',
                }
            },
        };

        @can('create', \App\Models\Category::class)
        buttons.btnAdd = {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('categories.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        };
        @endcan

        return buttons;
    };

    // Custom Field table buttons
    window.modelButtons = () => ({
        @can('create', \App\Models\AssetModel::class)
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('models.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
        @endcan
        @php
            $modelQuery = request()->query();
            $modelFilter = $modelQuery['obsolete'] ?? null;
            $modelBaseQuery = $modelQuery;
            unset($modelBaseQuery['obsolete']);
            $modelAllUrl = route('models.index', $modelBaseQuery);
            $modelObsoleteUrl = route('models.index', array_merge($modelBaseQuery, ['obsolete' => 1]));
            $modelActiveUrl = route('models.index', array_merge($modelBaseQuery, ['obsolete' => 0]));
            $modelState = $modelFilter === '1' ? 'obsolete' : ($modelFilter === '0' ? 'active' : 'all');
            $modelDeletedBaseQuery = $modelQuery;
            unset($modelDeletedBaseQuery['status']);
            $modelDeletedToggleUrl = request()->input('status') == 'deleted'
                ? route('models.index', $modelDeletedBaseQuery)
                : route('models.index', array_merge($modelDeletedBaseQuery, ['status' => 'deleted']));
        @endphp
        btnShowDeleted: {
            text: '{{ (request()->input('status') == "deleted") ? trans('general.list_all') : trans('general.deleted') }}',
            icon: 'fa-solid fa-trash',
            event () {
                window.location.href = {!! \Illuminate\Support\Js::from($modelDeletedToggleUrl) !!};
            },
            attributes: {
                class: '{{ (request()->input('status') == "deleted") ? ' btn-danger' : '' }}',
                title: '{{ (request()->input('status') == "deleted") ? trans('general.list_all') : trans('general.deleted') }}',

            }
        },
        btnFilterObsoleteModels: obsoleteOnlyButtonConfig(
            '{{ $modelState }}',
            {
                all: {!! \Illuminate\Support\Js::from($modelAllUrl) !!},
                obsolete: {!! \Illuminate\Support\Js::from($modelObsoleteUrl) !!},
                active: {!! \Illuminate\Support\Js::from($modelActiveUrl) !!}
            },
            {
                all: '{{ trans('admin/models/general.filter_all_to_obsolete') }}',
                obsolete: '{{ trans('admin/models/general.filter_obsolete_to_active') }}',
                active: '{{ trans('admin/models/general.filter_active_to_all') }}',
                inactive: '{{ trans('admin/models/general.filter_all_to_active') }}',
                optionAll: '{{ trans('admin/models/general.filter_all_option') }}',
                optionObsolete: '{{ trans('admin/models/general.filter_obsolete_option') }}',
                optionActive: '{{ trans('admin/models/general.filter_active_option') }}'
            }
        ),
        btnFilterCurrentModels: activeOnlyButtonConfig(
            '{{ $modelState }}',
            {
                all: {!! \Illuminate\Support\Js::from($modelAllUrl) !!},
                obsolete: {!! \Illuminate\Support\Js::from($modelObsoleteUrl) !!},
                active: {!! \Illuminate\Support\Js::from($modelActiveUrl) !!}
            },
            {
                all: '{{ trans('admin/models/general.filter_all_to_obsolete') }}',
                obsolete: '{{ trans('admin/models/general.filter_obsolete_to_active') }}',
                active: '{{ trans('admin/models/general.filter_active_to_all') }}',
                inactive: '{{ trans('admin/models/general.filter_all_to_active') }}',
                optionAll: '{{ trans('admin/models/general.filter_all_option') }}',
                optionObsolete: '{{ trans('admin/models/general.filter_obsolete_option') }}',
                optionActive: '{{ trans('admin/models/general.filter_active_option') }}'
            }
        ),
    });

    @can('create', \App\Models\Statuslabel::class)
    // Status label table buttons
    window.statuslabelButtons = () => ({
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('statuslabels.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            }
        },
    });
    @endcan


    // License table buttons
    window.licenseButtons = () => ({
        @can('create', \App\Models\License::class)
        btnAdd: {
            text: '{{ trans('general.create') }}',
            icon: 'fa fa-plus',
            event () {
                window.location.href = '{{ route('licenses.create') }}';
            },
            attributes: {
                class: 'btn-info',
                title: '{{ trans('general.create') }}',
                @if ($snipeSettings->shortcuts_enabled == 1)
                accesskey: 'n'
                @endif
            },
        },
        @endcan

        @if (auth()->check() && auth()->user()->hasAccess('licenses.request'))
        btnRequestCart: {
            text: 'License Cart <span class="badge license-request-cart-count">{{ count(session('license_request_cart', [])) }}</span>',
            icon: 'fas fa-shopping-cart',
            event () {
                openLicenseRequestCartModal();
            },
            attributes: {
                class: 'btn-default',
                title: 'Open license request cart',
            }
        },
        @endif

        btnExport: {
            text: '{{ trans('general.export_all_to_csv') }}',
            icon: 'fa-solid fa-file-csv',
            event () {
                window.location.href = '{{ route('licenses.export', ['category_id' => (isset($category)) ? $category->id :'' ]) }}';
            },
            attributes: {
                class: 'btn-primary',
                title: '{{ trans('general.export_all_to_csv') }}',
            }
        },

        btnShowExpiring: {
            text: '{{ (request()->input('status') == "expiring") ? trans('general.list_all') : trans('general.show_expiring') }}',
            icon: 'fas fa-clock',
            event () {
                window.location.href = '{{ (request()->input('status') == "expiring") ? route('licenses.index') : route('licenses.index', ['status' => 'expiring']) }}';
            },
            attributes: {
                class: "{{ (request()->input('status') == "expiring") ? ' btn-warning' : '' }}",
                title: '{{ (request()->input('status') == "expiring") ? trans('general.list_all') : trans('general.show_expiring') }}',

            }
        },

        btnShowInactive: {
            text: '{{ (request()->input('status') == "inactive") ? trans('general.list_all') : trans('general.show_inactive') }}',
            icon: 'fas fa-history',
            event () {
                window.location.href = '{{ (request()->input('status') == "inactive") ? route('licenses.index') : route('licenses.index', ['status' => 'inactive']) }}';
            },
            attributes: {
                class: "{{ (request()->input('status') == "inactive") ? ' btn-warning' : '' }}",
                title: '{{ (request()->input('status') == "inactive") ? trans('general.list_all') : trans('general.show_inactive') }}',

            }
        },
    });





    function dateRowCheckStyle(value) {
        if ((value.days_to_next_audit) && (value.days_to_next_audit < {{ $snipeSettings->audit_warning_days ?: 0 }})) {
            return { classes : "danger" }
        }
        return {};
    }


    // These methods dynamically add/remove hidden input values in the bulk actions form
    $('.snipe-table').on('check.bs.table .btSelectItem', function (row, $element) {
        var buttonName =  $(this).data('bulk-button-id');
        var tableId =  $(this).data('id-table');

        $(buttonName).removeAttr('disabled');
        $(buttonName).after('<input id="' + tableId + '_checkbox_' + $element.id + '" type="hidden" name="ids[]" value="' + $element.id + '">');
    });

    $('.snipe-table').on('check-all.bs.table', function (event, rowsAfter) {

        var buttonName =  $(this).data('bulk-button-id');
        $(buttonName).removeAttr('disabled');
        var tableId =  $(this).data('id-table');

        for (var i in rowsAfter) {
            // Do not select things that were already selected
            if($('#'+ tableId + '_checkbox_' + rowsAfter[i].id).length == 0) {
                $(buttonName).after('<input id="' + tableId + '_checkbox_' + rowsAfter[i].id + '" type="hidden" name="ids[]" value="' + rowsAfter[i].id + '">');
            }
        }
    });


    $('.snipe-table').on('uncheck.bs.table .btSelectItem', function (row, $element) {
        var tableId =  $(this).data('id-table');
        $( "#" + tableId + "_checkbox_" + $element.id).remove();
    });


    // Handle whether the edit button should be disabled
    $('.snipe-table').on('uncheck.bs.table', function () {
        var buttonName =  $(this).data('bulk-button-id');

        if ($(this).bootstrapTable('getSelections').length == 0) {

            $(buttonName).attr('disabled', 'disabled');
        }
    });

    $('.snipe-table').on('uncheck-all.bs.table', function (event, rowsAfter, rowsBefore) {

        var buttonName =  $(this).data('bulk-button-id');
        $(buttonName).attr('disabled', 'disabled');
        var tableId =  $(this).data('id-table');

        for (var i in rowsBefore) {
            $('#' + tableId + "_checkbox_" + rowsBefore[i].id).remove();
        }

    });

    $('.snipe-table').on('click mousedown touchstart focus', '.model-request-inline-control', function (event) {
        event.stopPropagation();
    });

    // Initialize sort-order for bulk actions (label-generation) for snipe-tables
    $('.snipe-table').each(function (i, table) {
        table_cookie_segment = $(table).data('cookie-id-table');
        sort = '';
        order = '';
        cookies = document.cookie.split(";");
        for(i in cookies) {
            cookiedef = cookies[i].split("=", 2);
            cookiedef[0] = cookiedef[0].trim();
            if (cookiedef[0] == table_cookie_segment + ".bs.table.sortOrder") {
                order = cookiedef[1];
            }
            if (cookiedef[0] == table_cookie_segment + ".bs.table.sortName") {
                sort = cookiedef[1];
            }
        }
        if (sort && order) {
            domnode = $($(this).data('bulk-form-id')).get(0);
            if ( domnode && domnode.elements && domnode.elements.sort ) {
                domnode.elements.sort.value = sort;
                domnode.elements.order.value = order;
            }
        }
    });

    // If sort order changes, update the sort-order for bulk-actions (for label-generation)
    $('.snipe-table').on('sort.bs.table', function (event, name, order) {
       domnode = $($(this).data('bulk-form-id')).get(0);
       // make safe in case there isn't a bulk-form-id, or it's not found, or has no 'sort' element
       if ( domnode && domnode.elements && domnode.elements.sort ) {
           domnode.elements.sort.value = name;
           domnode.elements.order.value = order;
       }
    });



    // This specifies the footer columns that should have special styles associated
    // (usually numbers)
    window.footerStyle = column => ({
        remaining: {
            classes: 'text-padding-number-footer-cell'
        },
        qty: {
            classes: 'text-padding-number-footer-cell',
        },
        purchase_cost: {
            classes: 'text-padding-number-footer-cell'
        },
        checkouts_count: {
            classes: 'text-padding-number-footer-cell'
        },
        assets_count: {
            classes: 'text-padding-number-footer-cell'
        },
        seats: {
            classes: 'text-padding-number-footer-cell'
        },
        free_seats_count: {
            classes: 'text-padding-number-footer-cell'
        },
    }[column.field]);




    // This only works for model index pages because it uses the row's model ID
    function genericRowLinkFormatter(destination) {
        return function (value,row) {

            if ((row) && (row.tag_color) && (row.tag_color!='') && (row.tag_color!=undefined)) {
                var tag_icon = '<i class="fa-solid fa-square" style="color: ' + row.tag_color + ';" aria-hidden="true"></i> ';
            } else {
                var tag_icon = '';
            }

            if (value) {
                return tag_icon + '<a href="{{ config('app.url') }}/' + destination + '/' + row.id + '">' + value + '</a>';
            }
        };
    }

    // This is a special formatter that will indicate whether a user is an admin or superadmin
    function usernameRoleLinkFormatter(value, row) {

            if ((value) && (row)) {

                if (row.role === 'superadmin') {
                    return '<span style="white-space: nowrap" data-tooltip="true" title="{{ trans('general.superuser_tooltip') }}"><x-icon type="superadmin" title="{{ trans('general.superuser') }}"  class="text-danger" /> <a href="{{ config('app.url') }}/users/' + row.id + '">' + value + '</a></span>';
                } else if (row.role === 'admin') {
                    return '<span style="white-space: nowrap" data-tooltip="true" title="{{ trans('general.admin_tooltip') }}"><x-icon type="superadmin" title="{{ trans('general.admin_user') }}" class="text-warning" /> <a href="{{ config('app.url') }}/users/' + row.id + '">' + value + '</a></span>';
                }

                // Regular user
                return '<a href="{{ config('app.url') }}/users/' + row.id + '">' + value + '</a>';
            }

    }

    // Use this when we're introspecting into a column object and need to link
    function genericColumnObjLinkFormatter(destination) {
        return function (value,row) {
            if ((value) && (value.status_meta)) {

                var text_color;
                var icon_style;
                var text_help;
                var status_meta = {
                  'deployed': '{{ strtolower(trans('general.deployed')) }}',
                  'deployable': '{{ strtolower(trans('admin/hardware/general.deployable')) }}',
                  'archived': '{{ strtolower(trans('general.archived')) }}',
                  'undeployable': '{{ strtolower(trans('general.undeployable')) }}',
                  'pending': '{{ strtolower(trans('general.pending')) }}'
                }

                switch (value.status_meta) {
                    case 'deployed':
                        text_color = 'blue';
                        icon_style = 'fa-circle';
                        text_help = '<label class="label label-default">{{ trans('general.deployed') }}</label>';
                    break;
                    case 'deployable':
                        text_color = 'green';
                        icon_style = 'fa-circle';
                        text_help = '';
                    break;
                    case 'pending':
                        text_color = 'orange';
                        icon_style = 'fa-circle';
                        text_help = '';
                        break;
                    default:
                        text_color = 'red';
                        icon_style = 'fa-times';
                        text_help = '';
                }

                return '<nobr><a href="{{ config('app.url') }}/' + destination + '/' + value.id + '" data-tooltip="true" title="'+ status_meta[value.status_meta] + '"> <i class="fa ' + icon_style + ' text-' + text_color + '"></i> ' + value.name + ' ' + text_help + ' </a> </nobr>';
            } else if ((value) && (value.name)) {

                // Add some overrides for any funny urls we have
                var dest = destination;
                var tag_color;
                var polymorphicItemFormatterDest = '';



                if (destination == 'fieldsets') {
                    var polymorphicItemFormatterDest = 'fields/';
                }

                // Handle the preceding icon if a tag_color is given in the API response
                if ((value.tag_color) && (value.tag_color!='')) {
                    var tag_icon = '<i class="fa-solid fa-square" style="color: ' + value.tag_color + ';" aria-hidden="true"></i>';
                } else {
                    var tag_icon = '';
                }

                var obsoleteIndicator = '';

                if ((destination === 'models') && (value.obsolete === true || value.obsolete === 1 || value.obsolete === '1')) {
                    obsoleteIndicator = ' <span class="label label-warning" data-tooltip="true" title="{{ trans('admin/models/general.obsolete_asset_tooltip') }}">{{ trans('admin/models/general.obsolete_indicator') }}</span>';
                }

                return '<nobr>'+ tag_icon + ' <a href="{{ config('app.url') }}/' + polymorphicItemFormatterDest + dest + '/' + value.id + '">' + value.name + '</a>' + obsoleteIndicator + '</nobr>';
            }
        };
    }

    function companiesCenterMatchObjFormatter(value, row) {
        var formattedValue = genericColumnObjLinkFormatter('companies')(value, row);

        if (!formattedValue) {
            return formattedValue;
        }

        if (row && row.is_closest_match) {
            return '<span style="white-space: nowrap;">' + formattedValue + ' <span class="label label-success" data-tooltip="true" title="Reusable asset from the same center">Same center</span></span>';
        }

        return formattedValue;
    }


    function colorTagFormatter(value, row) {
        if (value) {
            return '<i class="fa-solid fa-square" style="color: ' + value + ';" aria-hidden="true"></i> ' + value;
        }
    }




    function licenseKeyFormatter(value, row) {
        if (value) {
            return '<code class="single-line"><span class="js-copy-link" data-clipboard-target=".js-copy-key-' + row.id + '" aria-hidden="true" data-tooltip="true" data-placement="top" title="{{ trans('general.copy_to_clipboard') }}"><span class="js-copy-key-' + row.id + '">' + value + '</span></span></code>';
        }
    }



    function hardwareAuditFormatter(value, row) {
        return '<a href="{{ config('app.url') }}/hardware/' + row.id + '/audit" class="actions btn btn-sm btn-primary" data-tooltip="true" title="{{ trans('general.audit') }}"><x-icon type="audit" /><span class="sr-only">{{ trans('general.audit') }}</span></a>&nbsp;';
    }




    // Make the edit/delete buttons
    function genericActionsFormatter(owner_name, element_name) {
        if (!element_name) {
            element_name = '';
        }


        return function (value,row) {
            var actions = '<nobr>';

            // Add some overrides for any funny urls we have
            var dest = owner_name;

            if (dest =='groups') {
                var dest = 'admin/groups';
            }


            if(element_name != '') {
                dest = dest + '/' + row.owner_id + '/' + element_name;
            }

            if ((row.available_actions) && (row.available_actions.clone === true)) {
                actions += '<a href="{{ config('app.url') }}/' + dest + '/' + row.id + '/clone" class="actions btn btn-sm btn-info" data-tooltip="true" title="{{ trans('general.clone_item') }}"><x-icon type="clone" /><span class="sr-only">{{ trans('general.clone_item') }}</span></a>&nbsp;';
            }

            if ((row.available_actions) && (row.available_actions.audit === true)) {
                actions += '<a href="{{ config('app.url') }}/' + dest + '/' + row.id + '/audit" class="actions btn btn-sm btn-primary" data-tooltip="true" title="{{ trans('general.audit') }}"><x-icon type="audit" /><span class="sr-only">{{ trans('general.audit') }}</span></a>&nbsp;';
            }

            if ((row.available_actions) && (row.available_actions.update === true)) {
                actions += '<a href="{{ config('app.url') }}/' + dest + '/' + row.id + '/edit" class="actions btn btn-sm btn-warning" data-tooltip="true" title="{{ trans('general.update') }}"><x-icon type="edit" /><span class="sr-only">{{ trans('general.update') }}</span></a>&nbsp;';
            } else {
                if ((row.available_actions) && (row.available_actions.update != true)) {
                    actions += '<span data-tooltip="true" title="{{ trans('general.cannot_be_edited') }}"><a class="btn btn-warning btn-sm disabled" onClick="return false;"><x-icon type="edit" /></a></span>&nbsp;';
                }
            }

            if ((row.available_actions) && (row.available_actions.delete === true)) {

                // use the asset tag if no name is provided

                if (row.name) {
                    var name_for_box = row.name
                } else if (row.asset_tag) {
                    var name_for_box = row.asset_tag
                }


                
                actions += '<a href="{{ config('app.url') }}/' + dest + '/' + row.id + '" '
                    + ' class="actions btn btn-danger btn-sm delete-asset" data-tooltip="true"  '
                    + ' data-toggle="modal" data-icon="fa-trash"'
                    + ' data-content="{{ trans('general.sure_to_delete') }}: ' + name_for_box + '?" '
                    + ' data-title="{{  trans('general.delete') }}" onClick="return false;">'
                    + '<x-icon type="delete" /><span class="sr-only">{{ trans('general.delete') }}</span></a>&nbsp;';
            } else {
                // Do not show the delete button on things that are already deleted
                if ((row.available_actions) && (row.available_actions.restore != true)) {
                    actions += '<span data-tooltip="true" title="{{ trans('general.cannot_be_deleted') }}"><a class="btn btn-danger btn-sm delete-asset disabled" onClick="return false;"><x-icon type="delete" /><span class="sr-only">{{ trans('general.cannot_be_deleted') }}</span></a></span>&nbsp;';
                }

            }


            if ((row.available_actions) && (row.available_actions.restore === true)) {
                actions += '<form style="display: inline;" method="POST" action="{{ config('app.url') }}/' + dest + '/' + row.id + '/restore"> ';
                actions += '@csrf';
                actions += '<button class="btn btn-sm btn-warning" data-tooltip="true" title="{{ trans('general.restore') }}"><x-icon type="restore" /><span class="sr-only">{{ trans('general.restore') }}</span></button>&nbsp;';
            }

            actions +='</nobr>';
            return actions;

        };
    }


    // This handles the icons and display of polymorphic entries
    function polymorphicItemFormatter(value) {

        var item_destination = '';
        var item_icon;

        if ((value) && (value.type)) {

            if (value.type == 'asset') {
                item_destination = 'hardware';
                item_icon = 'fas fa-barcode';
            } else if (value.type == 'accessory') {
                item_destination = 'accessories';
                item_icon = 'far fa-keyboard';
            } else if (value.type == 'component') {
                item_destination = 'components';
                item_icon = 'far fa-hdd';
            } else if (value.type == 'consumable') {
                item_destination = 'consumables';
                item_icon = 'fas fa-tint';
            } else if (value.type == 'license') {
                item_destination = 'licenses';
                item_icon = 'far fa-save';
            } else if (value.type == 'user') {
                item_destination = 'users';
                item_icon = 'fas fa-user';
            } else if (value.type == 'location') {
                item_destination = 'locations'
                item_icon = 'fas fa-map-marker-alt';
            } else if (value.type == 'maintenance') {
                item_destination = 'maintenances'
                item_icon = 'fa-solid fa-screwdriver-wrench';
            } else if (value.type == 'model') {
                item_destination = 'models'
                item_icon = '';
            }

            // display the username if it's checked out to a user, but don't do it if the username's there already
            if (value.username && !value.name.match('\\(') && !value.name.match('\\)')) {
                value.name = value.name + ' (' + value.username + ')';
            }

            return '<nobr><a href="{{ config('app.url') }}/' + item_destination +'/' + value.id + '" data-tooltip="true" title="' + value.type + '"><i class="' + item_icon + ' text-{{ $snipeSettings->skin!='' ? $snipeSettings->skin : 'blue' }} "></i> ' + value.name + '</a></nobr>';

        } else {
            return '';
        }


    }

    // This just prints out the item type in the activity report
    function itemTypeFormatter(value, row) {

        if ((row) && (row.item) && (row.item.type)) {
            return row.item.type;
        }
    }

    function categoryReusableAssetsFormatter(value, row) {
        if (value === null || value === undefined) {
            return '0';
        }

        if (!row || row.category_type_raw !== 'asset' || value < 1) {
            return value;
        }

        return '<a href="{{ route('hardware.index') }}?category_id=' + row.id + '&reusable_assets=1">' + value + '</a>';
    }

    function categoryAvailableModelsFormatter(value, row) {
        if (value === null || value === undefined) {
            return '0';
        }

        if (!row || row.category_type_raw !== 'asset' || value < 1) {
            return value;
        }

        return '<a href="{{ route('models.index') }}?category_id=' + row.id + '&available_models=1">' + value + '</a>';
    }


    // Convert line breaks to <br>
    function notesFormatter(value) {
        if (value) {
            return value.replace(/(?:\r\n|\r|\n)/g, '<br />');
        }
    }

    // Check if checkbox should be selectable
    // Selectability is determined by the API field "selectable" which is set at the Presenter/API Transformer
    // However since different bulk actions have different requirements, we have to walk through the available_actions object
    // to determine whether to disable it
    function checkboxEnabledFormatter (value, row) {

        // add some stuff to get the value of the select2 option here?

        if ((row.available_actions) && (row.available_actions.bulk_selectable) && (row.available_actions.bulk_selectable.delete !== true)) {
            return {
                disabled:true,
                //checked: false, <-- not sure this will work the way we want?
            }
        }
    }

    function licenseInOutFormatter(value, row) {
        var requestQuery = '{{ request()->filled('request_id') ? '?request_id=' . urlencode((string) request()->input('request_id')) : '' }}';

        // check that checkin is not disabled
        if (row.user_can_checkout === false) {
            return '<span class="btn btn-sm bg-maroon disabled" data-tooltip="true" title="{{ trans('admin/licenses/message.checkout.unavailable') }}">{{ trans('general.checkout') }}</span>';
        } else if (row.disabled === true) {
            return '<span class="btn btn-sm bg-maroon disabled" data-tooltip="true" title="{{ trans('admin/licenses/message.checkout.license_is_inactive') }}">{{ trans('general.checkout') }}</span>';

        } else
            // The user is allowed to check the license seat out and it's available
        if ((row.available_actions.checkout === true) && (row.user_can_checkout === true) && (row.disabled === false)) {
            return '<a href="{{ config('app.url') }}/licenses/' + row.id + '/checkout/' + requestQuery + '" class="btn btn-sm bg-maroon" data-tooltip="true" title="{{ trans('general.checkout_tooltip') }}">{{ trans('general.checkout') }}</a>';
        }
    }
    // We need a special formatter for license seats, since they don't work exactly the same
    // Checkouts need the license ID, checkins need the specific seat ID

    function licenseSeatInOutFormatter(value, row) {
        if (row.disabled && (row.assigned_user || row.assigned_asset)) {
            return '<a href="{{ config('app.url') }}/licenses/' + row.id + '/checkin" class="btn btn-sm bg-purple" data-tooltip="true" title="{{ trans('general.checkin_tooltip') }}">{{ trans('general.checkin') }}</a>';
        }
        if (row.disabled) {
            return '<a href="{{ config('app.url') }}/licenses/' + row.id + '/checkin" class="btn btn-sm bg-maroon disabled" data-tooltip="true" title="{{ trans('general.checkin_tooltip') }}">{{ trans('general.checkout') }}</a>';
        }
        // The user is allowed to check the license seat out and it's available
        if ((row.available_actions.checkout === true) && (row.user_can_checkout === true) && ((!row.assigned_asset) && (!row.assigned_user))) {
            return '<a href="{{ config('app.url') }}/licenses/' + row.license_id + '/checkout/'+row.id+'" class="btn btn-sm bg-maroon" data-tooltip="true" title="{{ trans('general.checkout_tooltip') }}">{{ trans('general.checkout') }}</a>';
        }

        // The user is allowed to check the license seat in and it's available
        if ((row.available_actions.checkin === true) && ((row.assigned_asset) || (row.assigned_user))) {
            return '<a href="{{ config('app.url') }}/licenses/' + row.id + '/checkin/" class="btn btn-sm bg-purple" data-tooltip="true" title="{{ trans('general.checkin_tooltip') }}">{{ trans('general.checkin') }}</a>';
        }

    }

    function genericCheckinCheckoutFormatter(destination) {
        return function (value, row) {
            var requestQuery = '{{ request()->filled('request_id') ? '?request_id=' . urlencode((string) request()->input('request_id')) : '' }}';

            // The user is allowed to check items out, AND the item is deployable
            if ((row.available_actions.checkout == true) && (row.user_can_checkout == true) && ((!row.asset_id) && (!row.assigned_to))) {

                    return '<a href="{{ config('app.url') }}/' + destination + '/' + row.id + '/checkout' + requestQuery + '" class="btn btn-sm bg-maroon" data-tooltip="true" title="{{ trans('general.checkout_tooltip') }}">{{ trans('general.checkout') }}</a>';

            // The user is allowed to check items out, but the item is not able to be checked out
            } else if (((row.user_can_checkout == false)) && (row.available_actions.checkout == true) && (!row.assigned_to)) {

                // We use slightly different language for assets versus other things, since they are the only
                // item that has a status label
                if (destination =='hardware') {
                    return '<span  data-tooltip="true" title="{{ trans('admin/hardware/general.undeployable_tooltip') }}"><a class="btn btn-sm bg-maroon disabled">{{ trans('general.checkout') }}</a></span>';
                } else {
                    return '<span  data-tooltip="true" title="{{ trans('general.undeployable_tooltip') }}"><a class="btn btn-sm bg-maroon disabled">{{ trans('general.checkout') }}</a></span>';
                }

            // The user is allowed to check items in
            } else if (row.available_actions.checkin == true)  {
                if (row.assigned_to) {
                    return '<a href="{{ config('app.url') }}/' + destination + '/' + row.id + '/checkin' + requestQuery + '" class="btn btn-sm bg-purple" data-tooltip="true" title="{{ trans('general.checkin_tooltip') }}">{{ trans('general.checkin') }}</a>';
                } else if (row.assigned_pivot_id) {
                    return '<a href="{{ config('app.url') }}/' + destination + '/' + row.assigned_pivot_id + '/checkin' + requestQuery + '" class="btn btn-sm bg-purple" data-tooltip="true" title="{{ trans('general.checkin_tooltip') }}">{{ trans('general.checkin') }}</a>';
                }

            }

        }


    }


    // This is only used by the requestable assets section
    function assetRequestActionsFormatter (row, value) {
        if (value.assigned_to_self == true){
            return '<button class="btn btn-danger btn-sm btn-block disabled" data-tooltip="true" title="{{ trans('admin/hardware/message.requests.cancel') }}">{{ trans('button.cancel') }}</button>';
        } else if (value.available_actions.cancel == true)  {
            return '<form action="{{ config('app.url') }}/account/request-asset/' + value.id + '/cancel" method="POST">@csrf<button class="btn btn-danger btn-block btn-sm" data-tooltip="true" title="{{ trans('admin/hardware/message.requests.cancel') }}">{{ trans('button.cancel') }}</button></form>';
        } else if (value.available_actions.request == true)  {
            return '<form action="{{ config('app.url') }}/account/request-asset/'+ value.id + '" method="POST">@csrf<button class="btn btn-block btn-primary btn-sm" data-tooltip="true" title="{{ trans('general.request_item') }}">{{ trans('button.request') }}</button></form>';
        }

    }

    var modelRequestProjects = @json(\App\Models\Project::orderBy('name')->get(['id', 'name']));
    var modelRequestCompanies = @json(\App\Models\Company::orderBy('name')->get(['id', 'name']));
    var modelRequestDisciplines = @json(\App\Models\Discipline::orderBy('name')->get(['id', 'name']));
    var canCreateProjectsForRequests = @json(auth()->check() && (auth()->user()->hasAccess('models.request') || auth()->user()->hasAccess('licenses.request')));
    var createProjectForRequestsUrl = '{{ route('account.request-projects.store') }}';
    var modelRequestCartAddUrl = '{{ route('account.request-cart.items.add') }}';
    var modelRequestCartPreviewUrl = '{{ route('account.request-cart.preview') }}';
    var modelRequestCartRemoveUrl = '{{ route('account.request-cart.items.remove') }}';
    var modelRequestCartClearUrl = '{{ route('account.request-cart.clear') }}';
    var modelRequestCartSubmitUrl = '{{ route('account.request-cart.submit') }}';
    var modelRequestCartToastTimer = null;
    var licenseRequestCartAddUrl = '{{ route('account.request-cart.licenses.items.add') }}';
    var licenseRequestCartPreviewUrl = '{{ route('account.request-cart.licenses.preview') }}';
    var licenseRequestCartRemoveUrl = '{{ route('account.request-cart.licenses.items.remove') }}';
    var licenseRequestCartClearUrl = '{{ route('account.request-cart.licenses.clear') }}';
    var licenseRequestCartSubmitUrl = '{{ route('account.request-cart.licenses.submit') }}';
    var licenseRequestCartToastTimer = null;

    function buildModelRequestProjectOptions(selectedProjectId) {
        var options = ['<option value=\"\">{{ trans('general.select_project') }}</option>'];

        modelRequestProjects.forEach(function(project) {
            var selected = String(project.id) === String(selectedProjectId) ? ' selected' : '';
            options.push('<option value=\"' + project.id + '\"' + selected + '>' + project.name + '</option>');
        });

        return options.join('');
    }

    function buildModelRequestDisciplineOptions(selectedDisciplineId) {
        var options = ['<option value=\"\">{{ trans('general.select_discipline') }}</option>'];

        modelRequestDisciplines.forEach(function(discipline) {
            var selected = String(discipline.id) === String(selectedDisciplineId) ? ' selected' : '';
            options.push('<option value=\"' + discipline.id + '\"' + selected + '>' + discipline.name + '</option>');
        });

        return options.join('');
    }

    function buildModelRequestCompanyOptions(selectedCompanyId) {
        var options = ['<option value=\"\">{{ trans('general.select_company') }}</option>'];

        modelRequestCompanies.forEach(function(company) {
            var selected = String(company.id) === String(selectedCompanyId) ? ' selected' : '';
            options.push('<option value=\"' + company.id + '\"' + selected + '>' + company.name + '</option>');
        });

        return options.join('');
    }

    function formatEstimateCurrency(value) {
        var number = Number(value || 0);

        return number.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function referencePriceFormatter(value, row) {
        if (row && row.reference_price_formatted) {
            return row.reference_price_formatted;
        }

        if (value === null || value === undefined || value === '') {
            return '';
        }

        return formatEstimateCurrency(value);
    }

    function requestReferencePriceFormatter(value, row) {
        if (row && row.reference_price_snapshot_formatted) {
            return row.reference_price_snapshot_formatted;
        }

        if (value === null || value === undefined || value === '') {
            return '';
        }

        return formatEstimateCurrency(value);
    }

    function ensureModelRequestCartToast() {
        if (document.getElementById('model-request-cart-toast')) {
            return;
        }

        var toastHtml = ''
            + '<div id="model-request-cart-toast" style="display:none;position:fixed;right:20px;bottom:20px;z-index:1060;max-width:320px;background:#222d32;color:#fff;padding:12px 16px;border-radius:6px;box-shadow:0 8px 18px rgba(0,0,0,0.2);font-size:13px;">'
            + '  <div id="model-request-cart-toast-message"></div>'
            + '</div>';

        $('body').append(toastHtml);
    }

    function showModelRequestCartToast(message) {
        ensureModelRequestCartToast();

        $('#model-request-cart-toast-message').text(message);
        $('#model-request-cart-toast').stop(true, true).fadeIn(150);

        if (modelRequestCartToastTimer) {
            window.clearTimeout(modelRequestCartToastTimer);
        }

        modelRequestCartToastTimer = window.setTimeout(function () {
            $('#model-request-cart-toast').fadeOut(250);
        }, 2200);
    }

    function attachRequestTableHeaderTooltips() {
        $('.snipe-table[data-request-mode="requester"]').each(function () {
            $(this).find('thead th[data-request-tooltip]').each(function () {
                var $header = $(this);
                var $headerInner = $header.find('.th-inner').first();

                if ($header.find('.request-column-tooltip').length) {
                    return;
                }

                var tooltipText = $header.attr('data-request-tooltip');
                var iconHtml = ' <a href="#" class="request-column-tooltip" data-tooltip="true" title="' + escapeHtml(tooltipText) + '" onclick="return false;"><i class="fas fa-info-circle" aria-hidden="true"></i></a>';

                if ($headerInner.length) {
                    $headerInner.append(iconHtml);
                } else {
                    $header.append(iconHtml);
                }
            });

            $('[data-tooltip="true"]').tooltip();
        });
    }

    function ensureModelRequestModal() {
        if (document.getElementById('model-request-modal')) {
            return;
        }

        var modalHtml = ''
            + '<div class="modal fade" id="model-request-modal" tabindex="-1" role="dialog" aria-hidden="true">'
            + '  <div class="modal-dialog" role="document">'
            + '    <div class="modal-content">'
            + '      <form id="model-request-modal-form" method="POST">'
            + '        @csrf'
            + '        <div class="modal-header">'
            + '          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
            + '          <h4 class="modal-title" id="model-request-modal-title">Modify request</h4>'
            + '        </div>'
            + '        <div class="modal-body">'
            + '          <input type="hidden" name="request-action" id="model-request-modal-action" value="update">'
            + '          <div class="alert alert-danger" id="model-request-modal-error" style="display:none;"></div>'
            + '          <div class="form-group">'
            + '            <label for="model-request-modal-quantity">Quantity</label>'
            + '            <input type="number" name="request-quantity" id="model-request-modal-quantity" class="form-control" min="1" required>'
            + '          </div>'
            + '          <div class="form-group">'
            + '            <label for="model-request-modal-discipline">Discipline</label>'
            + '            <select name="requested_discipline_id" id="model-request-modal-discipline" class="form-control" required>' + buildModelRequestDisciplineOptions('') + '</select>'
            + '          </div>'
            + '          <div class="form-group">'
            + '            <label for="model-request-modal-company">{{ trans('general.company') }}</label>'
            + '            <select name="company_id" id="model-request-modal-company" class="form-control" required>' + buildModelRequestCompanyOptions('') + '</select>'
            + '          </div>'
            + '          <div class="form-group">'
            + '            <label for="model-request-modal-project">{{ trans('general.project') }}</label>'
            + '            <div class="input-group">'
            + '              <select name="project_id" id="model-request-modal-project" class="form-control" required>' + buildModelRequestProjectOptions('') + '</select>'
            + '              <span class="input-group-btn">'
            + '                <button type="button" class="btn btn-default" id="model-request-modal-create-project" data-tooltip="true" title="Create project" ' + (canCreateProjectsForRequests ? '' : 'disabled') + '><i class="fas fa-plus" aria-hidden="true"></i></button>'
            + '              </span>'
            + '            </div>'
            + '          </div>'
            + '          <div class="form-group">'
            + '            <label for="model-request-modal-needed-by-date">Needed By</label>'
            + '            <input type="date" name="needed_by_date" id="model-request-modal-needed-by-date" class="form-control" required>'
            + '          </div>'
            + '          <div id="model-request-modal-estimate" class="well well-sm" style="margin-bottom:0;">'
            + '            <div style="font-weight:600;margin-bottom:8px;">Reuse Estimate</div>'
            + '            <div style="display:grid;grid-template-columns:auto 1fr;column-gap:12px;row-gap:6px;">'
            + '              <span>Total Needed</span><span id="model-request-modal-estimate-requested">0</span>'
            + '              <span>Reusable Now</span><span id="model-request-modal-estimate-reusable">0</span>'
            + '              <span>Due Back Before Needed By</span><span id="model-request-modal-estimate-due-back">0</span>'
            + '              <span>Shortfall</span><span id="model-request-modal-estimate-shortfall">0</span>'
            + '              <span>Estimated Savings</span><span id="model-request-modal-estimate-savings">0.00</span>'
            + '            </div>'
            + '          </div>'
            + '        </div>'
            + '        <div class="modal-footer">'
            + '          <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('button.cancel') }}</button>'
            + '          <button type="submit" class="btn btn-primary" id="model-request-modal-submit">Update</button>'
            + '        </div>'
            + '      </form>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        $('body').append(modalHtml);

        $('#model-request-modal-project, #model-request-modal-needed-by-date, #model-request-modal-quantity, #model-request-modal-company').on('change keyup', function () {
            updateModelRequestEstimateSummary();
        });

        $('#model-request-modal-create-project').on('click', function () {
            createProjectFromRequestModal('#model-request-modal-project', '#model-request-modal-error');
        });
    }

    function ensureModelRequestCartModal() {
        if (document.getElementById('model-request-cart-modal')) {
            return;
        }

        var modalHtml = ''
            + '<div class="modal fade" id="model-request-cart-modal" tabindex="-1" role="dialog" aria-hidden="true">'
            + '  <div class="modal-dialog modal-lg" role="document">'
            + '    <div class="modal-content">'
            + '      <form id="model-request-cart-modal-form" method="POST" action="' + modelRequestCartSubmitUrl + '">'
            + '        @csrf'
            + '        <div class="modal-header">'
            + '          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
            + '          <h4 class="modal-title">Request Cart</h4>'
            + '        </div>'
            + '        <div class="modal-body">'
            + '          <div class="alert alert-danger" id="model-request-cart-modal-error" style="display:none;"></div>'
            + '          <div class="row">'
            + '            <div class="col-md-6">'
            + '              <div class="form-group">'
            + '                <label for="model-request-cart-project">{{ trans('general.project') }}</label>'
            + '                <div class="input-group">'
            + '                  <select name="project_id" id="model-request-cart-project" class="form-control" required>' + buildModelRequestProjectOptions('') + '</select>'
            + '                  <span class="input-group-btn">'
            + '                    <button type="button" class="btn btn-default" id="model-request-cart-create-project" data-tooltip="true" title="Create project" ' + (canCreateProjectsForRequests ? '' : 'disabled') + '><i class="fas fa-plus" aria-hidden="true"></i></button>'
            + '                  </span>'
            + '                </div>'
            + '              </div>'
            + '            </div>'
            + '          </div>'
            + '          <div class="row">'
            + '            <div class="col-md-6">'
            + '              <div class="form-group">'
            + '                <label for="model-request-cart-needed-by-date">Needed By</label>'
            + '                <input type="date" name="needed_by_date" id="model-request-cart-needed-by-date" class="form-control" required>'
            + '              </div>'
            + '            </div>'
            + '          </div>'
            + '          <div class="table-responsive">'
            + '            <table class="table table-striped table-condensed" style="margin-bottom:12px;">'
            + '              <thead>'
            + '                <tr>'
            + '                  <th>Model</th>'
            + '                  <th>Discipline</th>'
            + '                  <th>{{ trans('general.company') }}</th>'
            + '                  <th>Quantity</th>'
            + '                  <th>Reusable Now</th>'
            + '                  <th>Due Back</th>'
            + '                  <th>Reserved by Other Project</th>'
            + '                  <th>Shortfall</th>'
            + '                  <th>Estimated Savings</th>'
            + '                  <th>Amount to Buy</th>'
            + '                  <th></th>'
            + '                </tr>'
            + '              </thead>'
            + '              <tbody id="model-request-cart-lines"></tbody>'
            + '            </table>'
            + '          </div>'
            + '          <div class="well well-sm" style="margin-bottom:0;">'
            + '            <div style="font-weight:600;margin-bottom:8px;">Cart Totals</div>'
            + '            <div style="display:grid;grid-template-columns:auto 1fr;column-gap:12px;row-gap:6px;">'
            + '              <span>Total Needed</span><span id="model-request-cart-total-requested">0</span>'
            + '              <span>Reusable Now</span><span id="model-request-cart-total-reusable">0</span>'
            + '              <span>Due Back</span><span id="model-request-cart-total-due-back">0</span>'
            + '              <span>Reserved by Other Project</span><span id="model-request-cart-total-reserved-other">0</span>'
            + '              <span>Shortfall</span><span id="model-request-cart-total-shortfall">0</span>'
            + '              <span>Estimated Savings</span><span id="model-request-cart-total-savings">0.00</span>'
            + '              <span>Amount to Buy</span><span id="model-request-cart-total-buy">0.00</span>'
            + '            </div>'
            + '          </div>'
            + '        </div>'
            + '        <div class="modal-footer">'
            + '          <button type="button" class="btn btn-danger pull-left" id="model-request-cart-clear">Clear Cart</button>'
            + '          <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('button.cancel') }}</button>'
            + '          <button type="submit" class="btn btn-primary" id="model-request-cart-submit">{{ trans('button.request') }}</button>'
            + '        </div>'
            + '      </form>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        $('body').append(modalHtml);

        $('#model-request-cart-project, #model-request-cart-needed-by-date').on('change keyup', function () {
            refreshModelRequestCartPreview();
        });

        $('#model-request-cart-create-project').on('click', function () {
            createProjectFromRequestModal('#model-request-cart-project', '#model-request-cart-modal-error');
        });

        $('#model-request-cart-clear').on('click', function () {
            $.post(modelRequestCartClearUrl, {_token: '{{ csrf_token() }}'}).done(function (response) {
                updateModelRequestCartCount(response.cart_count || 0);
                refreshModelRequestCartPreview();
            });
        });
    }

    function updateModelRequestCartCount(count) {
        $('#modelRequestCartCount').text(count);
    }

    $('#modelRequestCartButton').on('click', function () {
        openModelRequestCartModal();
    });

    function getInlineModelBookingQuantity(modelId) {
        var value = $('#model-booking-quantity-' + modelId).val();
        var quantity = parseInt(value, 10);

        return Number.isFinite(quantity) ? quantity : 0;
    }

    function getInlineModelDisciplineId(modelId) {
        var value = $('#model-booking-discipline-' + modelId).val();
        var disciplineId = parseInt(value, 10);

        return Number.isFinite(disciplineId) ? disciplineId : 0;
    }

    function getInlineModelCompanyId(modelId) {
        var value = $('#model-booking-company-' + modelId).val();
        var companyId = parseInt(value, 10);

        return Number.isFinite(companyId) ? companyId : 0;
    }

    function buildInlineBookingInput(modelId, quantity) {
        return '<input type="number" min="1" id="model-booking-quantity-' + modelId + '" value="' + quantity + '" class="form-control input-sm model-request-inline-control" style="width:70px;height:30px;padding:4px 6px;display:inline-block;">';
    }

    function buildInlineDisciplineSelect(modelId, selectedDisciplineId) {
        return '<select id="model-booking-discipline-' + modelId + '" class="form-control input-sm model-request-inline-control" style="width:150px;height:30px;padding:4px 6px;display:inline-block;">'
            + buildModelRequestDisciplineOptions(selectedDisciplineId || '')
            + '</select>';
    }

    function buildInlineCompanySelect(modelId, selectedCompanyId) {
        return '<select id="model-booking-company-' + modelId + '" class="form-control input-sm model-request-inline-control" style="width:150px;height:30px;padding:4px 6px;display:inline-block;">'
            + buildModelRequestCompanyOptions(selectedCompanyId || '')
            + '</select>';
    }

    function addLinesToRequestCart(lines, openCartOnSuccess) {
        return $.ajax({
            url: modelRequestCartAddUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}',
                lines: lines
            }
        }).done(function (response) {
            updateModelRequestCartCount(response.cart_count || 0);
            showModelRequestCartToast(lines.length > 1 ? 'Items added to cart.' : 'Item added to cart.');

            if (openCartOnSuccess) {
                openModelRequestCartModal();
            }
        }).fail(function (xhr) {
            var message = 'Unable to add items to the request cart.';

            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                if (firstKey && xhr.responseJSON.errors[firstKey] && xhr.responseJSON.errors[firstKey][0]) {
                    message = xhr.responseJSON.errors[firstKey][0];
                }
            }

            window.alert(message);
        });
    }

    $('#modelsBulkForm').off('submit.model-booking').on('submit.model-booking', function (event) {
        var bulkAction = $(this).find('select[name="bulk_actions"]').val();

        if (bulkAction !== 'request') {
            return true;
        }

        event.preventDefault();

        var $table = $('#asssetModelsTable');
        var rows = $table.bootstrapTable('getSelections');

        if (!rows.length) {
            window.alert('Select at least one model.');
            return false;
        }

        var lines = [];

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];

            if (!row.available_actions || row.available_actions.request !== true) {
                window.alert('Only requestable models can be added to the cart.');
                return false;
            }

            var quantity = getInlineModelBookingQuantity(row.id);
            var disciplineId = getInlineModelDisciplineId(row.id);
            var companyId = getInlineModelCompanyId(row.id);

            if (!quantity) {
                window.alert('Enter a total needed quantity for each selected model.');
                return false;
            }

            if (!disciplineId) {
                window.alert('Select a discipline for each selected model.');
                return false;
            }

            if (!companyId) {
                window.alert('Select a company for each selected model.');
                return false;
            }

            lines.push({
                model_id: row.id,
                quantity: quantity,
                discipline_id: disciplineId,
                company_id: companyId
            });
        }

        addLinesToRequestCart(lines, true);
        return false;
    });

    function openModelRequestModal(options) {
        ensureModelRequestModal();

        $('#model-request-modal-form').attr('action', options.requestUrl);
        $('#model-request-modal-form').data('estimate-url', options.estimateUrl);
        $('#model-request-modal-title').text(options.title);
        $('#model-request-modal-action').val(options.action || 'update');
        $('#model-request-modal-quantity').val(options.quantity || '');
        $('#model-request-modal-discipline').html(buildModelRequestDisciplineOptions(options.requestedDisciplineId || ''));
        $('#model-request-modal-discipline').val(String(options.requestedDisciplineId || ''));
        $('#model-request-modal-company').html(buildModelRequestCompanyOptions(options.companyId || ''));
        $('#model-request-modal-company').val(String(options.companyId || ''));
        $('#model-request-modal-project').html(buildModelRequestProjectOptions(options.projectId || ''));
        $('#model-request-modal-project').val(String(options.projectId || ''));
        $('#model-request-modal-needed-by-date').val(options.neededByDate || '');
        $('#model-request-modal-submit').text(options.submitLabel);
        resetModelRequestEstimateState();
        $('#model-request-modal').modal('show');
        updateModelRequestEstimateSummary();
    }

    function resetModelRequestEstimateState() {
        $('#model-request-modal-error').hide().text('');
        $('#model-request-modal-estimate-requested').text('0');
        $('#model-request-modal-estimate-reusable').text('0');
        $('#model-request-modal-estimate-due-back').text('0');
        $('#model-request-modal-estimate-shortfall').text('0');
        $('#model-request-modal-estimate-savings').text(formatEstimateCurrency(0));
        $('#model-request-modal-submit').prop('disabled', false);
    }

    function estimateModelRequestModal() {
        var estimateUrl = $('#model-request-modal-form').data('estimate-url');
        var quantity = $('#model-request-modal-quantity').val();
        var companyId = $('#model-request-modal-company').val();
        var projectId = $('#model-request-modal-project').val();
        var neededByDate = $('#model-request-modal-needed-by-date').val();
        var action = $('#model-request-modal-action').val();

        $.ajax({
            url: estimateUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}',
                'request-action': action,
                'request-quantity': quantity,
                company_id: companyId,
                project_id: projectId,
                needed_by_date: neededByDate
            }
        }).done(function (response) {
            $('#model-request-modal-estimate-requested').text(response.requested_quantity);
            $('#model-request-modal-estimate-reusable').text(response.reusable_now);
            $('#model-request-modal-estimate-due-back').text(response.due_back_before_needed_by_quantity);
            $('#model-request-modal-estimate-shortfall').text(response.procurement_shortfall);
            $('#model-request-modal-estimate-savings').text(formatEstimateCurrency(response.estimated_savings));
        }).fail(function (xhr) {
            var message = 'Unable to estimate this request.';

            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];

                if (firstKey && xhr.responseJSON.errors[firstKey] && xhr.responseJSON.errors[firstKey][0]) {
                    message = xhr.responseJSON.errors[firstKey][0];
                }
            }

            $('#model-request-modal-error').text(message).show();
        });
    }

    function updateModelRequestEstimateSummary() {
        var companyId = $('#model-request-modal-company').val();
        var projectId = $('#model-request-modal-project').val();
        var neededByDate = $('#model-request-modal-needed-by-date').val();
        var quantity = $('#model-request-modal-quantity').val();

        resetModelRequestEstimateState();

        if (!companyId || !projectId || !neededByDate || !quantity) {
            return;
        }

        $('#model-request-modal-estimate-requested').text(quantity);
        estimateModelRequestModal();
    }

    function renderModelRequestCartLines(lines, metadataReady) {
        var rows = [];

        if (!lines.length) {
            rows.push('<tr><td colspan="11" class="text-muted">Your request cart is empty.</td></tr>');
        }

        lines.forEach(function (line) {
            rows.push(
                '<tr>'
                + '<td>' + escapeHtml(line.model_name) + '</td>'
                + '<td>' + escapeHtml(line.discipline_name) + '</td>'
                + '<td>' + escapeHtml(line.company_name) + '</td>'
                + '<td>' + line.quantity + '</td>'
                + '<td>' + (metadataReady ? line.reusable_quantity : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.due_back_before_needed_by_quantity : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.reserved_by_other_rfqs_count : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.procurement_shortfall : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.estimated_savings_formatted : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.amount_to_buy_formatted : '&mdash;') + '</td>'
                + '<td><button type="button" class="btn btn-danger btn-xs" onclick="removeLineFromModelRequestCart(' + line.model_id + ', ' + line.discipline_id + ', ' + line.company_id + ')"><i class="fas fa-times" aria-hidden="true"></i></button></td>'
                + '</tr>'
            );
        });

        $('#model-request-cart-lines').html(rows.join(''));
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function renderModelRequestCartTotals(totals, formattedTotals, metadataReady) {
        $('#model-request-cart-total-requested').text(totals.quantity || 0);
        $('#model-request-cart-total-reusable').html(metadataReady ? (totals.reusable_quantity || 0) : '&mdash;');
        $('#model-request-cart-total-due-back').html(metadataReady ? (totals.due_back_before_needed_by_quantity || 0) : '&mdash;');
        $('#model-request-cart-total-reserved-other').html(metadataReady ? (totals.reserved_by_other_rfqs_count || 0) : '&mdash;');
        $('#model-request-cart-total-shortfall').html(metadataReady ? (totals.procurement_shortfall || 0) : '&mdash;');
        $('#model-request-cart-total-savings').html(metadataReady ? formattedTotals.estimated_savings : '&mdash;');
        $('#model-request-cart-total-buy').html(metadataReady ? formattedTotals.amount_to_buy : '&mdash;');
    }

    function refreshModelRequestCartPreview() {
        var projectId = $('#model-request-cart-project').val();
        var neededByDate = $('#model-request-cart-needed-by-date').val();
        var metadataReady = Boolean(projectId && neededByDate);

        $.ajax({
            url: modelRequestCartPreviewUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}',
                project_id: projectId,
                needed_by_date: neededByDate
            }
        }).done(function (response) {
            $('#model-request-cart-modal-error').hide().text('');
            updateModelRequestCartCount(response.cart_count || 0);
            renderModelRequestCartLines(response.lines || [], metadataReady);
            renderModelRequestCartTotals(response.totals || {}, response.totals_formatted || {}, metadataReady);
            $('#model-request-cart-submit').prop('disabled', !response.cart_count);
        }).fail(function (xhr) {
            var message = 'Unable to load the request cart.';

            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                if (firstKey && xhr.responseJSON.errors[firstKey] && xhr.responseJSON.errors[firstKey][0]) {
                    message = xhr.responseJSON.errors[firstKey][0];
                }
            }

            $('#model-request-cart-modal-error').text(message).show();
        });
    }

    function openModelRequestCartModal() {
        ensureModelRequestCartModal();
        $('#model-request-cart-modal').modal('show');
        refreshModelRequestCartPreview();
    }

    function ensureLicenseRequestCartToast() {
        if (document.getElementById('license-request-cart-toast')) {
            return;
        }

        var toastHtml = ''
            + '<div id="license-request-cart-toast" style="display:none;position:fixed;right:20px;bottom:20px;z-index:1060;max-width:320px;background:#222d32;color:#fff;padding:12px 16px;border-radius:6px;box-shadow:0 8px 18px rgba(0,0,0,0.2);font-size:13px;">'
            + '  <div id="license-request-cart-toast-message"></div>'
            + '</div>';

        $('body').append(toastHtml);
    }

    function showLicenseRequestCartToast(message) {
        ensureLicenseRequestCartToast();

        $('#license-request-cart-toast-message').text(message);
        $('#license-request-cart-toast').stop(true, true).fadeIn(150);

        if (licenseRequestCartToastTimer) {
            window.clearTimeout(licenseRequestCartToastTimer);
        }

        licenseRequestCartToastTimer = window.setTimeout(function () {
            $('#license-request-cart-toast').fadeOut(250);
        }, 2200);
    }

    function ensureLicenseRequestCartModal() {
        if (document.getElementById('license-request-cart-modal')) {
            return;
        }

        var modalHtml = ''
            + '<div class="modal fade" id="license-request-cart-modal" tabindex="-1" role="dialog" aria-hidden="true">'
            + '  <div class="modal-dialog modal-lg" role="document">'
            + '    <div class="modal-content">'
            + '      <form id="license-request-cart-modal-form" method="POST" action="' + licenseRequestCartSubmitUrl + '">'
            + '        @csrf'
            + '        <div class="modal-header">'
            + '          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
            + '          <h4 class="modal-title">License Request Cart</h4>'
            + '        </div>'
            + '        <div class="modal-body">'
            + '          <div class="alert alert-danger" id="license-request-cart-modal-error" style="display:none;"></div>'
            + '          <div class="row">'
            + '            <div class="col-md-6">'
            + '              <div class="form-group">'
            + '                <label for="license-request-cart-project">{{ trans('general.project') }}</label>'
            + '                <div class="input-group">'
            + '                  <select name="project_id" id="license-request-cart-project" class="form-control" required>' + buildModelRequestProjectOptions('') + '</select>'
            + '                  <span class="input-group-btn">'
            + '                    <button type="button" class="btn btn-default" id="license-request-cart-create-project" data-tooltip="true" title="Create project" ' + (canCreateProjectsForRequests ? '' : 'disabled') + '><i class="fas fa-plus" aria-hidden="true"></i></button>'
            + '                  </span>'
            + '                </div>'
            + '              </div>'
            + '            </div>'
            + '            <div class="col-md-6">'
            + '              <div class="form-group">'
            + '                <label for="license-request-cart-needed-by-date">Needed By</label>'
            + '                <input type="date" name="needed_by_date" id="license-request-cart-needed-by-date" class="form-control" required>'
            + '              </div>'
            + '            </div>'
            + '          </div>'
            + '          <div class="table-responsive">'
            + '            <table class="table table-striped table-condensed" style="margin-bottom:12px;">'
            + '              <thead>'
            + '                <tr>'
            + '                  <th>License</th>'
            + '                  <th>Assignee Type</th>'
            + '                  <th>Assignee</th>'
            + '                  <th>Discipline</th>'
            + '                  <th>{{ trans('general.company') }}</th>'
            + '                  <th>Quantity</th>'
            + '                  <th>Reusable Now</th>'
            + '                  <th>Expected Release</th>'
            + '                  <th>Shortfall</th>'
            + '                  <th>Estimated Savings</th>'
            + '                  <th>Amount to Buy</th>'
            + '                  <th></th>'
            + '                </tr>'
            + '              </thead>'
            + '              <tbody id="license-request-cart-lines"></tbody>'
            + '            </table>'
            + '          </div>'
            + '          <div class="well well-sm" style="margin-bottom:0;">'
            + '            <div style="font-weight:600;margin-bottom:8px;">Cart Totals</div>'
            + '            <div style="display:grid;grid-template-columns:auto 1fr;column-gap:12px;row-gap:6px;">'
            + '              <span>Total Needed</span><span id="license-request-cart-total-requested">0</span>'
            + '              <span>Reusable Now</span><span id="license-request-cart-total-reusable">0</span>'
            + '              <span>Expected Release</span><span id="license-request-cart-total-expected-release">0</span>'
            + '              <span>Shortfall</span><span id="license-request-cart-total-shortfall">0</span>'
            + '              <span>Estimated Savings</span><span id="license-request-cart-total-savings">0.00</span>'
            + '              <span>Amount to Buy</span><span id="license-request-cart-total-buy">0.00</span>'
            + '            </div>'
            + '          </div>'
            + '        </div>'
            + '        <div class="modal-footer">'
            + '          <button type="button" class="btn btn-danger pull-left" id="license-request-cart-clear">Clear Cart</button>'
            + '          <button type="button" class="btn btn-default" data-dismiss="modal">{{ trans('button.cancel') }}</button>'
            + '          <button type="submit" class="btn btn-primary" id="license-request-cart-submit">{{ trans('button.request') }}</button>'
            + '        </div>'
            + '      </form>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        $('body').append(modalHtml);

        $('#license-request-cart-project, #license-request-cart-needed-by-date').on('change keyup', function () {
            refreshLicenseRequestCartPreview();
        });

        $('#license-request-cart-create-project').on('click', function () {
            createProjectFromRequestModal('#license-request-cart-project', '#license-request-cart-modal-error');
        });

        $('#license-request-cart-clear').on('click', function () {
            $.post(licenseRequestCartClearUrl, {_token: '{{ csrf_token() }}'}).done(function (response) {
                updateLicenseRequestCartCount(response.cart_count || 0);
                refreshLicenseRequestCartPreview();
            });
        });
    }

    function updateLicenseRequestCartCount(count) {
        $('.license-request-cart-count').text(count);
    }

    function getInlineLicenseBookingQuantity(licenseId) {
        var value = $('#license-booking-quantity-' + licenseId).val();
        var quantity = parseInt(value, 10);

        return Number.isFinite(quantity) ? quantity : 0;
    }

    function getInlineLicenseDisciplineId(licenseId) {
        var value = $('#license-booking-discipline-' + licenseId).val();
        var disciplineId = parseInt(value, 10);

        return Number.isFinite(disciplineId) ? disciplineId : 0;
    }

    function getInlineLicenseCompanyId(licenseId) {
        var value = $('#license-booking-company-' + licenseId).val();
        var companyId = parseInt(value, 10);

        return Number.isFinite(companyId) ? companyId : 0;
    }

    function getInlineLicenseAssigneeType(licenseId) {
        return $('#license-booking-assignee-type-' + licenseId).val() || '';
    }

    function getInlineLicenseAssigneeDisplay(licenseId) {
        return ($('#license-booking-assignee-display-' + licenseId).val() || '').trim();
    }

    function buildInlineLicenseBookingInput(licenseId, quantity) {
        return '<input type="number" min="1" id="license-booking-quantity-' + licenseId + '" value="' + quantity + '" class="form-control input-sm" style="width:70px;height:30px;padding:4px 6px;display:inline-block;">';
    }

    function buildInlineLicenseDisciplineSelect(licenseId, selectedDisciplineId) {
        return '<select id="license-booking-discipline-' + licenseId + '" class="form-control input-sm" style="width:150px;height:30px;padding:4px 6px;display:inline-block;">'
            + buildModelRequestDisciplineOptions(selectedDisciplineId || '')
            + '</select>';
    }

    function buildInlineLicenseCompanySelect(licenseId, selectedCompanyId) {
        return '<select id="license-booking-company-' + licenseId + '" class="form-control input-sm" style="width:150px;height:30px;padding:4px 6px;display:inline-block;">'
            + buildModelRequestCompanyOptions(selectedCompanyId || '')
            + '</select>';
    }

    function buildInlineLicenseAssigneeTypeSelect(licenseId, selectedType) {
        return '<select id="license-booking-assignee-type-' + licenseId + '" class="form-control input-sm" style="width:110px;height:30px;padding:4px 6px;display:inline-block;">'
            + '<option value="">Assignee Type</option>'
            + '<option value="user"' + (String(selectedType || '') === 'user' ? ' selected' : '') + '>User</option>'
            + '<option value="asset"' + (String(selectedType || '') === 'asset' ? ' selected' : '') + '>Asset</option>'
            + '</select>';
    }

    function buildInlineLicenseAssigneeInput(licenseId, value) {
        return '<input type="text" id="license-booking-assignee-display-' + licenseId + '" value="' + escapeHtml(value || '') + '" class="form-control input-sm" style="width:170px;height:30px;padding:4px 6px;display:inline-block;" placeholder="Assignee name or asset tag">';
    }

    function addLinesToLicenseRequestCart(lines, openCartOnSuccess) {
        return $.ajax({
            url: licenseRequestCartAddUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}',
                lines: lines
            }
        }).done(function (response) {
            updateLicenseRequestCartCount(response.cart_count || 0);
            showLicenseRequestCartToast(lines.length > 1 ? 'Licenses added to cart.' : 'License added to cart.');

            if (openCartOnSuccess) {
                openLicenseRequestCartModal();
            }
        }).fail(function (xhr) {
            var message = 'Unable to add licenses to the request cart.';

            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                if (firstKey && xhr.responseJSON.errors[firstKey] && xhr.responseJSON.errors[firstKey][0]) {
                    message = xhr.responseJSON.errors[firstKey][0];
                }
            }

            window.alert(message);
        });
    }

    function addInlineLicenseRowToCart(licenseId) {
        var quantity = getInlineLicenseBookingQuantity(licenseId);
        var disciplineId = getInlineLicenseDisciplineId(licenseId);
        var companyId = getInlineLicenseCompanyId(licenseId);
        var assigneeType = getInlineLicenseAssigneeType(licenseId);
        var assigneeDisplay = getInlineLicenseAssigneeDisplay(licenseId);

        if (!quantity) {
            window.alert('Enter a total needed quantity first.');
            return;
        }

        if (!assigneeType) {
            window.alert('Select an assignee type first.');
            return;
        }

        if (!assigneeDisplay) {
            window.alert('Enter the assignee first.');
            return;
        }

        if (!disciplineId) {
            window.alert('Select a discipline first.');
            return;
        }

        if (!companyId) {
            window.alert('Select a company first.');
            return;
        }

        addLinesToLicenseRequestCart([{
            license_id: licenseId,
            quantity: quantity,
            discipline_id: disciplineId,
            company_id: companyId,
            requested_for_type: assigneeType,
            requested_for_display: assigneeDisplay
        }], false);
    }

    function renderLicenseRequestCartLines(lines, metadataReady) {
        var rows = [];

        if (!lines.length) {
            rows.push('<tr><td colspan="12" class="text-muted">Your license request cart is empty.</td></tr>');
        }

        lines.forEach(function (line) {
            rows.push(
                '<tr>'
                + '<td>' + escapeHtml(line.license_name) + '</td>'
                + '<td>' + escapeHtml(line.requested_for_type) + '</td>'
                + '<td>' + escapeHtml(line.requested_for_display) + '</td>'
                + '<td>' + escapeHtml(line.discipline_name) + '</td>'
                + '<td>' + escapeHtml(line.company_name) + '</td>'
                + '<td>' + line.quantity + '</td>'
                + '<td>' + (metadataReady ? line.reusable_quantity : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.expected_release_before_needed_by_quantity : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.procurement_shortfall : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.estimated_savings_formatted : '&mdash;') + '</td>'
                + '<td>' + (metadataReady ? line.amount_to_buy_formatted : '&mdash;') + '</td>'
                + '<td><button type="button" class="btn btn-danger btn-xs" onclick="removeLineFromLicenseRequestCart(' + line.license_id + ', ' + line.discipline_id + ', ' + line.company_id + ', \'' + encodeURIComponent(line.requested_for_type) + '\', \'' + encodeURIComponent(line.requested_for_display) + '\')"><i class="fas fa-times" aria-hidden="true"></i></button></td>'
                + '</tr>'
            );
        });

        $('#license-request-cart-lines').html(rows.join(''));
    }

    function renderLicenseRequestCartTotals(totals, formattedTotals, metadataReady) {
        $('#license-request-cart-total-requested').text(totals.quantity || 0);
        $('#license-request-cart-total-reusable').html(metadataReady ? (totals.reusable_quantity || 0) : '&mdash;');
        $('#license-request-cart-total-expected-release').html(metadataReady ? (totals.expected_release_before_needed_by_quantity || 0) : '&mdash;');
        $('#license-request-cart-total-shortfall').html(metadataReady ? (totals.procurement_shortfall || 0) : '&mdash;');
        $('#license-request-cart-total-savings').html(metadataReady ? formattedTotals.estimated_savings : '&mdash;');
        $('#license-request-cart-total-buy').html(metadataReady ? formattedTotals.amount_to_buy : '&mdash;');
    }

    function refreshLicenseRequestCartPreview() {
        var projectId = $('#license-request-cart-project').val();
        var neededByDate = $('#license-request-cart-needed-by-date').val();
        var metadataReady = Boolean(projectId && neededByDate);

        $.ajax({
            url: licenseRequestCartPreviewUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}',
                project_id: projectId,
                needed_by_date: neededByDate
            }
        }).done(function (response) {
            $('#license-request-cart-modal-error').hide().text('');
            updateLicenseRequestCartCount(response.cart_count || 0);
            renderLicenseRequestCartLines(response.lines || [], metadataReady);
            renderLicenseRequestCartTotals(response.totals || {}, response.totals_formatted || {}, metadataReady);
            $('#license-request-cart-submit').prop('disabled', !response.cart_count);
        }).fail(function (xhr) {
            var message = 'Unable to load the request cart.';

            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var firstKey = Object.keys(xhr.responseJSON.errors)[0];
                if (firstKey && xhr.responseJSON.errors[firstKey] && xhr.responseJSON.errors[firstKey][0]) {
                    message = xhr.responseJSON.errors[firstKey][0];
                }
            }

            $('#license-request-cart-modal-error').text(message).show();
        });
    }

    function openLicenseRequestCartModal() {
        ensureLicenseRequestCartModal();
        $('#license-request-cart-modal').modal('show');
        refreshLicenseRequestCartPreview();
    }

    function removeLineFromLicenseRequestCart(licenseId, disciplineId, companyId, requestedForType, requestedForDisplay) {
        $.post(licenseRequestCartRemoveUrl, {
            _token: '{{ csrf_token() }}',
            license_id: licenseId,
            discipline_id: disciplineId,
            company_id: companyId,
            requested_for_type: decodeURIComponent(requestedForType),
            requested_for_display: decodeURIComponent(requestedForDisplay)
        }).done(function (response) {
            updateLicenseRequestCartCount(response.cart_count || 0);
            refreshLicenseRequestCartPreview();
        });
    }

    function removeLineFromModelRequestCart(modelId, disciplineId, companyId) {
        $.post(modelRequestCartRemoveUrl, {
            _token: '{{ csrf_token() }}',
            model_id: modelId,
            discipline_id: disciplineId,
            company_id: companyId
        }).done(function (response) {
            updateModelRequestCartCount(response.cart_count || 0);
            refreshModelRequestCartPreview();
        });
    }

    function createProjectFromRequestModal(targetSelect, errorTarget) {
        if (!canCreateProjectsForRequests) {
            return;
        }

        var projectName = window.prompt('Project name');

        if (!projectName) {
            return;
        }

        $.ajax({
            url: createProjectForRequestsUrl,
            method: 'POST',
            dataType: 'json',
            headers: {
                Accept: 'application/json'
            },
            data: {
                _token: '{{ csrf_token() }}',
                name: projectName
            }
        }).done(function (response) {
            if (!response || response.status !== 'success' || !response.payload) {
                var inlineError = 'Unable to create the project.';

                if (response && response.messages) {
                    if (typeof response.messages === 'string') {
                        inlineError = response.messages;
                    } else if (Array.isArray(response.messages) && response.messages[0]) {
                        inlineError = response.messages[0];
                    } else {
                        var inlineErrorKey = Object.keys(response.messages)[0];
                        if (inlineErrorKey && response.messages[inlineErrorKey] && response.messages[inlineErrorKey][0]) {
                            inlineError = response.messages[inlineErrorKey][0];
                        }
                    }
                }

                $(errorTarget).text(inlineError).show();
                return;
            }

            modelRequestProjects.push({
                id: response.payload.id,
                name: response.payload.name
            });
            modelRequestProjects.sort(function (a, b) {
                return a.name.localeCompare(b.name);
            });

            $(targetSelect).html(buildModelRequestProjectOptions(response.payload.id));
            $(targetSelect).val(String(response.payload.id)).trigger('change');
        }).fail(function (xhr) {
            var message = 'Unable to create the project.';

            if (xhr.responseJSON && xhr.responseJSON.messages) {
                if (typeof xhr.responseJSON.messages === 'string') {
                    message = xhr.responseJSON.messages;
                } else if (Array.isArray(xhr.responseJSON.messages) && xhr.responseJSON.messages[0]) {
                    message = xhr.responseJSON.messages[0];
                } else {
                    var messageKey = Object.keys(xhr.responseJSON.messages)[0];
                    if (messageKey && xhr.responseJSON.messages[messageKey] && xhr.responseJSON.messages[messageKey][0]) {
                        message = xhr.responseJSON.messages[messageKey][0];
                    }
                }
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errorKey = Object.keys(xhr.responseJSON.errors)[0];
                if (errorKey && xhr.responseJSON.errors[errorKey] && xhr.responseJSON.errors[errorKey][0]) {
                    message = xhr.responseJSON.errors[errorKey][0];
                }
            }

            $(errorTarget).text(message).show();
        });
    }

    function modelRequestActionsFormatter(value, row) {
        var requestedQuantity = 1;

        if ((row.available_actions) && (row.available_actions.request === true)) {
            return '<div style="display:flex;align-items:center;gap:6px;min-width:104px;">'
                + buildInlineBookingInput(row.id, requestedQuantity)
                + buildInlineDisciplineSelect(row.id, '')
                + buildInlineCompanySelect(row.id, '')
                + '<button type="button" class="btn btn-primary btn-sm model-request-inline-control" style="width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;" data-tooltip="true" title="Add to cart" onclick="var quantity = getInlineModelBookingQuantity(' + row.id + '); var disciplineId = getInlineModelDisciplineId(' + row.id + '); var companyId = getInlineModelCompanyId(' + row.id + '); if (!quantity) { window.alert(\'Enter a total needed quantity first.\'); return; } if (!disciplineId) { window.alert(\'Select a discipline first.\'); return; } if (!companyId) { window.alert(\'Select a company first.\'); return; } addLinesToRequestCart([{ model_id: ' + row.id + ', quantity: quantity, discipline_id: disciplineId, company_id: companyId }], false);"><i class=\"fas fa-cart-plus\" aria-hidden=\"true\"></i><span class=\"sr-only\">Add to cart</span></button>'
                + '</div>';
        }

        return '';
    }

    function licenseRequestActionsFormatter(value, row) {
        var requestUrl = '{{ route('account/request-item', ['itemType' => 'license', 'itemId' => '__LICENSE_ID__']) }}'.replace('__LICENSE_ID__', row.id);
        var requestsUrl = '{{ route('requests.index') }}?license_id=' + row.id;
        var requestedQuantity = row.requested_quantity || 1;
        var requestedProjectId = row.requested_project_id || '';
        var requestedDisciplineId = row.requested_discipline_id || ((row.discipline && row.discipline.id) ? row.discipline.id : '');
        var requestedCompanyId = row.requested_company_id || ((row.company && row.company.id) ? row.company.id : '');
        var requestedNeededByDate = row.requested_needed_by_date || '';
        var requestedForType = row.requested_for_type || 'user';
        var requestedForDisplay = row.requested_for_display || '';
        var actionBarId = 'license-request-actions-' + row.id;
        var editStateId = 'license-request-edit-' + row.id;
        var buildLicenseRequestForm = function (actionValue, selectedProjectId, selectedDisciplineId, selectedCompanyId, selectedNeededByDate, selectedForType, selectedForDisplay, selectedQuantity, buttonLabel, buttonClass) {
            return '<form action=\"' + requestUrl + '\" method=\"POST\" style=\"display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:780px;\">'
                + '@csrf'
                + '<input type=\"hidden\" name=\"request-action\" value=\"' + actionValue + '\">'
                + '<input type=\"number\" min=\"1\" name=\"request-quantity\" value=\"' + selectedQuantity + '\" class=\"form-control input-sm\" style=\"width:72px;\" aria-label=\"{{ trans('general.qty') }}\" required>'
                + '<select name=\"requested_for_type\" class=\"form-control input-sm\" style=\"width:110px;\" required>'
                + '<option value=\"\">Assignee Type</option>'
                + '<option value=\"user\"' + (String(selectedForType) === 'user' ? ' selected' : '') + '>User</option>'
                + '<option value=\"asset\"' + (String(selectedForType) === 'asset' ? ' selected' : '') + '>Asset</option>'
                + '</select>'
                + '<input type=\"text\" name=\"requested_for_display\" value=\"' + escapeHtml(selectedForDisplay) + '\" class=\"form-control input-sm\" style=\"min-width:160px;\" placeholder=\"Assignee name or asset tag\" required>'
                + '<select name=\"requested_discipline_id\" class=\"form-control input-sm\" style=\"min-width:150px;\" required>' + buildModelRequestDisciplineOptions(selectedDisciplineId) + '</select>'
                + '<select name=\"company_id\" class=\"form-control input-sm\" style=\"min-width:150px;\" required>' + buildModelRequestCompanyOptions(selectedCompanyId) + '</select>'
                + '<select name=\"project_id\" class=\"form-control input-sm\" style=\"min-width:150px;\" required>' + buildModelRequestProjectOptions(selectedProjectId) + '</select>'
                + '<input type=\"date\" name=\"needed_by_date\" value=\"' + selectedNeededByDate + '\" class=\"form-control input-sm\" style=\"width:145px;\" required>'
                + '<button class=\"btn ' + buttonClass + ' btn-sm\">' + buttonLabel + '</button>'
                + '</form>';
        };

        if ((row.available_actions) && (row.available_actions.update_request === true)) {
            return '<div style="display:flex;flex-direction:column;align-items:flex-start;gap:4px;min-width:220px;">'
                + '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;line-height:1.2;">'
                + '<span class="label label-info" style="font-size:11px;">{{ trans('general.requested') }}</span>'
                + '<span style="font-size:12px;color:#4d4d4d;">{{ trans('general.qty') }}: <strong>' + requestedQuantity + '</strong></span>'
                + '</div>'
                + '<div id="' + actionBarId + '" style="display:flex;align-items:center;gap:0;flex-wrap:wrap;font-size:12px;">'
                + '<a href="' + requestsUrl + '" style="margin-right:8px;">View request</a>'
                + '<button type="button" class="btn btn-link btn-sm" style="padding:0;margin-right:8px;" onclick="document.getElementById(\'' + editStateId + '\').style.display = \'flex\'; document.getElementById(\'' + actionBarId + '\').style.display = \'none\';" data-tooltip="true" title="{{ trans('general.update') }}">Edit</button>'
                + '<form action="' + requestUrl + '" method="POST" style="margin:0;">'
                + '@csrf'
                + '<input type="hidden" name="request-action" value="cancel">'
                + '<button class="btn btn-link btn-sm text-danger" style="padding:0;" data-tooltip="true" title="{{ trans('admin/hardware/message.requests.cancel') }}">{{ trans('button.cancel') }}</button>'
                + '</form>'
                + '</div>'
                + '<div id="' + editStateId + '" style="display:none;flex-direction:column;gap:6px;">'
                + buildLicenseRequestForm('update', requestedProjectId, requestedDisciplineId, requestedCompanyId, requestedNeededByDate, requestedForType, requestedForDisplay, requestedQuantity, '{{ trans('general.update') }}', 'btn-primary')
                + '<button type="button" class="btn btn-link btn-sm" style="padding:0;align-self:flex-start;" onclick="document.getElementById(\'' + editStateId + '\').style.display = \'none\'; document.getElementById(\'' + actionBarId + '\').style.display = \'flex\';">{{ trans('button.cancel') }}</button>'
                + '</div>'
                + '<div style="font-size:11px;color:#6b7280;">Expected release is informational only.</div>'
                + '</div>';
        } else if ((row.available_actions) && (row.available_actions.request === true)) {
            return '<div style="display:flex;flex-direction:column;gap:6px;align-items:flex-start;min-width:430px;">'
                + '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">'
                + buildInlineLicenseBookingInput(row.id, requestedQuantity)
                + buildInlineLicenseAssigneeTypeSelect(row.id, requestedForType)
                + buildInlineLicenseAssigneeInput(row.id, requestedForDisplay)
                + buildInlineLicenseDisciplineSelect(row.id, requestedDisciplineId)
                + buildInlineLicenseCompanySelect(row.id, requestedCompanyId)
                + '<button type="button" class="btn btn-primary btn-sm" style="width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;" data-tooltip="true" title="Add to cart" onclick="addInlineLicenseRowToCart(' + row.id + ');"><i class=\"fas fa-cart-plus\" aria-hidden=\"true\"></i><span class=\"sr-only\">Add to cart</span></button>'
                + '</div>'
                + '<div style="font-size:11px;color:#6b7280;">Add the license to cart first, then choose project and needed-by date in the cart. Expected release is informational only.</div>'
                + '</div>';
        }

        return '';
    }

    function requestStatusFormatter(value) {
        if (!value) {
            return '';
        }

        var normalized = String(value).toLowerCase();
        var labelClass = 'label-default';

        if (normalized === 'pending') {
            labelClass = 'label-warning';
        } else if (normalized === 'in progress' || normalized === 'under review') {
            labelClass = 'label-primary';
        } else if (normalized === 'fully allocated' || normalized === 'closed' || normalized === 'fulfilled') {
            labelClass = 'label-success';
        } else if (normalized === 'partially allocated') {
            labelClass = 'label-info';
        } else if (normalized === 'canceled' || normalized === 'rejected' || normalized === 'unable to complete' || normalized === 'not allocated') {
            labelClass = 'label-danger';
        }

        return '<span class="label ' + labelClass + '">' + value + '</span>';
    }

    function requestWorkflowActionsFormatter(value, row) {
        if (!row || !row.request_detail_url) {
            return '';
        }

        var actions = [];
        var viewTitle = 'View request';
        actions.push(
            '<a href="' + row.request_detail_url + '" class="btn btn-sm btn-primary" data-tooltip="true" title="' + viewTitle + '">'
            + '<i class="fas fa-eye" aria-hidden="true"></i>'
            + '<span class="sr-only">' + viewTitle + '</span>'
            + '</a>'
        );

        if (row.request_update_url && row.model_id) {
            var modifyTitle = 'Modify request';
            var estimateUrl = '{{ route('account.request-estimate', ['itemType' => 'asset_model', 'itemId' => '__MODEL_ID__']) }}'.replace('__MODEL_ID__', row.model_id);
            actions.push(
                '<button type="button" class="btn btn-sm btn-warning" data-tooltip="true" title="' + modifyTitle + '" onclick="openModelRequestModal({ requestUrl: \'' + row.request_update_url + '\', estimateUrl: \'' + estimateUrl + '\', action: \'update\', companyId: \'' + (row.company_id || '') + '\', projectId: \'' + (row.project_id || '') + '\', requestedDisciplineId: \'' + (row.requested_discipline_id || '') + '\', quantity: ' + (row.qty || 0) + ', neededByDate: \'' + (row.needed_by_date_value || '') + '\', title: \'' + modifyTitle + '\', submitLabel: \'Update\' });">'
                + '<i class="fas fa-pen" aria-hidden="true"></i>'
                + '<span class="sr-only">' + modifyTitle + '</span>'
                + '</button>'
            );
        }

        if (row.request_cancel_url) {
            var cancelTitle = 'Cancel request';
            actions.push(
                '<button type="button" class="btn btn-sm btn-danger" data-tooltip="true" title="' + cancelTitle + '" onclick="cancelSubmittedRequestRow(\'' + row.request_cancel_url + '\');">'
                + '<i class="fas fa-times" aria-hidden="true"></i>'
                + '<span class="sr-only">' + cancelTitle + '</span>'
                + '</button>'
            );
        }

        return '<div style="display:flex;gap:6px;align-items:center;">' + actions.join('') + '</div>';
    }

    function requestDetailLinkFormatter(value, row) {
        if (row && row.request_detail_url) {
            return '<a href="' + row.request_detail_url + '">#' + value + '</a>';
        }

        return value;
    }

    function requestModelLinkFormatter(value, row) {
        if (row && row.item_show_url) {
            return '<a href="' + row.item_show_url + '">' + value + '</a>';
        }

        if (row && row.model_show_url) {
            return '<a href="' + row.model_show_url + '">' + value + '</a>';
        }

        return value;
    }

    function requestProjectLinkFormatter(value, row) {
        if (row && row.project_requests_url && value) {
            return '<a href="' + row.project_requests_url + '">' + value + '</a>';
        }

        return value;
    }

    function requestAvailabilityLinkFormatter(value, row, urlField) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        if (row && row[urlField]) {
            return '<a href="' + row[urlField] + '">' + value + '</a>';
        }

        return value;
    }

    function requestReusableNowFormatter(value, row) {
        return requestAvailabilityLinkFormatter(value, row, 'reusable_now_url');
    }

    function requestDueBackFormatter(value, row) {
        return requestAvailabilityLinkFormatter(value, row, 'due_back_url');
    }

    function requestReservedFormatter(value, row) {
        return requestAvailabilityLinkFormatter(value, row, 'reserved_assets_url');
    }

    function requestReservedByOtherProjectFormatter(value, row) {
        return requestAvailabilityLinkFormatter(value, row, 'reserved_by_other_project_url');
    }

    function requestSavingsFormatter(value, row) {
        if (row && row.estimated_savings_formatted) {
            return row.estimated_savings_formatted;
        }

        if (value === null || value === undefined || value === '') {
            return '';
        }

        return formatEstimateCurrency(value);
    }

    function requestTotalNeedCostFormatter(value, row) {
        if (row && row.total_need_cost_formatted) {
            return row.total_need_cost_formatted;
        }

        if (value === null || value === undefined || value === '') {
            return '';
        }

        return formatEstimateCurrency(value);
    }

    function requestAmountToBuyFormatter(value, row) {
        if (row && row.amount_to_buy_formatted) {
            return row.amount_to_buy_formatted;
        }

        if (value === null || value === undefined || value === '') {
            return '';
        }

        return formatEstimateCurrency(value);
    }

    $(function () {
        attachRequestTableHeaderTooltips();
        $('.snipe-table').on('post-header.bs.table load-success.bs.table', attachRequestTableHeaderTooltips);
    });

    function cancelSubmittedRequestRow(url) {
        if (!window.confirm('Cancel this request?')) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        var token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = '{{ csrf_token() }}';
        form.appendChild(token);

        document.body.appendChild(form);
        form.submit();
    }



    var formatters = [
        'accessories',
        'categories',
        'companies',
        'components',
        'consumables',
        'departments',
        'disciplines',
        'depreciations',
        'fieldsets',
        'groups',
        'hardware',
        'kits',
        'licenses',
        'locations',
        'maintenances',
        'manufacturers',
        'models',
        'projects',
        'statuslabels',
        'suppliers',
        'users',
    ];

    for (var i in formatters) {
        window[formatters[i] + 'LinkFormatter'] = genericRowLinkFormatter(formatters[i]);
        window[formatters[i] + 'LinkObjFormatter'] = genericColumnObjLinkFormatter(formatters[i]);
        window[formatters[i] + 'ActionsFormatter'] = genericActionsFormatter(formatters[i]);
        window[formatters[i] + 'InOutFormatter'] = genericCheckinCheckoutFormatter(formatters[i]);
    }

    var child_formatters = [
        ['kits', 'models'],
        ['kits', 'licenses'],
        ['kits', 'consumables'],
        ['kits', 'accessories'],
    ];

    for (var i in child_formatters) {
        var owner_name = child_formatters[i][0];
        var child_name = child_formatters[i][1];
        window[owner_name + '_' + child_name + 'ActionsFormatter'] = genericActionsFormatter(owner_name, child_name);
    }



    // This is  gross, but necessary so that we can package the API response
    // for custom fields in a more useful way.
    function customFieldsFormatter(value, row) {


            if ((!this) || (!this.title)) {
                return '';
            }

            var field_column = this.title;

            // Pull out any HTMl that might be passed via the presenter
            // (for example, the locked icon for encrypted fields)
            var field_column_plain = field_column.replace(/<(?:.|\n)*?> ?/gm, '');
            if ((row.custom_fields) && (row.custom_fields[field_column_plain])) {

                // If the field type needs special formatting, do that here
                if ((row.custom_fields[field_column_plain].field_format) && (row.custom_fields[field_column_plain].value)) {
                    if (row.custom_fields[field_column_plain].field_format=='URL') {
                        return '<a href="' + row.custom_fields[field_column_plain].value + '" target="_blank" rel="noopener">' + row.custom_fields[field_column_plain].value + '</a>';
                    } else if (row.custom_fields[field_column_plain].field_format=='BOOLEAN') {
                        return (row.custom_fields[field_column_plain].value == 1) ? "<span class='fas fa-check-circle' style='color:green'>" : "<span class='fas fa-times-circle' style='color:red' />";
                    } else if (row.custom_fields[field_column_plain].field_format=='EMAIL') {
                        return '<a href="mailto:' + row.custom_fields[field_column_plain].value + '" style="white-space: nowrap" data-tooltip="true" title="{{ trans('general.send_email') }}"><x-icon type="email" /> ' + row.custom_fields[field_column_plain].value + '</a>';
                    }
                }
                return row.custom_fields[field_column_plain].value;

            }

    }


    function createdAtFormatter(value) {
        if ((value) && (value.formatted)) {
            return value.formatted;
        }
    }

    function externalLinkFormatter(value) {

        if (value) {
            if ((value.indexOf("{") === -1) || (value.indexOf("}") ===-1)) {
                return '<nobr><a href="' + value + '" target="_blank" title="{{ trans('general.external_link_tooltip') }} ' + value + '" data-tooltip="true"><x-icon type="external-link" /> ' + value + '</a></nobr>';
            }
            return value;
        }
    }

    function groupsFormatter(value) {

        if (value) {
            var groups = '';
            for (var index in value.rows) {
                groups += '<a href="{{ config('app.url') }}/admin/groups/' + value.rows[index].id + '" class="label label-default">' + value.rows[index].name + '</a> ';
            }
            return groups;
        }
    }



    function changeLogFormatter(value) {

        var result = '';
        var pretty_index = '';

            for (var index in value) {


                // Check if it's a custom field
                if (index.startsWith('_snipeit_')) {
                    pretty_index = index.replace("_snipeit_", "Custom:_");
                } else {
                    pretty_index = index;
                }

                extra_pretty_index = prettyLog(pretty_index);

                result += extra_pretty_index + ': <del>' + value[index].old + '</del>  <x-icon type="long-arrow-right" /> ' + value[index].new + '<br>'
            }

        return result;

    }

    function prettyLog(str) {
        let frags = str.split('_');
        for (let i = 0; i < frags.length; i++) {
            frags[i] = frags[i].charAt(0).toUpperCase() + frags[i].slice(1);
        }
        return frags.join(' ');
    }

    // Show the warning if below min qty
    function minAmtFormatter(row, value) {

        if ((row) && (row!=undefined)) {
            
            if (value.remaining <= value.min_amt) {
                return  '<span class="text-danger text-bold" data-tooltip="true" title="{{ trans('admin/licenses/general.below_threshold_short') }}"><x-icon type="warning" class="text-yellow" /> ' + value.min_amt + '</span>';
            }
            return value.min_amt
        }
        return '--';
    }

    

    // Create a linked phone number in the table list
    function phoneFormatter(value) {
        if (value) {
            return  '<span style="white-space: nowrap;"><a href="tel:' + value + '" data-tooltip="true" title="{{ trans('general.call') }}"><x-icon type="phone" /> ' + value + '</a></span>';
        }
    }

    // Create a linked phone number in the table list
    function mobileFormatter(value) {
        if (value) {
            return  '<span style="white-space: nowrap;"><a href="tel:' + value + '" data-tooltip="true" title="{{ trans('general.call') }}"><x-icon type="mobile" /> ' + value + '</a></span>';
        }
    }


    function deployedLocationFormatter(row, value) {
        if ((row) && (row!=undefined)) {
            // Handle the preceding icon if a tag_color is given in the API response
            if ((row.tag_color) && (row.tag_color!='')) {
                var tag_icon = '<i class="fa-solid fa-square" style="color: ' + row.tag_color + ';" aria-hidden="true"></i> ';
            } else {
                var tag_icon = '';
            }

            return '<nobr>' + tag_icon +'<a href="{{ config('app.url') }}/locations/' + row.id + '">' + row.name + '</a></nobr>';
        } else if (value.rtd_location) {
            return '<a href="{{ config('app.url') }}/locations/' + value.rtd_location.id + '">' + value.rtd_location.name + '</a>';
        }

    }

    function groupsAdminLinkFormatter(value, row) {
        return '<a href="{{ config('app.url') }}/admin/groups/' + row.id + '">' + value + '</a>';
    }

    function assetTagLinkFormatter(value, row) {
        if ((row.asset) && (row.asset.id)) {
            if (row.asset.deleted_at) {
                return '<span style="white-space: nowrap;"><x-icon type="x" class="text-danger" /><span class="sr-only">{{ trans('admin/hardware/general.deleted') }}</span> <del><a href="{{ config('app.url') }}/hardware/' + row.asset.id + '" data-tooltip="true" title="{{ trans('admin/hardware/general.deleted') }}">' + row.asset.asset_tag + '</a></del></span>';
            }
            return '<a href="{{ config('app.url') }}/hardware/' + row.asset.id + '">' + row.asset.asset_tag + '</a>';
        }
        return '';

    }

    function departmentNameLinkFormatter(value, row) {
        if ((row.assigned_user) && (row.assigned_user.department) && (row.assigned_user.department.name)) {
            return '<a href="{{ config('app.url') }}/departments/' + row.assigned_user.department.id + '">' + row.assigned_user.department.name + '</a>';
        }

    }

    function assetNameLinkFormatter(value, row) {
        if ((row.asset) && (row.asset.name)) {
            return '<a href="{{ config('app.url') }}/hardware/' + row.asset.id + '">' + row.asset.name + '</a>';
        }
    }

    function assetSerialLinkFormatter(value, row) {

        if ((row.asset) && (row.asset.serial)) {
            if (row.asset.deleted_at) {
                return '<span style="white-space: nowrap;"><x-icon type="x" class="text-danger" /><span class="sr-only">deleted</span> <del><a href="{{ config('app.url') }}/hardware/' + row.asset.id + '" data-tooltip="true" title="{{ trans('admin/hardware/general.deleted') }}">' + row.asset.serial + '</a></del></span>';
            }
            return '<a href="{{ config('app.url') }}/hardware/' + row.asset.id + '">' + row.asset.serial + '</a>';
        }
        return '';
    }

    function trueFalseFormatter(value) {
        if ((value) && ((value == 'true') || (value == '1'))) {
            return '<x-icon type="checkmark" class="text-success" /><span class="sr-only">{{ trans('general.true') }}</span>';
        } else {
            return '<x-icon type="x" class="text-danger" /><span class="sr-only">{{ trans('general.false') }}</span>';
        }
    }

    function yesNoFormatter(value) {
        if ((value) && ((value == 'true') || (value == '1'))) {
            return '{{ trans('general.yes') }}';
        }

        return '{{ trans('general.no') }}';
    }

    function dateDisplayFormatter(value) {
        if (value) {
            return  value.formatted;
        }
    }

    function iconFormatter(value) {
        if (value) {
            return '<i class="' + value + '  icon-med"></i>';
        }
    }

    function emailFormatter(value) {
        if (value) {
            return '<a href="mailto:' + value + '" style="white-space: nowrap" data-tooltip="true" title="{{ trans('general.send_email') }}"><x-icon type="email" /> ' + value + '</a>';
        }
    }

    function linkFormatter(value) {
        if (value) {
            return '<a href="' + value + '">' + value + '</a>';
        }
    }

    function assetCompanyFilterFormatter(value, row) {
        if (value) {
            return '<a href="{{ config('app.url') }}/hardware/?company_id=' + row.id + '">' + value + '</a>';
        }
    }

    function assetCompanyObjFilterFormatter(value, row) {
        if ((row) && (row.company)) {
            return '<a href="{{ config('app.url') }}/hardware/?company_id=' + row.company.id + '">' + row.company.name + '</a>';
        }
    }

    function usersCompanyObjFilterFormatter(value, row) {
        if (value) {
            return '<a href="{{ config('app.url') }}/users/?company_id=' + row.id + '">' + value + '</a>';
        } else {
            return value;
        }
    }

    function locationCompanyObjFilterFormatter(value, row) {
        if (value) {
            return '<a href="{{ url('/') }}/locations/?company_id=' + row.company.id + '">' + row.company.name + '</a>';
        } else {
            return value;
        }
    }

    function employeeNumFormatter(value, row) {

        if ((row) && (row.assigned_to) && ((row.assigned_to.employee_number))) {
            return '<a href="{{ config('app.url') }}/users/' + row.assigned_to.id + '">' + row.assigned_to.employee_number + '</a>';
        }
    }

    function jobtitleFormatter(value, row) {
        if ((row) && (row.assigned_to) && ((row.assigned_to.jobtitle))) {
            return '<a href="{{ config('app.url') }}/users/' + row.assigned_to.id + '">' + row.assigned_to.jobtitle + '</a>';
        }
    }

    function orderNumberObjFilterFormatter(value, row) {
        if (value) {
            return '<a href="{{ config('app.url') }}/hardware/?order_number=' + row.order_number + '">' + row.order_number + '</a>';
        }
    }

    function auditImageFormatter(value, row) {
        if ((row) && (row.file) && (row.file.url)) {
            return '<a href="' + row.file.url + '" data-toggle="lightbox" data-type="image"><img src="' + row.file.url + '" style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width: auto;" class="img-responsive" alt=""></a>'
        }
    }


   function imageFormatter(value, row) {

        if (value) {

            // This is a clunky override to handle unusual API responses where we're presenting a link instead of an array
            if (row.avatar) {
                var altName = '';
            }
            else if (row.name) {
                var altName = row.name;
            }
            else if ((row) && (row.model)) {
                var altName = row.model.name;
           }
            return '<a href="' + value + '" data-toggle="lightbox" data-type="image"><img src="' + value + '" style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width: auto;" class="img-responsive" alt="' + altName + '"></a>';
        }
    }


    // This is users in the user accounts section for EULAs
    function downloadFormatter(value) {
        if (value) {
            return '<a href="' + value + '" class="btn btn-sm btn-default"><x-icon type="download" /></a>';
        }
    }

    // This is used by the UploadedFilesPresenter and the HistoryPresenter
    // It handles the download and inline buttons for files that are uploaded to assets, users, etc
    function fileDownloadButtonsFormatter(row, value) {

        if (value)  {
            if (value.url) {
                var inlinable = value.inlineable;
                var exists_on_disk = value.exists_on_disk;
                var download_url = value.url;
            } else if (value.file) {
                var inlinable = value.file.inlineable;
                var exists_on_disk = value.file.exists_on_disk;
                var download_url = value.file.url;
            } else {
                return '';
            }

            var download_button = '<a href="' + download_url + '" class="btn btn-sm btn-default" data-tooltip="true" title="{{ trans('general.download') }}"><x-icon type="download" /></a>';
            var download_button_disabled = '<span data-tooltip="true" title="{{ trans('general.file_does_not_exist') }}"><a class="btn btn-sm btn-default disabled"><x-icon type="download" /></a></span>';
            var inline_button = '<a href="'+ download_url +'?inline=true" class="btn btn-sm btn-default" target="_blank" data-tooltip="true" title="{{ trans('general.open_new_window') }}"><x-icon type="external-link" /></a>';
            var inline_button_disabled = '<span data-tooltip="true" title="{{ trans('general.file_not_inlineable') }}"><a class="btn btn-sm btn-default disabled" target="_blank" data-tooltip="true" title="{{ trans('general.file_does_not_exist') }}"><x-icon type="external-link" /></a></span>';

            if (exists_on_disk === true) {
                if (inlinable === true) {
                    return '<span style="white-space: nowrap;">' + download_button + ' ' + inline_button + '</span>';
                } else {
                    return '<span style="white-space: nowrap;">' + download_button + ' ' + inline_button_disabled + '</span>';
                }
            } else {
                return '<span style="white-space: nowrap;">' + download_button_disabled + ' ' + inline_button_disabled + '</span>';
            }

        }
    }


    function filePreviewFormatter(row, value) {

        if ((value) && (value.url) && (value.inlineable)) {

            if (value.mediatype == 'image') {
                return '<a href="' + value.url + '" data-toggle="lightbox" data-type="image"><img src="' + value.url + '" style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width: auto;" class="img-responsive" alt=""></a>';
            } else if (value.mediatype == 'video') {
                return '<a href="' + value.url + '?inline=true" data-toggle="lightbox" data-type="video"><video style="max-height: {{ $snipeSettings->thumbnail_max_h }}px; width: auto;" class="img-responsive"><source src="' + value.url + '?inline=true"></video></a>';
            } else if (value.mediatype == 'audio') {
                return '<audio controls><source src="' + value.url + '?inline=true" type="audio/mp3">Your browser does not support the audio element.</audio>';
            }
            return '{{ trans('general.preview_not_available') }}';
        }
        return '{{ trans('general.preview_not_available') }}';

    }




    // This is used in the table listings
    function deleteUploadFormatter(value, row) {

        if ((row.available_actions) && (row.available_actions.delete === true)) {
            var destination;

            // This is kinda gross, but for right now we're posting to the GUI delete routes
            // All of these URLS and storage directories need to be updated to be more consistent :(
            if (row.item.type === 'assetmodels') {
                destination = 'models';
            } else if (row.item.type === 'assets') {
                destination = 'hardware';
            } else {
                destination = row.item.type;
            }

            return '<a href="{{ config('app.url') }}/' + destination + '/' + row.item.id + '/files/' + row.id + '/delete" '
                + ' data-target="#dataConfirmModal" class="actions btn btn-danger btn-sm delete-asset" data-tooltip="true"  '
                + ' data-toggle="modal" data-icon="fa-trash"'
                + ' data-content="{{ trans('general.file_upload_status.confirm_delete') }}: ' + row.filename + '?" '
                + ' data-title="{{  trans('general.delete') }}" onClick="return false;" data-icon="fa-trash">'
                + '<x-icon type="delete" /><span class="sr-only">{{ trans('general.delete') }}</span></a>&nbsp;';
        }
    }

    // This handles the custom view for the filestable blade component gallery-card component
    window.customViewFormatter = data => {
        const template = $('#fileGalleryTemplate').html()
        let view = ''

        $.each(data, function (i, row) {

            delete_url = row.url +'/delete';

            if (row.exists_on_disk === true)
            {
                if (row.mediatype === 'image') {
                    embed_code = '<a href="' + row.url + '" data-toggle="lightbox" data-type="image" data-title="' + row.filename + row.filename + '" data-footer="' + row.note + '" class="embed-responsive-item"><img src="' + row.url + '?inline=true" alt="" style="max-width: 100%"></a>';
                } else if (row.mediatype === 'video') {
                    embed_code = '<a href="' + row.url + '" data-toggle="lightbox" data-type="video" data-title="' + row.filename + row.filename + '" data-footer="' + row.note + '" class="embed-responsive-item"><video controls><source src="' + row.url + '?inline=true" type="video/mp4">Your browser does not support the video tag.</video></a>';
                } else if (row.mediatype === 'audio') {
                    embed_code = '<audio style="width: 100%" controls><source src="' + row.url + '?inline=true" type="audio/mpeg">Your browser does not support the audio element.</audio>';
                } else if (row.mediatype === 'pdf') {
                    embed_code = '<object height="200" style="width: 100%" type="application/pdf" data="' + row.url + '?inline=true">File cannot be displayed</object>';
                } else {
                    embed_code = '<div class="text-center"><a href="' + row.url + '?inline=true"><i class="' + row.icon + '" style="font-size: 50px" /></i></a></div>';
                }
            } else {
                embed_code = '<div class="text-center text-danger" style="padding-top: 20px;"><i class="fa-solid fa-heart-crack" style="font-size: 80px" /></i> <br><br>{{ trans('general.file_upload_status.file_not_found') }}</div>';
            }

            view += template.replace('%ID%', row.id)
                .replace('%ICON%', row.icon)
                .replace('%FILETYPE%', row.filetype)
                .replace('%FILE_URL%', row.url)
                .replace('%LINK_URL%', row.url)
                .replace('%FILENAME%', (row.exists_on_disk === true) ? row.filename : '<x-icon type="x" /> <del>' + row.filename + '</del>')
                .replace('%CREATED_AT%', row.created_at.formatted)
                .replace('%CREATED_BY%', (row.created_by) ? row.created_by.name : '')
                .replace('%NOTE%', (row.note) ? row.note : '')
                .replace('%PANEL_CLASS%', (row.exists_on_disk === true) ? 'default' : 'danger')
                .replace('%FILE_EMBED%', embed_code)
                .replace('%DOWNLOAD_BUTTON%', (row.exists_on_disk === true) ? '<a href="'+ row.url +'" class="btn btn-sm btn-default"><x-icon type="download" /></a> ' : '<span class="btn btn-sm btn-default disabled" data-tooltip="true" title="{{ trans('general.file_upload_status.file_not_found') }}"><x-icon type="download" /></span>')
                .replace('%NEW_WINDOW_BUTTON%', (row.exists_on_disk === true) ? '<a href="'+ row.url +'?inline=true" class="btn btn-sm btn-default" target="_blank"><x-icon type="external-link" /></a> ' : '<span class="btn btn-sm btn-default disabled" data-tooltip="true" title="{{ trans('general.file_upload_status.file_not_found') }}"><x-icon type="external-link"/></span>')
                .replace('%DELETE_BUTTON%', (row.available_actions.delete === true) ?
                    '<a href="'+delete_url+'" class="delete-asset btn btn-danger btn-sm" data-icon="fa-trash" data-toggle="modal" data-content="{{ trans('general.file_upload_status.confirm_delete') }} '+ row.filename +'?" data-title="{{ trans('general.delete') }}" onClick="return false;" data-target="#dataConfirmModal"><x-icon type="delete" /><span class="sr-only">{{ trans('general.delete') }}</span></a>' :
                    '<a class="btn btn-sm btn-danger disabled" data-tooltip="true" title="{{ trans('general.file_upload_status.file_not_found') }}"><x-icon type="delete" /><span class="sr-only">{{ trans('general.delete') }}</span></a>'
                );
        })

        return `<div class="row">${view}</div>`
    }



    function fileNameFormatter(row, value) {

        if (value) {
            if ((value.file) && (value.file.filename) && (value.file.url)) {

                if (value.file.exists_on_disk === true) {
                    return '<a href="' + value.file.url + '">' + value.file.filename + '</a>';
                }

                return '<span class="text-danger" style="text-decoration: line-through;" data-tooltip="true" title="{{ trans('general.file_does_not_exist') }}"><x-icon type="x" /> ' + value.file.filename + '</span>';

            } else if ((value.filename) && (value.url)) {
                if (value.exists_on_disk === true) {
                    return '<a href="' + value.url + '">' + value.filename + '</a>';
                }
                return '<span class="text-danger" style="text-decoration: line-through;" data-tooltip="true" title="{{ trans('general.file_does_not_exist') }}"><x-icon type="x" /> ' + value.filename + '</span>';
            }
        }

    }


    function linkToUserSectionBasedOnCount (count, id, section) {
        if (count) {
            return '<a href="{{ config('app.url') }}/users/' + id + '#' + section +'">' + count + '</a>';
        }

        return count;
    }

    function linkNumberToUserAssetsFormatter(value, row) {
        return linkToUserSectionBasedOnCount(value, row.id, 'asset');
    }

    function linkNumberToUserLicensesFormatter(value, row) {
        return linkToUserSectionBasedOnCount(value, row.id, 'licenses');
    }

    function linkNumberToUserConsumablesFormatter(value, row) {
        return linkToUserSectionBasedOnCount(value, row.id, 'consumables');
    }

    function linkNumberToUserAccessoriesFormatter(value, row) {
        return linkToUserSectionBasedOnCount(value, row.id, 'accessories');
    }

    function linkNumberToUserManagedUsersFormatter(value, row) {
        return linkToUserSectionBasedOnCount(value, row.id, 'managed-users');
    }

    function linkNumberToUserManagedLocationsFormatter(value, row) {
        return linkToUserSectionBasedOnCount(value, row.id, 'managed-locations');
    }

    function labelPerPageFormatter(value, row, index, field) {
        if (row) {
            if (!row.hasOwnProperty('sheet_info')) { return 1; }
            else { return row.sheet_info.labels_per_page; }
        }
    }

    function labelRadioFormatter(value, row, index, field) {
        if (row) {
            return row.name == '{{ str_replace("\\", "\\\\", $snipeSettings->label2_template) }}';
        }
    }

    function labelSizeFormatter(value, row) {
        if (row) {
            return row.width + ' x ' + row.height + ' ' + row.unit;
        }
    }

    function cleanFloat(number) {
        if(!number) { // in a JavaScript context, meaning, if it's null or zero or unset
            return 0.0;
        }
        if ("{{$snipeSettings->digit_separator}}" == "1.234,56") {
            // yank periods, change commas to periods
            periodless = number.toString().replace(/\./g,"");
            decimalfixed = periodless.replace(/,/g,".");
        } else {
            // yank commas, that's it.
            decimalfixed = number.toString().replace(/\,/g,"");
        }
        return parseFloat(decimalfixed);
    }


    function qtySumFormatter(data) {
        var currentField = this.field;
        var total = 0;
        var fieldname = this.field;

        $.each(data, function() {
            var r = this;
            total += this[currentField];
        });
        return total;
    }

    function sumFormatter(data) {
        if (Array.isArray(data)) {
            var field = this.field;
            var total_sum = data.reduce(function(sum, row) {
                
                return (sum) + (cleanFloat(row[field]) || 0);
            }, 0);
            
            return numberWithCommas(total_sum.toFixed(2));
        }
        return 'not an array';
    }

    function sumFormatterQuantity(data){
        if(Array.isArray(data)) {
            
            // Prevents issues on page load where data is an empty array
            if(data[0] == undefined){
                return 0.00
            }
            // Check that we are actually trying to sum cost from a table
            // that has a quantity column. We must perform this check to
            // support licences which use seats instead of qty
            if('qty' in data[0]) {
                var multiplier = 'qty';
            } else if('seats' in data[0]) {
                var multiplier = 'seats';
            } else {
                return 'no quantity';
            }
            var total_sum = data.reduce(function(sum, row) {
                return (sum) + (cleanFloat(row["purchase_cost"])*row[multiplier] || 0);
            }, 0);
            return numberWithCommas(total_sum.toFixed(2));
        }
        return 'not an array';
    }

    function numberWithCommas(value) {
        
        if ((value) && ("{{$snipeSettings->digit_separator}}" == "1.234,56")){
            var parts = value.toString().split(".");
             parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
             return parts.join(",");
         } else {
             var parts = value.toString().split(",");
             parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
             return parts.join(".");
        }
        return value
    }

    $(function () {
        $('#bulkEdit').click(function () {
            var selectedIds = $('.snipe-table').bootstrapTable('getSelections');
            $.each(selectedIds, function(key,value) {
                $( "#bulkForm" ).append($('<input type="hidden" name="ids[' + value.id + ']" value="' + value.id + '">' ));
            });

        });
    });

    $(function() {

        // This handles the search box highlighting on both ajax and client-side
        // bootstrap tables
        var searchboxHighlighter = function (event) {

            $('.search-input').each(function (index, element) {

                if ($(element).val() != '') {
                    $(element).addClass('search-highlight');
                    $(element).next().children().addClass('search-highlight');
                } else {
                    $(element).removeClass('search-highlight');
                    $(element).next().children().removeClass('search-highlight');
                }
            });
        };

        $('.search button[name=clearSearch]').click(searchboxHighlighter);
        searchboxHighlighter({ name:'pageload'});
        $('.search-input').keyup(searchboxHighlighter);

        //  This is necessary to make the bootstrap tooltips work inside of the
        // wenzhixin/bootstrap-table formatters
        $('#table').on('post-body.bs.table', function () {
            $('[data-tooltip="true"]').tooltip({
                container: 'body'
            });


        });
    });

</script>
    
@endpush
