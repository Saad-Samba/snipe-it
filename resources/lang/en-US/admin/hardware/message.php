<?php

return [

    'undeployable' 		 => 'The following assets cannot be deployed and have been removed from the assignment: :asset_tags',
    'does_not_exist' 	 => 'Asset does not exist.',
    'does_not_exist_var' => 'Asset with tag :asset_tag not found.',
    'no_tag' 	         => 'No asset tag provided.',
    'does_not_exist_or_not_requestable' => 'That asset does not exist or is not requestable.',
    'assoc_users'	 	 => 'This asset is currently assigned to a user and cannot be deleted. Return the asset first, then try again.',
    'warning_audit_date_mismatch' 	=> 'This asset\'s next verification due date (:next_audit_date) is before its last verification date (:last_audit_date). Update the next verification due date.',
    'labels_generated'   => 'Labels were successfully generated.',
    'error_generating_labels' => 'Error while generating labels.',
    'no_assets_selected' => 'No assets selected.',

    'create' => [
        'error'   		=> 'Asset was not created, please try again. :(',
        'success' 		=> 'Asset created successfully. :)',
        'success_linked' => 'Asset with tag :tag was created successfully. <strong><a href=":link" style="color: white;">Click here to view</a></strong>.',
        'multi_success_linked' => 'Asset with tag :links was created successfully.|:count assets were created succesfully. :links.',
        'partial_failure' => 'An asset was unable to be created. Reason: :failures|:count assets were unable to be created. Reasons: :failures',
        'target_not_found' => [
            'user' => 'The assigned user could not be found.',
            'asset' => 'The assigned asset could not be found.',
            'location' => 'The assigned location could not be found.',
        ],
    ],

    'update' => [
        'error'   			=> 'Asset was not updated, please try again',
        'success' 			=> 'Asset updated successfully.',
        'encrypted_warning' => 'Asset updated successfully, but encrypted custom fields were not due to permissions',
        'nothing_updated'	=>  'No fields were selected, so nothing was updated.',
        'no_assets_selected'  =>  'No assets were selected, so nothing was updated.',
        'assets_do_not_exist_or_are_invalid' => 'Selected assets cannot be updated.',
    ],

    'restore' => [
        'error'   		=> 'Asset was not restored, please try again',
        'success' 		=> 'Asset restored successfully.',
        'bulk_success' 		=> 'Asset restored successfully.',
        'nothing_updated'   => 'No assets were selected, so nothing was restored.', 
    ],

    'audit' => [
        'error'   		=> 'Asset verification was unsuccessful: :error',
        'success' 		=> 'Asset verification logged successfully.',
    ],


    'deletefile' => [
        'error'   => 'File not deleted. Please try again.',
        'success' => 'File successfully deleted.',
    ],

    'upload' => [
        'error'   => 'File(s) not uploaded. Please try again.',
        'success' => 'File(s) successfully uploaded.',
        'nofiles' => 'You did not select any files for upload, or the file you are trying to upload is too large',
        'invalidfiles' => 'One or more of your files is too large or is a filetype that is not allowed. Allowed filetypes are png, gif, jpg, doc, docx, pdf, and txt.',
    ],

    'import' => [
        'import_button'         => 'Process Import',
        'error'                 => 'Some items did not import correctly.',
        'errorDetail'           => 'The following Items were not imported because of errors.',
        'success'               => 'Your file has been imported',
        'file_delete_success'   => 'Your file has been been successfully deleted',
        'file_delete_error'      => 'The file was unable to be deleted',
        'file_missing' => 'The file selected is missing',
        'file_already_deleted' => 'The file selected was already deleted',
        'header_row_has_malformed_characters' => 'One or more attributes in the header row contain malformed UTF-8 characters',
        'content_row_has_malformed_characters' => 'One or more attributes in the first row of content contain malformed UTF-8 characters',
        'transliterate_failure' => 'Transliteration from :encoding to UTF-8 failed due to invalid characters in input'
    ],


    'delete' => [
        'confirm'   	=> 'Are you sure you wish to delete this asset?',
        'error'   		=> 'There was an issue deleting the asset. Please try again.',
        'assigned_to_error' => '{1}Asset Tag: :asset_tag is currently assigned. Return this device before deletion.|[2,*]Asset Tags: :asset_tag are currently assigned. Return these devices before deletion.',
        'nothing_updated'   => 'No assets were selected, so nothing was deleted.',
        'success' 		=> 'The asset was deleted successfully.',
    ],

    'checkout' => [
        'error'   		=> 'Asset was not assigned. Please try again.',
        'success' 		=> 'Asset assigned successfully.',
        'user_does_not_exist' => 'That user is invalid. Please try again.',
        'not_available' => 'That asset is not available for assignment!',
        'no_assets_selected' => 'You must select at least one asset from the list',
    ],

    'multi-checkout' => [
        'error'   => 'Asset was not assigned. Please try again.|Assets were not assigned. Please try again.',
        'success' => 'Asset assigned successfully.|Assets assigned successfully.',
    ],

    'checkin' => [
        'error'   		=> 'Asset was not returned. Please try again.',
        'success' 		=> 'Asset returned successfully.',
        'user_does_not_exist' => 'That user is invalid. Please try again.',
        'already_checked_in'  => 'That asset is already returned.',

    ],
    'bulk_checkin' => [
        'error' => 'No assets were returned.',
        'success' => 'Asset returned successfully.|:count assets returned successfully.',
        'already_checked_in' => 'Asset already returned: :asset_tags.|Assets already returned: :asset_tags.',
        'failed' => 'Asset could not be returned: :asset_tags.|Assets could not be returned: :asset_tags.',
    ],

    'requests' => [
        'error'   		=> 'Request was not successful, please try again.',
        'success' 		=> 'Request successfully submitted.',
        'canceled'      => 'Request successfully canceled.',
        'cancel'        => 'Cancel this item request',
    ],

];
