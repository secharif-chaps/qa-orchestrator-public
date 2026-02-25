<#import "template.ftl" as layout>

<@layout.registrationLayout displayMessage=messagesPerField.exists('global'); section>
    <#if section = "title">
        ${msg("loginProfileTitle")}
    <#elseif section = "header">
        ${msg("loginProfileTitle")}
    <#elseif section = "form">
    <div class="bg-white dark:bg-slate-800 rounded-lg p-6">
        <#-- Information message about profile update -->
        <div class="mb-6">
            <div class="rounded-md bg-blue-50 dark:bg-blue-900/20 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            ${msg("loginProfileMessage")}
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <form id="kc-update-profile-form" class="space-y-6" action="${url.loginAction}" method="post">
            <#-- Username field (if enabled and required) -->
            <#if user.editUsernameAllowed>
                <div>
                    <label for="username" class="block text-sm/6 font-medium text-gray-900 dark:text-slate-100">
                        ${msg("username")}
                    </label>
                    <div class="mt-2">
                        <input type="text" id="username" name="username" value="${(user.username!'')}"
                               class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-sage-600 sm:text-sm/6 dark:bg-slate-800 dark:text-slate-100 dark:outline-slate-700"
                               aria-invalid="<#if messagesPerField.existsError('username')>true</#if>"/>
                        
                        <#if messagesPerField.existsError('username')>
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400" id="username-error">
                                ${kcSanitize(messagesPerField.get('username'))?no_esc}
                            </p>
                        </#if>
                    </div>
                </div>
            </#if>

            <#-- Email field - Hidden (read-only, managed by admin) -->
            <div>
                <label for="email" class="block text-sm/6 font-medium text-gray-900 dark:text-slate-100">
                    ${msg("email")}
                </label>
                <div class="mt-2">
                    <div class="block w-full rounded-md bg-gray-100 px-3 py-1.5 text-base text-gray-500 outline-1 -outline-offset-1 outline-gray-300 sm:text-sm/6 dark:bg-slate-700 dark:text-slate-400 dark:outline-slate-600">
                        <#if user.email??>${user.email}<#else><#if profile?? && profile.attributes??><#list profile.attributes as attribute><#if attribute.name == "email">${attribute.value!''}</#if></#list></#if></#if>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        ${msg("emailReadOnly")}
                    </p>
                    <#-- Hidden input to preserve email value -->
                    <input type="hidden" id="email" name="email" value="<#if user.email??>${user.email}<#else><#if profile?? && profile.attributes??><#list profile.attributes as attribute><#if attribute.name == "email">${attribute.value!''}</#if></#list></#if></#if>" />
                </div>
            </div>

            <#-- First Name field -->
            <div>
                <label for="firstName" class="block text-sm/6 font-medium text-gray-900 dark:text-slate-100">
                    ${msg("firstName")} <span class="text-red-500">*</span>
                </label>
                <div class="mt-2">
                    <input type="text" id="firstName" name="firstName" value="${(user.firstName!'')}" required
                           class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-sage-600 sm:text-sm/6 dark:bg-slate-800 dark:text-slate-100 dark:outline-slate-700"
                           aria-invalid="<#if messagesPerField.existsError('firstName')>true</#if>"/>
                    
                    <#-- Only show error after form submission attempt -->
                    <#if messagesPerField.existsError('firstName') && (!isFirstAccess?? || !isFirstAccess)>
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400" id="firstName-error">
                            ${kcSanitize(messagesPerField.get('firstName'))?no_esc}
                        </p>
                    </#if>
                </div>
            </div>

            <#-- Last Name field -->
            <div>
                <label for="lastName" class="block text-sm/6 font-medium text-gray-900 dark:text-slate-100">
                    ${msg("lastName")} <span class="text-red-500">*</span>
                </label>
                <div class="mt-2">
                    <input type="text" id="lastName" name="lastName" value="${(user.lastName!'')}" required
                           class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-sage-600 sm:text-sm/6 dark:bg-slate-800 dark:text-slate-100 dark:outline-slate-700"
                           aria-invalid="<#if messagesPerField.existsError('lastName')>true</#if>"/>
                    
                    <#-- Only show error after form submission attempt -->
                    <#if messagesPerField.existsError('lastName') && (!isFirstAccess?? || !isFirstAccess)>
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400" id="lastName-error">
                            ${kcSanitize(messagesPerField.get('lastName'))?no_esc}
                        </p>
                    </#if>
                </div>
            </div>

            <#-- User Profile custom attributes -->
            <#if profile??>
                <#list profile.attributes as attribute>
                    <#if attribute.name != "username" && attribute.name != "email" && attribute.name != "firstName" && attribute.name != "lastName">
                        <div>
                            <label for="${attribute.name}" class="block text-sm/6 font-medium text-gray-900 dark:text-slate-100">
                                ${msg(attribute.displayName!attribute.name)}
                                <#if attribute.required>*</#if>
                            </label>
                            <div class="mt-2">
                                <#if attribute.annotations.inputType?? && attribute.annotations.inputType == "select">
                                    <select id="${attribute.name}" name="${attribute.name}" 
                                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-sage-600 sm:text-sm/6 dark:bg-slate-800 dark:text-slate-100 dark:outline-slate-700"
                                            <#if attribute.required>required</#if>
                                            aria-invalid="<#if messagesPerField.existsError('${attribute.name}')>true</#if>">
                                        <option value="">${msg("selectAnOption")}</option>
                                        <#if attribute.annotations.options??>
                                            <#list attribute.annotations.options?split(',') as option>
                                                <option value="${option}" <#if attribute.value?? && attribute.value == option>selected</#if>>${option}</option>
                                            </#list>
                                        </#if>
                                    </select>
                                <#elseif attribute.annotations.inputType?? && attribute.annotations.inputType == "textarea">
                                    <textarea id="${attribute.name}" name="${attribute.name}" rows="3"
                                              class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-sage-600 sm:text-sm/6 dark:bg-slate-800 dark:text-slate-100 dark:outline-slate-700"
                                              <#if attribute.required>required</#if>
                                              aria-invalid="<#if messagesPerField.existsError('${attribute.name}')>true</#if>">${(attribute.value!'')}</textarea>
                                <#else>
                                    <input type="<#if attribute.annotations.inputType??>${attribute.annotations.inputType}<#else>text</#if>" 
                                           id="${attribute.name}" name="${attribute.name}" value="${(attribute.value!'')}"
                                           class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-sage-600 sm:text-sm/6 dark:bg-slate-800 dark:text-slate-100 dark:outline-slate-700"
                                           <#if attribute.required>required</#if>
                                           aria-invalid="<#if messagesPerField.existsError('${attribute.name}')>true</#if>"/>
                                </#if>
                                
                                <#if messagesPerField.existsError('${attribute.name}')>
                                    <p class="mt-2 text-sm text-red-600 dark:text-red-400" id="${attribute.name}-error">
                                        ${kcSanitize(messagesPerField.get('${attribute.name}'))?no_esc}
                                    </p>
                                </#if>
                            </div>
                        </div>
                    </#if>
                </#list>
            </#if>

            <#-- Submit and Cancel buttons -->
            <div class="space-y-3">
                <#if isAppInitiatedAction??>
                    <button type="submit" 
                            class="flex w-full justify-center rounded-md bg-sage-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-sage-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage-600">
                        ${msg("doSubmit")}
                    </button>
                    <button type="submit" name="cancel-aia" value="true"
                            class="flex w-full justify-center rounded-md bg-white px-3 py-1.5 text-sm/6 font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-slate-700 dark:text-slate-100 dark:ring-slate-600 dark:hover:bg-slate-600">
                        ${msg("doCancel")}
                    </button>
                <#else>
                    <button type="submit" 
                            class="flex w-full justify-center rounded-md bg-sage-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-sage-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage-600">
                        ${msg("doSubmit")}
                    </button>
                </#if>
            </div>
        </form>

        <#-- Back to application link -->
        <#if !isAppInitiatedAction??>
            <div class="mt-6 text-center">
                <a href="${url.loginUrl}" class="text-sm font-semibold text-sage-600 hover:text-sage-700 dark:text-sage-400 dark:hover:text-sage-300">
                    ${msg("backToApplication")}
                </a>
            </div>
        </#if>

        <#-- JavaScript for form validation and submission -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('kc-update-profile-form');
                const firstNameInput = document.getElementById('firstName');
                const lastNameInput = document.getElementById('lastName');
                const emailInput = document.getElementById('email');
                
                // Ensure email field has a value for form submission
                if (emailInput && !emailInput.value) {
                    const emailDisplay = document.querySelector('div[class*="bg-gray-100"]');
                    if (emailDisplay && emailDisplay.textContent.trim()) {
                        emailInput.value = emailDisplay.textContent.trim();
                    }
                }
                
                // Log current email value for debugging
                console.log('Email input value:', emailInput ? emailInput.value : 'no email input');
                console.log('Email display content:', emailDisplay ? emailDisplay.textContent : 'no email display');
                
                // Handle form submission
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    // Validate first name
                    if (firstNameInput && !firstNameInput.value.trim()) {
                        isValid = false;
                        firstNameInput.focus();
                    }
                    
                    // Validate last name
                    if (lastNameInput && !lastNameInput.value.trim()) {
                        isValid = false;
                        if (isValid) lastNameInput.focus();
                    }
                    
                    // Ensure email is included
                    if (emailInput && !emailInput.value) {
                        const userEmail = '${(user.email!'')}';
                        if (userEmail) {
                            emailInput.value = userEmail;
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        return false;
                    }
                    
                    return true;
                });
                
                // Remove browser validation messages on focus
                [firstNameInput, lastNameInput].forEach(input => {
                    if (input) {
                        input.addEventListener('focus', function() {
                            this.setCustomValidity('');
                        });
                        
                        input.addEventListener('input', function() {
                            this.setCustomValidity('');
                        });
                    }
                });
            });
        </script>
    </div>
    </#if>
</@layout.registrationLayout>