<#macro registrationLayout bodyClass="" displayInfo=false displayMessage=true displayRequiredFields=false>
<!DOCTYPE html>
<html class="h-full bg-base-300"<#if realm.internationalizationEnabled?? && locale??> lang="${locale.currentLanguageTag}"</#if>>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <#if properties.meta?has_content>
        <#list properties.meta?split(' ') as meta>
            <meta name="${meta?split('==')[0]}" content="${meta?split('==')[1]}"/>
        </#list>
    </#if>
    <title><#nested "title"></title>
    <link rel="icon" href="${url.resourcesPath}/img/favicon.ico" />
    <#if properties.stylesCommon?has_content>
        <#list properties.stylesCommon?split(' ') as style>
            <link href="${url.resourcesCommonPath}/${style}" rel="stylesheet" />
        </#list>
    </#if>
    <#if properties.styles?has_content>
        <#list properties.styles?split(' ') as style>
            <link href="${url.resourcesPath}/${style}" rel="stylesheet" />
        </#list>
    </#if>
    <#if properties.scripts?has_content>
        <#list properties.scripts?split(' ') as script>
            <script src="${url.resourcesPath}/${script}" type="text/javascript"></script>
        </#list>
    </#if>
    <#if scripts??>
        <#list scripts as script>
            <script src="${script}" type="text/javascript"></script>
        </#list>
    </#if>
    <script>
        window.addEventListener('load', function() {
            const dropdown = document.getElementById('kc-locale-dropdown');
            const currentLink = document.getElementById('kc-current-locale-link');
            if (dropdown && currentLink) {
                currentLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    dropdown.querySelector('ul').classList.toggle('hidden');
                });
            }
        });
    </script>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

</head>

<body class="h-full bg-slate-100 dark:bg-slate-900">
<div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <div class="mx-auto h-10 w-auto text-center">
            <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-300">
                Mint
            </div>
        </div>
        <h2 class="mt-10 text-center text-2xl/9 font-bold tracking-tight text-gray-900 dark:text-slate-100">
            <#nested "title">
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-slate-400">
            ${msg("loginSubtitle")}
        </p>
    </div>

    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
        <#-- App-initiated actions should not see warning messages about the need to complete the action -->
        <#-- during login.                                                                               -->
        <#if displayMessage && message?has_content && (message.type != 'warning' || !isAppInitiatedAction??)>
            <div class="mb-6 rounded-md px-3 py-2 text-sm
                <#if message.type = 'success'>bg-green-50 border border-green-200 text-green-800</#if>
                <#if message.type = 'warning'>bg-yellow-50 border border-yellow-200 text-yellow-800</#if>
                <#if message.type = 'error'>bg-red-50 border border-red-200 text-red-800</#if>
                <#if message.type = 'info'>bg-blue-50 border border-blue-200 text-blue-800</#if>
            ">
                <span class="kc-feedback-text">${kcSanitize(message.summary)?no_esc}</span>
            </div>
        </#if>

        <#nested "form">

        <#if displayInfo>
            <div id="kc-info" class="mt-6">
                <div id="kc-info-wrapper">
                    <#nested "info">
                </div>
            </div>
        </#if>
    </div>
</div>
</body>
</html>
</#macro>