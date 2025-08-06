<#import "template.ftl" as layout>

<@layout.registrationLayout displayMessage=!messagesPerField.existsError('password','password-confirm'); section>
    <#if section = "title">
        ${msg("updatePasswordTitle")}
    <#elseif section = "header">
        ${msg("updatePasswordTitle")}
    <#elseif section = "form">
    <div class="bg-white dark:bg-slate-800 rounded-lg p-6">
        <#-- Information message about password update -->
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
                            ${msg("updatePasswordRequiredMessage")}
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <#-- Username display -->
        <#if username??>
            <div class="mb-6 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    ${msg("username")}
                </p>
                <p class="text-lg font-medium text-gray-900 dark:text-gray-100">${username}</p>
            </div>
        </#if>

        <form id="kc-passwd-update-form" onsubmit="login.disabled = true; return true;" action="${url.loginAction}" method="post" class="space-y-6">
            <#-- New Password Field -->
            <div>
                <label for="password-new" class="block text-sm/6 font-medium text-gray-900 dark:text-slate-100">
                    ${msg("passwordNew")}
                </label>
                <div class="mt-2">
                    <input type="password" id="password-new" name="password-new" 
                           class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-slate-800 dark:text-slate-100 dark:outline-slate-700"
                           autofocus autocomplete="new-password"
                           aria-invalid="<#if messagesPerField.existsError('password','password-confirm')>true</#if>"/>
                    
                    <#if messagesPerField.existsError('password')>
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400" id="password-error">
                            ${kcSanitize(messagesPerField.get('password'))?no_esc}
                        </p>
                    </#if>
                </div>
            </div>

            <#-- Confirm Password Field -->
            <div>
                <label for="password-confirm" class="block text-sm/6 font-medium text-gray-900 dark:text-slate-100">
                    ${msg("passwordConfirm")}
                </label>
                <div class="mt-2">
                    <input type="password" id="password-confirm" name="password-confirm" 
                           class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-slate-800 dark:text-slate-100 dark:outline-slate-700"
                           autocomplete="new-password"
                           aria-invalid="<#if messagesPerField.existsError('password-confirm')>true</#if>"/>
                    
                    <#if messagesPerField.existsError('password-confirm')>
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400" id="password-confirm-error">
                            ${kcSanitize(messagesPerField.get('password-confirm'))?no_esc}
                        </p>
                    </#if>
                </div>
            </div>

            <#-- Password Requirements Info - Basic implementation for now -->
            <div class="rounded-md bg-gray-50 dark:bg-gray-900/50 p-4">
                <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                    ${msg("passwordRequirements")}
                </h4>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li class="flex items-start">
                        <svg class="h-4 w-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        ${msg("passwordPolicyMinLength", "8")}
                    </li>
                    <li class="flex items-start">
                        <svg class="h-4 w-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        ${msg("passwordPolicyUpperCase", "1")}
                    </li>
                    <li class="flex items-start">
                        <svg class="h-4 w-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        ${msg("passwordPolicyLowerCase", "1")}
                    </li>
                    <li class="flex items-start">
                        <svg class="h-4 w-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        ${msg("passwordPolicyDigits", "1")}
                    </li>
                    <li class="flex items-start">
                        <svg class="h-4 w-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        ${msg("passwordPolicySpecialChars", "1")}
                    </li>
                </ul>
            </div>

            <#-- Submit and Logout buttons -->
            <div class="space-y-3">
                <#if isAppInitiatedAction??>
                    <button type="submit" id="kc-login" name="login"
                            class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        ${msg("doSubmit")}
                    </button>
                    <button type="submit" name="cancel-aia" value="true"
                            class="flex w-full justify-center rounded-md bg-white px-3 py-1.5 text-sm/6 font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-slate-700 dark:text-slate-100 dark:ring-slate-600 dark:hover:bg-slate-600">
                        ${msg("doCancel")}
                    </button>
                <#else>
                    <button type="submit" id="kc-login" name="login"
                            class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        ${msg("doSubmit")}
                    </button>
                </#if>
            </div>
        </form>

        <#-- Logout option -->
        <#if !isAppInitiatedAction??>
            <div class="mt-6 text-center">
                <a href="${url.loginUrl}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                    ${msg("backToLogin")}
                </a>
            </div>
        </#if>
    </div>
    </#if>
</@layout.registrationLayout>