<#import "template.ftl" as layout>

<@layout.registrationLayout displayMessage=!messagesPerField.existsError('username','password') displayInfo=realm.password && realm.registrationAllowed && !registrationDisabled??; section>
    <#if section = "title">
        Sign in to your account
    <#elseif section = "form">
        <#-- Language selector -->
        <#if realm.internationalizationEnabled?? && locale?? && locale.supported?? && locale.supported?size gt 1>
            <div class="mb-6 flex justify-end">
                <div class="relative">
                    <button type="button" id="kc-current-locale-link" class="text-sm text-gray-500 hover:text-gray-700">
                        ${locale.current}
                    </button>
                    <div id="kc-locale-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden">
                        <#list locale.supported as l>
                            <a href="${l.url}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">${l.label}</a>
                        </#list>
                    </div>
                </div>
            </div>
        </#if>

        <#-- Username display for re-authentication -->
        <#if auth?has_content && auth.showUsername() && !auth.showResetCredentials()>
            <div class="mb-6 text-center">
                <p class="text-sm text-gray-600 mb-2">
                    <#if !realm.loginWithEmailAllowed>${msg("username")}<#elseif !realm.registrationEmailAsUsername>${msg("usernameOrEmail")}<#else>${msg("email")}</#if>
                </p>
                <p class="text-lg font-medium text-gray-900">${auth.attemptedUsername}</p>
                <a id="reset-login" href="${url.loginRestartFlowUrl}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                    ${msg("restartLoginTooltip")}
                </a>
            </div>
        </#if>

        <form id="kc-form-login" onsubmit="login.disabled = true; return true;" action="${url.loginAction}" method="post" class="space-y-6">
            <div>
                <label for="username" class="block text-sm/6 font-medium text-gray-900">
                    <#if !realm.loginWithEmailAllowed>
                        ${msg("username")}
                    <#elseif !realm.registrationEmailAsUsername>
                        ${msg("usernameOrEmail")}
                    <#else>
                        ${msg("email")}
                    </#if>
                </label>
                <div class="mt-2">
                    <#if auth?has_content && auth.showUsername()>
                        <input tabindex="1" id="username" name="username" value="${(auth.attemptedUsername!'')}" 
                               type="<#if realm.loginWithEmailAllowed && realm.registrationEmailAsUsername>email<#else>text</#if>" 
                               autofocus autocomplete="<#if realm.loginWithEmailAllowed && realm.registrationEmailAsUsername>email<#else>username</#if>"
                               aria-invalid="<#if messagesPerField.existsError('username','password')>true</#if>"
                               class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                    <#else>
                        <input tabindex="1" id="username" name="username" value="${(login.username!'')}" 
                               type="<#if realm.loginWithEmailAllowed && realm.registrationEmailAsUsername>email<#else>text</#if>" 
                               autofocus autocomplete="<#if realm.loginWithEmailAllowed && realm.registrationEmailAsUsername>email<#else>username</#if>"
                               aria-invalid="<#if messagesPerField.existsError('username','password')>true</#if>"
                               class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                    </#if>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm/6 font-medium text-gray-900">
                        ${msg("password")}
                    </label>
                    <#if realm.resetPasswordAllowed>
                        <div class="text-sm">
                            <a href="${url.loginResetCredentialsUrl}" class="font-semibold text-indigo-600 hover:text-indigo-500">
                                ${msg("doForgotPassword")}
                            </a>
                        </div>
                    </#if>
                </div>
                <div class="mt-2">
                    <input tabindex="2" id="password" name="password" type="password" 
                           autocomplete="current-password" required
                           aria-invalid="<#if messagesPerField.existsError('username','password')>true</#if>"
                           class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6" />
                </div>
            </div>

            <#-- Remember me checkbox -->
            <#if realm.rememberMe && !usernameEditDisabled??>
                <div class="flex items-center">
                    <input tabindex="3" id="rememberMe" name="rememberMe" type="checkbox" <#if login.rememberMe??>checked</#if>
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    <label for="rememberMe" class="ml-3 block text-sm/6 text-gray-900">
                        ${msg("rememberMe")}
                    </label>
                </div>
            </#if>

            <div>
                <input type="hidden" id="id-hidden-input" name="credentialId" <#if auth.selectedCredential?has_content>value="${auth.selectedCredential}"</#if>/>
                <button tabindex="4" name="login" id="kc-login" type="submit" 
                        class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    ${msg("doLogIn")}
                </button>
            </div>
        </form>

        <#-- Registration link -->
        <#if realm.registrationAllowed && !registrationDisabled??>
            <p class="mt-10 text-center text-sm/6 text-gray-500">
                ${msg("noAccount")}
                <a href="${url.registrationUrl}" class="font-semibold text-indigo-600 hover:text-indigo-500">
                    ${msg("doRegister")}
                </a>
            </p>
        </#if>

        <#-- Try another way link -->
        <#if auth?has_content && auth.showTryAnotherWayLink() && showAnotherWayIfPresent>
            <form id="kc-select-try-another-way-form" action="${url.loginAction}" method="post">
                <div class="mt-6 text-center">
                    <input type="hidden" name="tryAnotherWay" value="on"/>
                    <a href="#" id="try-another-way" onclick="document.forms['kc-select-try-another-way-form'].submit();return false;" 
                       class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">
                        ${msg("doTryAnotherWay")}
                    </a>
                </div>
            </form>
        </#if>
    </#if>
</@layout.registrationLayout>