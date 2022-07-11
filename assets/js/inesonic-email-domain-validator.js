 /**********************************************************************************************************************
 * Copyright 2021 - 2022, Inesonic, LLC
 *
 * GNU Public License, Version 3:
 *   This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 *   License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any
 *   later version.
 *
 *   This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 *   warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 *   details.
 *
 *   You should have received a copy of the GNU General Public License along with this program.  If not, see
 *   <https://www.gnu.org/licenses/>.
 ***********************************************************************************************************************
 * \file confirm-upgrade.js
 *
 * JavaScript module that manages the confirm-upgrade page content.
 */

/***********************************************************************************************************************
 * Parameters:
 */

/**
 * Timeout for our AJAX requests.
 */
const AJAX_TIMEOUT = 30 * 1000;

/**
 * The AJAX response URL.
 */
const INESONIC_AJAX_URL = ajax_object.ajax_url;

/***********************************************************************************************************************
 * Class InesonicEmailDomainValidator:
 */

/**
 * This instance sets up Marionette to validate an appropriate email field after it's been updated by the user.
 */
var InesonicEmailDomainValidator = Marionette.Object.extend({
    /**
     * Method that initializes this instance.
     */
    initialize: function() {
        var submitChannel = Backbone.Radio.channel('submit');
        this.listenTo(submitChannel, 'validate:field', this.checkEmailAddress);

        var fieldsChannel = Backbone.Radio.channel('fields');
        this.listenTo(fieldsChannel, 'change:modelValue', this.checkEmailAddress);
    },

    // Function that is called whenever a field changes.
    checkEmailAddress: function(model) {
        let fieldType = model.get('type');
        let fieldKey = model.get('key');
        let fieldId = model.get('id');
        let fieldValue = model.get('value');

        if (fieldType == 'email') {
            jQuery.ajax(
                {
                    type: "POST",
                    url: INESONIC_AJAX_URL,
                    timeout: AJAX_TIMEOUT,
                    data: {
                        "action" : "inesonic_validate_email_address",
                        "key" : fieldKey,
                        "email" : fieldValue
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response !== null && response.status) {
                            let universityNameElement = document.getElementById("inesonic-university-name");
                            Backbone.Radio.channel('fields').request(
                                'remove:error',
                                fieldId,
                                'inesonic-email-domain-validator'
                            );

                            let status = response.status;
                            if (status == 'valid') {
                                ('textContent' in universityNameElement)
                                    ? (universityNameElement.textContent = response.institution)
                                    : (universityNameElement.innerText = response.institution);
                                universityNameElement.style.display = "block";
                            } else if (status == 'invalid') {
                                let reason = response.reason;

                                Backbone.Radio.channel('fields').request(
                                    'add:error',
                                    fieldId,
                                    'inesonic-email-domain-validator',
                                    reason
                                );

                                universityNameElement.style.display = "none";
                            } else {
                                console.log("Failed: " + status + " - " + response.reason);
                            }
                        } else {
                            console.log("Invalid response from server");
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert("Invalid response from server: " + errorThrown);
                    }
                }
            );
        }
    }
});

/***********************************************************************************************************************
 * Main:
 */

jQuery(document).ready(function($) {
    new InesonicEmailDomainValidator;
});
