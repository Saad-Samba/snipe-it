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

        function selectedTarget(row) {
            var type = row.find('.license-request-target-type').val();
            var select = type === 'asset'
                ? row.find('.license-request-asset-target')
                : row.find('.license-request-user-target');

            return {type: type, id: parseInt(select.val(), 10)};
        }

        function lineFromRow(row) {
            var target = selectedTarget(row);
            var line = {
                license_id: parseInt(row.data('license-id'), 10),
                quantity: parseInt(row.find('.license-request-quantity').val(), 10),
                discipline_id: parseInt(row.find('.license-request-discipline').val(), 10),
                company_id: parseInt(row.find('.license-request-company').val(), 10),
                requested_for_type: target.type,
                requested_for_id: target.id
            };

            if (!line.quantity || !line.discipline_id || !line.company_id || !line.requested_for_id) {
                window.alert('Choose quantity, destination discipline, destination company, and a target.');
                return null;
            }

            return line;
        }

        function previewCart() {
            $('#licenseRequestCartError').hide();
            return postJson(previewUrl, {
                project_id: $('#licenseRequestProject').val(),
                needed_by_date: $('#licenseRequestNeededBy').val()
            }).done(function (response) {
                var rows = [];
                (response.lines || []).forEach(function (line) {
                    rows.push('<tr>'
                        + '<td>' + escapeHtml(line.license_name) + '</td>'
                        + '<td>' + escapeHtml(line.requested_for_display) + '</td>'
                        + '<td>' + line.quantity + '</td>'
                        + '<td>' + line.reusable_quantity + '</td>'
                        + '<td>' + line.due_back_before_needed_by_quantity + '</td>'
                        + '<td>' + line.procurement_shortfall + '</td>'
                        + '<td><button type="button" class="btn btn-danger btn-xs remove-license-cart-line"'
                        + ' data-license-id="' + line.license_id + '"'
                        + ' data-discipline-id="' + line.discipline_id + '"'
                        + ' data-company-id="' + line.company_id + '"'
                        + ' data-target-type="' + escapeHtml(line.requested_for_type) + '"'
                        + ' data-target-id="' + line.requested_for_id + '"><i class="fas fa-times"></i></button></td>'
                        + '</tr>');
                });

                if (!rows.length) {
                    rows.push('<tr><td colspan="7" class="text-muted">Your license cart is empty.</td></tr>');
                }
                $('#licenseRequestCartLines').html(rows.join(''));
                updateCount(response.cart_count);

                var totals = response.totals || {};
                var formatted = response.totals_formatted || {};
                $('#licenseRequestCartTotals').html(
                    '<strong>Total:</strong> ' + (totals.quantity || 0)
                    + ' &nbsp; <strong>Reusable now:</strong> ' + (totals.reusable_quantity || 0)
                    + ' &nbsp; <strong>Expected:</strong> ' + (totals.due_back_before_needed_by_quantity || 0)
                    + ' &nbsp; <strong>Shortfall:</strong> ' + (totals.procurement_shortfall || 0)
                    + ' &nbsp; <strong>Estimated savings:</strong> ' + escapeHtml(formatted.estimated_savings || '0')
                    + ' &nbsp; <strong>Amount to buy:</strong> ' + escapeHtml(formatted.amount_to_buy || '0')
                );
                $('#submitLicenseRequestCart').prop('disabled', !(response.cart_count > 0));
            }).fail(function (xhr) {
                showError(xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Unable to preview the license request cart.');
            });
        }

        $('#licenseRequestProject').html(buildModelRequestProjectOptions(''));

        $(document).on('change', '.license-request-target-type', function () {
            var row = $(this).closest('tr');
            var isAsset = $(this).val() === 'asset';
            row.find('.license-request-user-target').toggle(!isAsset);
            row.find('.license-request-asset-target').toggle(isAsset);
        });

        $(document).on('change', '.license-request-user-target, .license-request-asset-target', function () {
            var row = $(this).closest('tr');
            var option = $(this).find('option:selected');
            if (option.data('company-id')) {
                row.find('.license-request-company').val(String(option.data('company-id')));
            }
            if (option.data('discipline-id')) {
                row.find('.license-request-discipline').val(String(option.data('discipline-id')));
            }
        });

        $(document).on('click', '.add-license-to-request-cart', function () {
            var line = lineFromRow($(this).closest('tr'));
            if (!line) {
                return;
            }

            postJson(addUrl, {lines: [line]}).done(function (response) {
                updateCount(response.cart_count);
            }).fail(function (xhr) {
                window.alert(xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Unable to add the license request.');
            });
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
                company_id: $(this).data('company-id'),
                requested_for_type: $(this).data('target-type'),
                requested_for_id: $(this).data('target-id')
            }).done(previewCart);
        });

        $('#clearLicenseRequestCart').on('click', function () {
            postJson(clearUrl, {}).done(previewCart);
        });
    })();
</script>
