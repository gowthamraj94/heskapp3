<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 * Common include for the "Create Customer / Follower" modal and its event handlers.
 * Used by new_ticket.php and edit_post.php.
 *
 * Requires the following JS variables to already be defined on the page before
 * the modal save button is clicked:
 *   - createCustomerSelectize  (the customer selectize instance)
 *   - createFollowerSelectize  (the follower selectize instance, when multi_eml is on)
*/
if (!defined('IN_SCRIPT')) { die('Hacking attempt!'); }
?>
<script>
    var createUserEventHandler = function(userType = 'customer', event, extraData) {
        /*
        Generalized handling of creating new customers or followers.
        1) By default clear the input fields before showing the modal,
        but allow passing specific values to event and pre-fill the input fields (i.e. from Add Customer/Follower click from dropdown)
        */
        let nameValue = '';
        let emailValue = '';
        if (extraData) {
            if (typeof extraData.nameValue !== 'undefined' && typeof extraData.nameValue === 'string') {
                nameValue = extraData.nameValue;
            }
            if (typeof extraData.emailValue !== 'undefined' && typeof extraData.emailValue === 'string') {
                emailValue = extraData.emailValue;
            }
        }
        $('[data-modal-id="create-customer"] input[name="name"]').val(nameValue);
        $('[data-modal-id="create-customer"] input[name="email"]').val(emailValue);

        // 2.) Update any titles and other related meta data
        let createCustomerTitle = '<?php echo hesk_makeJsString($hesklang['new_customer']); ?>';
        let customerType = 'CUSTOMER';
        if (userType === 'customer') {
            $('#new-customer-prompt').css('display', 'none');
        } else if (userType === 'follower') {
            createCustomerTitle = '<?php echo hesk_makeJsString($hesklang['new_follower']); ?>';
            customerType = 'FOLLOWER';
        }
        $('#create-customer-title').text(createCustomerTitle);
        $('[data-modal-id="create-customer"] input[name="customer_type"]').val(customerType);

        if (extraData) {
            // If extra data was passed also run validation checks - but only if anything is actually entered,
            // otherwise it looks weird if errors shows up when user didn't enter anything yet.
            if (nameValue !== '') {
                $('#create_name').keyup();
            }
            if (emailValue!== '') {
                $('#email').keyup();
            }
        }
        // We also want to clear any unnecessary errors on empty fields, as it feels weird to leave them from previous modal opens.
        updateValidation(nameValue === '', emailValue === '');
    };

    $('#new-customer-link').click(function(event, extraData = null) {
        createUserEventHandler('customer', event, extraData);
    });

    $('#new-follower-link').click(function(event, extraData = null) {
        createUserEventHandler('follower', event, extraData);
    });
</script>

<div class="modal" data-modal-id="create-customer">
    <div class="modal__body" style="white-space: normal; <?php if ($hesk_settings['limit_width']) echo 'max-width:'.$hesk_settings['limit_width'].'px'; ?>">
        <i class="modal__close" data-action="cancel">
            <svg class="icon icon-close">
                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
            </svg>
        </i>
        <h3 id="create-customer-title"><?php echo $hesklang['new_customer']; ?></h3>
        <div class="modal__description">
            <div id="new-customer-prompt" style="display: <?php echo (isset($show_create_modal) && $show_create_modal) ? 'block' : 'none'; ?>">
                <?php echo $hesklang['new_customer_prompt']; ?>
            </div>
            <div class="form">
                <div class="form-group">
                    <label for="create_name">
                        <?php echo $hesklang['name']; ?>: <span class="important">*</span>
                    </label>
                    <input type="text" id="create_name" name="name" class="form-control" maxlength="50">
                    <div class="form-control__error"></div>
                </div>
                <div class="form-group">
                    <label for="email">
                        <?php echo $hesklang['email'] . ':' . ($hesk_settings['require_email'] ? ' <span class="important">*</span>' : '') ; ?>
                    </label>
                    <input type="email"
                           class="form-control"
                           name="email" id="email" maxlength="1000">
                    <div class="form-control__error"></div>
                </div>
                <div id="email_suggestions" class="email-suggestion"></div>
            </div>
        </div>
        <div class="modal__buttons">
            <input type="hidden" name="customer_type" value="CUSTOMER">
            <button class="btn btn-border" ripple="ripple" data-action="cancel"><?php echo $hesklang['cancel']; ?></button>
            <a data-confirm-button href="#" class="btn btn-full text-white disabled" ripple="ripple" style="width: 152px; height: 40px;"><?php echo $hesklang['save']; ?></a>
        </div>
        <script>
            var $name = $('#create_name');
            var $email = $('#email');
            var $saveButton = $("[data-modal-id='create-customer']").find('a[data-confirm-button]');
            var nameValid = false;
            var emailValid = false;
            var emailFailureReason = '';

            $name.keyup(function() {
                updateNameValidation();

                let emailIsEmpty = ($email.val().trim() === '');
                <?php if (!$hesk_settings['require_email']): ?>
                emailValid = true;
                <?php endif; ?>

                /* If other/email field is empty, ignore always showing its error,
                UNLESS it was already shown (user interacted with that field since opening the modal)
                */
                let clearEmailError = (!$email.parent().hasClass('error') && emailIsEmpty)
                updateValidation(false, clearEmailError);
            });
            let fireCreateCustomerCallbackAfterEmailCheck = false;
            var debouncedEmailCheck = hesk_debounce(function() {
                /* If other/name field is empty, ignore always showing its error,
                UNLESS it was already shown (user interacted with that field since opening the modal)
                */
                let nameIsEmpty = ($name.val().trim() === '');
                let emailIsEmpty = ($email.val().trim() === '');
                let clearNameError = (!$name.parent().hasClass('error') && nameIsEmpty);

                // If email is not required, and the email field is empty, we can just skip ajax validation for email.
                // Otherwise, we always want to run ajax validation to maintain consistent UX.
                <?php if (!$hesk_settings['require_email']): ?>
                if (emailIsEmpty) {
                    emailValid = true;
                    updateValidation(clearNameError, false);

                    if (fireCreateCustomerCallbackAfterEmailCheck) {
                        // In this case, as no customer check happens, we have to call createCustomer callback directly
                        createCustomerOnValidationCallback(true);
                        fireCreateCustomerCallbackAfterEmailCheck = false;
                    }
                    return;
                }
                <?php endif; ?>

                //-- Disable save button initially until email check is complete
                emailValid = false;
                updateValidation(clearNameError, false);
                if (emailIsEmpty) {
                    emailFailureReason = '<?php echo hesk_makeJsString($hesklang['this_field_is_required']); ?>';
                    updateValidation(clearNameError, false);

                    if (fireCreateCustomerCallbackAfterEmailCheck) {
                        // Even in this case we call the callback, just with success : false.
                        // Reason is to ensure updateValidation logic is 100% the same as it was before the async request handling.
                        createCustomerOnValidationCallback(false);
                        fireCreateCustomerCallbackAfterEmailCheck = false;
                    }
                    return;
                }

                $.ajax({
                    url: 'ajax/check_customer.php',
                    type: 'GET',
                    data: {
                        email: $email.val(),
                        name: $name.val()
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (!data.emailValid) {
                            emailValid = false;
                            emailFailureReason = '<?php echo hesk_makeJsString($hesklang['enter_valid_email']); ?>';
                        } else if (data.customerAvailable === 'NOT_AVAILABLE_REGISTERED') {
                            emailValid = false;
                            emailFailureReason = '<?php echo hesk_makeJsString($hesklang['customer_email_exists_already_registered']); ?>';
                        } else if (data.customerAvailable === 'NOT_AVAILABLE_IDENTICAL') {
                            emailValid = false;
                            emailFailureReason = '<?php echo hesk_makeJsString($hesklang['customer_name_email_exists']); ?>';
                        } else {
                            emailValid = true;
                            emailFailureReason = '';

                            <?php if ($hesk_settings['detect_typos']): ?>
                            hesk_suggestEmail('email', 'email_suggestions', 1, 1);
                            <?php endif; ?>
                        }
                        updateValidation(clearNameError, false);

                        if (fireCreateCustomerCallbackAfterEmailCheck) {
                            createCustomerOnValidationCallback(true);
                            fireCreateCustomerCallbackAfterEmailCheck = false;
                        }
                    },
                    error: function(err) {
                        console.error(err);
                        emailValid = false;
                        emailFailureReason = '<?php echo hesk_makeJsString($hesklang['an_error_occurred_validating_email']); ?>';
                        updateValidation(clearNameError, false);

                        if (fireCreateCustomerCallbackAfterEmailCheck) {
                            // Even in this case we call the callback, just with success : false.
                            // Reason is to ensure updateValidation logic is 100% the same as it was before the async request handling.
                            createCustomerOnValidationCallback(false);
                            fireCreateCustomerCallbackAfterEmailCheck = false;
                        }
                    }
                });
            }, 300);
            $name.keyup(debouncedEmailCheck);
            $email.keyup(debouncedEmailCheck);
            $saveButton.click(function() {
                // As the debounceEmail check is async, we can only call the createCustomer AFTER the email check has completed.
                // While we could do it with async function it would require a bigger rework,
                // so this simple flag checking & callback from debouncedEmailCheck does the trick on ensuring it works on all browsers.
                fireCreateCustomerCallbackAfterEmailCheck = true;
                debouncedEmailCheck();
            });

            function createCustomerOnValidationCallback(validationSuccessful = false) {
                if (!nameValid || !emailValid || !validationSuccessful) {
                    //-- Fix validation state messages
                    updateValidation();
                    return;
                }

                $.ajax({
                    url: 'ajax/create_customer.php',
                    type: 'POST',
                    data: {
                        name: $name.val(),
                        email: $email.val(),
                        token: '<?php echo hesk_token_echo(0); ?>'
                    },
                    dataType: 'json',
                    success: function(data) {
                        <?php if ($hesk_settings['multi_eml']): ?>
                        var selectize = $('input[name="customer_type"]').val() === 'CUSTOMER' ?
                            createCustomerSelectize :
                            createFollowerSelectize;

                        createCustomerSelectize[0].selectize.addOption(data);
                        createFollowerSelectize[0].selectize.addOption(data);
                        <?php else: ?>
                        var selectize = createCustomerSelectize;
                        createCustomerSelectize[0].selectize.addOption(data);
                        <?php endif; ?>

                        if (typeof selectize[0].selectize.getValue() === 'string') {
                            selectize[0].selectize.setValue(data.id);
                        } else {
                            var currentCustomers = selectize[0].selectize.getValue();
                            currentCustomers.push(data.id);
                            selectize[0].selectize.setValue(currentCustomers);
                        }
                        $name.val('');
                        $email.val('');
                        $('[data-modal-id="create-customer"]').find('[data-action="cancel"]').click();
                    },
                    error: function(err) {
                        emailValid = false;
                        emailFailureReason = JSON.parse(err.responseText).message;
                        updateValidation();
                    }
                });
            }

            function updateNameValidation() {
                let nameIsEmpty = ($name.val().trim() === '');
                nameValid = !nameIsEmpty;
                if (nameIsEmpty) {
                    nameValid = false;
                } else {
                    nameValid = true;
                }
            }

            function updateValidation(forceClearNameError = false, forceClearEmailError = false) {
                // Need to re-fire this always, as otherwise if closing popup, old nameValid=true value might persist
                updateNameValidation();

                var anyFailure = false;
                /*
                There are situations where we might delibarately clear errors for clearer user experience, even if inputs are empty on modal open,
                BUT at same time keeping other validation/disabled buttons as they are.
                 */
                let clearNameError = forceClearNameError || nameValid;
                if (!nameValid) {
                    anyFailure = true;
                    if (!forceClearNameError) {
                        $name.parent().addClass('error');
                        $name.parent().find('.form-control__error').text('<?php echo hesk_makeJsString($hesklang['this_field_is_required']); ?>');
                    }
                }
                if (clearNameError) {
                    $name.parent().removeClass('error');
                    $name.parent().find('.form-control__error').text('');
                }

                let clearEmailError = forceClearEmailError || emailValid;
                if (!emailValid) {
                    anyFailure = true;

                    if (!forceClearEmailError && emailFailureReason !== '') {
                        $email.parent().addClass('error');
                        $email.parent().find('.form-control__error').text(emailFailureReason);
                    }
                }
                if (clearEmailError) {
                    $email.parent().removeClass('error');
                    $email.parent().find('.form-control__error').text('');
                }

                if (anyFailure) {
                    $saveButton.addClass('disabled');
                } else {
                    $saveButton.removeClass('disabled');
                }
            }
        </script>
    </div>
</div>
