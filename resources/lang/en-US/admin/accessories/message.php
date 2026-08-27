<?php

return array(

    'does_not_exist' => 'The accessory [:id] does not exist.',
    'not_found' => 'That accessory was not found.',
    'assoc_users'	 => 'This accessory currently has :count items assigned to users. Please return the accessories and try again.',

    'create' => array(
        'error'   => 'The accessory was not created, please try again.',
        'success' => 'The accessory was successfully created.'
    ),

    'update' => array(
        'error'   => 'The accessory was not updated, please try again',
        'success' => 'The accessory was updated successfully.'
    ),

    'delete' => array(
        'confirm'   => 'Are you sure you wish to delete this accessory?',
        'error'   => 'There was an issue deleting the accessory. Please try again.',
        'success' => 'The accessory was deleted successfully.'
    ),

     'checkout' => array(
        'error'   		=> 'Accessory was not assigned. Please try again.',
        'success' 		=> 'Accessory assigned successfully.',
        'unavailable'   => 'Accessory is not available for assignment. Check the available quantity.',
        'user_does_not_exist' => 'That user is invalid. Please try again.',
         'checkout_qty' => array(
            'lte'  => 'There is currently only one available accessory of this type, and you are trying to assign :checkout_qty. Adjust the assignment quantity or total stock and try again.|There are :number_currently_remaining available accessories, and you are trying to assign :checkout_qty. Adjust the assignment quantity or total stock and try again.',
            ),
           
    ),

    'checkin' => array(
        'error'   		=> 'Accessory was not returned. Please try again.',
        'success' 		=> 'Accessory returned successfully.',
        'user_does_not_exist' => 'That user is invalid. Please try again.'
    )


);
