<?php

return array(
    'about_licenses_title'      => 'About Licenses',
    'about_licenses'            => 'Licenses are used to track software. They have a specified number of seats that can be assigned to individuals.',
    'checkin'  					=> 'Return License Seat',
    'checkout_history'  		=> 'Assignment History',
    'checkout'  				=> 'Assign License Seat',
    'edit'  					=> 'Edit License',
    'filetype_info'				=> 'Allowed filetypes are png, gif, jpg, jpeg, doc, docx, pdf, txt, zip, and rar.',
    'clone'  					=> 'Clone License',
    'history_for'  				=> 'History for ',
    'in_out'  					=> 'In/Out',
    'info'  					=> 'License Info',
    'license_seats'  			=> 'License Seats',
    'seat'  					=> 'Seat',
    'seat_count'  				=> 'Seat :count',
    'seats'  					=> 'Seats',
    'software_licenses'  		=> 'Software Licenses',
    'user'  					=> 'User',
    'view'  					=> 'View License',
    'delete_disabled'           => 'This license cannot be deleted yet because some seats are still assigned.',
    'bulk'                      =>
        [
            'checkin_all'           => [
                'button'            => 'Return All Seats',
                'modal'             => 'This action will return one seat. | This action will return all :checkedout_seats_count seats for this license.',
                'enabled_tooltip'   => 'Return ALL seats for this license from both users and assets',
                'disabled_tooltip'  => 'This is disabled because there are no seats currently assigned',
                'disabled_tooltip_reassignable'  => 'This is disabled because the License is not reassignable',
                'success'           => 'License returned successfully! | All licenses were returned successfully!',
                'log_msg'           => 'Checked in via bulk license checkin in license GUI',
            ],

            'checkout_all'              => [
                'button'                => 'Assign All Seats',
                'modal'                 => 'This action will assign one seat to the first available user. | This action will assign all :available_seats_count seats to the first available users. A user is considered available if they do not already have this license assigned and Auto-Assign License is enabled on their account.',
                'enabled_tooltip'   => 'Assign ALL seats, or as many as are available, to ALL users',
                'disabled_tooltip'  => 'This is disabled because there are no seats currently available',
                'success'           => 'License assigned successfully! | :count licenses were assigned successfully!',
                'error_no_seats'    => 'There are no remaining seats left for this license.',
                'warn_not_enough_seats'    => ':count users were assigned this license, but we ran out of available license seats.',
                'warn_no_avail_users'    => 'Nothing to do. There are no users who do not already have this license assigned to them.',
                'log_msg'           => 'Checked out via bulk license checkout in license GUI',


            ],
    ],

    'below_threshold' => 'There are only :remaining_count seats left for this license with a minimum quantity of :min_amt. You may want to consider purchasing more seats.',
    'below_threshold_short' => 'This item is below the minimum required quantity.',
);
