<#import "template.ftl" as layout>

<@layout.registrationLayout displayMessage=false; section>
    <#if section = "title">
        ${msg("errorTitle")}
    <#elseif section = "form">
        <div id="kc-error-message">
            <h1 class="text-2xl font-bold text-red-600 text-center mb-6">
                ${msg("errorTitle")}
            </h1>
            <div class="alert-error mb-4">
                <p class="kc-feedback-text">
                    <#if message?has_content && message.summary??>
                        ${kcSanitize(message.summary)?no_esc}
                    <#else>
                        ${msg("internalServerError")}
                    </#if>
                </p>
            </div>
            <#if client?? && client.baseUrl?has_content>
                <div class="text-center mt-6">
                    <a href="${client.baseUrl}" class="btn btn-primary">
                        ${msg("backToApplication")}
                    </a>
                </div>
            </#if>
        </div>
    </#if>
</@layout.registrationLayout>