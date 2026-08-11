<script nonce="{{ csrf_token() }}">
    (function () {
        var addUrl = @json(route('account.request-cart.licenses.items.add'));
        var previewUrl = @json(route('account.request-cart.licenses.preview'));
        var removeUrl = @json(route('account.request-cart.licenses.items.remove'));
        var clearUrl = @json(route('account.request-cart.licenses.clear'));

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function postJson(url, data) {
            return $.ajax({
                url: url,
                method: 'POST',
                data: $.extend({_token: @json(csrf_token())}, data)
            });
        }

        function showError(message) {
            $('#licenseRequestCartError').text(message).show();
        }

        function updateCount(count) {
            $('#licenseRequestCartCount').text(count || 0);
        }

        function lineFromRow(row) {
            var line = {
                license_id: parseInt(row.data('license-id'), 10),
                quantity: parseInt(row.find('.license-request-quantity').val(), 10),
                discipline_id: parseInt(row.find('.license-request-discipline').val(), 10),
                company_id: parseInt(row.find('.license-request-company').val(), 10)
            };

            if (!line.quantity || !line.discipline_id || !line.company_id) {
                window.alert('Choose quantity, destination discipline, and destination company.');
                return null;
            }

            return line;
        }

        function addLinesToLicenseRequestCart(lines) {
            postJson(addUrl, {lines: lines}).done(function (response) {
                updateCount(response.cart_count);
                showModelRequestCartToast(lines.length > 1 ? 'Items added to cart.' : 'Item added to cart.');
            }).fail(function (xhr) {
                window.alert(xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Unable to add the license request.');
            });
        }

        function previewCart() {
            $('#licenseRequestCartError').hide();
            var projectId = $('#licenseRequestProject').val();
            var neededByDate = $('#licenseRequestNeededBy').val();
            var metadataReady = Boolean(projectId && neededByDate);

            return postJson(previewUrl, {
                project_id: projectId,
                needed_by_date: neededByDate
            }).done(function (response) {
                var rows = [];
                (response.lines || []).forEach(function (line) {
                    rows.push('<tr>'
                        + '<td>' + escapeHtml(line.license_name) + '</td>'
                        + '<td>' + escapeHtml(line.discipline_name) + '</td>'
                        + '<td>' + escapeHtml(line.company_name) + '</td>'
                        + '<td>' + line.quantity + '</td>'
                        + '<td>' + (metadataReady ? line.reusable_quantity : '&mdash;') + '</td>'
                        + '<td>' + (metadataReady ? line.due_back_before_needed_by_quantity : '&mdash;') + '</td>'
                        + '<td>' + (metadataReady ? line.procurement_shortfall : '&mdash;') + '</td>'
                        + '<td>' + (metadataReady ? escapeHtml(line.estimated_savings_formatted) : '&mdash;') + '</td>'
                        + '<td>' + (metadataReady ? escapeHtml(line.amount_to_buy_formatted) : '&mdash;') + '</td>'
                        + '<td><button type="button" class="btn btn-danger btn-xs remove-license-cart-line"'
                        + ' data-license-id="' + line.license_id + '"'
                        + ' data-discipline-id="' + line.discipline_id + '"'
                        + ' data-company-id="' + line.company_id + '"><i class="fas fa-times"></i></button></td>'
                        + '</tr>');
                });

                if (!rows.length) {
                    rows.push('<tr><td colspan="10" class="text-muted">Your request cart is empty.</td></tr>');
                }
                $('#licenseRequestCartLines').html(rows.join(''));
                updateCount(response.cart_count);

                var totals = response.totals || {};
                var formatted = response.totals_formatted || {};
                $('#licenseRequestCartTotalRequested').text(totals.quantity || 0);
                $('#licenseRequestCartTotalReusable').html(metadataReady ? (totals.reusable_quantity || 0) : '&mdash;');
                $('#licenseRequestCartTotalDueBack').html(metadataReady ? (totals.due_back_before_needed_by_quantity || 0) : '&mdash;');
                $('#licenseRequestCartTotalShortfall').html(metadataReady ? (totals.procurement_shortfall || 0) : '&mdash;');
                $('#licenseRequestCartTotalSavings').html(metadataReady ? escapeHtml(formatted.estimated_savings || '0.00') : '&mdash;');
                $('#licenseRequestCartTotalBuy').html(metadataReady ? escapeHtml(formatted.amount_to_buy || '0.00') : '&mdash;');
                $('#submitLicenseRequestCart').prop('disabled', !(response.cart_count > 0));
            }).fail(function (xhr) {
                showError(xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Unable to preview the license request cart.');
            });
        }

        $('#licenseRequestProject').html(buildModelRequestProjectOptions(''));

        $('#licenseRequestCreateProject').on('click', function () {
            createProjectFromRequestModal('#licenseRequestProject', '#licenseRequestCartError');
        });

        $(document).on('click', '.add-license-to-request-cart', function () {
            var line = lineFromRow($(this).closest('tr'));
            if (!line) {
                return;
            }

            addLinesToLicenseRequestCart([line]);
        });

        $('#requestableLicensesBulkForm').on('submit', function (event) {
            event.preventDefault();

            var rows = $('#requestableLicensesTable').bootstrapTable('getSelections');

            if (!rows.length) {
                window.alert('Select at least one license.');
                return false;
            }

            var lines = [];

            for (var i = 0; i < rows.length; i++) {
                var row = $('#requestableLicensesTable tr[data-license-id="' + parseInt(rows[i].id, 10) + '"]');
                var line = lineFromRow(row);

                if (!line) {
                    return false;
                }

                lines.push(line);
            }

            addLinesToLicenseRequestCart(lines);
            return false;
        });

        $('#licenseRequestCartButton').on('click', function () {
            $('#licenseRequestCartModal').modal('show');
            previewCart();
        });

        $('#licenseRequestProject, #licenseRequestNeededBy').on('change', previewCart);

        $(document).on('click', '.remove-license-cart-line', function () {
            postJson(removeUrl, {
                license_id: $(this).data('license-id'),
                discipline_id: $(this).data('discipline-id'),
                company_id: $(this).data('company-id')
            }).done(previewCart);
        });

        $('#clearLicenseRequestCart').on('click', function () {
            postJson(clearUrl, {}).done(previewCart);
        });
    })();
</script>
